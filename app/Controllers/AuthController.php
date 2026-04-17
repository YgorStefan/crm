<?php
// Gerencia todo o fluxo de login e logout:
//   loginForm() → exibe o formulário
//   login()     → valida credenciais e cria a sessão
//   logout()    → destrói a sessão

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\User;

class AuthController extends Controller
{
    //Exibe o formulário de login.
    //Se o usuário já estiver logado, redireciona para o dashboard.
    public function loginForm(array $params = []): void
    {
        // Usuário já autenticado? Vai direto ao dashboard
        if (!empty($_SESSION['user']['id'])) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/login', [
            'title' => 'Login — ' . APP_NAME,
            'csrf_token' => CsrfMiddleware::getToken(),
            // Mensagem de timeout (sessão expirada)
            'timeout' => isset($_GET['timeout']),
        ], 'blank'); // layout 'blank': sem sidebar/header (página pública)
    }

    /**
     * Fluxo de segurança:
     *   1. Valida campos obrigatórios
     *   2. Busca usuário por e-mail (não revela se o e-mail existe)
     *   3. Verifica senha com password_verify() (timing-safe)
     *   4. Regenera o ID de sessão (previne Session Fixation)
     *   5. Popula $_SESSION['user'] e redireciona
     */
    public function login(array $params = []): void
    {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        // Validação básica de campos
        if (empty($email) || empty($password)) {
            $this->render('auth/login', [
                'title' => 'Login — ' . APP_NAME,
                'csrf_token' => CsrfMiddleware::getToken(),
                'error' => 'Preencha o e-mail e a senha.',
            ], 'blank');
            return;
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        // Verifica a senha SOMENTE se o usuário existir.
        // sempre chamamos password_verify() para evitar
        // que um atacante meça diferenças de tempo de resposta e
        // descubra se um e-mail está ou não cadastrado.
        $passwordValid = false;
        if ($user) {
            $passwordValid = password_verify($password, $user['password_hash']);
        }

        if (!$user || !$passwordValid) {
            $this->render('auth/login', [
                'title' => 'Login — ' . APP_NAME,
                'csrf_token' => CsrfMiddleware::getToken(),
                'error' => 'E-mail ou senha incorretos.',
            ], 'blank');
            return;
        }

        // --- Sessão segura ---
        // session_regenerate_id(true) cria um NOVO ID de sessão e deleta o antigo.
        // Isso impede ataques de Session Fixation, onde o atacante força
        // o usuário a usar um ID de sessão pré-definido.
        session_regenerate_id(true);

        // Popula a sessão com os dados necessários em toda a aplicação
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'avatar' => $user['avatar'],
        ];
        $_SESSION['tenant_id']     = (int) $user['tenant_id'];
        $_SESSION['last_activity'] = time();

        // Redireciona para a URL que o usuário tentou acessar antes do login,
        // ou para o dashboard se não houver URL salva
        $savedRedirect = $_SESSION['redirect_after_login'] ?? '';
        unset($_SESSION['redirect_after_login']);

        // Valida que o redirect é um path interno (começa com APP_URL ou é relativo)
        $redirect = APP_URL . '/dashboard';
        if ($savedRedirect !== '') {
            $appUrlBase = rtrim(APP_URL, '/');
            if (str_starts_with($savedRedirect, $appUrlBase . '/')) {
                $redirect = $savedRedirect;
            } elseif (str_starts_with($savedRedirect, '/') && !str_starts_with($savedRedirect, '//')) {
                $redirect = $appUrlBase . $savedRedirect;
            }
        }

        header('Location: ' . $redirect);
        exit;
    }

    /**
     *   1. Limpa todas as variáveis de sessão
     *   2. Apaga o cookie de sessão do navegador
     *   3. Destrói os dados da sessão no servidor
     */
    public function logout(array $params = []): void
    {
        // Remove todas as variáveis da sessão
        $_SESSION = [];

        // Apaga o cookie de sessão no navegador do usuário
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,  // data no passado = expira imediatamente
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destrói os dados da sessão no servidor
        session_destroy();

        header('Location: ' . APP_URL . '/login');
        exit;
    }
}
