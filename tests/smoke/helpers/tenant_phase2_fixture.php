<?php
/**
 * Fixture compartilhado para smoke Wave 0 da Fase 2 (onboarding, governança, RBAC, auditoria).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (!function_exists('crm_tenant_phase2_autoload')) {
    function crm_tenant_phase2_autoload(string $className): void
    {
        $relativePath = str_replace('\\', DS, $className) . '.php';
        $map = [
            'Core' . DS => CORE_PATH . DS,
            'App' . DS => APP_PATH . DS,
        ];
        foreach ($map as $prefix => $baseDir) {
            if (str_starts_with($relativePath, $prefix)) {
                $file = $baseDir . substr($relativePath, strlen($prefix));
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    }
    spl_autoload_register('crm_tenant_phase2_autoload');
}

/**
 * Placeholder bcrypt para usuários sintéticos de smoke test.
 * Este hash NÃO é o hash de produção do schema — é um valor fictício
 * gerado apenas para satisfazer a coluna NOT NULL durante os testes.
 * Nunca use este hash fora do ambiente de testes.
 */
const TENANT_PHASE2_PLACEHOLDER_HASH = '$2y$12$SMOKETESTPLACEHOLDERXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

function tenant_phase2_random_suffix(): string
{
    return bin2hex(random_bytes(4));
}

/**
 * Valida que o schema deste plano foi aplicado no banco atual.
 *
 * @throws \RuntimeException
 */
function tenant_phase2_assert_wave0_schema(\PDO $pdo): void
{
    $st = $pdo->query(
        "SELECT COUNT(*) AS c FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN ('tenant_user_invitations','audit_logs')"
    );
    $row = $st->fetch(\PDO::FETCH_ASSOC);
    if ((int) ($row['c'] ?? 0) < 2) {
        throw new \RuntimeException(
            'Schema fase 2 incompleto: importe database/schema.sql (tenant_user_invitations, audit_logs).'
        );
    }

    $idx = $pdo->query(
        "SELECT COUNT(*) AS c FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'users'
           AND index_name = 'uq_users_tenant_email'"
    )->fetch(\PDO::FETCH_ASSOC);
    if ((int) ($idx['c'] ?? 0) < 1) {
        throw new \RuntimeException(
            'Schema users: falta índice uq_users_tenant_email (tenant_id, email).'
        );
    }
}

/**
 * Remove dados sintéticos de um tenant (ordem respeitando FKs).
 */
function tenant_phase2_teardown_tenant(\PDO $pdo, int $tenantId): void
{
    if ($tenantId <= 0) {
        return;
    }
    $pdo->prepare('DELETE FROM audit_logs WHERE tenant_id = ?')->execute([$tenantId]);
    $pdo->prepare('DELETE FROM tenant_user_invitations WHERE tenant_id = ?')->execute([$tenantId]);
    $pdo->prepare('DELETE FROM tasks WHERE tenant_id = ?')->execute([$tenantId]);
    $pdo->prepare('DELETE FROM interactions WHERE tenant_id = ?')->execute([$tenantId]);
    $pdo->prepare('DELETE FROM clients WHERE tenant_id = ?')->execute([$tenantId]);
    $pdo->prepare('DELETE FROM pipeline_stages WHERE tenant_id = ?')->execute([$tenantId]);
    $pdo->prepare('DELETE FROM users WHERE tenant_id = ?')->execute([$tenantId]);
    $pdo->prepare('DELETE FROM tenants WHERE id = ?')->execute([$tenantId]);
}
