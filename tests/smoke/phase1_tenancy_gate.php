<?php
/**
 * Gate agregado da fase 1 — TEN-01 / TEN-06 (modos progressivos).
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$mode = 'tenant-backfill';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--mode=')) {
        $mode = substr($arg, 7);
    }
}

$steps = [];
$failed = false;

$push = static function (array $s) use (&$steps, &$failed): void {
    $steps[] = $s;
    if ($s['ok'] === false) {
        $failed = true;
    }
};

$push(crm_smoke_step('gate.start', 'phase1_tenancy_gate', true, ['mode' => $mode]));

$php = PHP_BINARY;
$root = ROOT_PATH;
$verify = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'verify_tenant_backfill.php';

$runVerify = static function (string $check, string $assert = 'columns,indexes,fks,checkpoint-compat') use ($php, $verify, $push): void {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($verify)
        . ' --check=' . escapeshellarg($check)
        . ' --scope=d03'
        . ' --assert=' . escapeshellarg($assert);
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    $text = trim(implode("\n", $out));
    $decoded = json_decode($text, true);
    $jsonOk = json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['steps']) && is_array($decoded['steps']);
    $verifyOk = $code === 0 && $jsonOk;
    $push(crm_smoke_step(
        'gate.verify.' . $check,
        $verifyOk ? "verify_tenant_backfill {$check} OK" : "verify_tenant_backfill {$check} falhou",
        $verifyOk,
        $jsonOk ? ['child' => $decoded] : ['raw' => $text]
    ));
};

switch ($mode) {
    case 'tenant-backfill':
        $runVerify('schema-readiness');
        $runVerify('null-scan', 'nulls');
        break;

    case 'tenant-context':
    case 'tenant-context-route-coverage':
    case 'tenant-session-resolution':
        $tenantSmoke = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'smoke' . DIRECTORY_SEPARATOR . 'tenant_context_smoke.php';
        if (!is_file($tenantSmoke)) {
            $push(crm_smoke_step('gate.tenant_context', 'tenant_context_smoke.php ainda não disponível', false));
            break;
        }
        $out = [];
        $code = 0;
        exec(escapeshellarg($php) . ' ' . escapeshellarg($tenantSmoke) . ' 2>&1', $out, $code);
        $push(crm_smoke_step(
            'gate.tenant_context',
            $code === 0 ? 'tenant_context_smoke OK' : 'tenant_context_smoke falhou',
            $code === 0,
            ['output' => implode("\n", $out)]
        ));
        break;

    case 'full':
        $runVerify('schema-readiness');
        $runVerify('null-scan', 'nulls');
        $runVerify('db-hardening', 'slug');
        $tenantSmoke = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'smoke' . DIRECTORY_SEPARATOR . 'tenant_context_smoke.php';
        if (is_file($tenantSmoke)) {
            $out = [];
            $code = 0;
            exec(escapeshellarg($php) . ' ' . escapeshellarg($tenantSmoke) . ' 2>&1', $out, $code);
            $push(crm_smoke_step(
                'gate.tenant_context',
                $code === 0 ? 'tenant_context_smoke OK' : 'tenant_context_smoke falhou',
                $code === 0
            ));
        } else {
            $push(crm_smoke_step('gate.tenant_context', 'tenant_context_smoke ausente no modo full', false));
        }
        break;

    default:
        $push(crm_smoke_step('gate.mode', 'Modo desconhecido: ' . $mode, false));
}

$push(crm_smoke_step('gate.done', $failed ? 'Gate falhou' : 'Gate OK', !$failed));
crm_smoke_emit($steps, $failed ? 1 : 0);
