<?php
/**
 * Testes Phase 3 — CRM-03/CRM-04/CRM-05
 * Uso: php scripts/smoke/test_phase03_features.php
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'tenant_phase2_fixture.php';

use App\Models\AuditLog;
use App\Models\Task;
use App\Services\AuditLogger;

$pdo    = crm_smoke_pdo();
$steps  = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

// ─── Setup de sessão para currentTenantId() ───────────────────────────────────
// session_start() deve ser chamado antes de definir $_SESSION em CLI,
// para que a segunda chamada dentro de currentTenantId() seja no-op.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['tenant_id'] = 1;
$_SESSION['user']      = ['id' => 1, 'role' => 'admin'];

// ─── CRM-03: AuditLogger aceita prefix 'pipeline' ────────────────────────────
$prefixes = AuditLogger::ACTION_PREFIXES;
$add('crm03-prefix', "ACTION_PREFIXES contém 'pipeline': [" . implode(', ', $prefixes) . "]",
    in_array('pipeline', $prefixes));

// ─── CRM-03: Inserção de pipeline.stage_changed ──────────────────────────────
$before = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action='pipeline.stage_changed' AND target_id='9999'"
)->fetchColumn();

AuditLogger::record([
    'tenant_id'     => 1,
    'actor_user_id' => 1,
    'action'        => 'pipeline.stage_changed',
    'target_type'   => 'client',
    'target_id'     => '9999',
    'result'        => 'success',
    'metadata'      => ['from_stage_id' => null, 'to_stage_id' => 1,
                        'from_stage_name' => null, 'to_stage_name' => 'Prospecção'],
    'ip_address'    => '127.0.0.1',
]);

$after = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action='pipeline.stage_changed' AND target_id='9999'"
)->fetchColumn();
$add('crm03-insert', "pipeline.stage_changed inserido (rows: $before → $after)", $after === $before + 1);

// ─── CRM-03: findPipelineHistoryByClient ─────────────────────────────────────
$auditLog = new AuditLog();

$history = $auditLog->findPipelineHistoryByClient(9999, 1);
$add('crm03-read', "findPipelineHistoryByClient(9999, 1) retornou " . count($history) . " row(s)",
    count($history) >= 1);

if (!empty($history)) {
    $row  = $history[0];
    $meta = json_decode($row['metadata_json'] ?? '{}', true);
    $add('crm03-meta', "Metadata preservada: to_stage_name='{$meta['to_stage_name']}'",
        ($meta['to_stage_name'] ?? '') === 'Prospecção');
}

// Isolamento: tenant_id=0 → vazio
$add('crm03-isolation-zero', "Isolamento: tenantId=0 não vê nada",
    count($auditLog->findPipelineHistoryByClient(9999, 0)) === 0);

// Isolamento cross-tenant: tenant_id=2 → vazio
$add('crm03-isolation-cross', "Isolamento cross-tenant: tenant=2 não vê registros de tenant=1",
    count($auditLog->findPipelineHistoryByClient(9999, 2)) === 0);

// ─── CRM-04: data.task_status_changed ────────────────────────────────────────
$beforeTask = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action='data.task_status_changed' AND target_id='9998'"
)->fetchColumn();

AuditLogger::record([
    'tenant_id'     => 1,
    'actor_user_id' => 1,
    'action'        => 'data.task_status_changed',
    'target_type'   => 'task',
    'target_id'     => '9998',
    'result'        => 'success',
    'metadata'      => ['from_status' => 'pending', 'to_status' => 'done'],
    'ip_address'    => '127.0.0.1',
]);

$afterTask = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_logs WHERE action='data.task_status_changed' AND target_id='9998'"
)->fetchColumn();
$add('crm04-insert', "data.task_status_changed inserido ($beforeTask → $afterTask)",
    $afterTask === $beforeTask + 1);

$taskRow  = $pdo->query(
    "SELECT metadata_json FROM audit_logs WHERE action='data.task_status_changed' AND target_id='9998' ORDER BY id DESC LIMIT 1"
)->fetch();
$taskMeta = json_decode($taskRow['metadata_json'] ?? '{}', true);
$add('crm04-meta', "Metadata from=pending → to=done",
    ($taskMeta['from_status'] ?? '') === 'pending' && ($taskMeta['to_status'] ?? '') === 'done');

// ─── CRM-05: Task::findOverdue() ─────────────────────────────────────────────
$taskModel = new Task();
$overdue   = $taskModel->findOverdue(null);
$add('crm05-overdue-type', "findOverdue(null) retornou array (" . count($overdue) . " tarefa(s) atrasada(s))",
    is_array($overdue));

$wrongTenant = array_filter($overdue, fn($t) => ((int)($t['tenant_id'] ?? 0)) !== 1);
$add('crm05-overdue-scope', "Todas as tarefas atrasadas pertencem ao tenant=1 (sem vazamento)",
    count($wrongTenant) === 0);

// ─── Limpeza ──────────────────────────────────────────────────────────────────
$pdo->exec("DELETE FROM audit_logs WHERE target_id IN ('9999','9998') AND tenant_id=1");

// ─── Saída ────────────────────────────────────────────────────────────────────
$passed = count(array_filter($steps, fn($s) => $s['ok']));
$total  = count($steps);

foreach ($steps as $s) {
    $icon = $s['ok'] ? '[PASS]' : '[FAIL]';
    echo "$icon {$s['step']}: {$s['message']}\n";
}

echo "\n" . str_repeat('─', 55) . "\n";
echo "RESULTADO: $passed/$total" . ($failed ? " — {$_GLOBALS['f']} FALHOU" : " — tudo OK") . "\n";

crm_smoke_emit($steps, $failed ? 1 : 0);
