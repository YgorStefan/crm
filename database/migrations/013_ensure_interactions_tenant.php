<?php
/**
 * Migration 013 — Garante coluna tenant_id em interactions (e backfill).
 *
 * Por que existe:
 *   O schema.sql já declara interactions.tenant_id, mas bancos criados antes
 *   dessa adição (produção atual) ficaram sem a coluna. Sem ela, qualquer
 *   INSERT via Interaction::create() lança SQLSTATE[42S22] e devolve HTTP 500
 *   em /interactions/store. Esta migration cria a coluna se faltar, popula
 *   a partir de clients.tenant_id e adiciona índice + FK quando seguro.
 *
 * Idempotente: pode ser executada múltiplas vezes sem efeito colateral.
 *
 * Execute: php database/migrations/013_ensure_interactions_tenant.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$col = $pdo->query("SHOW COLUMNS FROM interactions LIKE 'tenant_id'")->fetch();
if (!$col) {
    $pdo->exec("
        ALTER TABLE interactions
        ADD COLUMN tenant_id INT UNSIGNED NOT NULL DEFAULT 1
            COMMENT 'Isolamento multi-tenant'
        AFTER occurred_at
    ");
    echo "Migration 013: coluna interactions.tenant_id criada.\n";
} else {
    echo "Migration 013: coluna interactions.tenant_id já existe — ignorado.\n";
}

$updated = $pdo->exec("
    UPDATE interactions i
    INNER JOIN clients c ON c.id = i.client_id
    SET i.tenant_id = c.tenant_id
    WHERE i.tenant_id IS NULL OR i.tenant_id = 0
");
echo "Migration 013: {$updated} interaction(s) recebeu(ram) tenant_id via JOIN.\n";

$orphans = (int) $pdo->query("SELECT COUNT(*) FROM interactions WHERE tenant_id IS NULL OR tenant_id = 0")->fetchColumn();
if ($orphans > 0) {
    echo "Migration 013: AVISO — {$orphans} órfã(s) sem client_id válido — atribuindo tenant_id = 1.\n";
    $pdo->exec("UPDATE interactions SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0");
}

$idx = $pdo->query("SHOW INDEX FROM interactions WHERE Key_name = 'idx_interactions_tenant'")->fetch();
if (!$idx) {
    $pdo->exec("CREATE INDEX idx_interactions_tenant ON interactions(tenant_id)");
    echo "Migration 013: índice idx_interactions_tenant criado.\n";
}

$fk = $pdo->query("
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'interactions'
      AND CONSTRAINT_NAME = 'fk_interactions_tenant'
")->fetch();
if (!$fk) {
    try {
        $pdo->exec("
            ALTER TABLE interactions
            ADD CONSTRAINT fk_interactions_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
                ON DELETE CASCADE
        ");
        echo "Migration 013: FK fk_interactions_tenant criada.\n";
    } catch (Throwable $e) {
        echo "Migration 013: AVISO — não foi possível criar FK (não bloqueia): " . $e->getMessage() . "\n";
    }
}

echo "Migration 013 concluída.\n";
