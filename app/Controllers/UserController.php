<?php

namespace App\Controllers;

use Core\Controller;
use Core\Logger;
use Core\Middleware\CsrfMiddleware;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\Google\CurlTransport;
use App\Services\Google\GoogleAuth;
use App\Services\Google\GoogleConfig;

class UserController extends Controller
{
    public function __construct(
        private User $users = new User(),
    ) {}

    /**
     * Lista todos os usuários (somente admin).
     */
    public function index(array $params = []): void
    {
        $this->requireRole('admin');

        $this->render('admin/users/index', [
            'title' => 'Usuários — ' . APP_NAME,
            'users' => $this->users->findAll(),
        ]);
    }

    /**
     * Exibe o formulário de cadastro de novo usuário (somente admin).
     *
     * @param  array  $params  Parâmetros da rota (não utilizados)
     * @return void
     */
    public function create(array $params = []): void
    {
        $this->requireRole('admin');
        $this->render('admin/users/create', [
            'title' => 'Novo Usuário — ' . APP_NAME,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Cria um novo usuário com hash bcrypt após validação (somente admin).
     *
     * @param  array  $params  Parâmetros da rota (não utilizados)
     * @return void
     */
    public function store(array $params = []): void
    {
        $this->requireRole('admin');

        $name = $this->input('name');
        $raw   = trim($_POST['email'] ?? '');
        $email = filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : '';
        $password = $_POST['password'] ?? '';
        $role = $this->inputPost('role', 'seller');

        if (!$email) {
            $this->flash('error', 'E-mail inválido.');
            $this->redirect('/admin/users/create');
            return;
        }

        if (empty($name) || strlen($password) < MIN_PASSWORD_LENGTH) {
            $this->flash('error', 'Preencha todos os campos. Senha mínima: ' . MIN_PASSWORD_LENGTH . ' caracteres.');
            $this->redirect('/admin/users/create');
            return;
        }

        $this->users->create([
            'name' => $name,
            'email' => $email,
            // Aplica o hash bcrypt — nunca armazene senhas em texto puro
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => in_array($role, ['admin', 'seller', 'viewer']) ? $role : 'seller',
        ]);

        $this->flash('success', "Usuário \"{$name}\" criado com sucesso!");
        $this->redirect('/admin/users');
    }

    /**
     * Exibe o formulário de edição de um usuário existente (somente admin).
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function edit(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);
        $user = $this->users->findById($id);

        if (!$user) {
            $this->flash('error', 'Usuário não encontrado.');
            $this->redirect('/admin/users');
        }

        $this->render('admin/users/edit', [
            'title' => 'Editar Usuário — ' . APP_NAME,
            'user' => $user,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Atualiza dados do usuário; atualiza a sessão se for o próprio admin (somente admin).
     *
     * @param  array  $params  Parâmetros da rota (requer 'id')
     * @return void
     */
    public function update(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);

        $rawEmail = trim($_POST['email'] ?? '');
        $email    = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? $rawEmail : '';
        if (!$email) {
            $this->flash('error', 'E-mail inválido.');
            $this->redirect('/admin/users/' . $id . '/edit');
            return;
        }

        $requestedRole = $this->inputPost('role', 'seller');
        $data = [
            'name' => $this->input('name'),
            'email' => $email,
            'role' => in_array($requestedRole, ['admin', 'seller', 'viewer'], true) ? $requestedRole : 'seller',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        // Atualiza senha apenas se foi preenchida
        $newPassword = $_POST['password'] ?? '';
        if (!empty($newPassword)) {
            if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
                $this->flash('error', 'Senha mínima: ' . MIN_PASSWORD_LENGTH . ' caracteres.');
                $this->redirect('/admin/users/' . $id . '/edit');
                return;
            }
            $data['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->users->update($id, $data);
        if (self::editRemovesGoogle($data)) {
            $this->disconnectGoogle($id);
        }

        // Atualiza a sessão se o admin editou o próprio perfil
        if ($id === (int) ($_SESSION['user']['id'] ?? 0)) {
            $_SESSION['user']['name']   = $data['name'];
            $_SESSION['user']['email']  = $data['email'];
            $_SESSION['user']['role']   = $data['role'];
        }

        $this->flash('success', 'Usuário atualizado com sucesso!');
        $this->redirect('/admin/users');
    }

    /**
     * Soft-delete: desativa o usuário em vez de apagá-lo.
     */
    public function destroy(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);

        // Impede auto-exclusão
        if ($id === (int) $_SESSION['user']['id']) {
            $this->flash('error', 'Você não pode desativar a própria conta.');
            $this->redirect('/admin/users');
            return;
        }

        $this->users->update($id, ['is_active' => 0]);
        $this->disconnectGoogle($id);

        $this->flash('success', 'Usuário desativado com sucesso.');
        $this->redirect('/admin/users');
    }

    /**
     * leitor nao tem a barra do google para desconectar, e o envio nao olha o
     * papel, entao rebaixar a leitor desconecta como desativar.
     *
     * @param array{is_active:int, role:string} $data o que a edicao gravou
     */
    public static function editRemovesGoogle(array $data): bool
    {
        return $data['is_active'] === 0 || $data['role'] === 'viewer';
    }

    // usuario desativado ou rebaixado a leitor nao deixa credencial do google
    // guardada. a edicao ja foi gravada, entao a falha aqui so fica registrada.
    private function disconnectGoogle(int $userId): void
    {
        try {
            $config = GoogleConfig::fromEnv();
            if ($config->isConfigured()) {
                (new GoogleAuth($config, new CurlTransport()))->disconnect($userId);
            } else {
                // com a integracao desligada o crm nao chama o google, entao so
                // os tokens guardados saem, sem a revogacao.
                (new GoogleAccount())->markDisconnected($userId);
            }
        } catch (\Throwable $e) {
            (new Logger())->error('[UserController] desconectar o google do usuario desativado ou rebaixado a leitor', [
                'user_id'   => $userId,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
        }
    }
}
