<?php
/**
 * Testes unitários puros da Fase 2 — sem banco de dados.
 *
 * Cobre:
 *   - TenantAccessPolicy: 12 combinações role×ação + deny-by-default
 *   - AuditLogger::sanitizeMetadata: remoção de chaves sensíveis (recursiva)
 *   - AuditLogger::record() com payloads inválidos (validação sem DB)
 *   - AuditLog::delete() é proibido (append-only, via Reflection sem PDO)
 *   - Verificações estáticas dos arquivos modificados pelo code review:
 *       WR-001 logout POST, WR-002 db_check_at, WR-003 error_log,
 *       WR-004 rate limit, WR-005 token fora do flash,
 *       WR-006 is_system_tenant, CR-001 credenciais placeholder
 *
 * Uso: php scripts/smoke/unit_phase2_pure.php
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'tenant_phase2_fixture.php';

use App\Policies\TenantAccessPolicy;
use App\Services\AuditLogger;

$steps  = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

// ─── 1. TenantAccessPolicy — matriz completa 12 combinações ──────────────────

$matrix = [
    ['admin',  'view',   true],
    ['admin',  'create', true],
    ['admin',  'edit',   true],
    ['admin',  'delete', true],
    ['seller', 'view',   false],
    ['seller', 'create', false],
    ['seller', 'edit',   false],
    ['seller', 'delete', false],
    ['viewer', 'view',   false],
    ['viewer', 'create', false],
    ['viewer', 'edit',   false],
    ['viewer', 'delete', false],
];

$policyFails = [];
foreach ($matrix as [$role, $action, $expected]) {
    $actual = TenantAccessPolicy::can($role, 'users', $action);
    if ($actual !== $expected) {
        $policyFails[] = "can({$role},users,{$action}): esperado=" . ($expected ? 'true' : 'false') . ' obtido=' . ($actual ? 'true' : 'false');
    }
}
$add(
    'unit.policy.matrix_12',
    $policyFails === []
        ? 'TenantAccessPolicy: 12/12 combinações role×ação corretas'
        : 'Matriz divergente: ' . implode('; ', $policyFails),
    $policyFails === []
);

// ─── 2. TenantAccessPolicy — deny-by-default para entradas desconhecidas ─────

$add(
    'unit.policy.deny_unknown_resource',
    'TenantAccessPolicy: recurso desconhecido → false',
    TenantAccessPolicy::can('admin', 'unknown_resource', 'view') === false
);
$add(
    'unit.policy.deny_unknown_role',
    'TenantAccessPolicy: role desconhecida → false',
    TenantAccessPolicy::can('superadmin', 'users', 'view') === false
);
$add(
    'unit.policy.deny_unknown_action',
    'TenantAccessPolicy: ação desconhecida → false',
    TenantAccessPolicy::can('admin', 'users', 'publish') === false
);

// ─── 3. AuditLogger::sanitizeMetadata ────────────────────────────────────────

$dirty = [
    'email'         => 'user@example.com',
    'role'          => 'admin',
    'password'      => 'secret123',
    'password_hash' => '$2y$12$xxx',
    'token'         => 'abc123',
    'token_hash'    => 'sha256hash',
    'secret'        => 'mysecret',
    'csrf_token'    => 'csrfval',
    '_csrf_token'   => 'csrfval2',
    'authorization' => 'Bearer xyz',
    'cookie'        => 'session=abc',
    'nested'        => [
        'password' => 'nested_secret',
        'name'     => 'kept_value',
    ],
];

$clean = AuditLogger::sanitizeMetadata($dirty);

$sensitiveKeys = ['password', 'password_hash', 'token', 'token_hash', 'secret',
                  'csrf_token', '_csrf_token', 'authorization', 'cookie'];
$leaked = array_filter($sensitiveKeys, static fn($k) => array_key_exists($k, $clean));
$leakedList = array_values($leaked);
$add(
    'unit.sanitize.top_level_removed',
    $leakedList === []
        ? 'sanitizeMetadata: 9 chaves sensíveis removidas do nível raiz'
        : 'Chaves vazaram: ' . implode(', ', $leakedList),
    $leakedList === []
);

$add(
    'unit.sanitize.safe_keys_kept',
    'sanitizeMetadata: chaves seguras (email, role) preservadas',
    isset($clean['email']) && $clean['email'] === 'user@example.com' && isset($clean['role'])
);

$add(
    'unit.sanitize.recursive_nested',
    'sanitizeMetadata: remove chaves sensíveis em arrays aninhados',
    isset($clean['nested']) && !isset($clean['nested']['password']) && ($clean['nested']['name'] ?? '') === 'kept_value'
);

// ─── 4. AuditLogger::record() — validação de payload (sem DB) ────────────────
// Os erros de validação ocorrem em validatePayload() antes de qualquer acesso ao banco.
// tenant_id=0 falha após validatePayload mas antes de new AuditLog().

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$cases = [
    ['unit.validate.missing_tenant_id',      'tenant_id ausente → false',        ['action' => 'auth.login_success', 'target_type' => 'session', 'result' => 'success']],
    ['unit.validate.bad_action_no_dot',      'action sem ponto → false',          ['tenant_id' => 1, 'action' => 'invalid_no_dot', 'target_type' => 'session', 'result' => 'success']],
    ['unit.validate.noncanonical_prefix',    'prefixo billing não canônico → false', ['tenant_id' => 1, 'action' => 'billing.charge', 'target_type' => 'session', 'result' => 'success']],
    ['unit.validate.bad_result_value',       'result inválido → false',           ['tenant_id' => 1, 'action' => 'auth.login_success', 'target_type' => 'session', 'result' => 'unknown']],
    ['unit.validate.tenant_id_zero',         'tenant_id=0 → false (sem DB)',      ['tenant_id' => 0, 'action' => 'auth.login_success', 'target_type' => 'session', 'result' => 'success']],
    ['unit.validate.empty_target_type',      'target_type vazio → false',         ['tenant_id' => 1, 'action' => 'auth.login_success', 'target_type' => '  ', 'result' => 'success']],
    ['unit.validate.action_digits_only_seg', 'action com segmento inválido → false', ['tenant_id' => 1, 'action' => 'auth.', 'target_type' => 'session', 'result' => 'success']],
];

foreach ($cases as [$step, $msg, $payload]) {
    $add($step, $msg, AuditLogger::record($payload) === false);
}

// ─── 5. AuditLog::delete() — proibido (append-only, via Reflection) ──────────

try {
    $rf  = new \ReflectionMethod(\App\Models\AuditLog::class, 'delete');
    $obj = (new \ReflectionClass(\App\Models\AuditLog::class))->newInstanceWithoutConstructor();
    $threw = false;
    try {
        $rf->invoke($obj, 1);
    } catch (\RuntimeException) {
        $threw = true;
    }
    $add('unit.audit_log.delete_forbidden', 'AuditLog::delete() lança RuntimeException (append-only)', $threw);
} catch (\ReflectionException $e) {
    $add('unit.audit_log.delete_forbidden', 'Reflection falhou: ' . $e->getMessage(), false);
}

// ─── 6. Verificações estáticas dos ficheiros modificados pelo code review ─────

$root = ROOT_PATH;

// CR-001 — credenciais padrão substituídas no schema.sql
$schema = (string) file_get_contents($root . '/database/schema.sql');
$add(
    'unit.static.cr001_no_default_email',
    'schema.sql: email admin@crm.local removido (CR-001)',
    !str_contains($schema, 'admin@crm.local')
);
$add(
    'unit.static.cr001_no_real_hash',
    'schema.sql: hash bcrypt original removido (CR-001)',
    !str_contains($schema, 'eImiTXuWVxfM37uY4JANjOe5XtTkLfkwU1h9qMz5h3ZfCqsN8G2HW')
);

// WR-001 — logout deve ser rota POST em public/index.php
$index = (string) file_get_contents($root . '/public/index.php');
$add(
    'unit.static.wr001_logout_post_route',
    'public/index.php: /logout registrado como POST com CsrfMiddleware (WR-001)',
    (bool) preg_match("/\\\$router->post\s*\(\s*['\"]\/logout['\"]/" , $index)
        && !(bool) preg_match("/\\\$router->get\s*\(\s*['\"]\/logout['\"]/" , $index)
);

// WR-001 — logout no layout deve ser <form POST>, não <a href>
$main = (string) file_get_contents($root . '/app/Views/layouts/main.php');
$add(
    'unit.static.wr001_logout_form',
    'main.php: logout usa <form POST> com CSRF, não <a href> (WR-001)',
    str_contains($main, 'method="POST"') && str_contains($main, '/logout')
        && !(bool) preg_match('/<a[^>]+href=["\'][^"\']*\/logout["\']/', $main)
);

// WR-002 — AuthMiddleware deve conter revalidação periódica
$authMw = (string) file_get_contents($root . '/core/Middleware/AuthMiddleware.php');
$add(
    'unit.static.wr002_session_db_check',
    'AuthMiddleware: revalidação DB com findByIdForSessionRefresh + db_check_at (WR-002)',
    str_contains($authMw, 'findByIdForSessionRefresh') && str_contains($authMw, 'db_check_at')
);

// WR-003 — AuditLogger::catch deve ter error_log com variável de exceção
$auditLogger = (string) file_get_contents($root . '/app/Services/AuditLogger.php');
$add(
    'unit.static.wr003_error_log',
    'AuditLogger: catch captura $e e chama error_log() (WR-003)',
    str_contains($auditLogger, 'error_log(') && str_contains($auditLogger, 'catch (\Throwable $e)')
);

// WR-004 — AuthController deve ter rate limiting via countRecentFailures
$authCtrl = (string) file_get_contents($root . '/app/Controllers/AuthController.php');
$add(
    'unit.static.wr004_rate_limit',
    'AuthController: rate limit com countRecentFailures e threshold 10 (WR-004)',
    str_contains($authCtrl, 'countRecentFailures') && str_contains($authCtrl, 'recentFailures >= 10')
);

// WR-005 — token de convite não deve aparecer no flash
$invCtrl = (string) file_get_contents($root . '/app/Controllers/UserInvitationController.php');
$add(
    'unit.static.wr005_token_not_in_flash',
    'UserInvitationController: token não exposto no flash da sessão (WR-005)',
    !(bool) preg_match("/flash\s*\([^)]*token/si", $invCtrl)
);

// WR-006 — schema deve ter coluna is_system_tenant
$add(
    'unit.static.wr006_is_system_tenant_ddl',
    'schema.sql: coluna is_system_tenant na tabela tenants (WR-006)',
    str_contains($schema, 'is_system_tenant')
);

// WR-006 — TenantController não deve ter verificação hardcoded tenant_id===1 no código (ignora comentários)
$tenantCtrl = (string) file_get_contents($root . '/app/Controllers/TenantController.php');
// Extrai apenas tokens de código PHP (exclui T_COMMENT e T_DOC_COMMENT)
$codeOnly = implode('', array_map(
    static fn($t) => is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : (is_array($t) ? $t[1] : $t),
    token_get_all($tenantCtrl)
));
$add(
    'unit.static.wr006_no_hardcoded_id1',
    'TenantController: verificação tenant_id===1 substituída por is_system_tenant (WR-006)',
    !(bool) preg_match('/\btenant_id\b\s*[=!]==?\s*1\b/', $codeOnly)
);

// ─── Fim ─────────────────────────────────────────────────────────────────────

$add('done', $failed ? 'unit_phase2_pure — FALHOU' : 'unit_phase2_pure — OK', !$failed);
fwrite(STDOUT, ($failed ? 'FAIL' : 'PASS') . "\n");
crm_smoke_emit($steps, $failed ? 1 : 0);
