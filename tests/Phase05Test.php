<?php
/**
 * tests/Phase05Test.php — Testes unitários da Fase 05 (Refactoring)
 *
 * Sem dependências externas. Execute:
 *   php tests/Phase05Test.php
 *
 * Cobre:
 *   1. format_currency()     — helpers.php
 *   2. navLink()             — helpers.php
 *   3. Core\Logger           — core/Logger.php
 *   4. Estrutural / integridade de arquivos produzidos
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Micro test-runner
// ---------------------------------------------------------------------------

$results = ['pass' => 0, 'fail' => 0, 'errors' => []];

function ok(string $desc, bool $cond): void
{
    global $results;
    if ($cond) {
        echo "\033[32m  ✓\033[0m {$desc}\n";
        $results['pass']++;
    } else {
        echo "\033[31m  ✗\033[0m {$desc}\n";
        $results['fail']++;
        $results['errors'][] = $desc;
    }
}

function section(string $title): void
{
    echo "\n\033[1;34m── {$title}\033[0m\n";
}

// ---------------------------------------------------------------------------
// Bootstrap mínimo: define constantes requeridas pelos helpers
// ---------------------------------------------------------------------------

define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . DS . 'app');
define('CORE_PATH', ROOT_PATH . DS . 'core');
define('APP_URL',   'http://localhost');     // suficiente para testes de navLink

// Carrega helpers (define navLink + format_currency)
require_once ROOT_PATH . '/core/helpers.php';

// Carrega Logger (Core\Logger no namespace Core)
require_once ROOT_PATH . '/core/Logger.php';


// ===========================================================================
// 1. format_currency()
// ===========================================================================
section('1. format_currency()');

ok('inteiro positivo',          format_currency(1000)        === 'R$ 1.000,00');
ok('float positivo',            format_currency(1234.56)     === 'R$ 1.234,56');
ok('zero',                      format_currency(0)           === 'R$ 0,00');
ok('string numérica inteira',   format_currency('500')       === 'R$ 500,00');
ok('string numérica decimal',   format_currency('1234.56')   === 'R$ 1.234,56');
ok('string com vírgula (BR)',   format_currency('1234,56')   === 'R$ 1.234,56');
ok('valor negativo',            format_currency(-99.9)       === 'R$ -99,90');
ok('INF retorna string vazia',  format_currency(INF)         === '');
ok('-INF retorna string vazia', format_currency(-INF)        === '');
ok('NAN retorna string vazia',  format_currency(NAN)         === '');
ok('string não-numérica → 0',   format_currency('abc')       === 'R$ 0,00');
ok('milhões',                   format_currency(1000000)     === 'R$ 1.000.000,00');


// ===========================================================================
// 2. navLink()
// ===========================================================================
section('2. navLink()');

$currentPath = '/dashboard';
$linkActive   = navLink('/dashboard', '📊', 'Dashboard', $currentPath);
$linkInactive = navLink('/clients',   '👥', 'Clientes',  $currentPath);

ok('link ativo contém bg-indigo-600',           str_contains($linkActive, 'bg-indigo-600'));
ok('link ativo contém text-white',              str_contains($linkActive, 'text-white'));
ok('link inativo não contém bg-indigo-600',     !str_contains($linkInactive, 'bg-indigo-600'));
ok('link inativo contém hover:bg-indigo-800',   str_contains($linkInactive, 'hover:bg-indigo-800'));
ok('href correto no ativo',                     str_contains($linkActive, 'href="http://localhost/dashboard"'));
ok('href correto no inativo',                   str_contains($linkInactive, 'href="http://localhost/clients"'));
ok('retorna tag <a>',                           str_starts_with(trim($linkActive), '<a '));

// sub-path é tratado como ativo (str_starts_with logic)
$subpath = navLink('/clients', '👥', 'Clientes', '/clients/42');
ok('sub-path /clients/42 ativa link /clients',  str_contains($subpath, 'bg-indigo-600'));

// Proteção XSS: label com caracteres perigosos deve ser escaped
$xssLink = navLink('/test', '', '<script>alert(1)</script>', '/other');
ok('label com <script> é escapado no HTML',     !str_contains($xssLink, '<script>'));
ok('label escapado contém &lt;script&gt;',      str_contains($xssLink, '&lt;script&gt;'));

// Proteção XSS: href com aspas
$xssHref = navLink('/path" onmouseover="evil()', '', 'Label', '/other');
ok('href com aspas é escapado',                 !str_contains($xssHref, '"evil()'));


// ===========================================================================
// 3. Core\Logger
// ===========================================================================
section('3. Core\\Logger');

$tmpDir = sys_get_temp_dir() . '/crm_logger_test_' . uniqid();

$logger = new \Core\Logger($tmpDir);

// --- Cria diretório automaticamente ---
$logger->info('Mensagem de inicialização');
ok('diretório de log criado automaticamente', is_dir($tmpDir));

// --- Arquivo diário criado ---
$expectedFile = $tmpDir . '/logger-' . date('Y-m-d') . '.log';
ok('arquivo logger-YYYY-MM-DD.log criado', file_exists($expectedFile));

// --- Formato de linha ---
$content = file_get_contents($expectedFile);
ok('linha contém timestamp [YYYY-MM-DD',    (bool) preg_match('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content));
ok('linha contém nível INFO',               str_contains($content, 'INFO'));
ok('linha contém a mensagem',               str_contains($content, 'Mensagem de inicialização'));
ok('linha termina com newline',             str_ends_with($content, PHP_EOL));

// --- Todos os níveis de conveniência ---
$logger->emergency('em');
$logger->alert('al');
$logger->critical('cr');
$logger->error('er');
$logger->warning('wa');
$logger->notice('no');
$logger->debug('de');

$all = file_get_contents($expectedFile);
foreach (['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'DEBUG'] as $lvl) {
    ok("nível {$lvl} registrado", str_contains($all, $lvl));
}

// --- Contexto serializado como JSON ---
$ctxLogger = new \Core\Logger($tmpDir);
$ctxLogger->info('Com contexto', ['user_id' => 42, 'action' => 'login']);
$ctxContent = file_get_contents($expectedFile);
ok('contexto serializado como JSON',        str_contains($ctxContent, '{"user_id":42'));
ok('contexto contém chave action',          str_contains($ctxContent, '"action":"login"'));

// --- Segunda instância acumula (FILE_APPEND) ---
$linesBefore = count(file($expectedFile));
(new \Core\Logger($tmpDir))->info('Append test');
$linesAfter  = count(file($expectedFile));
ok('FILE_APPEND: nova instância acumula sem sobrescrever', $linesAfter > $linesBefore);

// --- Diretório inexistente em subpath ---
$deepDir = $tmpDir . '/deep/nested/logs';
$deepLogger = new \Core\Logger($deepDir);
$deepLogger->warning('Deep dir test');
ok('cria subdiretórios aninhados automaticamente', is_dir($deepDir));

// --- T-05: Log Injection — newlines sanitizadas ---
$injLogger = new \Core\Logger($tmpDir);
$injLogger->info("Linha1\nfake timestamp] EMERGENCY — injected\nLinha3");
$injContent = file_get_contents($expectedFile);
// Sanitização correta: entrada com 2 \n vira 1 única linha no arquivo (não 3 linhas separadas)
$linesWithInjected = array_filter(explode("\n", trim($injContent)), fn($l) => str_contains($l, 'injected'));
ok('T-05: injeção não cria múltiplas entradas — exatamente 1 linha contém o conteúdo', count($linesWithInjected) === 1);
// Deve aparecer como espaço, não como quebra real
ok('T-05: conteúdo injetado é sanitizado para espaço', str_contains($injContent, 'Linha1 fake timestamp'));

// --- T-07: .htaccess criado automaticamente pelo ensureLogDir ---
ok('T-07: .htaccess criado no diretório de logs', file_exists($tmpDir . '/.htaccess'));
$htContent = file_get_contents($tmpDir . '/.htaccess');
ok('T-07: .htaccess contém Require all denied (Apache 2.4)',  str_contains($htContent, 'Require all denied'));
ok('T-07: .htaccess contém Deny from all (Apache 2.2)',       str_contains($htContent, 'Deny from all'));

// Limpeza
@unlink($tmpDir . '/.htaccess');
array_map('unlink', glob($tmpDir . '/*.log'));
@unlink($deepDir . '/.htaccess');
array_map('unlink', glob($deepDir . '/*.log'));
@rmdir($deepDir);
@rmdir($tmpDir . '/deep/nested');
@rmdir($tmpDir . '/deep');
@rmdir($tmpDir);


// ===========================================================================
// 4. Integridade estrutural dos arquivos gerados
// ===========================================================================
section('4. Integridade estrutural dos arquivos gerados');

$root = ROOT_PATH;

// core/helpers.php
ok('core/helpers.php existe',               file_exists("$root/core/helpers.php"));
ok('core/helpers.php define navLink',       str_contains(file_get_contents("$root/core/helpers.php"), 'function navLink('));
ok('core/helpers.php define format_currency', str_contains(file_get_contents("$root/core/helpers.php"), 'function format_currency('));

// core/Logger.php
ok('core/Logger.php existe',                file_exists("$root/core/Logger.php"));
ok('core/Logger.php define class Logger',   str_contains(file_get_contents("$root/core/Logger.php"), 'class Logger'));
ok('core/Logger.php tem método log()',       str_contains(file_get_contents("$root/core/Logger.php"), 'public function log('));

// core/bootstrap.php
$bootstrap = file_get_contents("$root/core/bootstrap.php");
ok('core/bootstrap.php existe',             file_exists("$root/core/bootstrap.php"));
ok('bootstrap.php chama session_start()',   str_contains($bootstrap, 'session_start()'));
ok('bootstrap.php registra autoloader',     str_contains($bootstrap, 'spl_autoload_register('));
ok('bootstrap.php inclui helpers.php',      str_contains($bootstrap, 'helpers.php'));
ok('bootstrap.php inclui config/app.php',   str_contains($bootstrap, 'app.php'));

// config/routes.php
$routes = file_get_contents("$root/config/routes.php");
ok('config/routes.php existe',              file_exists("$root/config/routes.php"));
ok("routes.php tem GET /login",             str_contains($routes, "\$router->get('/login'"));
ok("routes.php tem GET /dashboard",         str_contains($routes, "\$router->get('/dashboard'"));
ok("routes.php tem GET /clients",           str_contains($routes, "\$router->get('/clients'"));
ok("routes.php tem GET /pipeline",          str_contains($routes, "\$router->get('/pipeline'"));
ok("routes.php tem rota toggle-won (fase 4)", str_contains($routes, 'toggle-won'));
ok("routes.php tem rotas /settings (fase 4)", str_contains($routes, "'/settings'"));

// public/index.php — deve ser entrypoint enxuto
$index = file_get_contents("$root/public/index.php");
ok('public/index.php existe',               file_exists("$root/public/index.php"));
ok('index.php NÃO contém session_start()', !str_contains($index, 'session_start()'));
ok('index.php NÃO contém $router->get()', !str_contains($index, '$router->get('));
ok('index.php NÃO contém $router->post()', !str_contains($index, '$router->post('));
ok('index.php inclui bootstrap.php',        str_contains($index, 'bootstrap.php'));
ok('index.php inclui config/routes.php',    str_contains($index, 'routes.php'));
ok('index.php chama $router->dispatch()',   str_contains($index, '$router->dispatch()'));

// app/Views/layouts/main.php — navLink() inline deve ter sido removida
$mainView = file_get_contents("$root/app/Views/layouts/main.php");
ok('main.php NÃO contém "function navLink"', !str_contains($mainView, 'function navLink'));

// Task 6 — pipeline/index.php adota format_currency (TD-04)
$pipelineView = file_get_contents("$root/app/Views/pipeline/index.php");
ok('pipeline/index.php usa format_currency()',
    str_contains($pipelineView, 'format_currency('));
ok('pipeline/index.php não contém number_format() para valores monetários',
    !preg_match('/number_format\s*\(\s*\$\w*(value|deal|revenue|total)\w*/i', $pipelineView));


// ===========================================================================
// 5. Integridade de segurança do bootstrap (FRAG-01 — session hardening)
// ===========================================================================
section('5. Bootstrap — session hardening (FRAG-01)');

ok('bootstrap.php configura cookie_httponly',
    str_contains($bootstrap, "session.cookie_httponly"));
ok('bootstrap.php configura cookie_samesite',
    str_contains($bootstrap, "session.cookie_samesite"));
ok('bootstrap.php configura use_strict_mode',
    str_contains($bootstrap, "session.use_strict_mode"));
ok('bootstrap.php chama session_name() antes de session_start()',
    (bool) preg_match('/session_name\s*\(.*\).*session_start\s*\(\s*\)/s', $bootstrap));


// ===========================================================================
// 6. Preservação de middlewares em config/routes.php (TD-03)
// ===========================================================================
section('6. Preservação de middlewares nas rotas (TD-03)');

// POST /login deve ter CsrfMiddleware (rota pública sensível)
ok("POST /login preserva CsrfMiddleware",
    (bool) preg_match("/\\\$router->post\s*\(\s*'\/login'.*CsrfMiddleware/", $routes));

// GET /dashboard deve ter AuthMiddleware
ok("GET /dashboard preserva AuthMiddleware",
    (bool) preg_match("/\\\$router->get\s*\(\s*'\/dashboard'.*AuthMiddleware/", $routes));

// POST /clients/{id}/update deve ter AuthMiddleware + CsrfMiddleware
ok("POST /clients/{id}/update preserva AuthMiddleware e CsrfMiddleware",
    (bool) preg_match("/\\\$router->post\s*\(\s*'\/clients\/\{id\}\/update'.*AuthMiddleware.*CsrfMiddleware/s", $routes));

// POST /settings/update deve ter AuthMiddleware (fase 04 route — non-regression)
ok("POST /settings/update preserva AuthMiddleware",
    (bool) preg_match("/\\\$router->post\s*\(\s*'\/settings\/update'.*AuthMiddleware/", $routes));

// GET /login deve ser pública (sem AuthMiddleware)
ok("GET /login é rota pública (sem AuthMiddleware)",
    (bool) preg_match("/\\\$router->get\s*\(\s*'\/login'\s*,\s*'AuthController'/", $routes) &&
    !preg_match("/\\\$router->get\s*\(\s*'\/login'.*AuthMiddleware/", $routes));


// ===========================================================================
// Resultado final
// ===========================================================================

$total = $results['pass'] + $results['fail'];
echo "\n\033[1m────────────────────────────────────\033[0m\n";
echo "\033[1mResultado: {$results['pass']}/{$total} testes passaram\033[0m\n";

if ($results['fail'] > 0) {
    echo "\n\033[31mFalharam:\033[0m\n";
    foreach ($results['errors'] as $e) {
        echo "  • {$e}\n";
    }
    exit(1);
}

echo "\033[32mTodos os testes passaram.\033[0m\n";
exit(0);
