<?php
/**
 * Applies database/schema.sql to the configured database.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$config = require ROOT_PATH . DS . 'config' . DS . 'database.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=' . $config['charset'],
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $config['dbname'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $config['dbname'] . '`');

    $sql = file_get_contents(ROOT_PATH . DS . 'database' . DS . 'schema.sql');
    if ($sql === false) {
        echo 'ERROR: Could not read schema.sql' . PHP_EOL;
        exit(1);
    }

    // Split on semicolons followed by newline or end-of-string
    $statements = preg_split('/;\s*\n/', $sql);
    $ok = 0;
    $warn = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
            $ok++;
        } catch (PDOException $e) {
            // Ignore "already exists" type errors
            if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'Duplicate')) {
                $warn++;
            } else {
                echo 'WARN: ' . $e->getMessage() . PHP_EOL;
                $warn++;
            }
        }
    }

    echo "Schema import complete: {$ok} statements OK, {$warn} warnings" . PHP_EOL;

    // Verify phase 2 objects
    $tables = $pdo->query('SHOW TABLES FROM `' . $config['dbname'] . '`')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['tenant_user_invitations', 'audit_logs'] as $t) {
        echo (in_array($t, $tables, true) ? 'FOUND' : 'MISSING') . ': ' . $t . PHP_EOL;
    }
    $idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_tenant_email'")->fetchAll();
    echo (count($idx) > 0 ? 'FOUND' : 'MISSING') . ': uq_users_tenant_email' . PHP_EOL;

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
