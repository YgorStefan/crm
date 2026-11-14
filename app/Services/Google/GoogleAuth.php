<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\GoogleAccount;
use App\Models\GoogleEventDeletion;
use App\Models\Task;
use App\Services\PlacesApiService;
use Core\Database;

final class GoogleAuth
{
    public const SCOPE_CALENDAR = 'https://www.googleapis.com/auth/calendar.events.owned';

    private const AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const FORM_HEADERS = ['Content-Type: application/x-www-form-urlencoded'];

    private const EXCHANGE_TIMEOUT_MS = 10000;
    private const REVOKE_TIMEOUT_MS   = 3000;

    // folga para o token nao vencer entre a leitura e a chamada que o usa: um
    // 401 nessa hora seria lido como conta revogada.
    private const EXPIRY_MARGIN_S = 60;

    /** @var \Closure():float */
    private \Closure $now;

    /** @param \Closure():float|null $now segundos, como microtime(true) */
    public function __construct(
        private readonly GoogleConfig $config,
        private readonly HttpTransport $http,
        private readonly GoogleAccount $accounts = new GoogleAccount(),
        private readonly Task $tasks = new Task(),
        private readonly GoogleEventDeletion $deletions = new GoogleEventDeletion(),
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): float => microtime(true);
    }

    public function consentUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $this->config->clientId,
            'redirect_uri'  => $this->config->redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email ' . self::SCOPE_CALENDAR,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{email:string,sub:string,refresh_token:string,access_token:string,expires_in:int}
     * @throws GoogleAccessDenied sem escopo de agenda, sem refresh_token ou sem identificar a conta
     * @throws GoogleUnavailable  rede, 429 ou 5xx
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->http->send('POST', self::TOKEN_URL, self::FORM_HEADERS, http_build_query([
            'code'          => $code,
            'client_id'     => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'redirect_uri'  => $this->config->redirectUri,
            'grant_type'    => 'authorization_code',
        ]), self::EXCHANGE_TIMEOUT_MS);

        if (self::isTransient($response['status'])) {
            throw new GoogleUnavailable("O Google não respondeu à troca do código (HTTP {$response['status']}).");
        }
        if ($response['status'] !== 200) {
            throw new GoogleAccessDenied("O Google recusou a troca do código (HTTP {$response['status']}).");
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new GoogleAccessDenied('A resposta da troca do código não é JSON.');
        }

        // o google pode devolver so parte dos escopos pedidos, e a lista vem
        // separada por espaco. comparar por item evita aceitar um escopo vizinho.
        $granted = is_string($data['scope'] ?? null) ? explode(' ', $data['scope']) : [];
        if (!in_array(self::SCOPE_CALENDAR, $granted, true)) {
            throw new GoogleAccessDenied('O acesso à agenda não foi concedido.');
        }

        $refresh = $data['refresh_token'] ?? null;
        $access  = $data['access_token'] ?? null;
        if (!is_string($refresh) || $refresh === '' || !is_string($access) || $access === '') {
            throw new GoogleAccessDenied('O Google não devolveu os tokens de acesso offline.');
        }

        $identity = self::identityFromIdToken($data['id_token'] ?? null);
        if ($identity === null) {
            throw new GoogleAccessDenied('O id_token não identifica a conta Google.');
        }

        return [
            'email'         => $identity['email'],
            'sub'           => $identity['sub'],
            'refresh_token' => $refresh,
            'access_token'  => $access,
            'expires_in'    => is_int($data['expires_in'] ?? null) ? $data['expires_in'] : 0,
        ];
    }

    /**
     * @param array{email:string,sub:string,refresh_token:string,access_token:string,expires_in:int} $exchanged
     * @return bool  false quando o usuario nao pode conectar, sem nada gravado
     * @throws GoogleAccountInUse a conta google ja esta ligada a outro usuario do tenant, sem nada gravado
     */
    public function completeConnection(int $userId, array $exchanged): bool
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // o id do evento nao leva o dono, entao duas pessoas do crm na mesma
            // conta google dividiriam a agenda, e uma troca de responsavel entre
            // elas apagaria o evento e cancelaria a tarefa.
            $othersWithSub = $this->accounts->statusesOfSubForOtherUsers($userId, $exchanged['sub']);
            if ($othersWithSub === []) {
                return $this->storeConnection($db, $userId, $exchanged);
            }
            $db->rollBack();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // o code ja foi trocado, e a autorizacao nova so fica orfa se ninguem
        // mais a usa: a revogacao vale para a conta google inteira neste
        // projeto, entao com a do outro usuario ativa ela o desconectaria.
        if (!in_array('active', $othersWithSub, true)) {
            $this->revoke($exchanged['refresh_token']);
        }
        throw new GoogleAccountInUse('A conta Google já está conectada a outro usuário.');
    }

    /**
     * roda dentro da transacao do completeConnection e a encerra.
     *
     * @param array{email:string,sub:string,refresh_token:string,access_token:string,expires_in:int} $exchanged
     */
    private function storeConnection(\PDO $db, int $userId, array $exchanged): bool
    {
        // o sub anterior e lido antes do upsert, que o sobrescreve.
        $previous = $this->accounts->findForUser($userId);

        $written = $this->accounts->upsertConnected(
            $userId,
            $exchanged['email'],
            $exchanged['sub'],
            $this->encrypt($exchanged['refresh_token']),
            $this->encrypt($exchanged['access_token']),
            $this->expiresAt(($this->now)(), $exchanged['expires_in']),
        );
        if (!$written) {
            // usuario desativado, rebaixado ou de outro tenant: os vinculos e
            // as remocoes da conexao anterior ficam como estao.
            $db->rollBack();
            return false;
        }

        if ($previous !== false && (string) $previous['google_sub'] !== $exchanged['sub']) {
            // conta google diferente: os ids salvos e as remocoes pendentes
            // apontam para a agenda da conta antiga.
            $this->tasks->clearGoogleLinksForUser($userId);
            $this->deletions->purgeForUser($userId);
        }
        $this->tasks->markOpenUnlinkedPending($userId);

        $db->commit();
        return true;
    }

    /**
     * forceRefresh e para quando a agenda responde 401 a um token que o crm
     * ainda considera valido: o google pode invalidar o token antes do prazo, e
     * so a renovacao diz se a conta caiu. quem chama tenta de novo uma vez com
     * o token renovado, e so um segundo 401 significa reconexao.
     *
     * @throws GoogleReconnectRequired conta sem uso ate o usuario autorizar de novo
     * @throws GoogleUnavailable       renovacao sem resposta util, conta intacta
     */
    public function accessTokenFor(int $userId, int $timeoutMs, bool $forceRefresh = false): string
    {
        $account = $this->accounts->findForUser($userId);
        if ($account === false || $account['status'] !== 'active') {
            throw new GoogleReconnectRequired('A conta Google não está ativa.');
        }
        // as gravacoes abaixo conferem este texto cifrado, para nao cair numa
        // conta google conectada depois desta leitura.
        $readRefresh = is_string($account['refresh_token']) ? $account['refresh_token'] : null;

        $now = ($this->now)();
        if (!$forceRefresh) {
            $access    = $this->decrypt($account['access_token']);
            $expiresAt = is_string($account['access_token_expires_at']) ? strtotime($account['access_token_expires_at']) : false;
            if ($access !== null && $expiresAt !== false && $expiresAt - $now > self::EXPIRY_MARGIN_S) {
                return $access;
            }
        }

        $refresh = $this->decrypt($readRefresh);
        if ($refresh === null) {
            // a causa tipica e a APP_KEY trocada, e so uma nova autorizacao
            // devolve um token legivel.
            $this->accounts->markRevokedIfRefreshToken($userId, $readRefresh);
            throw new GoogleReconnectRequired('O token salvo da conta Google não pôde ser decifrado.');
        }

        if ($timeoutMs <= 0) {
            throw new GoogleUnavailable('Sem tempo restante para renovar o token do Google.');
        }

        $response = $this->http->send('POST', self::TOKEN_URL, self::FORM_HEADERS, http_build_query([
            'client_id'     => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'refresh_token' => $refresh,
            'grant_type'    => 'refresh_token',
        ]), $timeoutMs);

        $data = json_decode($response['body'], true);
        $data = is_array($data) ? $data : [];

        $renewed = $data['access_token'] ?? null;
        if ($response['status'] === 200 && is_string($renewed) && $renewed !== '') {
            // a renovacao normalmente nao traz refresh_token, e o salvo continua valendo.
            $this->accounts->storeAccessToken(
                $userId,
                $readRefresh,
                $this->encrypt($renewed),
                $this->expiresAt($now, is_int($data['expires_in'] ?? null) ? $data['expires_in'] : 0),
            );
            return $renewed;
        }

        if (($data['error'] ?? null) === 'invalid_grant') {
            $this->accounts->markRevokedIfRefreshToken($userId, $readRefresh);
            throw new GoogleReconnectRequired('O Google recusou a renovação do token (invalid_grant).');
        }

        // invalid_client e afins sao configuracao do app: reconectar nao resolve,
        // entao a conta fica ativa.
        throw new GoogleUnavailable("A renovação do token falhou (HTTP {$response['status']}).");
    }

    public function disconnect(int $userId): void
    {
        $account = $this->accounts->findForUser($userId);
        $this->accounts->markDisconnected($userId);

        $refresh = $account === false ? null : $this->decrypt($account['refresh_token']);
        if ($refresh === null) {
            return;
        }

        // melhor esforco: a conexao local ja foi desfeita, e a resposta nao muda nada.
        $this->revoke($refresh);
    }

    private function revoke(string $refreshToken): void
    {
        // o token vai no corpo, porque a url e o que acaba em log.
        $this->http->send('POST', self::REVOKE_URL, self::FORM_HEADERS, http_build_query([
            'token' => $refreshToken,
        ]), self::REVOKE_TIMEOUT_MS);
    }

    private static function isTransient(int $status): bool
    {
        return $status === 0 || $status === 429 || $status >= 500;
    }

    /**
     * o id_token chega direto do endpoint de token por tls e so identifica a
     * conta, entao basta decodificar o payload, sem conferir a assinatura.
     *
     * @return array{email:string,sub:string}|null
     */
    private static function identityFromIdToken(mixed $idToken): ?array
    {
        if (!is_string($idToken)) {
            return null;
        }
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode($payload, true);
        $claims = $json === false ? null : json_decode($json, true);
        if (!is_array($claims)) {
            return null;
        }

        $sub   = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        if (!is_string($sub) || $sub === '' || !is_string($email) || $email === '') {
            return null;
        }

        return ['email' => $email, 'sub' => $sub];
    }

    private function encrypt(string $token): string
    {
        return PlacesApiService::encryptKey($token, $this->config->appKey);
    }

    private function decrypt(mixed $stored): ?string
    {
        return is_string($stored) && $stored !== ''
            ? PlacesApiService::decryptKey($stored, $this->config->appKey)
            : null;
    }

    private function expiresAt(float $now, int $expiresIn): string
    {
        return date('Y-m-d H:i:s', (int) floor($now) + $expiresIn);
    }
}
