<?php
/**
 * Smoke: Phase 02.1 — RateLimiter behavioral tests.
 * Tests: login_attempts schema, check/block/expiry behavior.
 * Run: php scripts/smoke/02.1-rate-limit.php
 */
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

// PSR-4 autoloader for App\ and Core\ (mirrors public/index.php)
spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\' => ROOT_PATH . DS . 'core' . DS,
        'App\\'  => ROOT_PATH . DS . 'app'  . DS,
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', DS, substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

$steps  = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) { $failed = true; }
};

// --- Guard: RateLimiter class must exist ---
$rateLimiterFile = ROOT_PATH . DS . 'app' . DS . 'Services' . DS . 'RateLimiter.php';
if (!file_exists($rateLimiterFile)) {
    $add('rate_limit.class_exists', 'app/Services/RateLimiter.php not yet created (Wave 1 pending)', false, ['file' => $rateLimiterFile]);
    crm_smoke_emit($steps, 1);
}

$add('rate_limit.class_exists', 'app/Services/RateLimiter.php exists', true);

// --- DB: login_attempts table ---
try {
    $pdo = crm_smoke_pdo();
} catch (\Throwable $e) {
    $add('schema.db_connection', 'DB connection failed: ' . $e->getMessage(), false);
    crm_smoke_emit($steps, 1);
}

$tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'login_attempts'")->fetchColumn();
$add('schema.login_attempts_table_exists', $tableExists ? 'login_attempts table exists' : 'login_attempts table missing', $tableExists);
if (!$tableExists) {
    crm_smoke_emit($steps, 1);
}

// Column check
$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM login_attempts') as $row) {
    $cols[] = $row['Field'];
}
$requiredCols = ['id', 'ip_address', 'email', 'attempted_at'];
$missingCols  = array_diff($requiredCols, $cols);
$add(
    'schema.login_attempts_columns',
    $missingCols === [] ? 'All required columns present' : 'Missing columns: ' . implode(', ', $missingCols),
    $missingCols === [],
    ['columns' => $cols, 'missing' => array_values($missingCols)]
);

// --- Behavioral tests ---
$testIp    = '127.0.0.127';
$testEmail = 'smoke-rate-limit@crm.test';

// Clean slate
$pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip AND email = :email')
    ->execute([':ip' => $testIp, ':email' => $testEmail]);

// Test: check() returns 0 with no attempts
try {
    $result = \App\Services\RateLimiter::check($testIp, $testEmail);
    $add(
        'rate_limit.check_no_attempts_returns_zero',
        $result === 0 ? 'check() returns 0 with no attempts' : "check() returned {$result}, expected 0",
        $result === 0,
        ['result' => $result]
    );
} catch (\Throwable $e) {
    $add('rate_limit.check_no_attempts_returns_zero', 'Exception: ' . $e->getMessage(), false);
}

// Test: insert 5 attempts (direct SQL to avoid side effects)
$stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (:ip, :email, NOW())');
for ($i = 0; $i < 5; $i++) {
    $stmt->execute([':ip' => $testIp, ':email' => $testEmail]);
}
$add('rate_limit.record_five_attempts', 'Inserted 5 test attempts into login_attempts', true);

// Test: check() returns positive int after 5 attempts (D-03: MAX_ATTEMPTS = 5)
try {
    $result = \App\Services\RateLimiter::check($testIp, $testEmail);
    $add(
        'rate_limit.check_blocked_after_five_attempts',
        $result > 0 ? "check() returns {$result}s remaining (blocked)" : 'check() returned 0 after 5 attempts — not blocked',
        $result > 0,
        ['result' => $result]
    );
} catch (\Throwable $e) {
    $add('rate_limit.check_blocked_after_five_attempts', 'Exception: ' . $e->getMessage(), false);
}

// Test: after window expires, check() returns 0 again (D-03: 15-min window)
$pdo->prepare(
    'UPDATE login_attempts SET attempted_at = DATE_SUB(NOW(), INTERVAL 20 MINUTE)
     WHERE ip_address = :ip AND email = :email'
)->execute([':ip' => $testIp, ':email' => $testEmail]);

try {
    $result = \App\Services\RateLimiter::check($testIp, $testEmail);
    $add(
        'rate_limit.check_expired_window_returns_zero',
        $result === 0 ? 'check() returns 0 after window expires (auto-recovery)' : "check() returned {$result} after window expired — expected 0",
        $result === 0,
        ['result' => $result]
    );
} catch (\Throwable $e) {
    $add('rate_limit.check_expired_window_returns_zero', 'Exception: ' . $e->getMessage(), false);
}

// Cleanup
$pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip AND email = :email')
    ->execute([':ip' => $testIp, ':email' => $testEmail]);

crm_smoke_emit($steps, $failed ? 1 : 0);
