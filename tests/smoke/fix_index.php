<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

try {
    $pdo = crm_smoke_pdo();

    // Check if the unique index already exists
    $idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_tenant_email'")->fetchAll();
    if (count($idx) > 0) {
        echo 'ALREADY EXISTS: uq_users_tenant_email' . PHP_EOL;
        exit(0);
    }

    // Drop old global unique key on email if it exists
    $emailIdx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'email' AND Non_unique = 0")->fetchAll();
    if (count($emailIdx) > 0) {
        $pdo->exec('ALTER TABLE users DROP INDEX email');
        echo 'Dropped old global unique email index' . PHP_EOL;
    }

    // Add composite unique key
    $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_tenant_email (tenant_id, email)');
    echo 'CREATED: uq_users_tenant_email' . PHP_EOL;
} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
