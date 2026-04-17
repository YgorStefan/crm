<?php
// Middleware executado ANTES de qualquer rota protegida.
// Se o usuário não estiver logado, redireciona para /login.

namespace Core\Middleware;

class AuthMiddleware
{
    /**
     * Verifica se existe uma sessão de usuário válida.
     * A sessão é criada em AuthController::login() quando as
     * credenciais são verificadas com password_verify().
     * Se não existir (usuário não logado), redireciona para /login.
     */
    public function handle(): void
    {
        // Inicia a sessão se ainda não foi iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verifica se a chave 'user' existe na sessão
        // (populada pelo AuthController após login bem-sucedido)
        if (empty($_SESSION['user']['id'])) {
            // Salva apenas o path relativo (sem o prefixo do APP_URL) para
            // evitar duplicação quando AuthController concatenar APP_URL novamente.
            $uri = strtok($_SERVER['REQUEST_URI'], '?');
            $basePath = parse_url(APP_URL, PHP_URL_PATH) ?? '';
            if ($basePath !== '' && str_starts_with($uri, $basePath)) {
                $uri = substr($uri, strlen($basePath));
            }
            $_SESSION['redirect_after_login'] = '/' . ltrim($uri, '/');

            // Redireciona para a página de login
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Verifica inatividade por timeout
        $timeout = SESSION_LIFETIME;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
            // Sessão expirada: destrói tudo e redireciona
            session_unset();
            session_destroy();
            header('Location: ' . APP_URL . '/login?timeout=1');
            exit;
        }

        // Atualiza o timestamp de última atividade
        $_SESSION['last_activity'] = time();

        // Forçar troca de senha se flag ativa
        // Exceção: o próprio /profile/change-password e /logout não são bloqueados
        if (!empty($_SESSION['user']['password_must_change'])) {
            $uri = strtok($_SERVER['REQUEST_URI'], '?');
            $basePath = parse_url(APP_URL, PHP_URL_PATH) ?? '';
            $path = ($basePath !== '' && str_starts_with($uri, $basePath))
                ? substr($uri, strlen($basePath))
                : $uri;
            $allowed = ['/profile/change-password', '/logout'];
            if (!in_array('/' . ltrim($path, '/'), $allowed, true)) {
                header('Location: ' . APP_URL . '/profile/change-password');
                exit;
            }
        }
    }
}
