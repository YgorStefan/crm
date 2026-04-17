<?php
/**
 * Smoke TEN-04 — roles base admin/seller/viewer e checagens admin em UserController (D-05/D-06).
 * Rotas admin de usuários: /admin/users* com AuthMiddleware + TenantContextMiddleware + CSRF nas mutações.
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'tenant_phase2_fixture.php';
require_once ROOT_PATH . DS . 'app' . DS . 'Policies' . DS . 'TenantAccessPolicy.php';

use App\Policies\TenantAccessPolicy;

$steps = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

try {
    $pdo = crm_smoke_pdo();
    tenant_phase2_assert_wave0_schema($pdo);
} catch (\Throwable $e) {
    $add('ten04.prelude', $e->getMessage(), false);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
}

$typeRow = $pdo->query(
    "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'"
)->fetch(\PDO::FETCH_ASSOC);
$type = (string) ($typeRow['t'] ?? '');
$hasAll = str_contains($type, 'admin') && str_contains($type, 'seller') && str_contains($type, 'viewer');
$noExtra = !preg_match("/enum\s*\(([^)]+)\)/i", $type, $m)
    || count(array_map('trim', explode(',', $m[1]))) === 3;
$add(
    'ten04.db.role_enum',
    $hasAll && $noExtra ? 'ENUM role contém apenas admin, seller, viewer' : 'ENUM role diverge do modelo D-05',
    $hasAll && $noExtra,
    ['column_type' => $type]
);

$ucPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'UserController.php';
$uc = is_file($ucPath) ? (string) file_get_contents($ucPath) : '';
$methodsNeedAdmin = ['index', 'create', 'store', 'edit', 'update', 'destroy'];
$missing = [];
foreach ($methodsNeedAdmin as $m) {
    $needle = 'function ' . $m . '(';
    $pos = strpos($uc, $needle);
    if ($pos === false) {
        continue;
    }
    $slice = substr($uc, $pos, 800);
    if (!str_contains($slice, "requireRole('admin')")) {
        $missing[] = $m;
    }
}
$add(
    'ten04.controller.user_admin_gate',
    $missing === [] ? 'UserController: mutações/listagem exigem requireRole(admin)' : 'Métodos sem requireRole(admin): ' . implode(', ', $missing),
    $missing === []
);

$policyViolations = [];
$actions = ['view', 'create', 'edit', 'delete'];
foreach (['admin', 'seller', 'viewer'] as $role) {
    foreach ($actions as $act) {
        $allowed = TenantAccessPolicy::can($role, 'users', $act);
        if ($role === 'admin' && !$allowed) {
            $policyViolations[] = "{$role}/{$act} deveria permitir";
        }
        if ($role !== 'admin' && $allowed) {
            $policyViolations[] = "{$role}/{$act} deveria negar";
        }
    }
}
$add(
    'ten04.policy.matrix',
    $policyViolations === []
        ? 'TenantAccessPolicy: admin permitido em view/create/edit/delete; seller/viewer negados'
        : 'TenantAccessPolicy divergente: ' . implode('; ', $policyViolations),
    $policyViolations === []
);

$ucHasPolicy = str_contains($uc, 'TenantAccessPolicy') && str_contains($uc, 'assertUserPolicy');
$add(
    'ten04.controller.policy_integration',
    $ucHasPolicy ? 'UserController integra TenantAccessPolicy antes das operações' : 'UserController sem integração explícita da policy',
    $ucHasPolicy
);

$add('done', $failed ? 'Smoke TEN-04 falhou' : 'Smoke TEN-04 OK', !$failed);
fwrite(STDOUT, ($failed ? 'FAIL' : 'PASS') . "\n");
crm_smoke_emit($steps, $failed ? 1 : 0);
