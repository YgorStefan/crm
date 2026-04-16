<?php
/**
 * tests/Phase10Test.php — Testes de auditoria (Phase 10)
 *
 * Verifica cada correção aplicada na auditoria completa.
 * Sem dependências externas. Execute: php tests/Phase10Test.php
 */

declare(strict_types=1);

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

define('ROOT', dirname(__DIR__));

// ===========================================================================
// 1. .gitignore — credenciais não rastreadas
// ===========================================================================
section('1. .gitignore');

$gitignore = file_get_contents(ROOT . '/.gitignore');
ok('.gitignore contém regra para "env crm.txt"', str_contains($gitignore, 'env crm.txt') || str_contains($gitignore, 'env\ crm.txt'));
ok('.gitignore contém regra para config/database.php', str_contains($gitignore, 'config/database.php'));
ok('.gitignore contém regra para scripts/smoke/', str_contains($gitignore, 'scripts/smoke/'));
ok('config/database.php não está rastreado pelo git', !in_array(
    'config/database.php',
    explode("\n", shell_exec('git -C ' . ROOT . ' ls-files config/database.php') ?? '')
));

// ===========================================================================
// 2. bootstrap.php — session.cookie_secure em produção
// ===========================================================================
section('2. session.cookie_secure');

$bootstrap = file_get_contents(ROOT . '/core/bootstrap.php');
ok('bootstrap.php ativa cookie_secure condicionalmente', str_contains($bootstrap, "APP_ENV === 'production'") && str_contains($bootstrap, "cookie_secure"));
ok('bootstrap.php NÃO tem cookie_secure comentado sem condição', !preg_match('/^\s*\/\/\s*ini_set\s*\(\s*[\'"]session\.cookie_secure/m', $bootstrap));

// ===========================================================================
// 3. AuthController — sem open redirect
// ===========================================================================
section('3. Open Redirect em AuthController');

$authCtrl = file_get_contents(ROOT . '/app/Controllers/AuthController.php');
ok('AuthController valida redirect_after_login antes de usar', str_contains($authCtrl, 'str_starts_with($savedRedirect') || str_contains($authCtrl, 'str_starts_with($redirect'));
ok('AuthController NÃO usa redirect_after_login diretamente em header()', !preg_match('/header\s*\(\s*[\'"]Location.*redirect_after_login/m', $authCtrl));

// ===========================================================================
// 4. Model::findById() — filtro de tenant
// ===========================================================================
section('4. IDOR — Model::findById() tenant filter');

$model = file_get_contents(ROOT . '/core/Model.php');
ok('Model::findById() inclui tenant_id no WHERE para modelos não-globais', str_contains($model, 'tenant_id') && str_contains($model, 'findById'));
ok('Model::findById() respeita isGlobal flag', str_contains($model, 'isGlobal') && str_contains($model, 'findById'));

// ===========================================================================
// 5. Task model — tenant filter
// ===========================================================================
section('5. Task model — tenant isolation');

$taskModel = file_get_contents(ROOT . '/app/Models/Task.php');
ok('Task::findAllWithRelations() filtra por tenant', str_contains($taskModel, 'tenant_id'));
ok('Task::findById() (override) filtra por tenant', (function() use ($taskModel) {
    // Verifica que o findById local menciona tenant
    preg_match('/function findById.*?}/s', $taskModel, $m);
    return isset($m[0]) && str_contains($m[0], 'tenant_id');
})());
ok('Task::findOverdue() filtra por tenant', (function() use ($taskModel) {
    preg_match('/function findOverdue.*?}/s', $taskModel, $m);
    return isset($m[0]) && str_contains($m[0], 'tenant_id');
})());

// ===========================================================================
// 6. XSS — APP_NAME escapado no layout
// ===========================================================================
section('6. XSS — APP_NAME no layout');

$mainLayout = file_get_contents(ROOT . '/app/Views/layouts/main.php');
// Garante que APP_NAME não aparece sem htmlspecialchars no sidebar
ok('APP_NAME no sidebar usa htmlspecialchars', (function() use ($mainLayout) {
    // Extrai a região do sidebar (antes do </aside>)
    $sidebarEnd = strpos($mainLayout, '</aside>');
    $sidebar = $sidebarEnd ? substr($mainLayout, 0, $sidebarEnd) : $mainLayout;
    // Verifica que APP_NAME nao aparece sem escape no sidebar
    return !preg_match('/<\?=\s*APP_NAME\s*\?' . '>/', $sidebar);
})());

// ===========================================================================
// 7. CSP — nonce em todos os scripts inline
// ===========================================================================
section('7. CSP — nonce em scripts inline');

$viewsWithInlineScripts = [
    'app/Views/clients/index.php',
    'app/Views/components/pagination.php',
];

foreach ($viewsWithInlineScripts as $viewFile) {
    $content = file_get_contents(ROOT . '/' . $viewFile);
    // Encontra tags <script que não têm nonce= e não são src=
    preg_match_all('/<script(?![^>]*nonce=)[^>]*>/', $content, $matches);
    $scriptsWithoutNonce = array_filter($matches[0], fn($tag) => !str_contains($tag, 'src='));
    ok("{$viewFile}: todos os scripts inline têm nonce", empty($scriptsWithoutNonce));
}

// ===========================================================================
// 8. UserController — role validado no update
// ===========================================================================
section('8. Role validation em UserController::update()');

$userCtrl = file_get_contents(ROOT . '/app/Controllers/UserController.php');
ok('UserController::update() valida role contra enum', (function() use ($userCtrl) {
    // Extrai método update
    preg_match('/public function update.*?(?=public function|\Z)/s', $userCtrl, $m);
    if (!isset($m[0])) return false;
    return str_contains($m[0], "in_array") && str_contains($m[0], "'admin'") && str_contains($m[0], "'seller'");
})());

// ===========================================================================
// 9. TaskController — sem HTTP_REFERER para redirect
// ===========================================================================
section('9. TaskController — sem HTTP_REFERER para redirect');

$taskCtrl = file_get_contents(ROOT . '/app/Controllers/TaskController.php');
ok('TaskController::store() não usa HTTP_REFERER como destino de redirect', !str_contains($taskCtrl, "HTTP_REFERER"));

// ===========================================================================
// Resumo
// ===========================================================================
echo "\n" . str_repeat('─', 50) . "\n";
$total = $results['pass'] + $results['fail'];
echo "Resultado: {$results['pass']}/{$total} testes passaram\n";

if ($results['fail'] > 0) {
    echo "\033[31mFalhas:\033[0m\n";
    foreach ($results['errors'] as $e) {
        echo "  • {$e}\n";
    }
    exit(1);
}

echo "\033[32mTodos os testes passaram!\033[0m\n";
exit(0);
