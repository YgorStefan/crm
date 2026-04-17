<?php
/**
 * Applies database/schema.sql to the configured database using mysqli multi-query.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$config = require ROOT_PATH . DS . 'config' . DS . 'database.php';

$sql = file_get_contents(ROOT_PATH . DS . 'database' . DS . 'schema.sql');
if ($sql === false) {
    echo 'ERROR: Cannot read schema.sql' . PHP_EOL;
    exit(1);
}

echo 'File size: ' . strlen($sql) . ' bytes' . PHP_EOL;

// Use mysqli for multi-query support (better for .sql files)
$mysqli = new mysqli($config['host'], $config['user'], $config['pass'], '', (int)$config['port']);
if ($mysqli->connect_error) {
    echo 'Connection error: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$mysqli->query('CREATE DATABASE IF NOT EXISTS `' . $config['dbname'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$mysqli->select_db($config['dbname']);
$mysqli->set_charset('utf8mb4');

// Execute schema SQL
if ($mysqli->multi_query($sql)) {
    $ok = 0;
    $warn = 0;
    do {
        $ok++;
        if ($mysqli->more_results()) {
            $mysqli->next_result();
        }
    } while ($mysqli->more_results());
    echo "multi_query: {$ok} result sets" . PHP_EOL;
} else {
    echo 'multi_query error: ' . $mysqli->error . PHP_EOL;
}

// Re-connect to check results
$mysqli->close();
$mysqli = new mysqli($config['host'], $config['user'], $config['pass'], $config['dbname'], (int)$config['port']);

$tables = [];
$res = $mysqli->query('SHOW TABLES');
while ($row = $res->fetch_row()) {
    $tables[] = $row[0];
}
echo 'Tables (' . count($tables) . '): ' . implode(', ', $tables) . PHP_EOL;

foreach (['tenant_user_invitations', 'audit_logs'] as $t) {
    echo (in_array($t, $tables, true) ? 'FOUND' : 'MISSING') . ': ' . $t . PHP_EOL;
}

$res2 = $mysqli->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_tenant_email'");
$idx = $res2->fetch_all();
echo (count($idx) > 0 ? 'FOUND' : 'MISSING') . ': uq_users_tenant_email' . PHP_EOL;

$mysqli->close();
