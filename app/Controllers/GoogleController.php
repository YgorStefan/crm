<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GoogleAccount;
use App\Services\Google\CurlTransport;
use App\Services\Google\GoogleAccessDenied;
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
    private const MSG_UNEXPECTED     = 'Não foi possível concluir a conexão com o Google Agenda. Tente de novo em alguns instantes.';
    private const MSG_DISCONNECT     = 'Não foi possível desconectar o Google Agenda. Tente de novo.';
    private const MSG_INVALID_FIELDS = 'Dados do evento inválidos.';

    public function connect(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);

        $config = GoogleConfig::fromEnv();
        if (!$config->isConfigured()) {
            $this->backToTasks('error', self::MSG_NOT_CONFIGURED);
            return;
        }
        if (self::blocksNewConnection((new GoogleAccount())->findForUser($this->userId()))) {
            $this->backToTasks('error', self::MSG_ALREADY_ACTIVE);
            return;
        }

        $state = bin2hex(random_bytes(32));
        $_SESSION[self::STATE_KEY] = ['value' => $state, 'expires_at' => time() + self::STATE_TTL_S];

        // o redirect do controller so aceita caminho interno.
        header('Location: ' . (new GoogleAuth($config, new CurlTransport()))->consentUrl($state));
        exit;
    }

    public function callback(array $params = []): void
    {
        // o cookie samesite strict nao vem na volta do google, e a sessao nova que
        // o bootstrap abriu mandaria um set-cookie que troca o cookie do usuario.
        if (!isset($_COOKIE[session_name()])) {
            session_destroy();
            header_remove('Set-Cookie');
        }

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
        try {
            // o connect ja recusa conta ativa, mas outra sessao do mesmo usuario
            // guarda o proprio state, que continua valido depois de ela conectar.
            $alreadyActive = self::blocksNewConnection((new GoogleAccount())->findForUser($this->userId()));
            if (!$alreadyActive) {
                $auth = new GoogleAuth($config, new CurlTransport());
                $auth->completeConnection($this->userId(), $auth->exchangeCode($code));
            }
        } catch (GoogleAccessDenied $e) {
            $this->logFailure('conexao recusada', $e);
            $this->backToTasks('warning', self::MSG_DENIED);
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
        $this->backToTasks('success', self::MSG_CONNECTED);
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
