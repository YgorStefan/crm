<?php
/**
 * Migration Runner — aplica migrations pendentes em ordem alfabética.
 *
 * Por que existe:
 *   Antes desse script, cada migration era rodada manualmente. Resultado:
 *   prod ficava dessincronizado com local (caso interactions.tenant_id que
 *   gerou HTTP 500 em /interactions/store por semanas). Este runner torna
 *   o deploy idempotente e auditável.
 *
 * Como funciona:
 *   1. Garante que a tabela `_migrations` existe (cria se for primeira execução).
 *   2. Lê todos os arquivos em database/migrations/ ordenados por nome.
 *   3. Filtra os que JÁ estão registrados na tabela _migrations (skip).
 *   4. Para cada pendente:
 *        - .php → require (cada migration é responsável pela própria idempotência)
 *        - .sql → executa cada statement separado por ';' (suporta IF NOT EXISTS)
 *   5. Registra em _migrations com data + hash do arquivo.
 *
 * Modo "baseline" (--baseline):
 *   Apenas registra TODAS as migrations existentes como aplicadas, sem rodar.
 *   Use uma única vez por ambiente onde o schema já está atualizado mas o
 *   _migrations ainda não existe (caso atual: local + prod, onde tudo já
 *   está aplicado e queremos só começar a rastrear daqui pra frente).
 *
 * Uso:
 *   php bin/migrate.php             # aplica pendentes
 *   php bin/migrate.php --baseline  # marca tudo como aplicado (1ª vez por env)
 *   php bin/migrate.php --status    # lista o que está aplicado vs pendente
 *
 * Saída:
 *   - Códigos de saída: 0 = sucesso (ou nada a fazer), 1 = erro.
 *   - Logs no stdout no formato `[migrate] <nome> <ação>`.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

$mode = 'apply';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--baseline') $mode = 'baseline';
    elseif ($arg === '--status') $mode = 'status';
    elseif ($arg === '--help' || $arg === '-h') {
        echo "Uso: php bin/migrate.php [--baseline | --status]\n";
        echo "  (sem flag)   aplica migrations pendentes\n";
        echo "  --baseline   registra todas como aplicadas sem rodar (1ª vez por env)\n";
        echo "  --status     mostra aplicadas vs pendentes\n";
        exit(0);
    }
}

try {
    $pdo = Core\Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "[migrate] ERRO conexão DB: {$e->getMessage()}\n");
    exit(1);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS _migrations (
        name VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        checksum CHAR(40) NULL COMMENT 'sha1 do arquivo no momento da aplicação'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$dir = realpath(__DIR__ . '/../database/migrations');
if ($dir === false || !is_dir($dir)) {
    fwrite(STDERR, "[migrate] ERRO: pasta database/migrations não encontrada\n");
    exit(1);
}

$files = array_values(array_filter(scandir($dir), function ($f) use ($dir) {
    if ($f === '.' || $f === '..') return false;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    return in_array($ext, ['php', 'sql'], true) && is_file($dir . DIRECTORY_SEPARATOR . $f);
}));
sort($files);

$applied = [];
$rs = $pdo->query("SELECT name, applied_at FROM _migrations ORDER BY name");
foreach ($rs as $row) $applied[$row['name']] = $row['applied_at'];

if ($mode === 'status') {
    echo str_pad("Migration", 60) . "Status\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($files as $f) {
        $status = isset($applied[$f]) ? "aplicada em {$applied[$f]}" : "PENDENTE";
        echo str_pad($f, 60) . $status . "\n";
    }
    foreach ($applied as $name => $when) {
        if (!in_array($name, $files, true)) {
            echo str_pad($name, 60) . "registrada mas arquivo SUMIU\n";
        }
    }
    exit(0);
}

if ($mode === 'baseline') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO _migrations (name, checksum) VALUES (?, ?)");
    $count = 0;
    foreach ($files as $f) {
        if (isset($applied[$f])) continue;
        $checksum = sha1_file($dir . DIRECTORY_SEPARATOR . $f);
        $stmt->execute([$f, $checksum]);
        echo "[migrate] BASELINE registrada: {$f}\n";
        $count++;
    }
    echo "[migrate] baseline concluído ({$count} migrations registradas, nenhuma executada).\n";
    exit(0);
}

$pending = array_values(array_filter($files, fn($f) => !isset($applied[$f])));
if (empty($pending)) {
    echo "[migrate] nada a fazer — " . count($files) . " migration(s) já aplicada(s).\n";
    exit(0);
}

echo "[migrate] " . count($pending) . " migration(s) pendente(s):\n";
foreach ($pending as $f) echo "  - {$f}\n";

$stmt = $pdo->prepare("INSERT INTO _migrations (name, checksum) VALUES (?, ?)");

foreach ($pending as $f) {
    $path = $dir . DIRECTORY_SEPARATOR . $f;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    echo "[migrate] aplicando {$f}...\n";

    try {
        if ($ext === 'php') {
            (function () use ($path) { require $path; })();
        } else {
            $sql = file_get_contents($path);
            $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
            foreach ($stmts as $s) {
                if ($s === '' || str_starts_with($s, '--')) continue;
                $pdo->exec($s);
            }
        }
        $stmt->execute([$f, sha1_file($path)]);
        echo "[migrate] OK {$f}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "[migrate] FALHA em {$f}: {$e->getMessage()}\n");
        fwrite(STDERR, "[migrate] abortado — corrija e rode novamente.\n");
        exit(1);
    }
}

echo "[migrate] todas as migrations aplicadas com sucesso.\n";
exit(0);
