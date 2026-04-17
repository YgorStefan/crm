<?php
/**
 * Smoke: Phase 02.1 — Password policy validation tests.
 * Tests isValidPassword() helper from config/app.php.
 * Run: php scripts/smoke/02.1-password-policy.php
 * Per D-09: min 8 chars + at least 1 letter + at least 1 number. mb_strlen safe.
 */
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';
// bootstrap.php already loads config/app.php which will define isValidPassword()

$steps  = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) { $failed = true; }
};

// Guard: helper must exist
if (!function_exists('isValidPassword')) {
    $add('password_policy.helper_function_exists', 'isValidPassword() not yet defined in config/app.php (Plan 04 pending)', false);
    crm_smoke_emit($steps, 1);
}
$add('password_policy.helper_function_exists', 'isValidPassword() is defined', true);

// Test 1: too short — 7 chars with letter + number → false (D-09: min 8)
$r = isValidPassword('Abc1234');
$add('password_policy.rejects_too_short', $r === false ? "Rejected 'Abc1234' (7 chars)" : "FAIL: accepted 'Abc1234' — should reject (<8 chars)", $r === false, ['input' => 'Abc1234', 'result' => $r]);

// Test 2: letters only, 8+ chars → false (D-09: needs digit)
$r = isValidPassword('abcdefgh');
$add('password_policy.rejects_letters_only', $r === false ? "Rejected 'abcdefgh' (no digit)" : "FAIL: accepted 'abcdefgh' — should reject (no digit)", $r === false, ['input' => 'abcdefgh', 'result' => $r]);

// Test 3: numbers only, 8+ chars → false (D-09: needs letter)
$r = isValidPassword('12345678');
$add('password_policy.rejects_numbers_only', $r === false ? "Rejected '12345678' (no letter)" : "FAIL: accepted '12345678' — should reject (no letter)", $r === false, ['input' => '12345678', 'result' => $r]);

// Test 4: valid — 8 chars, has letter and number → true
$r = isValidPassword('Abcd1234');
$add('password_policy.accepts_valid_password', $r === true ? "Accepted 'Abcd1234' (valid)" : "FAIL: rejected 'Abcd1234' — should accept", $r === true, ['input' => 'Abcd1234', 'result' => $r]);

// Test 5: valid with special chars → true (special chars are allowed, not required)
$r = isValidPassword('Abc#1234');
$add('password_policy.accepts_valid_with_special_chars', $r === true ? "Accepted 'Abc#1234' (valid, has special char)" : "FAIL: rejected 'Abc#1234' — should accept", $r === true);

// Test 6: multibyte safety — 'çãõabc1' has 7 Unicode chars but >7 bytes; mb_strlen should return 7 → false
// If strlen() is used (bytes): strlen('çãõabc1') = 10 bytes → would wrongly pass; mb_strlen = 7 → correctly reject
$r = isValidPassword('çãõabc1');
$add(
    'password_policy.multibyte_safe',
    $r === false ? "Rejected 'çãõabc1' (7 Unicode chars — multibyte-safe check)" : "FAIL: accepted 'çãõabc1' — strlen() used instead of mb_strlen(); 7 chars should be rejected",
    $r === false,
    ['input' => 'çãõabc1', 'mb_strlen' => mb_strlen('çãõabc1', 'UTF-8'), 'strlen' => strlen('çãõabc1')]
);

// Test 7: exactly MIN_PASSWORD_LENGTH chars with letter + number → true
$r = isValidPassword('Abcde12345'[0] === 'A' ? 'Abcde123' : 'Abcde123'); // 8 chars
$add('password_policy.accepts_exactly_min_length', $r === true ? "Accepted 8-char valid password" : "FAIL: rejected 8-char valid password", $r === true);

crm_smoke_emit($steps, $failed ? 1 : 0);
