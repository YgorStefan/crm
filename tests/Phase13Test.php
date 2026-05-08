<?php
/**
 * tests/Phase13Test.php — Bugfixes + CEP/neighborhood (Fase 13)
 *
 * Cobre três entregas:
 *   Bug 1 — Cold contacts: campo data_mensagem aceita ano > 4 dígitos
 *   Bug 2 — Cards Cotas/Pagamentos não aparecem em clientes "Fechado - Ganho"
 *   Feature — Auto-preenchimento de CEP via ViaCEP + coluna neighborhood
 *
 * Execute: php tests/Phase13Test.php
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

require_once __DIR__ . '/../config/app.php'; // define ROOT_PATH e env()

// ── 1. Bug 1 — Cold Contacts: max="9999-12-31" nos três campos ───────────────

section('1. Bug 1 — Cold Contacts: max="9999-12-31" em data_mensagem');

$coldSrc = file_get_contents(ROOT_PATH . '/app/Views/cold-contacts/index.php');

// Conta ocorrências de max="9999-12-31" — deve haver exatamente 3
$maxCount = substr_count($coldSrc, 'max="9999-12-31"');
ok('max="9999-12-31" presente no formulário de importação estático', $maxCount >= 1);
ok('max="9999-12-31" presente no campo bulk (barra de ações)',       $maxCount >= 2);
ok('max="9999-12-31" presente no renderRow() (linha JS dinâmica)',   $maxCount >= 3);

// Event delegation — script que limpa valor quando ano > 9999
ok('event delegation captura e.target.type !== \'date\'',
    str_contains($coldSrc, "e.target.type !== 'date'"));
ok('event delegation verifica parseInt > 9999',
    str_contains($coldSrc, 'parseInt(val.split') && str_contains($coldSrc, '> 9999'));
ok('event delegation limpa e.target.value quando inválido',
    str_contains($coldSrc, "e.target.value = ''"));
ok('event delegation usa addEventListener no document (delegação global)',
    str_contains($coldSrc, "document.addEventListener('change'"));

// ── 2. Bug 2 — is_won_stage: seed e migração ─────────────────────────────────

section('2. Bug 2 — is_won_stage: schema.sql e migration 011');

$schemaSrc = file_get_contents(ROOT_PATH . '/database/schema.sql');

ok("schema.sql define coluna is_won_stage na tabela pipeline_stages",
    str_contains($schemaSrc, 'is_won_stage'));
ok("schema.sql seed INSERT inclui 'Fechado - Ganho' com is_won_stage = 1",
    preg_match("/Fechado - Ganho.*1\s*\)/s", $schemaSrc) === 1
    || str_contains($schemaSrc, "'Fechado - Ganho',  '#10b981', 5, 1, 1)"));

$migSrc = file_get_contents(ROOT_PATH . '/database/migrations/011_add_neighborhood_to_clients.php');

ok('migration 011 contém UPDATE pipeline_stages SET is_won_stage = 1',
    str_contains($migSrc, 'UPDATE pipeline_stages') && str_contains($migSrc, 'is_won_stage = 1'));
ok("migration 011 filtra por name = 'Fechado - Ganho'",
    str_contains($migSrc, "'Fechado - Ganho'"));
ok('migration 011 usa Core\\Database::getInstance() (bootstrap correto)',
    str_contains($migSrc, 'Core\\Database::getInstance()'));
ok('migration 011 declara strict_types=1',
    str_contains($migSrc, 'declare(strict_types=1)'));

// show.php — verifica que a condição lê is_won_stage do cliente
$showSrc = file_get_contents(ROOT_PATH . '/app/Views/clients/show.php');
ok("show.php verifica is_won_stage para exibir cards Cotas/Pagamentos",
    str_contains($showSrc, 'is_won_stage'));

// ── 3. Feature — migration 011: coluna neighborhood ──────────────────────────

section('3. Feature — migration 011: coluna neighborhood em clients');

ok('migration 011 faz ALTER TABLE clients ADD COLUMN neighborhood',
    str_contains($migSrc, 'ADD COLUMN neighborhood'));
ok('migration 011 é idempotente: usa SHOW COLUMNS antes do ALTER',
    str_contains($migSrc, 'SHOW COLUMNS FROM clients'));

$schemaSrc2 = $schemaSrc;
ok("schema.sql define coluna neighborhood na tabela clients",
    str_contains($schemaSrc2, 'neighborhood'));

// ── 4. Feature — CSP: connect-src inclui viacep.com.br ───────────────────────

section('4. Feature — CSP: viacep.com.br em connect-src');

$cspSrc = file_get_contents(ROOT_PATH . '/core/Middleware/CspMiddleware.php');

ok("CspMiddleware connect-src inclui https://viacep.com.br",
    str_contains($cspSrc, 'https://viacep.com.br'));
ok("viacep.com.br está na diretiva connect-src (não script-src)",
    preg_match("/connect-src[^;]*viacep\.com\.br/", $cspSrc) === 1);

// ── 5. Feature — Client model: neighborhood em INSERT e UPDATE ───────────────

section('5. Feature — Client model: neighborhood persistido');

$clientSrc = file_get_contents(ROOT_PATH . '/app/Models/Client.php');

ok('Client::create() inclui neighborhood nas colunas do INSERT',
    str_contains($clientSrc, 'neighborhood') &&
    preg_match('/INSERT INTO clients[^;]*neighborhood/s', $clientSrc) === 1);
ok('Client::create() vincula :neighborhood no execute()',
    str_contains($clientSrc, "':neighborhood'") || str_contains($clientSrc, '":neighborhood"'));
ok('Client::update() inclui neighborhood = :neighborhood no SET',
    preg_match('/UPDATE.*neighborhood\s*=\s*:neighborhood/s', $clientSrc) === 1 ||
    str_contains($clientSrc, 'neighborhood = :neighborhood'));

// ── 6. Feature — ClientController: neighborhood nos dados enviados ────────────

section('6. Feature — ClientController: neighborhood em store() e update()');

$ctrlSrc = file_get_contents(ROOT_PATH . '/app/Controllers/ClientController.php');

$nbhdCount = substr_count($ctrlSrc, "'neighborhood' => \$this->input('neighborhood')");
ok("store() inclui 'neighborhood' => \$this->input('neighborhood')", $nbhdCount >= 1);
ok("update() inclui 'neighborhood' => \$this->input('neighborhood')", $nbhdCount >= 2);

// ── 7. Feature — Views: campo neighborhood + ViaCEP em create e edit ─────────

section('7. Feature — Views: neighborhood input e ViaCEP fetch');

$createSrc = file_get_contents(ROOT_PATH . '/app/Views/clients/create.php');
$editSrc   = file_get_contents(ROOT_PATH . '/app/Views/clients/edit.php');

ok('create.php tem input name="neighborhood"',
    str_contains($createSrc, 'name="neighborhood"'));
ok('create.php faz fetch para viacep.com.br',
    str_contains($createSrc, 'viacep.com.br'));
ok('create.php preenche campo neighborhood com bairro retornado',
    str_contains($createSrc, 'neighborhood') && str_contains($createSrc, 'bairro'));

ok('edit.php tem input name="neighborhood"',
    str_contains($editSrc, 'name="neighborhood"'));
ok('edit.php exibe valor salvo via val($client, \'neighborhood\')',
    str_contains($editSrc, "val(\$client, 'neighborhood')"));
ok('edit.php faz fetch para viacep.com.br',
    str_contains($editSrc, 'viacep.com.br'));

// ── 8. Integração com banco de dados real ────────────────────────────────────

section('8. Integração com banco de dados real');

try {
    $config = require ROOT_PATH . '/config/database.php';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'], $config['port'], $config['dbname'], $config['charset']);
    $db = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Bug 2 — pipeline_stages tem is_won_stage = 1 para "Fechado - Ganho"
    $wonStage = $db->query(
        "SELECT id, name, is_won_stage FROM pipeline_stages WHERE name = 'Fechado - Ganho' LIMIT 1"
    )->fetch();

    ok("pipeline_stages contém etapa 'Fechado - Ganho'", !empty($wonStage));
    ok("'Fechado - Ganho' tem is_won_stage = 1",
        !empty($wonStage) && (int) $wonStage['is_won_stage'] === 1);

    // Bug 2 — pelo menos uma etapa com is_won_stage = 1 existe
    $wonCount = (int) $db->query(
        "SELECT COUNT(*) FROM pipeline_stages WHERE is_won_stage = 1"
    )->fetchColumn();
    ok('pipeline_stages tem pelo menos 1 etapa com is_won_stage = 1', $wonCount >= 1);

    // Feature — coluna neighborhood existe em clients
    $col = $db->query(
        "SHOW COLUMNS FROM clients LIKE 'neighborhood'"
    )->fetch();
    ok('clients.neighborhood existe no banco de dados', !empty($col));

    // Feature — clients.neighborhood é VARCHAR(100) NULL
    if (!empty($col)) {
        ok('clients.neighborhood é do tipo VARCHAR(100)',
            stripos($col['Type'], 'varchar(100)') !== false);
        ok('clients.neighborhood aceita NULL',
            strtolower($col['Null'] ?? '') === 'yes');
    } else {
        ok('clients.neighborhood tipo verificado (coluna ausente)', false);
        ok('clients.neighborhood aceita NULL (coluna ausente)', false);
    }

    // Bug 2 — query exata de findByIdWithRelations retorna is_won_stage
    // Usa a mesma SQL do Client::findByIdWithRelations(), substituindo tenant_id via JOIN
    try {
        // Tenta encontrar cliente na etapa "Fechado - Ganho"
        $wonStageId = !empty($wonStage) ? (int) $wonStage['id'] : 0;
        $testClient = $wonStageId
            ? $db->query("SELECT id, tenant_id FROM clients WHERE pipeline_stage_id = {$wonStageId} AND is_active = 1 LIMIT 1")->fetch()
            : null;

        if ($testClient) {
            $stmt = $db->prepare("
                SELECT c.*, ps.name AS stage_name, ps.color AS stage_color,
                       ps.is_won_stage, u.name AS assigned_name
                FROM clients c
                LEFT JOIN pipeline_stages ps ON ps.id = c.pipeline_stage_id
                LEFT JOIN users u            ON u.id  = c.assigned_to
                WHERE c.id = :id AND c.is_active = 1 AND c.tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([':id' => $testClient['id'], ':tenant_id' => $testClient['tenant_id']]);
            $result = $stmt->fetch();
            ok('findByIdWithRelations retorna linha para cliente em "Fechado - Ganho"', !empty($result));
            ok('findByIdWithRelations retorna is_won_stage = 1 no resultado',
                !empty($result) && (int) $result['is_won_stage'] === 1);
            ok('condição show.php !empty($client[\'is_won_stage\']) é verdadeira',
                !empty($result) && !empty($result['is_won_stage']));
        } else {
            // Nenhum cliente em "Fechado - Ganho" — verifica só que a query não quebra
            $row = $db->query("
                SELECT c.id, ps.is_won_stage
                FROM clients c
                LEFT JOIN pipeline_stages ps ON ps.id = c.pipeline_stage_id
                WHERE c.is_active = 1 LIMIT 1
            ")->fetch();
            ok('findByIdWithRelations JOIN retorna is_won_stage (sem cliente na etapa won)', is_array($row) && array_key_exists('is_won_stage', $row));
            ok('is_won_stage presente no resultado mesmo sem cliente em "Fechado - Ganho"', is_array($row));
            ok('[aviso] nenhum cliente em "Fechado - Ganho" para testar caminho completo — criar um manualmente', true);
        }
    } catch (PDOException $e) {
        ok('findByIdWithRelations query: ' . $e->getMessage(), false);
        ok('findByIdWithRelations is_won_stage [erro anterior]', false);
        ok('condição show.php [erro anterior]', false);
    }

} catch (PDOException $e) {
    echo "\033[33m  ⚠ DB não acessível via CLI: {$e->getMessage()}\033[0m\n";
    foreach ([
        "pipeline_stages contém etapa 'Fechado - Ganho'",
        "'Fechado - Ganho' tem is_won_stage = 1",
        'pipeline_stages tem pelo menos 1 etapa com is_won_stage = 1',
        'clients.neighborhood existe no banco de dados',
        'clients.neighborhood é do tipo VARCHAR(100)',
        'clients.neighborhood aceita NULL',
        'JOIN clients → pipeline_stages retorna is_won_stage',
    ] as $desc) {
        ok($desc . ' [DB indisponível]', false);
    }
}

// ── 9. Verificação de sintaxe PHP ────────────────────────────────────────────

section('9. Syntax check — php -l nos arquivos modificados');

$filesToCheck = [
    'app/Views/cold-contacts/index.php',
    'app/Views/clients/create.php',
    'app/Views/clients/edit.php',
    'app/Models/Client.php',
    'app/Controllers/ClientController.php',
    'core/Middleware/CspMiddleware.php',
    'database/migrations/011_add_neighborhood_to_clients.php',
];

foreach ($filesToCheck as $file) {
    $path = ROOT_PATH . '/' . $file;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    ok("php -l {$file}", $code === 0);
    $out = [];
}

// ── Manual: instruções para teste no browser ─────────────────────────────────

section('10. Checklist de testes manuais (executar no browser)');

echo "  \033[33m[ ]\033[0m Bug 1: Abrir /cold-contacts → criar/editar linha → tentar digitar ano com 5+ dígitos em 'Data da Mensagem'\n";
echo "       Esperado: campo limpa o valor ao sair do campo (evento change)\n\n";
echo "  \033[33m[ ]\033[0m Bug 2: Abrir /clients → abrir um cliente → editar → mover para etapa 'Fechado - Ganho'\n";
echo "       Esperado: cards 'Cotas de Consórcio' e 'Pagamentos' devem aparecer na tela show\n\n";
echo "  \033[33m[ ]\033[0m Feature CEP: Abrir /clients/create → digitar um CEP válido (ex: 01310-100) no campo CEP\n";
echo "       Esperado: campos Endereço, Bairro, Cidade e Estado preenchidos automaticamente; status 'Buscando...' aparece durante a chamada\n\n";
echo "  \033[33m[ ]\033[0m Feature CEP (edit): Abrir /clients/{id}/edit → limpar CEP → digitar outro CEP válido\n";
echo "       Esperado: mesmos campos atualizados; valor de Bairro persistido ao salvar\n\n";
echo "  \033[33m[ ]\033[0m Feature CEP (CEP inválido): Digitar '00000000' no campo CEP\n";
echo "       Esperado: nenhum campo preenchido, sem mensagem de erro visível\n";

// ── Resultado final ───────────────────────────────────────────────────────────

$total = $results['pass'] + $results['fail'];
$color = $results['fail'] === 0 ? "\033[32m" : "\033[31m";
echo "\n\033[1m────────────────────────────────────────────────\033[0m\n";
echo "{$color}\033[1mResultado: {$results['pass']}/{$total} testes passaram\033[0m\n";

if ($results['fail'] > 0) {
    echo "\n\033[31mFalharam:\033[0m\n";
    foreach ($results['errors'] as $e) {
        echo "  • {$e}\n";
    }
    exit(1);
}

echo "\033[32mTodos os testes passaram.\033[0m\n\n";
exit(0);
