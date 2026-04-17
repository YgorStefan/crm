<?php
/**
 * Migração idempotente — Fase 3: tenant_id em cold_contacts.
 *
 * Aplica a coluna tenant_id INT UNSIGNED NOT NULL à tabela cold_contacts,
 * faz backfill de todas as linhas sem escopo para tenant_id=1 (default),
 * e adiciona índice + FK para tenants(id).
 *
 * Idempotente: cada passo verifica o estado atual antes de executar.
 *
 * Uso:
 *   php scripts/migrations/migrate_cold_contacts_tenant.php           # executa
 *   php scripts/migrations/migrate_cold_contacts_tenant.php --dry-run # simula
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
    $log('plan',   'Adicionaria tenant_id NOT NULL + backfill + índice + FK em cold_contacts', true);
    $log('done',   'Dry-run concluído', true);
    crm_smoke_emit($steps, 0);
}

// ─── helpers ─────────────────────────────────────────────────────────────────

function cc_columnExists(\PDO $pdo, string $db, string $table, string $col): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t AND COLUMN_NAME=:c'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':c' => $col]);
    return (int) $stmt->fetchColumn() > 0;
}

function cc_indexExists(\PDO $pdo, string $db, string $table, string $idx): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t AND INDEX_NAME=:i'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':i' => $idx]);
    return (int) $stmt->fetchColumn() > 0;
}

function cc_fkExists(\PDO $pdo, string $db, string $table, string $fk): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t AND CONSTRAINT_NAME=:n AND CONSTRAINT_TYPE=:type'
    );
    $stmt->execute([':s' => $db, ':t' => $table, ':n' => $fk, ':type' => 'FOREIGN KEY']);
    return (int) $stmt->fetchColumn() > 0;
}

// ─── conexão ─────────────────────────────────────────────────────────────────

try {
    $pdo = crm_smoke_pdo();
} catch (Throwable $e) {
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

// ─── 1. Adicionar coluna tenant_id (nullable primeiro para backfill) ──────────

if (cc_columnExists($pdo, $dbName, $TABLE, 'tenant_id')) {
    $log('column', "tenant_id já existe em {$TABLE} — nada a fazer", true);
} else {
    $log('column', "Adicionando tenant_id INT UNSIGNED NULL em {$TABLE}", true);
    $pdo->exec("ALTER TABLE `{$TABLE}` ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id");
}

// ─── 2. Backfill: linhas sem tenant_id → tenant_id = 1 ───────────────────────

$orphans = (int) $pdo->query(
    "SELECT COUNT(*) FROM `{$TABLE}` WHERE tenant_id IS NULL OR tenant_id = 0"
)->fetchColumn();

if ($orphans === 0) {
    $log('backfill', "Nenhuma linha órfã em {$TABLE} — backfill já aplicado", true);
} else {
    $log('backfill', "Backfill: {$orphans} linhas com tenant_id NULL/0 → 1", true);
    $affected = $pdo->exec(
        "UPDATE `{$TABLE}` SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0"
    );
    $log('backfill.done', "Backfill concluído: {$affected} linha(s) atualizada(s)", true);
}

// ─── 3. NOT NULL ──────────────────────────────────────────────────────────────

// Verificar se já é NOT NULL
$nullable = $pdo->query(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA='{$dbName}' AND TABLE_NAME='{$TABLE}' AND COLUMN_NAME='tenant_id'"
)->fetchColumn();

if ($nullable === 'NO') {
    $log('notnull', "tenant_id já é NOT NULL em {$TABLE}", true);
} else {
    $log('notnull', "Definindo tenant_id NOT NULL em {$TABLE}", true);
    $pdo->exec("ALTER TABLE `{$TABLE}` MODIFY tenant_id INT UNSIGNED NOT NULL");
}

// ─── 4. Índice ────────────────────────────────────────────────────────────────

$IDX = 'idx_cold_tenant';
if (cc_indexExists($pdo, $dbName, $TABLE, $IDX)) {
    $log('index', "Índice {$IDX} já existe em {$TABLE}", true);
} else {
    $log('index', "Criando índice {$IDX} em {$TABLE}(tenant_id)", true);
    $pdo->exec("CREATE INDEX `{$IDX}` ON `{$TABLE}` (tenant_id)");
}

// ─── 5. Foreign Key ───────────────────────────────────────────────────────────

$FK = 'fk_cold_contacts_tenant';
if (cc_fkExists($pdo, $dbName, $TABLE, $FK)) {
    $log('fk', "FK {$FK} já existe em {$TABLE}", true);
} else {
    $log('fk', "Adicionando FK {$FK}: cold_contacts.tenant_id → tenants(id)", true);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec(
        "ALTER TABLE `{$TABLE}`
         ADD CONSTRAINT `{$FK}`
         FOREIGN KEY (tenant_id) REFERENCES tenants(id)
         ON UPDATE CASCADE ON DELETE RESTRICT"
    );
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

// ─── 6. Verificação final ─────────────────────────────────────────────────────

$remaining = (int) $pdo->query(
    "SELECT COUNT(*) FROM `{$TABLE}` WHERE tenant_id IS NULL OR tenant_id = 0"
)->fetchColumn();

$log(
    'verify',
    $remaining === 0
        ? "Verificação final: nenhuma linha órfã em {$TABLE} ✓"
        : "FALHA: ainda há {$remaining} linha(s) sem tenant_id em {$TABLE}",
    $remaining === 0
);

// ─── saída ────────────────────────────────────────────────────────────────────

$hasFailed = (bool) count(array_filter($steps, fn($s) => !$s['ok']));
crm_smoke_emit($steps, $hasFailed ? 1 : 0);
