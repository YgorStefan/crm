<?php
/**
 * Testes unitários com banco de dados da Fase 2.
 *
 * Requer: config/database.php + schema fase 2 aplicado.
 *
 * Cobre:
 *   - Coluna is_system_tenant existe na tabela tenants (WR-006 DDL)
 *   - Tenant id=1 marcado com is_system_tenant=1 (WR-006 data)
 *   - AuditLog::countRecentFailures retorna 0 para estado limpo
 *   - countRecentFailures incrementa ao inserir falhas na janela
 *   - countRecentFailures respeita a janela de tempo
 *   - countRecentFailures faz OR por email OU IP (disjunção)
 *   - countRecentFailures não conta login_success
 *   - AuditLog::delete() lança RuntimeException via PDO real
 *
 * Uso: php scripts/smoke/unit_phase2_db.php
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'tenant_phase2_fixture.php';

use App\Models\AuditLog;

$steps    = [];
$failed   = false;
$tenantId = 0;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

// ─── Prelúdio ─────────────────────────────────────────────────────────────────

try {
    $pdo = crm_smoke_pdo();
    tenant_phase2_assert_wave0_schema($pdo);
} catch (\Throwable $e) {
    $add('unit_db.prelude', $e->getMessage(), false, ['type' => get_class($e)]);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
    exit(1);
}

// ─── 1. Coluna is_system_tenant existe na tabela tenants ──────────────────────

$colRow = $pdo->query(
    "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tenants'
       AND COLUMN_NAME  = 'is_system_tenant'"
)->fetch(\PDO::FETCH_ASSOC);
$add(
    'unit_db.schema.is_system_tenant_col',
    'tenants.is_system_tenant: coluna presente no banco (WR-006)',
    (int) ($colRow['c'] ?? 0) === 1
);

// ─── 2. Tenant id=1 marcado com is_system_tenant=1 ───────────────────────────

$sysRow = $pdo->query(
    "SELECT is_system_tenant FROM tenants WHERE id = 1 LIMIT 1"
)->fetch(\PDO::FETCH_ASSOC);
$add(
    'unit_db.data.tenant1_marked_system',
    'Tenant id=1: is_system_tenant=1 no banco (WR-006)',
    $sysRow !== false && (int) $sysRow['is_system_tenant'] === 1,
    ['row' => $sysRow ?: []]
);

// ─── Seed ─────────────────────────────────────────────────────────────────────

$suffix    = tenant_phase2_random_suffix();
$testEmail = "rl-unit+{$suffix}@smoke.local";
$testIp    = '192.0.2.' . random_int(1, 253);

try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)')->execute(['RL Unit ' . $suffix, 'rl-unit-' . $suffix]);
    $tenantId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)'
    )->execute([$tenantId, 'RL Actor', $testEmail, TENANT_PHASE2_PLACEHOLDER_HASH, 'admin']);
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $add('unit_db.seed', 'Falha ao criar tenant de teste: ' . $e->getMessage(), false);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
    exit(1);
}

$auditModel = new AuditLog();

// ─── 3. countRecentFailures = 0 para estado limpo ────────────────────────────

$countClean = $auditModel->countRecentFailures($testEmail, $testIp, 900);
$add(
    'unit_db.ratelimit.clean_state_zero',
    'countRecentFailures: retorna 0 para IP/email sem histórico de falhas',
    $countClean === 0,
    ['count' => $countClean]
);

// ─── 4. countRecentFailures incrementa corretamente ──────────────────────────

$insertFailure = $pdo->prepare(
    "INSERT INTO audit_logs (tenant_id, actor_user_id, action, target_type, ip_address, result, metadata_json)
     VALUES (:tid, NULL, 'auth.login_failure', 'session', :ip, 'failure', JSON_OBJECT('email', :email))"
);
for ($i = 0; $i < 5; $i++) {
    $insertFailure->execute([':tid' => $tenantId, ':ip' => $testIp, ':email' => $testEmail]);
}

$countAfter = $auditModel->countRecentFailures($testEmail, $testIp, 900);
$add(
    'unit_db.ratelimit.counts_5_failures',
    'countRecentFailures: conta exatamente 5 falhas injetadas dentro da janela',
    $countAfter === 5,
    ['count' => $countAfter]
);

// ─── 5. countRecentFailures respeita janela de tempo ─────────────────────────

$pdo->prepare(
    "INSERT INTO audit_logs (tenant_id, actor_user_id, action, target_type, ip_address, result, metadata_json, created_at)
     VALUES (:tid, NULL, 'auth.login_failure', 'session', :ip, 'failure',
             JSON_OBJECT('email', :email), DATE_SUB(NOW(), INTERVAL 30 SECOND))"
)->execute([':tid' => $tenantId, ':ip' => $testIp, ':email' => $testEmail]);

$countShortWindow = $auditModel->countRecentFailures($testEmail, $testIp, 5);
$add(
    'unit_db.ratelimit.respects_window',
    'countRecentFailures: falha com 30s de atraso não entra na janela de 5s',
    $countShortWindow <= 5,
    ['count_5s_window' => $countShortWindow]
);

// ─── 6. countRecentFailures faz OR: match por IP com email diferente ──────────

$otherEmail  = "other-{$suffix}@smoke.local";
$countByIp   = $auditModel->countRecentFailures($otherEmail, $testIp, 900);
$add(
    'unit_db.ratelimit.matches_by_ip',
    'countRecentFailures: casa por IP mesmo com email diferente (disjunção OR)',
    $countByIp >= 5,
    ['count_by_ip' => $countByIp]
);

// ─── 7. countRecentFailures faz OR: match por email com IP diferente ──────────

$countByEmail = $auditModel->countRecentFailures($testEmail, '10.255.255.1', 900);
$add(
    'unit_db.ratelimit.matches_by_email',
    'countRecentFailures: casa por email mesmo com IP diferente (disjunção OR)',
    $countByEmail >= 5,
    ['count_by_email' => $countByEmail]
);

// ─── 8. countRecentFailures não conta login_success ──────────────────────────

// Baseline: count atual de falhas (inclui as 5 recentes + a de 30s atrás na janela de 900s)
$countBaseline = $auditModel->countRecentFailures($testEmail, $testIp, 900);

$pdo->prepare(
    "INSERT INTO audit_logs (tenant_id, actor_user_id, action, target_type, ip_address, result, metadata_json)
     VALUES (:tid, NULL, 'auth.login_success', 'session', :ip, 'success', JSON_OBJECT('email', :email))"
)->execute([':tid' => $tenantId, ':ip' => $testIp, ':email' => $testEmail]);

$countAfterSuccess = $auditModel->countRecentFailures($testEmail, $testIp, 900);
$add(
    'unit_db.ratelimit.ignores_success_events',
    'countRecentFailures: não conta eventos auth.login_success',
    $countAfterSuccess === $countBaseline,
    ['count_baseline' => $countBaseline, 'count_after_success_insert' => $countAfterSuccess]
);

// ─── 9. AuditLog::delete() lança RuntimeException com PDO real ───────────────

$throwsOk = false;
try {
    $auditModel->delete(1);
} catch (\RuntimeException) {
    $throwsOk = true;
}
$add(
    'unit_db.audit_log.delete_throws',
    'AuditLog::delete() lança RuntimeException com PDO real (append-only)',
    $throwsOk
);

// ─── Teardown ─────────────────────────────────────────────────────────────────

if ($tenantId > 0) {
    tenant_phase2_teardown_tenant($pdo, $tenantId);
}

// ─── Fim ──────────────────────────────────────────────────────────────────────

$add('done', $failed ? 'unit_phase2_db — FALHOU' : 'unit_phase2_db — OK', !$failed);
fwrite(STDOUT, ($failed ? 'FAIL' : 'PASS') . "\n");
crm_smoke_emit($steps, $failed ? 1 : 0);
