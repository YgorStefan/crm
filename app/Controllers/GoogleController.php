<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GoogleAccount;
use App\Services\Google\CurlTransport;
use App\Services\Google\GoogleAccessDenied;
use App\Services\Google\GoogleAccountInUse;
use App\Services\Google\GoogleAuth;
use App\Services\Google\GoogleCalendarSync;
use App\Services\Google\GoogleConfig;
use App\Services\Google\GoogleUnavailable;
use Core\Controller;
use Core\Http\ApiResponse;
use Core\Logger;

class GoogleController extends Controller
{
    private const STATE_KEY   = 'google_oauth_state';
    private const STATE_TTL_S = 600;

    private const MSG_NOT_CONFIGURED = 'A integração com o Google Agenda não está configurada.';
    private const MSG_STATE          = 'A conexão com o Google Agenda não foi concluída porque o pedido expirou ou foi substituído por outro. Tente conectar de novo.';
    private const MSG_DENIED         = 'O Google Agenda não foi conectado. Na tela do Google, marque a permissão de acesso à agenda e tente de novo.';
    private const MSG_UNAVAILABLE    = 'O Google não respondeu agora. Tente conectar de novo em alguns instantes.';
    private const MSG_CONNECTED      = 'Google Agenda conectado. Suas tarefas abertas de hoje em diante vão para a sua agenda do Google.';
    private const MSG_ALREADY_ACTIVE = 'O Google Agenda já está conectado. Para usar outra conta, desconecte antes.';
    private const MSG_NOT_ALLOWED    = 'Seu usuário não pode conectar o Google Agenda.';
    private const MSG_ACCOUNT_IN_USE = 'Esta conta Google já está conectada a outro usuário. Peça para essa pessoa desconectar antes.';
    private const MSG_UNEXPECTED     = 'Não foi possível concluir a conexão com o Google Agenda. Tente de novo em alguns instantes.';
    private const MSG_DISCONNECT     = 'Não foi possível desconectar o Google Agenda. Tente de novo.';
    private const MSG_INVALID_FIELDS = 'Dados do evento inválidos.';

    public function connect(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);

        // o papel da sessao pode estar velho, e o google pode registrar o acesso
        // ja no consentimento, entao quem foi rebaixado ou desativado nem chega la.
        $accounts = new GoogleAccount();
        if (!$accounts->userCanConnect($this->userId())) {
            $this->backToTasks('warning', self::MSG_NOT_ALLOWED);
            return;
        }

        $config = GoogleConfig::fromEnv();
        if (!$config->isConfigured()) {
            $this->backToTasks('error', self::MSG_NOT_CONFIGURED);
            return;
        }
        if (self::blocksNewConnection($accounts->findForUser($this->userId()))) {
            $this->backToTasks('error', self::MSG_ALREADY_ACTIVE);
            return;
        }

        $state = bin2hex(random_bytes(32));
        $_SESSION[self::STATE_KEY] = ['value' => $state, 'expires_at' => time() + self::STATE_TTL_S];

        $this->redirectToGoogle($this->googleAuth($config)->consentUrl($state));
    }

    // o redirect do controller so aceita caminho interno.
    protected function redirectToGoogle(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    public function callback(array $params = []): void
    {
        $this->withoutNewSession();

        // a url desta pagina leva o code, que nao pode ir em cache nem em referer.
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('Content-Type: text/html; charset=utf-8');

        $target = htmlspecialchars(self::finishUrl($_GET), ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta http-equiv="refresh" content="0;url=' . $target . '">'
            . '<title>Google Agenda</title></head><body>'
            . '<p><a href="' . $target . '">Continuar a conexão com o Google Agenda</a></p>'
            . '</body></html>';
    }

    public function finish(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);

        $stored    = $_SESSION[self::STATE_KEY] ?? null;
        $expected  = is_array($stored) ? ($stored['value'] ?? null) : null;
        $expiresAt = is_array($stored) && is_int($stored['expires_at'] ?? null) ? $stored['expires_at'] : 0;
        // o state guardado so e consumido quando confere: a volta de uma aba
        // antiga nao derruba a conexao que a aba mais nova ainda vai concluir.
        if (!self::stateIsValid($_GET['state'] ?? null, $expected, $expiresAt, time())) {
            $this->backToTasks('warning', self::MSG_STATE);
            return;
        }
        unset($_SESSION[self::STATE_KEY]);

        $config = GoogleConfig::fromEnv();
        if (!$config->isConfigured()) {
            $this->backToTasks('error', self::MSG_NOT_CONFIGURED);
            return;
        }

        $code = $_GET['code'] ?? null;
        if (isset($_GET['error']) || !is_string($code) || $code === '') {
            $this->backToTasks('warning', self::MSG_DENIED);
            return;
        }
        $connected = false;
        try {
            $accounts = new GoogleAccount();
            // antes da troca do code: depois dela o google ja emitiu um refresh
            // token que ninguem revoga, e o app fica nos acessos de terceiros da
            // pessoa. o upsert repete a regra para quem for rebaixado no meio.
            if (!$accounts->userCanConnect($this->userId())) {
                $this->backToTasks('warning', self::MSG_NOT_ALLOWED);
                return;
            }
            // o connect ja recusa conta ativa, mas outra sessao do mesmo usuario
            // guarda o proprio state, que continua valido depois de ela conectar.
            $alreadyActive = self::blocksNewConnection($accounts->findForUser($this->userId()));
            if (!$alreadyActive) {
                $auth = $this->googleAuth($config);
                $connected = $auth->completeConnection($this->userId(), $auth->exchangeCode($code));
            }
        } catch (GoogleAccessDenied $e) {
            $this->logFailure('conexao recusada', $e);
            $this->backToTasks('warning', self::MSG_DENIED);
            return;
        } catch (GoogleAccountInUse $e) {
            $this->backToTasks('warning', self::MSG_ACCOUNT_IN_USE);
            return;
        } catch (GoogleUnavailable $e) {
            $this->logFailure('troca do codigo sem resposta', $e);
            $this->backToTasks('error', self::MSG_UNAVAILABLE);
            return;
        } catch (\Throwable $e) {
            // sem isto o erro de banco ou de chave vira uma pagina 500 em branco.
            $this->logUnexpected('concluir a conexao', $e);
            $this->backToTasks('error', self::MSG_UNEXPECTED);
            return;
        }

        if ($alreadyActive) {
            $this->backToTasks('error', self::MSG_ALREADY_ACTIVE);
            return;
        }
        if (!$connected) {
            // so quem foi rebaixado ou desativado entre a conferencia acima e a
            // gravacao chega aqui, e o upsert recusa.
            $this->backToTasks('warning', self::MSG_NOT_ALLOWED);
            return;
        }
        $this->backToTasks('success', self::MSG_CONNECTED);
    }

    protected function googleAuth(GoogleConfig $config): GoogleAuth
    {
        return new GoogleAuth($config, new CurlTransport());
    }

    public function disconnect(array $params = []): void
    {
        // o googleauth deixa escapar erro de banco, e a tela precisa de json para avisar.
        try {
            (new GoogleAuth(GoogleConfig::fromEnv(), new CurlTransport()))->disconnect($this->userId());
        } catch (\Throwable $e) {
            $this->logFailure('desconectar', $e);
            $this->json(ApiResponse::error(self::MSG_DISCONNECT), 500);
            return;
        }

        $this->json(ApiResponse::success());
    }

    public function updateEvent(array $params = []): void
    {
        $this->requireRole(['admin', 'seller'], json: true);

        $fields = self::eventFields($_POST);
        if ($fields === null) {
            $this->json(ApiResponse::error(self::MSG_INVALID_FIELDS), 422);
            return;
        }

        $this->eventResult(
            GoogleCalendarSync::default()->updateForeignEvent($this->userId(), (string) ($params['google_id'] ?? ''), $fields)
        );
    }

    public function deleteEvent(array $params = []): void
    {
        $this->requireRole(['admin', 'seller'], json: true);

        $this->eventResult(
            GoogleCalendarSync::default()->deleteForeignEvent($this->userId(), (string) ($params['google_id'] ?? ''))
        );
    }

    /**
     * trocar de conta google com a atual ativa deixaria uma renovacao ou um
     * envio no ar gravar a conta antiga na nova. desconectada ou revogada, a
     * conta nao tem chamada no ar, entao pode conectar de novo.
     *
     * @param array<string, mixed>|false $account linha de GoogleAccount::findForUser
     */
    public static function blocksNewConnection(array|false $account): bool
    {
        return $account !== false && ($account['status'] ?? null) === 'active';
    }

    public static function stateIsValid(mixed $received, mixed $expected, int $expiresAt, int $now): bool
    {
        return is_string($received) && is_string($expected) && $expected !== ''
            && $now < $expiresAt
            && hash_equals($expected, $received);
    }

    /** @param array<array-key, mixed> $query */
    public static function finishUrl(array $query): string
    {
        $forwarded = [];
        foreach (['code', 'state', 'error'] as $key) {
            if (is_string($query[$key] ?? null)) {
                $forwarded[$key] = $query[$key];
            }
        }

        $url = APP_URL . '/google/callback/finish';
        return $forwarded === [] ? $url : $url . '?' . http_build_query($forwarded, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * o servico confia nos tipos, entao campo que nao chega como texto recusa o
     * pedido inteiro em vez de virar a palavra Array no google.
     *
     * @param array<array-key, mixed> $post
     * @return array{title?:string, description?:string, start?:string, end?:string, all_day?:bool}|null
     */
    public static function eventFields(array $post): ?array
    {
        $fields = [];
        foreach (['title', 'description', 'start', 'end'] as $key) {
            if (!array_key_exists($key, $post)) {
                continue;
            }
            if (!is_string($post[$key])) {
                return null;
            }
            $fields[$key] = $post[$key];
        }

        if (array_key_exists('all_day', $post)) {
            if (!is_string($post['all_day'])) {
                return null;
            }
            // o formulario manda o dia inteiro como '1' ou '0'.
            $fields['all_day'] = $post['all_day'] === '1';
        }

        return $fields;
    }

    /** @param array{ok:bool, error?:string} $result */
    private function eventResult(array $result): void
    {
        if ($result['ok']) {
            $this->json(ApiResponse::success());
            return;
        }

        $this->json(ApiResponse::error($result['error'] ?? 'Não foi possível concluir a operação no Google Agenda.'), 422);
    }

    private function backToTasks(string $type, string $message): void
    {
        $this->flash($type, $message);
        $this->redirect('/tasks');
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    private function logFailure(string $what, \Throwable $e): void
    {
        // so a classe e a mensagem, que o googleauth monta sem token.
        (new Logger())->warning("[GoogleController] {$what}", [
            'user_id'   => $this->userId(),
            'exception' => $e::class,
            'message'   => $e->getMessage(),
        ]);
    }

    private function logUnexpected(string $what, \Throwable $e): void
    {
        // a mensagem de um erro inesperado pode vir de qualquer camada e nao
        // tem garantia de sair sem token, entao fica so a classe, o codigo e o lugar.
        (new Logger())->error("[GoogleController] {$what}", [
            'user_id'   => $this->userId(),
            'exception' => $e::class,
            'code'      => $e->getCode(),
            'at'        => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    }
}
