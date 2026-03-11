<?php
// ============================================================
// core/Middleware/AuthMiddleware.php — Verificação de Sessão
// ============================================================
// Middleware executado ANTES de qualquer rota protegida.
// Se o usuário não estiver logado, redireciona para /login.
//
// Uso no Router (routes.php):
//   $router->get('/dashboard', 'DashboardController', 'index', ['AuthMiddleware']);
// ============================================================

namespace Core\Middleware;

class AuthMiddleware
{
    /**
     * Verifica se existe uma sessão de usuário válida.
     *
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
            // Salva a URL atual para redirecionar de volta após o login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

            // Redireciona para a página de login
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Verifica inatividade por timeout (segurança adicional)
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
    }
}
