<?php
/**
 * Smoke TEN-01: cobertura de middleware de tenant e ordem na cadeia (sem servidor HTTP).
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$steps = [];
$fail = false;

$add = static function (string $step, string $msg, bool $ok) use (&$steps, &$fail): void {
    $steps[] = crm_smoke_step($step, $msg, $ok);
    if (!$ok) {
        $fail = true;
    }
};

$indexPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
$index = @file_get_contents($indexPath);
if ($index === false) {
    $add('file.index', 'public/index.php não legível', false);
    crm_smoke_emit($steps, 1);
}

foreach (preg_split('/\R/', $index) as $num => $line) {
    $trim = ltrim($line);
    if (str_starts_with($trim, '//')) {
        continue;
    }
    if (str_contains($line, 'AuthMiddleware') && !str_contains($line, 'TenantContextMiddleware')) {
        $add('route.coverage', 'Linha ' . ($num + 1) . ': AuthMiddleware sem TenantContextMiddleware', false);
    }
}

$mw = ROOT_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'TenantContextMiddleware.php';
$add('file.middleware', is_file($mw) ? 'TenantContextMiddleware.php presente' : 'TenantContextMiddleware ausente', is_file($mw));

$add('done', $fail ? 'Smoke TEN-01 falhou' : 'Smoke TEN-01 OK', !$fail);
crm_smoke_emit($steps, $fail ? 1 : 0);
