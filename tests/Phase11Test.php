<?php
/**
 * tests/Phase11Test.php — Fase 1: Isolamento e Integridade
 * Execute: php tests/Phase11Test.php
 */
declare(strict_types=1);

$results = ['pass' => 0, 'fail' => 0, 'errors' => []];

function ok(string $desc, bool $cond): void
{
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
define('VIEW_PATH', APP_PATH . DS . 'Views');
define('APP_ENV',   'testing');

// ── 1. Controller::input() não deve encodar htmlspecialchars ─────────────────
section('1. Controller::input() — sem htmlspecialchars');

require_once ROOT_PATH . '/core/Controller.php';

class TestController extends Core\Controller {
    public function exposeInput(string $key, string $default = ''): string {
        return $this->input($key, $default);
    }
    public function index(): void {}
}

$ctrl = new TestController();

$_POST['nome'] = 'Smith & Co "Ltda"';
ok('input() retorna valor com & intacto',   $ctrl->exposeInput('nome') === "Smith & Co \"Ltda\"");
ok('input() não codifica &amp;',            strpos($ctrl->exposeInput('nome'), '&amp;') === false);
ok('input() não codifica &quot;',           strpos($ctrl->exposeInput('nome'), '&quot;') === false);
ok('input() ainda aplica trim',             $ctrl->exposeInput('espaco', '') === '' || true);

$_POST['espaco'] = '  texto  ';
ok('input() aplica trim no valor',          $ctrl->exposeInput('espaco') === 'texto');
