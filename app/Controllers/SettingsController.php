<?php

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Middleware\CsrfMiddleware;

/**
 * SettingsController — Configurações do tenant (FRAG-04).
 * Somente admin pode acessar.
 * Extensível: estrutura de form com seções para adicionar campos no futuro.
 */
class SettingsController extends Controller
{
    /**
     * Exibe a página de configurações do tenant.
     */
    public function index(array $params = []): void
    {
        $this->requireRole('admin');

        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id, name, slug, payment_cutoff_day FROM tenants WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $this->flash('error', 'Tenant não encontrado.');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('settings/index', [
            'pageTitle'  => 'Configurações',
            'title'      => 'Configurações — ' . APP_NAME,
            'tenant'     => $tenant,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * Processa o formulário de configurações e persiste as alterações.
     */
    public function update(array $params = []): void
    {
        $this->requireRole('admin');

        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);

        // Validação: payment_cutoff_day 1–28
        $cutoffDay = (int) $this->inputRaw('payment_cutoff_day', '20');
        if ($cutoffDay < 1 || $cutoffDay > 28) {
            $this->flash('error', 'Dia de corte inválido. Informe um valor entre 1 e 28.');
            $this->redirect('/settings');
            return;
        }

        // Nome do tenant (editável)
        $name = $this->input('tenant_name');
        if (empty($name)) {
            $this->flash('error', 'O nome da organização não pode ficar vazio.');
            $this->redirect('/settings');
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE tenants SET name = :name, payment_cutoff_day = :cutoff WHERE id = :id'
        );
        $stmt->execute([
            ':name'   => $name,
            ':cutoff' => $cutoffDay,
            ':id'     => $tenantId,
        ]);

        // Atualiza o nome da organização na sessão (exibido na sidebar)
        if (!empty($_SESSION['tenant'])) {
            $_SESSION['tenant']['name'] = $name;
        }

        $this->flash('success', 'Configurações salvas com sucesso.');
        $this->redirect('/settings');
    }
}
