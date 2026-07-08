<?php
/**
 * Bootstrap compartilhado das migrations de tenancy (001–003).
 *
 * Estas migrations nasceram como scripts one-shot em scripts/migrations/ e foram
 * movidas para database/migrations/ para entrar no runner (bin/migrate.php). Este
 * arquivo concentra os helpers que elas usam: parse de argumentos CLI, coleta de
 * passos, emissão de JSON e conexão PDO — reaproveitando a config do app.
 *
 * As migrations já estão baselined em todos os ambientes; este bootstrap existe
 * para que continuem executáveis do zero (e para o analisador estático resolver
 * as funções).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

/**
 * Tabelas do escopo D-03 (isolamento por tenant da Fase 1).
 * cold_contacts entrou depois, na Fase 3 (migration 002), fora deste conjunto.
 *
 * @var string[]
 */
if (!defined('CRM_TENANCY_D03_TABLES')) {
    define('CRM_TENANCY_D03_TABLES', ['users', 'pipeline_stages', 'clients', 'interactions', 'tasks']);
}

if (!function_exists('crm_parse_cli_args')) {
    /**
     * Interpreta os argumentos de linha de comando.
     *
     * Reconhece `--dry-run`, `--chave=valor` e `--flag` (sem valor).
     *
     * @param  array<int, string> $argv
     * @return array{dry_run: bool, flags: array<string, string|bool>}
     */
    function crm_parse_cli_args(array $argv): array
    {
        $flags = [];
        $dryRun = false;

        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--dry-run') {
                $dryRun = true;
                continue;
            }
            if (!str_starts_with($arg, '--')) {
                continue;
            }
            $body = substr($arg, 2);
            if (str_contains($body, '=')) {
                [$key, $value] = explode('=', $body, 2);
                $flags[$key] = $value;
            } else {
                $flags[$body] = true;
            }
        }

        return ['dry_run' => $dryRun, 'flags' => $flags];
    }
}

if (!function_exists('crm_smoke_step')) {
    /**
     * Monta um passo estruturado para o relatório JSON da migration.
     *
     * @param  array<string, mixed> $data
     * @return array{step: string, msg: string, ok: bool, data: array<string, mixed>}
     */
    function crm_smoke_step(string $step, string $msg, bool $ok = true, array $data = []): array
    {
        return ['step' => $step, 'msg' => $msg, 'ok' => $ok, 'data' => $data];
    }
}

if (!function_exists('crm_smoke_emit')) {
    /**
     * Emite o relatório dos passos em JSON no stdout e encerra o processo.
     * Retorna `never`: sempre finaliza via exit(), nunca devolve o controle.
     *
     * @param  array<int, array<string, mixed>> $steps
     */
    function crm_smoke_emit(array $steps, int $exitCode): never
    {
        $ok = $exitCode === 0 && !in_array(false, array_column($steps, 'ok'), true);
        fwrite(
            STDOUT,
            json_encode(
                ['ok' => $ok, 'exit' => $exitCode, 'steps' => $steps],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            ) . PHP_EOL
        );
        exit($exitCode);
    }
}

if (!function_exists('crm_smoke_pdo')) {
    /**
     * Abre uma conexão PDO usando a mesma config do app (config/database.php + .env).
     *
     * Diferente de Core\Database::getInstance() (que faz die() em falha), aqui deixamos
     * a PDOException propagar para as migrations tratarem com try/catch e emitirem o JSON.
     */
    function crm_smoke_pdo(): \PDO
    {
        /** @var array{host: string, port: string, dbname: string, user: string, pass: string, charset: string} $config */
        $config = require ROOT_PATH . DS . 'config' . DS . 'database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        return new \PDO($dsn, $config['user'], $config['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    }
}
