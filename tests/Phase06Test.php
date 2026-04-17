<?php
/**
 * tests/Phase06Test.php — Testes unitários da Fase 06 (Tailwind CLI Build)
 *
 * Sem dependências externas. Execute:
 *   php tests/Phase06Test.php
 *
 * Cobre:
 *   06-01 — .gitignore contém .bin/; scripts/setup_tailwind.php existe
 *   06-02 — tailwind.config.js existe com content paths e theme.extend.colors
 *   06-03 — resources/css/input.css tem diretivas @tailwind e estilos kanban
 *   06-04 — public/assets/css/tailwind.css existe e não está vazio
 *   06-05 — main.php não usa cdn.tailwindcss.com; aponta para /assets/css/tailwind.css
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Micro test-runner (identical structure to Phase05Test.php)
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
// Root path
// ---------------------------------------------------------------------------

$root = dirname(__DIR__);


// ===========================================================================
// 06-01 — setup_tailwind.php + .gitignore contém .bin/
// ===========================================================================
section('06-01 · setup_tailwind.php e .gitignore');

$gitignore = file_exists("$root/.gitignore") ? file_get_contents("$root/.gitignore") : '';
ok('.gitignore existe',
    file_exists("$root/.gitignore"));

ok('.gitignore contém a linha ".bin/"',
    (bool) preg_match('/^\.bin\//m', $gitignore));

ok('scripts/setup_tailwind.php existe',
    file_exists("$root/scripts/setup_tailwind.php"));

$setupContent = file_exists("$root/scripts/setup_tailwind.php")
    ? file_get_contents("$root/scripts/setup_tailwind.php")
    : '';

ok('setup_tailwind.php é um script PHP válido (abre com <?php)',
    str_starts_with(ltrim($setupContent), '<?php'));

ok('setup_tailwind.php detecta PHP_OS_FAMILY',
    str_contains($setupContent, 'PHP_OS_FAMILY'));

ok('setup_tailwind.php faz download para o diretório .bin/',
    str_contains($setupContent, '.bin'));

ok('setup_tailwind.php define TAILWIND_VERSION',
    str_contains($setupContent, 'TAILWIND_VERSION'));


// ===========================================================================
// 06-02 — tailwind.config.js
// ===========================================================================
section('06-02 · tailwind.config.js');

ok('tailwind.config.js existe',
    file_exists("$root/tailwind.config.js"));

$tailwindConfig = file_exists("$root/tailwind.config.js")
    ? file_get_contents("$root/tailwind.config.js")
    : '';

ok('tailwind.config.js exporta module.exports',
    str_contains($tailwindConfig, 'module.exports'));

ok('content array inclui path de views PHP (app/Views/**/*.php)',
    str_contains($tailwindConfig, './app/Views/**/*.php'));

ok('content array inclui path de assets JS (public/assets/js/**/*.js)',
    str_contains($tailwindConfig, './public/assets/js/**/*.js'));

ok('theme.extend.colors contém a chave "primary"',
    str_contains($tailwindConfig, 'primary'));

ok('theme.extend.colors contém a chave "sidebar"',
    str_contains($tailwindConfig, 'sidebar'));

ok('cor primary contém pelo menos uma shade (ex: 500)',
    (bool) preg_match("/'?500'?\s*:\s*'#[0-9a-fA-F]{6}'/", $tailwindConfig));

ok('cor sidebar possui valor hexadecimal',
    (bool) preg_match("/sidebar\s*:\s*'#[0-9a-fA-F]{6}'/", $tailwindConfig));


// ===========================================================================
// 06-03 — resources/css/input.css
// ===========================================================================
section('06-03 · resources/css/input.css');

ok('resources/css/input.css existe',
    file_exists("$root/resources/css/input.css"));

$inputCss = file_exists("$root/resources/css/input.css")
    ? file_get_contents("$root/resources/css/input.css")
    : '';

ok('input.css contém @tailwind base',
    str_contains($inputCss, '@tailwind base'));

ok('input.css contém @tailwind components',
    str_contains($inputCss, '@tailwind components'));

ok('input.css contém @tailwind utilities',
    str_contains($inputCss, '@tailwind utilities'));

ok('input.css contém estilo .kanban-scroll (custom Kanban scrollbar)',
    str_contains($inputCss, '.kanban-scroll'));

ok('input.css contém estilo .dragging (cartão sendo arrastado)',
    str_contains($inputCss, '.dragging'));

ok('as três diretivas @tailwind aparecem antes dos estilos customizados',
    (bool) preg_match('/@tailwind base.*@tailwind components.*@tailwind utilities.*\.kanban-scroll/s', $inputCss));


// ===========================================================================
// 06-04 — public/assets/css/tailwind.css (CSS compilado)
// ===========================================================================
section('06-04 · public/assets/css/tailwind.css (build output)');

$compiledCssPath = "$root/public/assets/css/tailwind.css";

ok('public/assets/css/tailwind.css existe',
    file_exists($compiledCssPath));

ok('public/assets/css/tailwind.css não está vazio',
    file_exists($compiledCssPath) && filesize($compiledCssPath) > 0);

ok('public/assets/css/tailwind.css tem tamanho mínimo de 1 KB (build real, não stub)',
    file_exists($compiledCssPath) && filesize($compiledCssPath) >= 1024);

ok('scripts/build_css.php existe',
    file_exists("$root/scripts/build_css.php"));

$buildContent = file_exists("$root/scripts/build_css.php")
    ? file_get_contents("$root/scripts/build_css.php")
    : '';

ok('build_css.php é um script PHP válido (abre com <?php)',
    str_starts_with(ltrim($buildContent), '<?php'));

ok('build_css.php aponta input para resources/css/input.css',
    str_contains($buildContent, 'resources/css/input.css'));

ok('build_css.php aponta output para public/assets/css/tailwind.css',
    str_contains($buildContent, 'public/assets/css/tailwind.css'));

ok('build_css.php usa flag --minify',
    str_contains($buildContent, '--minify'));


// ===========================================================================
// 06-05 — app/Views/layouts/main.php atualizado (sem CDN, com link local)
// ===========================================================================
section('06-05 · app/Views/layouts/main.php — remoção do CDN');

ok('app/Views/layouts/main.php existe',
    file_exists("$root/app/Views/layouts/main.php"));

$mainPhp = file_exists("$root/app/Views/layouts/main.php")
    ? file_get_contents("$root/app/Views/layouts/main.php")
    : '';

ok('main.php NÃO contém referência a cdn.tailwindcss.com',
    !str_contains($mainPhp, 'cdn.tailwindcss.com'));

ok('main.php NÃO contém script CDN do Tailwind (<script src="https://cdn.tailwindcss.com">)',
    !(bool) preg_match('/<script[^>]+cdn\.tailwindcss\.com/i', $mainPhp));

ok('main.php NÃO contém bloco de configuração inline "tailwind.config ="',
    !str_contains($mainPhp, 'tailwind.config ='));

ok('main.php contém link para /assets/css/tailwind.css',
    str_contains($mainPhp, '/assets/css/tailwind.css'));

// The href attribute contains a PHP short-echo tag, so [^>]+ based regexes
// terminate at the closing angle-bracket inside that tag before reaching
// tailwind.css. Use a line-by-line check instead: both rel="stylesheet"
// and tailwind.css must appear on the same line.
$hasStylesheetLink = false;
foreach (explode("\n", $mainPhp) as $_line) {
    if (str_contains($_line, 'stylesheet') && str_contains($_line, 'tailwind.css')) {
        $hasStylesheetLink = true;
        break;
    }
}
ok('main.php usa <link rel="stylesheet" ... tailwind.css>', $hasStylesheetLink);

ok('main.php NÃO contém bloco <style> com estilos do Kanban (.kanban-scroll)',
    !str_contains($mainPhp, '.kanban-scroll'));

ok('main.php NÃO contém .dragging inline (estilo foi movido para input.css)',
    !str_contains($mainPhp, '.dragging'));


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
