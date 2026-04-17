<?php
/**
 * Migração idempotente 010 — colunas geradas em cold_contacts.
 *
 * Adiciona ao banco de produção as colunas que existem no schema.sql
 * mas não possuíam migração própria:
 *   - archived_at          DATETIME NULL DEFAULT NULL
 *   - imported_year_month  CHAR(7) GENERATED ALWAYS AS (DATE_FORMAT(imported_at, '%Y-%m')) STORED
 *
 * Causa do erro 500: queries em ColdContact::countByMonth(), findMonthSummaries()
 * e AcompanhamentoController::index() referenciam imported_year_month.
 * Se a coluna não existir no banco, o PDO lança PDOException → HTTP 500.
 *
 * Idempotente: cada passo verifica o estado atual antes de executar.
 *
 * Uso:
 *   php scripts/migrations/migrate_010_cold_contacts_columns.php
 *   php scripts/migrations/migrate_010_cold_contacts_columns.php --dry-run
 */
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smoke' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$args   = crm_parse_cli_args($argv);
$dryRun = $args['dry_run'];

$steps = [];
$log   = static function (string $step, string $msg, bool $ok = true, array $data = []) use (&$steps): void {
    $steps[] = crm_smoke_step($step, $msg, $ok, $data);
};

if ($dryRun) {
    $log('start',  'Dry-run: nenhuma mutação aplicada', true);
    $log('plan',   'Adicionaria archived_at + imported_year_month (GENERATED) + índice em cold_contacts', true);
    $log('done',   'Dry-run concluído', true);
    crm_smoke_emit($steps, 0);
}

// ─── helpers ─────────────────────────────────────────────────────────────────

function col010Exists(\PDO $pdo, string $db, string $table, string $col): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t AND COLUMN_NAME=:c'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':c' => $col]);
    return (int) $stmt->fetchColumn() > 0;
}

function idx010Exists(\PDO $pdo, string $db, string $table, string $idx): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t AND INDEX_NAME=:i'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':i' => $idx]);
    return (int) $stmt->fetchColumn() > 0;
}

// ─── conexão ─────────────────────────────────────────────────────────────────

try {
    $pdo = crm_smoke_pdo();
} catch (\Throwable $e) {
    $log('pdo', 'Falha de conexão: ' . $e->getMessage(), false);
    crm_smoke_emit($steps, 1);
}

$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($dbName === '') {
    $log('database', 'DATABASE() vazio — verifique .env', false);
    crm_smoke_emit($steps, 1);
}
$log('connect', "Conectado a {$dbName}", true);

$TABLE = 'cold_contacts';

// ─── 1. Verificar se a tabela existe ─────────────────────────────────────────

$tableCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t'
);
$tableCheck->execute([':s' => $dbName, ':t' => $TABLE]);
if ((int) $tableCheck->fetchColumn() === 0) {
    $log('table', "Tabela {$TABLE} não existe — execute o schema.sql primeiro", false);
    crm_smoke_emit($steps, 1);
}
$log('table', "Tabela {$TABLE} existe", true);

// ─── 2. Adicionar archived_at ─────────────────────────────────────────────────

if (col010Exists($pdo, $dbName, $TABLE, 'archived_at')) {
    $log('archived_at', "Coluna archived_at já existe — nada a fazer", true);
} else {
    $log('archived_at', "Adicionando archived_at DATETIME NULL DEFAULT NULL", true);
    $pdo->exec("ALTER TABLE `{$TABLE}` ADD COLUMN archived_at DATETIME NULL DEFAULT NULL");
    $log('archived_at.done', "archived_at adicionada com sucesso", true);
}

// ─── 3. Adicionar imported_year_month (GENERATED) ────────────────────────────

if (col010Exists($pdo, $dbName, $TABLE, 'imported_year_month')) {
    $log('imported_year_month', "Coluna imported_year_month já existe — nada a fazer", true);
} else {
    $log('imported_year_month', "Adicionando imported_year_month CHAR(7) GENERATED STORED", true);
    // DATE_FORMAT() não é permitido em GENERATED ALWAYS AS no MariaDB 11.x
    $pdo->exec(
        "ALTER TABLE `{$TABLE}`
         ADD COLUMN imported_year_month CHAR(7)
             GENERATED ALWAYS AS (CONCAT(YEAR(imported_at), '-', LPAD(MONTH(imported_at), 2, '0'))) STORED"
    );
    $log('imported_year_month.done', "imported_year_month adicionada com sucesso", true);
}

// ─── 4. Índice em imported_year_month ────────────────────────────────────────

$IDX = 'idx_cc_year_month';
if (idx010Exists($pdo, $dbName, $TABLE, $IDX)) {
    $log('index', "Índice {$IDX} já existe — nada a fazer", true);
} else {
    $log('index', "Criando índice {$IDX} em {$TABLE}(imported_year_month)", true);
    $pdo->exec("CREATE INDEX `{$IDX}` ON `{$TABLE}` (imported_year_month)");
    $log('index.done', "Índice {$IDX} criado com sucesso", true);
}

// ─── 5. Verificação final ─────────────────────────────────────────────────────

$hasArchived  = col010Exists($pdo, $dbName, $TABLE, 'archived_at');
$hasGenerated = col010Exists($pdo, $dbName, $TABLE, 'imported_year_month');
$hasIndex     = idx010Exists($pdo, $dbName, $TABLE, $IDX);

$ok = $hasArchived && $hasGenerated && $hasIndex;
$log(
    'verify',
    $ok
        ? "Verificação final: archived_at + imported_year_month + índice OK ✓"
        : "FALHA: archived_at={$hasArchived} imported_year_month={$hasGenerated} index={$hasIndex}",
    $ok
);

crm_smoke_emit($steps, $ok ? 0 : 1);
