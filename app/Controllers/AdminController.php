<?php

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Middleware\CsrfMiddleware;
use App\Models\User;

/**
 * AdminController — Painel administrativo unificado.
 *
 * Consolida Usuarios + Configuracoes da Organizacao + Ciclo de Pagamento
 * em uma unica tela com abas. Endpoints POST (criar/editar/excluir usuario,
 * update de settings) permanecem nas controllers originais.
 */
class AdminController extends Controller
{
    public function __construct(
        private User $users = new User(),
    ) {}

    /**
     * Render do painel admin com abas.
     * Query param: ?tab=users|org|payment (default: users)
     */
    public function index(array $params = []): void
    {
        $this->requireRole('admin');

        $tab = $_GET['tab'] ?? 'users';
        if (!in_array($tab, ['users', 'org', 'payment'], true)) {
            $tab = 'users';
        }

        $data = [
            'pageTitle'  => 'Administracao',
            'title'      => 'Administracao — ' . APP_NAME,
            'activeTab'  => $tab,
            'csrf_token' => CsrfMiddleware::getToken(),
        ];

        // Dados dependendo da aba ativa
        if ($tab === 'users') {
            $data['users'] = $this->users->findAll();
        } else {
            $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT id, name, slug, payment_cutoff_day FROM tenants WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $tenantId]);
            $tenant = $stmt->fetch();
            if (!$tenant) {
                $this->flash('error', 'Tenant nao encontrado.');
                $this->redirect('/dashboard');
                return;
            }
            $data['tenant'] = $tenant;
        }

        $this->render('admin/index', $data);
    }
}
