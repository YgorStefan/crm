<?php
// Fluxo de troca de senha do próprio usuário logado.
// Rota de "escape" obrigatória para AuthMiddleware quando
// password_must_change está ativo (ex.: senha padrão do seed).

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\User;

class ProfileController extends Controller
{
    public function __construct(
        private User $users = new User(),
    ) {}

    /**
     * Exibe o formulário de troca de senha.
     *
     * @param  array  $params  Parâmetros da rota (não utilizados)
     * @return void
     */
    public function changePasswordForm(array $params = []): void
    {
        $flashError = null;
        if (!empty($_SESSION['flash']) && $_SESSION['flash']['type'] === 'error') {
            $flashError = $_SESSION['flash']['message'];
            unset($_SESSION['flash']);
        }

        $this->render('profile/change-password', [
            'title' => 'Trocar Senha — ' . APP_NAME,
            'csrf_token' => CsrfMiddleware::getToken(),
            'forced' => !empty($_SESSION['user']['password_must_change']),
            'error' => $flashError,
        ], 'blank');
    }

    /**
     * Valida a senha atual e grava a nova senha com hash bcrypt.
     * Limpa a flag password_must_change no banco e na sessão.
     *
     * @param  array  $params  Parâmetros da rota (não utilizados)
     * @return void
     */
    public function changePassword(array $params = []): void
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userId = (int) $_SESSION['user']['id'];
        $user = $this->users->findById($userId);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $this->flash('error', 'Senha atual incorreta.');
            $this->redirect('/profile/change-password');
            return;
        }

        if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
            $this->flash('error', 'A nova senha deve ter no mínimo ' . MIN_PASSWORD_LENGTH . ' caracteres.');
            $this->redirect('/profile/change-password');
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->flash('error', 'A confirmação não corresponde à nova senha.');
            $this->redirect('/profile/change-password');
            return;
        }

        if (password_verify($newPassword, $user['password_hash'])) {
            $this->flash('error', 'A nova senha deve ser diferente da senha atual.');
            $this->redirect('/profile/change-password');
            return;
        }

        $this->users->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'password_must_change' => 0,
        ]);

        $_SESSION['user']['password_must_change'] = false;

        $this->flash('success', 'Senha alterada com sucesso!');
        $this->redirect('/dashboard');
    }
}
