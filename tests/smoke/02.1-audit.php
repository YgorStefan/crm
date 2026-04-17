<?php
/**
 * Smoke: Phase 02.1 — Audit log security assertions.
 * Tests: no plaintext email in auth metadata, email_hash used, auth.login_blocked present.
 * Run: php scripts/smoke/02.1-audit.php
 */
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$steps  = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) { $failed = true; }
};

$authFile = ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'AuthController.php';
if (!file_exists($authFile)) {
    $add('audit.auth_controller_exists', 'AuthController.php not found', false, ['file' => $authFile]);
    crm_smoke_emit($steps, 1);
}

$source = (string) file_get_contents($authFile);

// Test 1: AuthController uses RateLimiter (D-05 / D-03)
$usesRateLimiter = str_contains($source, 'RateLimiter::check') || str_contains($source, 'use App\\Services\\RateLimiter');
$add(
    'audit.auth_controller_calls_rate_limiter',
    $usesRateLimiter ? 'AuthController references RateLimiter' : 'AuthController does not call RateLimiter::check — not yet integrated',
    $usesRateLimiter
);

// Test 2: auth.login_blocked event exists (D-06)
$hasBlockedEvent = str_contains($source, "'auth.login_blocked'") || str_contains($source, '"auth.login_blocked"');
$add(
    'audit.login_blocked_event_exists',
    $hasBlockedEvent ? "auth.login_blocked event present in AuthController" : "auth.login_blocked event missing — not yet added",
    $hasBlockedEvent
);

// Test 3: NO plaintext email in auth metadata — 'email' => $email must not appear in metadata arrays (D-06)
// Pattern: look for "'email' => $email" inside array context (the existing auth.login_failure metadata)
$hasPlaintextEmail = (bool) preg_match("/['\"]email['\"]\s*=>\s*\\\$email/", $source);
$add(
    'audit.no_plaintext_email_in_metadata',
    !$hasPlaintextEmail ? 'No plaintext email found in auth metadata' : "Plaintext email ('email' => \$email) found in metadata — must be replaced with email_hash",
    !$hasPlaintextEmail
);

// Test 4: email_hash pattern present (D-06)
$hasEmailHash = str_contains($source, "'email_hash'");
$add(
    'audit.email_hash_pattern_exists',
    $hasEmailHash ? "email_hash pattern found in AuthController" : "email_hash not found — plaintext email not yet replaced",
    $hasEmailHash
);

crm_smoke_emit($steps, $failed ? 1 : 0);
