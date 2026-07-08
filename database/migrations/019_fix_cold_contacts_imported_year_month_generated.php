<?php
/**
 * Migration 019 — Garante que cold_contacts.imported_year_month seja GENERATED.
 *
 * Por que existe:
 *   schema.sql e a migration 010 declaram imported_year_month como coluna
 *   GENERATED ALWAYS AS (DATE_FORMAT(imported_at, '%Y-%m')) STORED, mas em
 *   bancos provisionados antes da migration 010 ela pode ter ficado como
 *   VARCHAR(7) comum (drift de schema). Como MySQL/MariaDB rejeitam INSERT
 *   com valor explicito em coluna GENERATED, o codigo (App\Models\ColdContact)
 *   so pode confiar na auto-geracao se a coluna realmente for GENERATED em
 *   TODOS os ambientes — por isso esta migration normaliza o schema.
 *
 *   Se a coluna ja existir e ja for GENERATED, nao faz nada. Se existir como
 *   coluna comum, recria como GENERATED (o valor e 100% derivavel de
 *   imported_at, entao recriar nao perde informacao). Se nao existir, cria.
 *
 * Idempotente: pode ser executada multiplas vezes sem efeito colateral.
 *
 * Execute: php bin/migrate.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

$column = $pdo->prepare("
    SELECT EXTRA
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = :db
      AND TABLE_NAME = 'cold_contacts'
      AND COLUMN_NAME = 'imported_year_month'
");
$column->execute([':db' => $dbName]);
$extra = $column->fetchColumn();

$isGenerated = $extra !== false && str_contains(strtoupper((string) $extra), 'GENERATED');

if ($extra !== false && $isGenerated) {
    echo "Migration 019: imported_year_month ja e GENERATED — ignorado.\n";
    echo "Migration 019 concluida.\n";
    return;
}

if ($extra !== false && !$isGenerated) {
    echo "Migration 019: imported_year_month existe mas nao e GENERATED — recriando.\n";
    $pdo->exec("ALTER TABLE cold_contacts DROP COLUMN imported_year_month");
}

$pdo->exec("
    ALTER TABLE cold_contacts
        ADD COLUMN imported_year_month
            CHAR(7) GENERATED ALWAYS AS (DATE_FORMAT(imported_at, '%Y-%m')) STORED
");

$idx = $pdo->query("SHOW INDEX FROM cold_contacts WHERE Key_name = 'idx_cc_year_month'")->fetch();
if (!$idx) {
    $pdo->exec("CREATE INDEX idx_cc_year_month ON cold_contacts (imported_year_month)");
}

echo "Migration 019: imported_year_month normalizada como GENERATED.\n";
echo "Migration 019 concluida.\n";
