<?php
/**
 * Verificação de fechamento da Fase 3 — TEN-01, TEN-06, CRM-01, CRM-02, D-12, D-13.
 * Requer acesso ao banco de dados para as verificações de TEN-06.
 * Executa apenas SELECTs (read-only) — não faz INSERT, UPDATE ou DELETE.
 *
 * Uso: php scripts/smoke/verify_phase03_closure.php
 * Exit 0 = todas as verificações obrigatórias passaram.
 * Exit 1 = pelo menos uma verificação obrigatória falhou.
 */
declare(strict_types=1);

$failures = 0;

// ----- Conexão com o banco -----
require __DIR__ . '/../../config/app.php';
$dbConfig = require __DIR__ . '/../../config/database.php';

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[PASS] DB: conexão estabelecida com {$dbConfig['dbname']}\n";
} catch (\PDOException $e) {
    echo "[FAIL] DB: não foi possível conectar — " . $e->getMessage() . "\n";
    echo "       Verifique o arquivo .env e tente novamente.\n";
    exit(1);
}

echo "\n=== TEN-06: Backfill de dados legados (tenant_id = 0) ===\n";

// TEN-06: Nenhuma linha órfã deve existir após o backfill
$tables = ['clients', 'interactions', 'tasks', 'pipeline_stages', 'cold_contacts'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE tenant_id IS NULL OR tenant_id = 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int) $row['cnt'];
    if ($count === 0) {
        echo "[PASS] TEN-06 {$table}: no orphaned rows (tenant_id = 0)\n";
    } else {
        echo "[FAIL] TEN-06 {$table}: {$count} orphaned rows with tenant_id = 0 — run database/backfill_tenant_legacy.sql\n";
        $failures++;
    }
}

echo "\n=== TEN-01: Cobertura de TenantContextMiddleware nas rotas autenticadas ===\n";

// TEN-01: Verificação via grep no index.php
$indexContent = file_get_contents(__DIR__ . '/../../public/index.php');
$authenticatedRoutes = substr_count($indexContent, 'TenantContextMiddleware');
if ($authenticatedRoutes >= 10) {
    echo "[PASS] TEN-01: TenantContextMiddleware found in {$authenticatedRoutes} route registrations\n";
} else {
    echo "[WARN] TEN-01: Only {$authenticatedRoutes} TenantContextMiddleware references found — expected 10+\n";
}

echo "\n=== CRM-01: Métodos CRUD no ClientController ===\n";

// CRM-01: Verificar que os 6 métodos CRUD existem no ClientController
$clientController = file_get_contents(__DIR__ . '/../../app/Controllers/ClientController.php');
foreach (['store', 'index', 'show', 'edit', 'update', 'destroy'] as $method) {
    if (str_contains($clientController, "function {$method}(")) {
        echo "[PASS] CRM-01: ClientController::{$method}() exists\n";
    } else {
        echo "[FAIL] CRM-01: ClientController::{$method}() not found\n";
        $failures++;
    }
}

echo "\n=== CRM-02: Escopo de tenant no model Interaction ===\n";

// CRM-02: Interaction model usa tenant scoping
$interactionModel = file_get_contents(__DIR__ . '/../../app/Models/Interaction.php');
if (str_contains($interactionModel, 'currentTenantId') || str_contains($interactionModel, 'tenant_id')) {
    echo "[PASS] CRM-02: Interaction model uses tenant scoping\n";
} else {
    echo "[FAIL] CRM-02: Interaction model missing tenant scoping\n";
    $failures++;
}

echo "\n=== D-12: Escopo de tenant no model ColdContact ===\n";

// D-12: ColdContact model usa currentTenantId() em todos os 8 métodos (WARNING somente — não incrementa $failures)
$coldContactModel = file_get_contents(__DIR__ . '/../../app/Models/ColdContact.php');
$d12Refs = substr_count($coldContactModel, 'currentTenantId()') + substr_count($coldContactModel, 'tenant_id');
if ($d12Refs >= 8) {
    echo "[PASS] D-12: ColdContact model has {$d12Refs} tenant scope references (currentTenantId / tenant_id) across its 8 methods\n";
} else {
    echo "[WARN] D-12: ColdContact model has only {$d12Refs} tenant scope references — expected ≥8 (one per method).\n";
    echo "       ACTION REQUIRED: Open app/Models/ColdContact.php, identify any method missing currentTenantId() or WHERE tenant_id = currentTenantId(), and add the filter before proceeding to Plan 02.\n";
}

echo "\n=== D-13: AcompanhamentoController delega apenas para models com escopo de tenant ===\n";

// D-13: AcompanhamentoController referencias tenant scoping (WARNING somente — não incrementa $failures)
if (file_exists(__DIR__ . '/../../app/Controllers/AcompanhamentoController.php')) {
    $acompController = file_get_contents(__DIR__ . '/../../app/Controllers/AcompanhamentoController.php');
    $d13Scoped = str_contains($acompController, 'currentTenantId') || str_contains($acompController, 'tenant_id');
    if ($d13Scoped) {
        echo "[PASS] D-13: AcompanhamentoController references tenant scoping (currentTenantId / tenant_id)\n";
    } else {
        echo "[WARN] D-13: AcompanhamentoController has no visible tenant scope references.\n";
        echo "       ACTION REQUIRED: Verify that every model method called by AcompanhamentoController already enforces tenant isolation internally (e.g., via Model::currentTenantId()). If any direct DB query or method call bypasses tenant filtering, fix it before proceeding to Plan 02.\n";
    }
} else {
    echo "[INFO] D-13: AcompanhamentoController.php not found — skipping check\n";
}

echo "\n=== RESULTADO FINAL ===\n";
if ($failures === 0) {
    echo "[PASS] Todos os checks obrigatórios passaram — Fase 3 pode prosseguir para o Plano 02.\n";
} else {
    echo "[FAIL] {$failures} check(s) obrigatório(s) falharam — corrija antes de prosseguir.\n";
}
echo "\n";
exit($failures === 0 ? 0 : 1);
