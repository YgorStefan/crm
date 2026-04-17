<?php
/**
 * tests/Phase12Test.php — Fase 2: Segurança
 * Execute: php tests/Phase12Test.php
 */
declare(strict_types=1);

$results = ['pass' => 0, 'fail' => 0, 'errors' => []];
function ok(string $desc, bool $cond): void {
    global $results;
    if ($cond) { echo "\033[32m  ✓\033[0m {$desc}\n"; $results['pass']++; }
    else       { echo "\033[31m  ✗\033[0m {$desc}\n"; $results['fail']++; $results['errors'][] = $desc; }
}
function section(string $title): void { echo "\n\033[1;34m── {$title}\033[0m\n"; }

define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . DS . 'app');
define('CORE_PATH', ROOT_PATH . DS . 'core');
define('APP_URL',   'http://localhost');
define('APP_ENV',   'testing');
define('SESSION_LIFETIME', 7200);

// ── 1. RateLimitMiddleware existe ────────────────────────────────────────────
section('1. RateLimitMiddleware');
ok('arquivo existe', file_exists(ROOT_PATH . '/core/Middleware/RateLimitMiddleware.php'));
$src = file_get_contents(ROOT_PATH . '/core/Middleware/RateLimitMiddleware.php');
ok('classe RateLimitMiddleware definida', strpos($src, 'class RateLimitMiddleware') !== false);
ok('método handle() existe',              strpos($src, 'public function handle()') !== false);
ok('consulta login_attempts',             strpos($src, 'login_attempts') !== false);
ok('usa threshold de 5 tentativas',       strpos($src, '5') !== false);

// ── 2. Rotas — RateLimitMiddleware no POST /login ────────────────────────────
section('2. Rotas — RateLimitMiddleware no POST /login');
$routes = file_get_contents(ROOT_PATH . '/config/routes.php');
ok('POST /login tem RateLimitMiddleware', strpos($routes, "post('/login'") !== false
    && preg_match("/post\('\/login'[^;]*RateLimitMiddleware/s", $routes) === 1);

// ── 3. AuthMiddleware — password_must_change ─────────────────────────────────
section('3. AuthMiddleware — password_must_change');
$src = file_get_contents(ROOT_PATH . '/core/Middleware/AuthMiddleware.php');
ok('verifica password_must_change',       strpos($src, 'password_must_change') !== false);
ok('redireciona para /profile/change-password', strpos($src, '/profile/change-password') !== false);

// ── 4. TaskController — role auth ────────────────────────────────────────────
section('4. TaskController — role auth em update/destroy');
$src = file_get_contents(ROOT_PATH . '/app/Controllers/TaskController.php');
ok('update() verifica role viewer',   preg_match("/update[^}]*viewer/s", $src) === 1);
ok('update() verifica role seller',   preg_match("/update[^}]*seller/s", $src) === 1);
ok('destroy() verifica role viewer',  preg_match("/destroy[^}]*viewer/s", $src) === 1);
ok('destroy() verifica role seller',  preg_match("/destroy[^}]*seller/s", $src) === 1);

// ── 5. Controller::requireRole() — 403 para JSON ─────────────────────────────
section('5. Controller::requireRole() — 403 para JSON');

require_once ROOT_PATH . '/core/Controller.php';

$src = file_get_contents(ROOT_PATH . '/core/Controller.php');
ok('requireRole detecta Accept JSON',          strpos($src, 'application/json') !== false);
ok('requireRole retorna 403 para JSON',         strpos($src, '403') !== false);
ok('redirect() rejeita paths com ://',          strpos($src, '://') !== false && strpos($src, "str_contains(\$path, '://')") !== false);
ok('redirect() rejeita paths sem /inicial',    strpos($src, "str_starts_with(\$path, '/')") !== false);

// ── 6. .htaccess por diretório ───────────────────────────────────────────────
section('6. .htaccess por diretório');
foreach (['app', 'core', 'config', 'database', 'tests'] as $dir) {
    $path = ROOT_PATH . "/{$dir}/.htaccess";
    ok("{$dir}/.htaccess existe",            file_exists($path));
    ok("{$dir}/.htaccess tem Require denied", strpos(file_get_contents($path), 'Require all denied') !== false);
}

// ── 7. CSP — sem unsafe-inline, sem jsdelivr em connect-src ──────────────────
section('7. CspMiddleware');
$src = file_get_contents(ROOT_PATH . '/core/Middleware/CspMiddleware.php');
ok("sem 'unsafe-inline' no style-src",     strpos($src, "'unsafe-inline'") === false);
ok("sem cdn.jsdelivr.net em connect-src",   strpos($src, 'cdn.jsdelivr.net') === false);
ok("style-src usa nonce",                   strpos($src, "nonce-{") !== false || preg_match('/style-src[^;]*nonce/', $src) === 1);

// ── 8. bootstrap.php — session gc e Logger .htaccess ─────────────────────────
section('8. bootstrap.php e Logger .htaccess');
$bsrc = file_get_contents(ROOT_PATH . '/core/bootstrap.php');
ok('bootstrap define session.gc_maxlifetime', strpos($bsrc, 'session.gc_maxlifetime') !== false);
ok('bootstrap cria .htaccess de storage/logs', strpos($bsrc, 'storage/logs') !== false);

$lsrc = file_get_contents(ROOT_PATH . '/core/Logger.php');
ok('Logger não escreve .htaccess no handle()',
    preg_match('/function log[^}]*htaccess/s', $lsrc) === 0
    && preg_match('/function log[^}]*\.htaccess/s', $lsrc) === 0);

// ── 9. filter_var — FILTER_SANITIZE_EMAIL removido ───────────────────────────
section('9. filter_var — sem FILTER_SANITIZE_EMAIL');
foreach (['AuthController', 'UserController'] as $ctrl) {
    $src = file_get_contents(ROOT_PATH . "/app/Controllers/{$ctrl}.php");
    ok("{$ctrl} não usa FILTER_SANITIZE_EMAIL", strpos($src, 'FILTER_SANITIZE_EMAIL') === false);
    ok("{$ctrl} usa FILTER_VALIDATE_EMAIL",      strpos($src, 'FILTER_VALIDATE_EMAIL') !== false);
}

// ── Resultado final ───────────────────────────────────────────────────────────
$total = $results['pass'] + $results['fail'];
echo "\n\033[1m────────────────────────────────────\033[0m\n";
echo "\033[1mResultado: {$results['pass']}/{$total} testes passaram\033[0m\n";

if ($results['fail'] > 0) {
    echo "\n\033[31mFalharam:\033[0m\n";
    foreach ($results['errors'] as $e) { echo "  • {$e}\n"; }
    exit(1);
}
echo "\033[32mTodos os testes passaram.\033[0m\n";
exit(0);