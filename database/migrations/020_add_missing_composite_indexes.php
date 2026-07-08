<?php
/**
 * Migration 020 — Índices compostos identificados na auditoria de performance.
 *
 * Todas as queries afetadas já filtram por tenant_id + outra coluna (status,
 * is_active, phone, imported_year_month) — os índices existentes cobrem só
 * uma das colunas, forçando scan parcial dentro do tenant em tabelas
 * grandes. Migration puramente aditiva (CREATE INDEX), sem mudança de
 * comportamento.
 *
 * client_sales NÃO tem coluna tenant_id própria (isolamento é via JOIN com
 * clients — ver App\Models\ClientSale), por isso não entra nesta lista.
 *
 * Também adiciona UNIQUE (recurrence_parent_id, due_date) em tasks: evita
 * instâncias duplicadas se duas requisições concorrentes gerarem a mesma
 * recorrência ao mesmo tempo (NULLs múltiplos são permitidos pelo MySQL,
 * então tarefas não-recorrentes/pais não são afetadas).
 *
 * Idempotente: pode ser executada múltiplas vezes sem efeito colateral.
 *
 * Execute: php bin/migrate.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$indexes = [
    ['cold_contacts',   'idx_cc_tenant_month',         'INDEX idx_cc_tenant_month (tenant_id, imported_year_month)'],
    ['clients',         'idx_clients_tenant_active',   'INDEX idx_clients_tenant_active (tenant_id, is_active)'],
    ['clients',         'idx_clients_tenant_phone',    'INDEX idx_clients_tenant_phone (tenant_id, phone)'],
    ['tasks',           'idx_tasks_tenant_status_due', 'INDEX idx_tasks_tenant_status_due (tenant_id, status, due_date)'],
    ['tasks',           'idx_tasks_recurrence',        'INDEX idx_tasks_recurrence (tenant_id, recurrence_parent_id, due_date)'],
    ['pipeline_stages', 'idx_pipeline_stages_tenant',  'INDEX idx_pipeline_stages_tenant (tenant_id)'],
];

foreach ($indexes as [$table, $indexName, $definition]) {
    $exists = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'")->fetch();
    if ($exists) {
        echo "Migration 020: {$indexName} já existe em {$table} — ignorado.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE {$table} ADD {$definition}");
    echo "Migration 020: {$indexName} criado em {$table}.\n";
}

// UNIQUE evita duplicar instâncias de recorrência em corridas concorrentes.
$dup = $pdo->query("
    SELECT recurrence_parent_id, due_date, COUNT(*) AS total
    FROM tasks
    WHERE recurrence_parent_id IS NOT NULL
    GROUP BY recurrence_parent_id, due_date
    HAVING total > 1
")->fetchAll();

if ($dup) {
    echo "Migration 020: AVISO — instâncias de recorrência duplicadas encontradas; UNIQUE não criada.\n";
    foreach ($dup as $d) {
        echo "  - recurrence_parent_id={$d['recurrence_parent_id']} due_date={$d['due_date']} ({$d['total']}x)\n";
    }
} else {
    $exists = $pdo->query("SHOW INDEX FROM tasks WHERE Key_name = 'uq_tasks_recurrence_instance'")->fetch();
    if ($exists) {
        echo "Migration 020: uq_tasks_recurrence_instance já existe em tasks — ignorado.\n";
    } else {
        $pdo->exec("ALTER TABLE tasks ADD UNIQUE KEY uq_tasks_recurrence_instance (recurrence_parent_id, due_date)");
        echo "Migration 020: uq_tasks_recurrence_instance criada em tasks.\n";
    }
}

echo "Migration 020 concluida.\n";
