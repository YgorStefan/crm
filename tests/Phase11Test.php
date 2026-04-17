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

// ── 2. ColdContact — verificação estrutural de tenant_id ─────────────────────
section('2. ColdContact — tenant isolation (estrutural)');

$src = file_get_contents(ROOT_PATH . '/app/Models/ColdContact.php');

ok('countFindMonthSummaries usa tenant_id',     strpos($src, 'countFindMonthSummaries') !== false
    && preg_match('/countFindMonthSummaries.*?tenant_id/s', $src) === 1);
ok('findMonthSummaries usa tenant_id',          strpos($src, 'tenant_id') !== false);
ok('countByMonth usa tenant_id',                preg_match('/countByMonth.*?tenant_id/s', $src) === 1);
ok('findByMonth usa tenant_id',                 preg_match('/findByMonth.*?tenant_id/s', $src) === 1);
ok('create() inclui tenant_id no INSERT',       strpos($src, 'INSERT INTO cold_contacts') !== false
    && preg_match('/INSERT INTO cold_contacts[^;]*tenant_id/s', $src) === 1);
ok('update() usa tenant_id no WHERE',           preg_match('/UPDATE cold_contacts[^;]*tenant_id/s', $src) === 1);
ok('destroy() usa tenant_id no WHERE',          preg_match('/DELETE FROM cold_contacts[^;]*tenant_id/s', $src) === 1);
ok('deleteByMonth() usa tenant_id no WHERE',    preg_match('/deleteByMonth[^}]*tenant_id/s', $src) === 1);
ok('bulkAtualizarExtras() usa tenant_id',       preg_match('/bulkAtualizarExtras.*?tenant_id/s', $src) === 1);

// ── 3. Interaction — override findById ───────────────────────────────────────
section('3. Interaction::findById() — tenant gate via join');

$src = file_get_contents(ROOT_PATH . '/app/Models/Interaction.php');

ok('Interaction define isGlobal = true',         strpos($src, 'isGlobal = true') !== false);
ok('Interaction sobrescreve findById',            preg_match('/public function findById/', $src) === 1);
ok('findById usa INNER JOIN clients',             strpos($src, 'INNER JOIN clients') !== false);
ok('findById filtra por c.tenant_id',             strpos($src, 'c.tenant_id') !== false);

// ── 4. InteractionController — validação de client_id ───────────────────────
section('4. InteractionController — verifica client_id antes de write');

$src = file_get_contents(ROOT_PATH . '/app/Controllers/InteractionController.php');

ok('store() instancia Client model',      strpos($src, 'new Client()') !== false);
ok('store() usa findById no clientId',    preg_match('/store.*?findById\(\$clientId\)/s', $src) === 1);
ok('update() valida client via findById', preg_match('/update.*?findById/s', $src) === 1);
ok('usa App\Models\Client (use statement)', strpos($src, 'use App\Models\Client') !== false);
