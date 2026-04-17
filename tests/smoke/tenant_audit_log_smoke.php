<?php
/**
 * Smoke TEN-05 — audit_logs tenant-scoped via AuditLogger (D-07/D-08).
 * Gate fase 2 completo: php scripts/smoke/tenant_phase2_full_suite.php
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'tenant_phase2_fixture.php';

use App\Services\AuditLogger;

$steps = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

$tenantId = 0;
$userId = 0;

try {
    $pdo = crm_smoke_pdo();
    tenant_phase2_assert_wave0_schema($pdo);
} catch (\Throwable $e) {
    $add('ten05.prelude', $e->getMessage(), false);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
}

$suffix = tenant_phase2_random_suffix();

try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)')->execute(['Audit ' . $suffix, 'audit-' . $suffix]);
    $tenantId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)'
    )->execute([$tenantId, 'Actor', 'actor+' . $suffix . '@test.local', TENANT_PHASE2_PLACEHOLDER_HASH, 'admin']);
    $userId = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $add('ten05.seed', 'Falha ao preparar tenant: ' . $e->getMessage(), false);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
}

$_SERVER['REMOTE_ADDR'] = '192.0.2.10';

$okLog = AuditLogger::record([
    'tenant_id' => $tenantId,
    'actor_user_id' => $userId,
    'action' => 'permission.role_changed',
    'target_type' => 'user',
    'target_id' => (string) $userId,
    'ip_address' => '192.0.2.10',
    'result' => 'success',
    'metadata' => ['before_role' => 'seller', 'after_role' => 'admin', 'resource' => 'user'],
]);
$add('ten05.logger.insert', $okLog ? 'AuditLogger::record persistiu evento canônico' : 'AuditLogger::record falhou', $okLog);

$sel = $pdo->prepare(
    'SELECT action, result, ip_address, metadata_json FROM audit_logs
     WHERE tenant_id = ? AND actor_user_id = ? ORDER BY id DESC LIMIT 1'
);
$sel->execute([$tenantId, $userId]);
$row = $sel->fetch(\PDO::FETCH_ASSOC);
$okRow = is_array($row)
    && ($row['action'] ?? '') === 'permission.role_changed'
    && ($row['result'] ?? '') === 'success'
    && ($row['ip_address'] ?? '') === '192.0.2.10';
$add('ten05.select.tenant_scope', $okRow ? 'Consulta filtrada por tenant retorna evento esperado' : 'Linha lida não bate com o insert', $okRow);

$stripOk = AuditLogger::record([
    'tenant_id' => $tenantId,
    'actor_user_id' => $userId,
    'action' => 'user.updated',
    'target_type' => 'user',
    'target_id' => (string) $userId,
    'result' => 'success',
    'metadata' => ['password' => 'should-not-appear', 'name' => 'ok'],
]);
$sel2 = $pdo->prepare(
    'SELECT metadata_json FROM audit_logs WHERE tenant_id = ? AND action = ? ORDER BY id DESC LIMIT 1'
);
$sel2->execute([$tenantId, 'user.updated']);
$row2 = $sel2->fetch(\PDO::FETCH_ASSOC);
$metaDecoded = [];
if (is_array($row2) && isset($row2['metadata_json'])) {
    $raw = $row2['metadata_json'];
    if (is_string($raw) && $raw !== '') {
        $metaDecoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR) ?: [];
    }
}
$noPasswordKey = $stripOk && is_array($metaDecoded) && !array_key_exists('password', $metaDecoded);
$add(
    'ten05.metadata.sanitize',
    $noPasswordKey ? 'Chave sensível removida do metadata antes de persistir' : 'Metadata ainda contém campo proibido ou insert falhou',
    $noPasswordKey
);

$categories = [
    ['auth.login_success', 'session', '1'],
    ['tenant.created', 'tenant', (string) $tenantId],
    ['user.created', 'user', '99'],
    ['permission.policy_denied', 'users', 'view'],
    ['data.client_updated', 'client', '42'],
];
$catOk = true;
foreach ($categories as [$act, $ttype, $tid]) {
    if (!AuditLogger::record([
        'tenant_id' => $tenantId,
        'actor_user_id' => $userId,
        'action' => $act,
        'target_type' => $ttype,
        'target_id' => $tid,
        'result' => 'success',
        'metadata' => ['smoke' => 'category_probe'],
    ])) {
        $catOk = false;
        break;
    }
}
$distinct = $pdo->prepare(
    "SELECT COUNT(DISTINCT SUBSTRING_INDEX(action, '.', 1)) AS c FROM audit_logs
     WHERE tenant_id = ? AND action IN (
       'auth.login_success','tenant.created','user.created','permission.policy_denied','data.client_updated',
       'permission.role_changed','user.updated'
     )"
);
$distinct->execute([$tenantId]);
$prefixCount = (int) $distinct->fetchColumn();
$add(
    'ten05.categories',
    $catOk && $prefixCount >= 5 ? 'Cinco prefixos canônicos (auth, tenant, user, permission, data) aceitos e persistidos' : 'Falha na cobertura por categoria',
    $catOk && $prefixCount >= 5
);

$metaStr = is_array($row) ? (string) ($row['metadata_json'] ?? '') : '';
$forbidden = ['password', 'token', 'secret'];
$leak = false;
foreach ($forbidden as $word) {
    if (stripos($metaStr, '"' . $word . '"') !== false) {
        $leak = true;
        break;
    }
}
$add(
    'ten05.metadata.no_secrets',
    !$leak ? 'Primeiro evento sem chaves de segredo em texto no JSON' : 'Metadados contêm termo sensível indevido',
    !$leak
);

$otherTenant = 0;
try {
    $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)')->execute(['Other ' . $suffix, 'audit-other-' . $suffix]);
    $otherTenant = (int) $pdo->lastInsertId();
    $iso = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE tenant_id = ? AND action = ?');
    $iso->execute([$otherTenant, 'permission.role_changed']);
    $cnt = (int) $iso->fetchColumn();
    $add(
        'ten05.isolation.negative',
        $cnt === 0 ? 'Outro tenant não vê eventos de auditoria do primeiro (escopo)' : 'Vazamento cross-tenant na leitura',
        $cnt === 0
    );
} catch (\Throwable $e) {
    $add('ten05.isolation.negative', 'Falha no teste de isolamento: ' . $e->getMessage(), false);
}

if ($otherTenant > 0) {
    tenant_phase2_teardown_tenant($pdo, $otherTenant);
}
tenant_phase2_teardown_tenant($pdo, $tenantId);

$controllerPaths = [
    'app/Controllers/AuthController.php',
    'app/Controllers/TenantController.php',
    'app/Controllers/UserController.php',
    'app/Controllers/UserInvitationController.php',
    'app/Controllers/ClientController.php',
];
$wired = 0;
foreach ($controllerPaths as $rel) {
    $full = ROOT_PATH . DS . str_replace('/', DS, $rel);
    if (is_file($full) && str_contains((string) file_get_contents($full), 'AuditLogger::record')) {
        ++$wired;
    }
}
$add(
    'ten05.controllers.instrumented',
    $wired === count($controllerPaths) ? 'Cinco controllers críticos chamam AuditLogger::record' : "Esperado instrumentação em 5 controllers, encontrado {$wired}",
    $wired === count($controllerPaths)
);

$add('done', $failed ? 'Smoke TEN-05 falhou' : 'Smoke TEN-05 OK', !$failed);
fwrite(STDOUT, ($failed ? 'FAIL' : 'PASS') . "\n");
crm_smoke_emit($steps, $failed ? 1 : 0);
