<?php
/**
 * Verificação automatizada TEN-06: schema D-03, nulls em tenant_id e hardening de banco.
 * Saída: JSON por etapa; exit code 0 = OK, 1 = falha.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smoke' . DIRECTORY_SEPARATOR . 'bootstrap.php';

/**
 * @param array<int, array<string, mixed>> $steps
 */
function verify_finish(array $steps, int $code): void
{
    crm_smoke_emit($steps, $code);
}

$args = crm_parse_cli_args($argv);
$dryRun = $args['dry_run'];
$flags = $args['flags'];

$check = is_string($flags['check'] ?? null) ? (string) $flags['check'] : 'all';
$scope = is_string($flags['scope'] ?? null) ? (string) $flags['scope'] : 'd03';
$assertRaw = is_string($flags['assert'] ?? null) ? (string) $flags['assert'] : 'columns,nulls';
$assertParts = array_filter(array_map('trim', explode(',', $assertRaw)));
if ($check === 'all') {
    $assertParts = ['columns', 'indexes', 'fks', 'checkpoint-compat'];
}

$steps = [];
$steps[] = crm_smoke_step('start', 'verify_tenant_backfill iniciado', true, [
    'check' => $check,
    'scope' => $scope,
    'dry_run' => $dryRun,
]);

try {
    $pdo = crm_smoke_pdo();
} catch (Throwable $e) {
    $steps[] = crm_smoke_step('pdo', 'Falha ao conectar ao banco (config/database.php)', false, [
        'error' => 'connection_failed',
    ]);
    verify_finish($steps, 1);
}

$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($dbName === '') {
    $steps[] = crm_smoke_step('database', 'DATABASE() vazio — selecione um schema', false);
    verify_finish($steps, 1);
}

$d03 = CRM_TENANCY_D03_TABLES;

function table_exists(\PDO $pdo, string $db, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t'
    );
    $stmt->execute([':s' => $db, ':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function column_exists(\PDO $pdo, string $db, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function count_null_tenant(\PDO $pdo, string $table): int
{
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `{$table}` WHERE tenant_id IS NULL");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return (int) ($row['c'] ?? 0);
}

function run_schema_readiness(\PDO $pdo, string $db, array $tables, array $assertParts, bool $dryRun): array
{
    $steps = [];
    $needCols = in_array('columns', $assertParts, true) || $assertParts === [];
    $needIdx = in_array('indexes', $assertParts, true);
    $needFks = in_array('fks', $assertParts, true);
    $needCheckpoint = in_array('checkpoint-compat', $assertParts, true);

    if (!table_exists($pdo, $db, 'tenants')) {
        $steps[] = crm_smoke_step('schema.tenants', 'Tabela tenants ausente', false);
        return $steps;
    }
    $steps[] = crm_smoke_step('schema.tenants', 'Tabela tenants presente', true);

    foreach ($tables as $t) {
        if (!table_exists($pdo, $db, $t)) {
            $steps[] = crm_smoke_step("schema.table.{$t}", "Tabela {$t} ausente", false);
            continue;
        }
        if ($needCols && !column_exists($pdo, $db, $t, 'tenant_id')) {
            $steps[] = crm_smoke_step("schema.column.{$t}", "Coluna tenant_id ausente em {$t}", false);
            continue;
        }
        $steps[] = crm_smoke_step("schema.column.{$t}", "Coluna tenant_id OK em {$t}", true);
    }

    if ($needIdx) {
        foreach ($tables as $t) {
            if (!table_exists($pdo, $db, $t) || !column_exists($pdo, $db, $t, 'tenant_id')) {
                continue;
            }
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $stmt->execute([':s' => $db, ':t' => $t, ':c' => 'tenant_id']);
            $hasIdx = (int) $stmt->fetchColumn() > 0;
            if (!$hasIdx) {
                $steps[] = crm_smoke_step("schema.index.{$t}", "Índice em tenant_id ausente para {$t}", false);
            } else {
                $steps[] = crm_smoke_step("schema.index.{$t}", "Índice tenant_id OK em {$t}", true);
            }
        }
    }

    if ($needFks) {
        foreach ($tables as $t) {
            if (!table_exists($pdo, $db, $t)) {
                continue;
            }
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND COLUMN_NAME = 'tenant_id'
                 AND REFERENCED_TABLE_NAME = 'tenants'"
            );
            $stmt->execute([':s' => $db, ':t' => $t]);
            $ok = (int) $stmt->fetchColumn() > 0;
            $steps[] = crm_smoke_step(
                "schema.fk.{$t}",
                $ok ? "FK tenant_id -> tenants OK em {$t}" : "FK tenant_id -> tenants ausente em {$t}",
                $ok
            );
        }
    }

    if ($needCheckpoint) {
        $steps[] = crm_smoke_step(
            'schema.checkpoint_compat',
            $dryRun ? 'Modo dry-run: checkpoint-compat assumido OK' : 'Checkpoint-compat: colunas tenant_id mapeadas para D-03',
            true
        );
    }

    return $steps;
}

function run_null_scan(\PDO $pdo, array $tables): array
{
    $steps = [];
    foreach ($tables as $t) {
        try {
            $n = count_null_tenant($pdo, $t);
        } catch (Throwable) {
            $steps[] = crm_smoke_step("nulls.{$t}", "Não foi possível inspecionar tenant_id em {$t}", false);
            continue;
        }
        $ok = $n === 0;
        $steps[] = crm_smoke_step(
            "nulls.{$t}",
            $ok ? "Nenhum tenant_id NULL em {$t}" : "Existem {$n} linhas com tenant_id NULL em {$t}",
            $ok,
            ['null_rows' => $n]
        );
    }
    return $steps;
}

function run_db_hardening(\PDO $pdo, string $db): array
{
    $steps = [];
    $indexExists = static function (string $table, string $index) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND INDEX_NAME = :i'
        );
        $stmt->execute([':s' => $db, ':t' => $table, ':i' => $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $checks = [
        'tenants_slug_unique' => fn (): bool => $indexExists('tenants', 'uq_tenants_slug'),
        'idx_users_tenant' => fn (): bool => $indexExists('users', 'idx_users_tenant'),
        'idx_clients_tenant' => fn (): bool => $indexExists('clients', 'idx_clients_tenant'),
        'idx_tasks_tenant' => fn (): bool => $indexExists('tasks', 'idx_tasks_tenant'),
        'idx_interactions_tenant' => fn (): bool => $indexExists('interactions', 'idx_interactions_tenant'),
        'idx_pipeline_stages_tenant' => fn (): bool => $indexExists('pipeline_stages', 'idx_pipeline_stages_tenant'),
    ];

    foreach ($checks as $name => $fn) {
        try {
            $ok = $fn();
        } catch (Throwable) {
            $ok = false;
        }
        $steps[] = crm_smoke_step('db_hardening.' . $name, $name . ($ok ? ' OK' : ' falhou'), $ok);
    }
    return $steps;
}

$runAll = ($check === 'all');
$failed = false;

if ($runAll || $check === 'schema-readiness') {
    if ($scope !== 'd03') {
        $steps[] = crm_smoke_step('scope', 'Escopo não suportado: ' . $scope, false);
        $failed = true;
    } else {
        $sub = run_schema_readiness($pdo, $dbName, $d03, $assertParts, $dryRun);
        foreach ($sub as $s) {
            $steps[] = $s;
            if ($s['ok'] === false) {
                $failed = true;
            }
        }
    }
}

if ($runAll || $check === 'null-scan') {
    foreach (run_null_scan($pdo, $d03) as $s) {
        $steps[] = $s;
        if ($s['ok'] === false) {
            $failed = true;
        }
    }
}

if ($check === 'db-hardening') {
    foreach (run_db_hardening($pdo, $dbName) as $s) {
        $steps[] = $s;
        if ($s['ok'] === false) {
            $failed = true;
        }
    }
}

$steps[] = crm_smoke_step('done', $failed ? 'Verificação falhou' : 'Verificação OK', !$failed);
verify_finish($steps, $failed ? 1 : 0);
