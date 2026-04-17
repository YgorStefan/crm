<?php
/**
 * Verificação local da Fase 02.1 — tudo que não precisa de DB ou servidor web.
 */
declare(strict_types=1);

$ok  = 0;
$fail = 0;

function chk(string $label, bool $pass): void {
    global $ok, $fail;
    if ($pass) {
        echo "  OK  $label\n";
        $ok++;
    } else {
        echo " FAIL $label\n";
        $fail++;
    }
}

$idx      = file_get_contents(__DIR__ . '/../../public/index.php');
$htaccess = file_get_contents(__DIR__ . '/../../public/.htaccess');
$auth     = file_get_contents(__DIR__ . '/../../app/Controllers/AuthController.php');
$login    = file_get_contents(__DIR__ . '/../../app/Views/auth/login.php');
$cfg      = file_get_contents(__DIR__ . '/../../config/app.php');
$rl       = file_get_contents(__DIR__ . '/../../app/Services/RateLimiter.php');
$uc       = file_get_contents(__DIR__ . '/../../app/Controllers/UserController.php');
$tc       = file_get_contents(__DIR__ . '/../../app/Controllers/TenantController.php');
$ui       = file_get_contents(__DIR__ . '/../../app/Models/UserInvitation.php');
$schema   = file_get_contents(__DIR__ . '/../../database/schema.sql');

echo "\n=== SESSION COOKIE (D-07) ===\n";
chk('session_set_cookie_params() presente', str_contains($idx, 'session_set_cookie_params'));
chk("samesite => 'Lax'", str_contains($idx, "'samesite' => 'Lax'"));
chk("httponly => true", str_contains($idx, "'httponly' => true"));
chk('secure condicional ($isHttps)', str_contains($idx, "'secure'   => \$isHttps"));
chk('ini_set cookie_httponly removido', !str_contains($idx, "ini_set('session.cookie_httponly'"));
chk('ini_set cookie_samesite removido', !str_contains($idx, "ini_set('session.cookie_samesite'"));

echo "\n=== APACHE HEADERS (D-08) ===\n";
chk('X-Frame-Options DENY', str_contains($htaccess, 'X-Frame-Options "DENY"'));
chk('Referrer-Policy presente', str_contains($htaccess, 'Referrer-Policy'));
chk('Permissions-Policy presente', str_contains($htaccess, 'Permissions-Policy'));
chk('X-XSS-Protection removido (sem diretiva Header set)', !str_contains($htaccess, 'Header set X-XSS-Protection') && !str_contains($htaccess, 'Header always set X-XSS-Protection'));
chk('X-Content-Type-Options nosniff mantido', str_contains($htaccess, 'X-Content-Type-Options'));
chk('RewriteEngine intacto', str_contains($htaccess, 'RewriteEngine On'));

echo "\n=== RATE LIMITER (D-01..D-05) ===\n";
chk('arquivo RateLimiter.php existe', file_exists(__DIR__ . '/../../app/Services/RateLimiter.php'));
chk('final class RateLimiter', str_contains($rl, 'final class RateLimiter'));
chk('namespace App\\Services', str_contains($rl, 'namespace App\\Services'));
chk('WINDOW_SECONDS = 900', str_contains($rl, 'private const WINDOW_SECONDS = 900'));
chk('MAX_ATTEMPTS = 5', str_contains($rl, 'private const MAX_ATTEMPTS = 5'));
chk('CLEANUP_CHANCE = 5', str_contains($rl, 'private const CLEANUP_CHANCE = 5'));
chk('check() public static', str_contains($rl, 'public static function check(string $ip, string $email): int'));
chk('recordAttempt() public static', str_contains($rl, 'public static function recordAttempt(string $ip, string $email): void'));
chk('cleanup() private static', str_contains($rl, 'private static function cleanup(): void'));
chk('WHERE usa AND (nao OR)', str_contains($rl, 'ip_address = :ip AND email = :email'));

echo "\n=== SCHEMA SQL (login_attempts) ===\n";
chk('CREATE TABLE login_attempts presente', str_contains($schema, 'CREATE TABLE IF NOT EXISTS login_attempts'));
chk('coluna ip_address VARCHAR(45)', str_contains($schema, 'ip_address   VARCHAR(45)'));
chk('coluna attempted_at TIMESTAMP', str_contains($schema, 'attempted_at TIMESTAMP'));
chk('indice composto idx_login_attempts_ip_email_at', str_contains($schema, 'idx_login_attempts_ip_email_at'));

echo "\n=== AUTH CONTROLLER (D-04..D-06) ===\n";
chk('use App\\Services\\RateLimiter presente', str_contains($auth, 'use App\\Services\\RateLimiter'));
chk('RateLimiter::check() chamado', str_contains($auth, 'RateLimiter::check($auditIp'));
chk('RateLimiter::recordAttempt() chamado', str_contains($auth, 'RateLimiter::recordAttempt($auditIp'));
chk("evento 'auth.login_blocked' presente", str_contains($auth, "'auth.login_blocked'"));
chk('email_hash no metadata (D-06)', str_contains($auth, "'email_hash' => hash('sha256'"));
chk("email => \$email removido do metadata", !preg_match("/'email'\s*=>\s*\\\$email/", $auth));
chk('rate limit antigo (countRecentFailures) removido', !str_contains($auth, 'countRecentFailures'));
chk('mensagem de bloqueio D-04', str_contains($auth, 'Muitas tentativas. Tente novamente em'));

echo "\n=== PASSWORD POLICY (D-09..D-10) ===\n";
chk('isValidPassword() definida em config/app.php', str_contains($cfg, 'function isValidPassword'));
chk('mb_strlen (multibyte safe)', str_contains($cfg, "mb_strlen(\$password, 'UTF-8')"));
chk('regex letra [a-zA-Z]', str_contains($cfg, "preg_match('/[a-zA-Z]/'"));
chk('regex digito [0-9]', str_contains($cfg, "preg_match('/[0-9]/'"));
chk('guard function_exists', str_contains($cfg, "function_exists('isValidPassword')"));
chk('UserController usa isValidPassword', str_contains($uc, 'isValidPassword'));
chk('TenantController usa isValidPassword', str_contains($tc, 'isValidPassword'));
chk('UserInvitation usa isValidPassword', str_contains($ui, 'isValidPassword'));
chk('strlen antigo removido de UserController', !str_contains($uc, 'strlen($password) < MIN_PASSWORD_LENGTH'));
chk('strlen antigo removido de TenantController', !str_contains($tc, 'strlen($password) < MIN_PASSWORD_LENGTH'));
chk('mensagem unificada em UserController', str_contains($uc, 'A senha deve ter pelo menos 8 caracteres, incluindo letras e números.'));
chk('mensagem unificada em TenantController', str_contains($tc, 'A senha deve ter pelo menos 8 caracteres, incluindo letras e números.'));
chk('mensagem unificada em UserInvitation', str_contains($ui, 'A senha deve ter pelo menos 8 caracteres, incluindo letras e números.'));

echo "\n=== LOGIN UX (org_slug obrigatorio) ===\n";
chk('"slug — opcional" removido do label', !str_contains($login, 'slug — opcional'));
chk('asterisco vermelho (text-red-500)', str_contains($login, 'text-red-500'));
chk('texto ajuda atualizado', str_contains($login, 'Obtenha com o administrador'));
chk('campo org_slug nao removido', str_contains($login, 'name="org_slug"'));
chk('placeholder="minha-empresa" mantido', str_contains($login, 'placeholder="minha-empresa"'));

echo "\n=== SMOKE TESTS (syntax only — sem DB) ===\n";
$smokeFiles = [
    'scripts/smoke/02.1-rate-limit.php',
    'scripts/smoke/02.1-audit.php',
    'scripts/smoke/02.1-password-policy.php',
];
foreach ($smokeFiles as $f) {
    $path = __DIR__ . '/../../' . $f;
    chk("$f existe", file_exists($path));
}

echo "\n=== RESULTADO FINAL ===\n";
$total = $ok + $fail;
echo "  PASSOU: $ok / $total\n";
if ($fail > 0) {
    echo "  FALHOU: $fail / $total\n";
} else {
    echo "  TUDO OK — nenhuma falha detectada\n";
}
echo "\n";
exit($fail > 0 ? 1 : 0);
