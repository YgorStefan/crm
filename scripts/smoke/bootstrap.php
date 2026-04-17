<?php
/**
 * Bootstrap compartilhado para scripts CLI de smoke/migrations.
 * Define constantes de caminho, carrega config e expõe PDO.
 */
declare(strict_types=1);

$bootstrapDir = __DIR__;
$rootPath = dirname($bootstrapDir, 2);

require_once $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

/** Tabelas do escopo D-03 (fase 1). */
const CRM_TENANCY_D03_TABLES = ['users', 'pipeline_stages', 'clients', 'interactions', 'tasks'];

/**
 * @return array{ok: bool, step: string, message: string, data?: array<string, mixed>}
 */
function crm_smoke_step(string $step, string $message, bool $ok = true, array $data = []): array
{
    $row = [
        'ok' => $ok,
        'step' => $step,
        'message' => $message,
    ];
    if ($data !== []) {
        $row['data'] = $data;
    }
    return $row;
}

function crm_smoke_emit(array $steps, int $exitCode = 0): void
{
    fwrite(STDOUT, json_encode(['steps' => $steps], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($exitCode);
}

/**
 * PDO for CLI smoke/migration scripts: throws PDOException on failure.
 * Avoids Core\Database::getInstance() which calls die() and breaks verify exit codes.
 */
function crm_smoke_pdo(): \PDO
{
    $config = require ROOT_PATH . DS . 'config' . DS . 'database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['charset']
    );
    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
    $pdo = new \PDO($dsn, $config['user'], $config['pass'], $options);
    $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec("SET time_zone = '-03:00'");

    return $pdo;
}

/**
 * @return array<string, mixed>
 */
function crm_parse_cli_args(array $argv): array
{
    $out = [
        'dry_run' => false,
        'flags' => [],
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $out['dry_run'] = true;
            continue;
        }
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $key = $parts[0];
            $out['flags'][$key] = $parts[1] ?? true;
        }
    }
    return $out;
}
