<?php
/**
 * Migration 009 — Backfill de tenant_id na tabela interactions.
 *
 * Rodar APÓS a coluna interactions.tenant_id existir no banco
 * (criada pela migration inicial ou pelo schema.sql atualizado).
 *
 * Popula interactions.tenant_id a partir do clients.tenant_id via JOIN.
 *
 * Execute: php database/migrations/009_backfill_interactions_tenant.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$col = $pdo->query("SHOW COLUMNS FROM interactions LIKE 'tenant_id'")->fetch();
if (!$col) {
    echo "ERRO: coluna interactions.tenant_id não existe. Rode o schema.sql antes.\n";
    exit(1);
}

$stmt = $pdo->prepare("
    UPDATE interactions i
    INNER JOIN clients c ON c.id = i.client_id
    SET i.tenant_id = c.tenant_id
    WHERE i.tenant_id IS NULL OR i.tenant_id = 0
");
$stmt->execute();
echo "Interactions atualizadas: {$stmt->rowCount()}\n";

$orphans = (int) $pdo->query("SELECT COUNT(*) FROM interactions WHERE tenant_id IS NULL OR tenant_id = 0")->fetchColumn();
if ($orphans > 0) {
    echo "AVISO: {$orphans} interaction(s) sem client_id válido — atribuindo tenant_id = 1\n";
    $pdo->exec("UPDATE interactions SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0");
}

echo "Migration 009 concluída.\n";
