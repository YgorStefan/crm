<?php
/**
 * Migração idempotente TEN-06: tenant inicial + backfill de tenant_id (ordem D-10).
 * Uso: php scripts/migrations/migrate_tenant_initial.php [--dry-run]
 */
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smoke' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$args = crm_parse_cli_args($argv);
$dryRun = $args['dry_run'];

$steps = [];
$log = static function (string $step, string $msg, bool $ok = true, array $data = []) use (&$steps): void {
    $steps[] = crm_smoke_step($step, $msg, $ok, $data);
};

if ($dryRun) {
    $log('start', 'Dry-run: sem conexão ao banco; nenhuma mutação aplicada', true);
    $log('plan', 'Garantir tenants + tenant idempotente + backfill D-03 + NOT NULL/FK + gate tenant-backfill', true);
    $log('done', 'Dry-run concluído', true);
    crm_smoke_emit($steps, 0);
}

function tableExists(\PDO $pdo, string $db, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t'
    );
    $stmt->execute([':s' => $db, ':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(\PDO $pdo, string $db, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function fkExists(\PDO $pdo, string $db, string $table, string $constraintName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND CONSTRAINT_NAME = :n AND CONSTRAINT_TYPE = :type'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':n' => $constraintName, ':type' => 'FOREIGN KEY']);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    $pdo = crm_smoke_pdo();
} catch (Throwable $e) {
    $log('pdo', 'Falha de conexão', false);
    crm_smoke_emit($steps, 1);
}

$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($dbName === '') {
    $log('database', 'DATABASE() vazio', false);
    crm_smoke_emit($steps, 1);
}

$run = static function (callable $fn) use ($dryRun, $pdo): void {
    if ($dryRun) {
        return;
    }
    $fn();
};

$log('start', $dryRun ? 'Dry-run: nenhuma mutação será aplicada' : 'Migração tenant inicial');

$run(static function () use ($pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tenants (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(80) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tenants_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
});
$log('tenants_table', 'Tabela tenants garantida', true);

$run(static function () use ($pdo): void {
    $pdo->exec("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1, 'Organização Padrão', 'default')");
});
$log('default_tenant', 'Tenant inicial id=1 garantido (INSERT IGNORE)', true);

$addNullableTenantId = static function (string $table) use ($pdo, $dbName, $dryRun, $run, $log): void {
    if (!tableExists($pdo, $dbName, $table)) {
        $log("skip.{$table}", "Tabela {$table} ausente — ignorada", true);
        return;
    }
    if (columnExists($pdo, $dbName, $table, 'tenant_id')) {
        $log("column.{$table}", "Coluna tenant_id já existe em {$table}", true);
        return;
    }
    $log("column.{$table}", $dryRun ? "Adicionaria tenant_id NULL em {$table}" : "Adicionando tenant_id NULL em {$table}", true);
    $run(static function () use ($pdo, $table): void {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id");
    });
};

$tables = ['users', 'pipeline_stages', 'clients', 'interactions', 'tasks'];
foreach ($tables as $t) {
    $addNullableTenantId($t);
}

$run(static function () use ($pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
});

$backfill = static function (string $sql, string $label) use ($pdo, $dryRun, $run, $log): void {
    $log('backfill.' . $label, $dryRun ? "Dry-run: {$label}" : $label, true);
    $run(static function () use ($pdo, $sql): void {
        $pdo->exec($sql);
    });
};

$backfill("UPDATE pipeline_stages SET tenant_id = 1 WHERE tenant_id IS NULL", 'pipeline_stages -> 1');
$backfill("UPDATE users SET tenant_id = 1 WHERE tenant_id IS NULL", 'users -> 1');
$backfill("UPDATE clients SET tenant_id = 1 WHERE tenant_id IS NULL", 'clients -> 1');
$backfill(
    "UPDATE interactions i INNER JOIN clients c ON c.id = i.client_id SET i.tenant_id = c.tenant_id WHERE i.tenant_id IS NULL",
    'interactions from clients'
);
$backfill(
    "UPDATE tasks t INNER JOIN users u ON u.id = t.assigned_to SET t.tenant_id = u.tenant_id WHERE t.tenant_id IS NULL",
    'tasks from assigned user'
);
$backfill("UPDATE interactions SET tenant_id = 1 WHERE tenant_id IS NULL", 'interactions órfãs -> tenant 1');
$backfill("UPDATE tasks SET tenant_id = 1 WHERE tenant_id IS NULL", 'tasks órfãs -> tenant 1');

$notNullAndIndex = static function (string $table, string $idx, string $fkName) use ($pdo, $dbName, $dryRun, $run, $log): void {
    if (!tableExists($pdo, $dbName, $table) || !columnExists($pdo, $dbName, $table, 'tenant_id')) {
        return;
    }
    $log("notnull.{$table}", $dryRun ? "Definiria NOT NULL + índice em {$table}" : "NOT NULL + índice em {$table}", true);
    $run(static function () use ($pdo, $table, $idx): void {
        $pdo->exec("ALTER TABLE `{$table}` MODIFY tenant_id INT UNSIGNED NOT NULL");
        // índice auxiliar (idempotente via verificação manual)
        try {
            $pdo->exec("CREATE INDEX `{$idx}` ON `{$table}` (tenant_id)");
        } catch (\PDOException) {
            // índice já existe
        }
    });
    if (!fkExists($pdo, $dbName, $table, $fkName)) {
        $log("fk.{$table}", $dryRun ? "Adicionaria FK {$fkName}" : "Adicionando FK {$fkName}", true);
        $run(static function () use ($pdo, $table, $fkName): void {
            $pdo->exec(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT"
            );
        });
    }
};

$run(static function () use ($pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
});

$notNullAndIndex('users', 'idx_users_tenant', 'fk_users_tenant');
$notNullAndIndex('pipeline_stages', 'idx_pipeline_stages_tenant', 'fk_pipeline_stages_tenant');
$notNullAndIndex('clients', 'idx_clients_tenant', 'fk_clients_tenant');
$notNullAndIndex('interactions', 'idx_interactions_tenant', 'fk_interactions_tenant');
$notNullAndIndex('tasks', 'idx_tasks_tenant', 'fk_tasks_tenant');

// Gate D-12 agregado (TEN-06 + checagens de schema)
if (!$dryRun) {
    $php = PHP_BINARY;
    $gate = ROOT_PATH . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'smoke' . DIRECTORY_SEPARATOR . 'phase1_tenancy_gate.php';
    $out = [];
    $code = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($gate) . ' --mode=tenant-backfill 2>&1', $out, $code);
    $text = trim(implode("\n", $out));
    $log(
        'gate.tenant-backfill',
        $code === 0 ? 'phase1_tenancy_gate tenant-backfill OK' : 'phase1_tenancy_gate falhou',
        $code === 0,
        ['output' => $text]
    );
    fwrite(STDOUT, json_encode(['migration_steps' => $steps, 'gate_exit' => $code], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($code === 0 ? 0 : 1);
}
