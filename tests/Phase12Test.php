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
