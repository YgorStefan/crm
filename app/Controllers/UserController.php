<?php
// ============================================================
// app/Controllers/UserController.php — Administração de Usuários
// ============================================================

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\User;

class UserController extends Controller
{
    /**
     * GET /admin/users
     * Lista todos os usuários (somente admin).
     */
    public function index(array $params = []): void
    {
        $this->requireRole('admin');
        $userModel = new User();

        $this->render('admin/users/index', [
            'pageTitle' => 'Usuários',
            'title'     => 'Usuários — ' . APP_NAME,
            'users'     => $userModel->findAll(),
        ]);
    }

    /**
     * GET /admin/users/create
     */
    public function create(array $params = []): void
    {
        $this->requireRole('admin');
        $this->render('admin/users/create', [
            'pageTitle'  => 'Novo Usuário',
            'title'      => 'Novo Usuário — ' . APP_NAME,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /admin/users/store
     */
    public function store(array $params = []): void
    {
        $this->requireRole('admin');

        $name     = $this->input('name');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $role     = $this->inputRaw('role', 'seller');

        if (empty($name) || empty($email) || strlen($password) < MIN_PASSWORD_LENGTH) {
            $this->flash('error', 'Preencha todos os campos. Senha mínima: ' . MIN_PASSWORD_LENGTH . ' caracteres.');
            $this->redirect('/admin/users/create');
            return;
        }

        $userModel = new User();
        $userModel->create([
            'name'          => $name,
            'email'         => $email,
            // Aplica o hash bcrypt — nunca armazene senhas em texto puro!
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role'          => in_array($role, ['admin','seller','viewer']) ? $role : 'seller',
        ]);

        $this->flash('success', "Usuário \"{$name}\" criado com sucesso!");
        $this->redirect('/admin/users');
    }

    /**
     * GET /admin/users/{id}/edit
     */
    public function edit(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);
        $userModel = new User();
        $user = $userModel->findById($id);

        if (!$user) {
            $this->flash('error', 'Usuário não encontrado.');
            $this->redirect('/admin/users');
        }

        $this->render('admin/users/edit', [
            'pageTitle'  => 'Editar: ' . $user['name'],
            'title'      => 'Editar Usuário — ' . APP_NAME,
            'user'       => $user,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * POST /admin/users/{id}/update
     */
    public function update(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);

        $data = [
            'name'      => $this->input('name'),
            'email'     => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'role'      => $this->inputRaw('role', 'seller'),
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

        $userModel = new User();
        $userModel->update($id, $data);

        $this->flash('success', 'Usuário atualizado com sucesso!');
        $this->redirect('/admin/users');
    }

    /**
     * POST /admin/users/{id}/delete
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

        $userModel = new User();
        $userModel->update($id, ['is_active' => 0]);

        $this->flash('success', 'Usuário desativado com sucesso.');
        $this->redirect('/admin/users');
    }
}
