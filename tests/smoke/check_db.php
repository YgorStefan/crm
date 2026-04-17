<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $pdo = crm_smoke_pdo();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'DB OK: ' . count($tables) . ' tables' . PHP_EOL;
    echo implode(', ', $tables) . PHP_EOL;

    // Check phase 2 tables
    $phase2 = ['tenant_user_invitations', 'audit_logs'];
    foreach ($phase2 as $t) {
        $exists = in_array($t, $tables, true);
        echo ($exists ? 'FOUND' : 'MISSING') . ': ' . $t . PHP_EOL;
    }

    // Check uq_users_tenant_email index
    $indexes = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_tenant_email'")->fetchAll();
    echo (count($indexes) > 0 ? 'FOUND' : 'MISSING') . ': uq_users_tenant_email index on users' . PHP_EOL;
} catch (Exception $e) {
    echo 'DB ERROR: ' . $e->getMessage() . PHP_EOL;
}
