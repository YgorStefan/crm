<?php
/**
 * Migration 005 — Garantir que todos os pipeline_stages tenham tenant_id correto.
 *
 * O campo tenant_id existe com DEFAULT 1. Esta migration verifica se há stages
 * com tenant_id = 0 ou NULL e os atribui ao tenant 1 (default).
 * Se houver apenas 1 tenant ativo, não há ação necessária.
 *
 * Execute: php database/migrations/005_pipeline_stages_assign_tenants.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$tenantCount = (int) $pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
echo "Tenants no banco: {$tenantCount}\n";

$stmt = $pdo->prepare("UPDATE pipeline_stages SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0");
$stmt->execute();
echo "Stages corrigidos: {$stmt->rowCount()}\n";

$rows = $pdo->query("SELECT tenant_id, COUNT(*) as total FROM pipeline_stages GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  tenant_id={$r['tenant_id']}: {$r['total']} stages\n";
}

echo "Migration 005 concluída.\n";
