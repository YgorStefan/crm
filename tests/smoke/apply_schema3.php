<?php
/**
 * Applies database/schema.sql using PDO with a robust statement splitter.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$config = require ROOT_PATH . DS . 'config' . DS . 'database.php';

$sql = file_get_contents(ROOT_PATH . DS . 'database' . DS . 'schema.sql');
if ($sql === false) {
    echo 'ERROR: Cannot read schema.sql' . PHP_EOL;
    exit(1);
}

// Normalize line endings to LF
$sql = str_replace("\r\n", "\n", $sql);
$sql = str_replace("\r", "\n", $sql);

try {
    // Connect without selecting DB first
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4',
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $config['dbname'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $config['dbname'] . '`');
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("SET sql_mode = ''");
} catch (PDOException $e) {
    echo 'Connection error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Split SQL into individual statements
// Strategy: split on ';' that is not inside quotes or comments
$statements = [];
$current = '';
$inSingleQuote = false;
$inDoubleQuote = false;
$inLineComment = false;
$inBlockComment = false;
$len = strlen($sql);

for ($i = 0; $i < $len; $i++) {
    $c = $sql[$i];
    $next = $sql[$i + 1] ?? '';

    if ($inLineComment) {
        $current .= $c;
        if ($c === "\n") {
            $inLineComment = false;
        }
        continue;
    }

    if ($inBlockComment) {
        $current .= $c;
        if ($c === '*' && $next === '/') {
            $current .= $next;
            $i++;
            $inBlockComment = false;
        }
        continue;
    }

    if ($c === '-' && $next === '-') {
        $inLineComment = true;
        $current .= $c;
        continue;
    }

    if ($c === '/' && $next === '*') {
        $inBlockComment = true;
        $current .= $c;
        continue;
    }

    if ($c === "'" && !$inDoubleQuote) {
        $inSingleQuote = !$inSingleQuote;
        $current .= $c;
        continue;
    }

    if ($c === '"' && !$inSingleQuote) {
        $inDoubleQuote = !$inDoubleQuote;
        $current .= $c;
        continue;
    }

    if ($c === ';' && !$inSingleQuote && !$inDoubleQuote) {
        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }
        $current = '';
        continue;
    }

    $current .= $c;
}

// Last statement without semicolon
$stmt = trim($current);
if ($stmt !== '') {
    $statements[] = $stmt;
}

echo 'Statements to execute: ' . count($statements) . PHP_EOL;

$ok = 0;
$skipped = 0;
$errors = 0;
foreach ($statements as $stmt) {
    // Skip pure comment lines
    if (preg_match('/^(--.*)$/', $stmt) || trim($stmt) === '') {
        $skipped++;
        continue;
    }
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // "already exists" / duplicate key on index — expected on re-import
        if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate key name')) {
            $skipped++;
        } else {
            echo 'ERROR [' . substr($stmt, 0, 60) . ']: ' . $msg . PHP_EOL;
            $errors++;
        }
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "Done: {$ok} executed, {$skipped} skipped/already-exist, {$errors} errors" . PHP_EOL;

// Verify
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables (' . count($tables) . '): ' . implode(', ', $tables) . PHP_EOL;

foreach (['tenant_user_invitations', 'audit_logs'] as $t) {
    echo (in_array($t, $tables, true) ? 'FOUND' : 'MISSING') . ': ' . $t . PHP_EOL;
}

$idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_tenant_email'")->fetchAll();
echo (count($idx) > 0 ? 'FOUND' : 'MISSING') . ': uq_users_tenant_email' . PHP_EOL;
