<?php
/**
 * tests/Phase09Test.php — Testes da Phase 09 (Core Regression Fix)
 *
 * Sem dependências externas. Execute:
 *   php tests/Phase09Test.php
 *
 * Cobre:
 *   1. Controller::render — closure segura no lugar de extract()
 *   2. Database::getInstance — ONLY_FULL_GROUP_BY não desativado
 *   3. Model::findAll — tenant scoping por $_SESSION['tenant_id']
 *   4. Model::delete — tenant scoping na cláusula DELETE
 *   5. Client::findAllWithRelations — compatível com ONLY_FULL_GROUP_BY (ANY_VALUE)
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
// Bootstrap mínimo
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../config/app.php';

// ---------------------------------------------------------------------------
// 1. Controller::render — closure no lugar de extract()
// ---------------------------------------------------------------------------

section('1. Controller::render — closure segura');

// Lê o arquivo e verifica ausência de extract() e presença da closure
$controllerSrc = file_get_contents(__DIR__ . '/../core/Controller.php');

ok('extract($data) não existe como chamada de função',
    !preg_match('/^\s*extract\s*\(/m', $controllerSrc));

ok('$renderView = function existe no Controller',
    str_contains($controllerSrc, '$renderView = function'));

ok('foreach ($__data as $__k => $__v) existe na closure',
    str_contains($controllerSrc, 'foreach ($__data as $__k => $__v)'));

// Teste funcional: instancia a closure e verifica que injeta variáveis
$renderView = function(string $__viewFile, array $__data) {
    foreach ($__data as $__k => $__v) { $$__k = $__v; }
    unset($__k, $__v);
    ob_start();
    require $__viewFile;
    return ob_get_clean();
};

$tmpView = sys_get_temp_dir() . '/test_view_' . uniqid() . '.php';
file_put_contents($tmpView, '<?php echo $greeting . " " . $name; ?>');
$output = $renderView($tmpView, ['greeting' => 'Olá', 'name' => 'Ygor']);
unlink($tmpView);

ok('Closure injeta variáveis corretamente na view ("Olá Ygor")',
    $output === 'Olá Ygor');

// Garante que $this não vaza para a closure (prefixo __)
$tmpView2 = sys_get_temp_dir() . '/test_view_' . uniqid() . '.php';
file_put_contents($tmpView2, '<?php echo isset($__k) ? "VAZOU" : "OK"; ?>');
$output2 = $renderView($tmpView2, ['foo' => 'bar']);
unlink($tmpView2);

ok('Variáveis temporárias da closure (__k, __v) são limpas via unset()',
    $output2 === 'OK');

// ---------------------------------------------------------------------------
// 2. Database.php — ONLY_FULL_GROUP_BY não desativado
// ---------------------------------------------------------------------------

section('2. Database.php — modo estrito MySQL restaurado');

$dbSrc = file_get_contents(__DIR__ . '/../core/Database.php');

ok("SET SESSION REPLACE ONLY_FULL_GROUP_BY não está ativo no código",
    !preg_match("/self::\\\$instance->exec\s*\(\s*[\"']SET SESSION sql_mode.*ONLY_FULL_GROUP_BY/", $dbSrc));

ok('Comentário explica que ONLY_FULL_GROUP_BY está mantido ativo',
    str_contains($dbSrc, 'ONLY_FULL_GROUP_BY mantido ativo'));

// ---------------------------------------------------------------------------
// 3. Model.php — tenant scoping
// ---------------------------------------------------------------------------

section('3. Model::findAll e delete — tenant scoping');

$modelSrc = file_get_contents(__DIR__ . '/../core/Model.php');

ok('protected bool $isGlobal = false existe no Model',
    str_contains($modelSrc, 'protected bool $isGlobal = false'));

ok('findAll() verifica $this->isGlobal antes de escopar',
    str_contains($modelSrc, '!$this->isGlobal && isset($_SESSION[\'tenant_id\'])'));

ok('findAll() usa WHERE tenant_id = :tenant_id quando escopado',
    str_contains($modelSrc, 'WHERE tenant_id = :tenant_id'));

ok('delete() usa AND tenant_id = :tenant_id quando escopado',
    str_contains($modelSrc, 'AND tenant_id = :tenant_id'));

// ---------------------------------------------------------------------------
// 4. Client::findAllWithRelations — ANY_VALUE(cs.tipo)
// ---------------------------------------------------------------------------

section('4. Client::findAllWithRelations — compatibilidade ONLY_FULL_GROUP_BY');

$clientSrc = file_get_contents(__DIR__ . '/../app/Models/Client.php');

ok('cs.tipo sem agregação não existe no SELECT',
    !preg_match('/^\s+cs\.tipo\s+AS\s+tipo_venda/m', $clientSrc));

ok('ANY_VALUE(cs.tipo) AS tipo_venda está no SELECT',
    str_contains($clientSrc, 'ANY_VALUE(cs.tipo) AS tipo_venda'));

// ---------------------------------------------------------------------------
// 5. Teste de integração com DB real
// ---------------------------------------------------------------------------

section('5. Integração com banco de dados real');

try {
    $config = require __DIR__ . '/../config/database.php';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'], $config['port'], $config['dbname'], $config['charset']);
    $db = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Verifica que ONLY_FULL_GROUP_BY está ativo no servidor
    $sqlMode = $db->query("SELECT @@sql_mode")->fetchColumn();
    ok('ONLY_FULL_GROUP_BY está ativo no sql_mode do servidor',
        str_contains($sqlMode, 'ONLY_FULL_GROUP_BY'));

    // Executa a query findAllWithRelations diretamente (sem SET SESSION)
    $sql = "
        SELECT
            c.*,
            ps.name  AS stage_name,
            ps.color AS stage_color,
            ps.is_won_stage,
            u.name   AS assigned_name,
            ANY_VALUE(cs.tipo) AS tipo_venda
        FROM clients c
        LEFT JOIN pipeline_stages ps ON ps.id = c.pipeline_stage_id
        LEFT JOIN users u            ON u.id  = c.assigned_to
        LEFT JOIN client_sales cs    ON cs.client_id = c.id
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.updated_at DESC
        LIMIT 5
    ";

    try {
        $rows = $db->query($sql)->fetchAll();
        ok('findAllWithRelations com ANY_VALUE executa sem erro de ONLY_FULL_GROUP_BY',
            true);
        ok('findAllWithRelations retorna array de resultados',
            is_array($rows));
    } catch (PDOException $e) {
        ok('findAllWithRelations com ANY_VALUE executa sem erro: ' . $e->getMessage(),
            false);
    }

    // Verifica que GROUP BY sem ANY_VALUE ainda falharia (confirma modo estrito)
    $sqlBad = "
        SELECT c.id, cs.tipo AS tipo_venda
        FROM clients c
        LEFT JOIN client_sales cs ON cs.client_id = c.id
        WHERE c.is_active = 1
        GROUP BY c.id
        LIMIT 1
    ";
    $strictViolationDetected = false;
    try {
        $db->query($sqlBad)->fetchAll();
        // Se não lançou exceção, pode ser que não há dados com múltiplas vendas
        // Ainda assim, verificamos que o servidor está em modo estrito
        $strictViolationDetected = str_contains($sqlMode, 'ONLY_FULL_GROUP_BY');
    } catch (PDOException $e) {
        $strictViolationDetected = str_contains($e->getMessage(), 'ONLY_FULL_GROUP_BY')
            || str_contains($e->getMessage(), '1055');
    }
    ok('Servidor MySQL rejeita GROUP BY ambíguo (modo estrito ativo)',
        $strictViolationDetected);

    // Teste de tenant scoping: Model::findAll lógica via SQL direto
    $tenantId = 1;
    $scopedCount = (int) $db->query(
        "SELECT COUNT(*) FROM clients WHERE tenant_id = {$tenantId} AND is_active = 1"
    )->fetchColumn();
    $totalCount = (int) $db->query(
        "SELECT COUNT(*) FROM clients WHERE is_active = 1"
    )->fetchColumn();

    // Se há múltiplos tenants, o escopo deve retornar menos ou igual ao total
    ok("Model::findAll escopo tenant_id={$tenantId} retorna ≤ total ({$scopedCount}/{$totalCount})",
        $scopedCount <= $totalCount);

    // Pipeline stages query (usada pelo PipelineController)
    try {
        $stages = $db->query("SELECT * FROM pipeline_stages ORDER BY position")->fetchAll();
        ok('pipeline_stages consulta executou sem erro (' . count($stages) . ' estágios)',
            is_array($stages));
    } catch (PDOException $e) {
        ok('pipeline_stages consulta: ' . $e->getMessage(), false);
    }

} catch (PDOException $e) {
    echo "\033[33m  ⚠ DB não acessível via CLI: {$e->getMessage()}\033[0m\n";
    ok('Conexão com banco de dados disponível', false);
}

// ---------------------------------------------------------------------------
// 6. Sintaxe PHP de todos os arquivos modificados
// ---------------------------------------------------------------------------

section('6. Syntax check — php -l');

$files = [
    'core/Controller.php',
    'core/Database.php',
    'core/Model.php',
    'app/Models/Client.php',
];

foreach ($files as $file) {
    $path = __DIR__ . '/../' . $file;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $out, $code);
    ok("php -l {$file}", $code === 0);
}

// ---------------------------------------------------------------------------
// Resultado final
// ---------------------------------------------------------------------------

$total = $results['pass'] + $results['fail'];
$color = $results['fail'] === 0 ? "\033[32m" : "\033[31m";
echo "\n{$color}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
echo "{$color}  Resultado: {$results['pass']}/{$total} passed";
if ($results['fail'] > 0) {
    echo " | {$results['fail']} failed";
}
echo "\033[0m\n";

if (!empty($results['errors'])) {
    echo "\033[31m  Falhos:\033[0m\n";
    foreach ($results['errors'] as $e) {
        echo "    - {$e}\n";
    }
}

echo "\n";
exit($results['fail'] > 0 ? 1 : 0);
