<?php

declare(strict_types=1);

use App\Models\Task;
use App\Services\Google\GoogleAuth;
use App\Services\Google\GoogleCalendarSync;
use App\Services\Google\GoogleConfig;
use App\Services\PlacesApiService;
use Core\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';
require_once __DIR__ . '/Support/FakeTransport.php';

final class GoogleWindowTest extends TestCase
{
    private const TENANT = 1;
    private const ANA    = 1; // tenant 1, admin
    private const BIA    = 2; // tenant 1, seller
    private const DUDA   = 4; // tenant 2

    private const SP         = 'America/Sao_Paulo';
    private const EVENTS_URL = FakeTransport::EVENTS_URL;
    private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';

    private ?\PDO $pdo = null;
    private string $chave = '';
    private GoogleConfig $config;
    private FakeTransport $http;
    private float $t0 = 0.0;
    private string $logDir = '';
    private \DateTimeImmutable $dia;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $this->chave = 'base64:' . base64_encode(str_repeat('k', 32));
        $this->config = new GoogleConfig(
            'cliente-falso.apps.googleusercontent.com', 'segredo-falso', $this->chave, 'http://localhost:8000/google/callback'
        );
        $this->http = new FakeTransport();
        $this->t0 = ($this->http->now())();
        $this->logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-window-log-' . bin2hex(random_bytes(6));
        $_SESSION['tenant_id'] = self::TENANT;
        // a janela comeca a meia-noite de sao paulo de um dia futuro, longe o bastante para o envio aceitar as tarefas.
        $this->dia = (new \DateTimeImmutable('today', new \DateTimeZone(self::SP)))->modify('+10 days');

        $this->pdo->exec("INSERT INTO users (id, name, tenant_id, is_active) VALUES
            (1, 'Ana', 1, 1), (2, 'Bia', 1, 1), (3, 'Caio', 1, 0), (4, 'Duda', 2, 1)");
        $this->pdo->exec("INSERT INTO clients (id, name, tenant_id) VALUES (10, 'Cliente Teste', 1)");
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        unset($_SESSION['tenant_id']);
        foreach (glob($this->logDir . DIRECTORY_SEPARATOR . '*') ?: [] as $arquivo) {
            unlink($arquivo);
        }
        if (is_dir($this->logDir)) {
            rmdir($this->logDir);
        }
    }

    private function sync(?Task $tasks = null): GoogleCalendarSync
    {
        $auth = new GoogleAuth($this->config, $this->http, now: $this->http->now());

        return new GoogleCalendarSync(
            $this->config,
            $this->http,
            $auth,
            tasks: $tasks ?? new Task(),
            now: $this->http->now(),
            logger: new Logger($this->logDir),
        );
    }

    /** @return array{status:string, tasks:list<array<string, mixed>>, team:list<array<string, mixed>>, foreign:list<array<string, mixed>>} */
    private function abrir(bool $isAdmin = false, bool $team = false, ?Task $tasks = null): array
    {
        return $this->sync($tasks)->window(self::ANA, $isAdmin, $this->dia, $this->dia->modify('+7 days'), $team);
    }

    private function token(int $userId): string
    {
        return "acesso-falso-{$userId}";
    }

    /** @param array<string, mixed> $campos */
    private function conta(int $userId, array $campos = []): void
    {
        $this->http->tokenOf("conta-{$userId}", $this->token($userId));
        $this->inserir('google_accounts', array_merge([
            'user_id'                 => $userId,
            'tenant_id'               => self::TENANT,
            'google_email'            => 'pessoa@example.com',
            'google_sub'              => "sub-falso-{$userId}",
            'refresh_token'           => PlacesApiService::encryptKey('refresh-falso', $this->chave),
            'access_token'            => PlacesApiService::encryptKey($this->token($userId), $this->chave),
            'access_token_expires_at' => date('Y-m-d H:i:s', (int) floor($this->t0) + 3600),
            'status'                  => 'active',
        ], $campos));
    }

    /** @return array<string, mixed> */
    private function contaDe(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM google_accounts WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch();
    }

    /** outro envio esta no ar: o drain pula a conta e a pendencia fica como esta */
    private function travarEnvio(int $userId): void
    {
        $this->pdo->prepare('UPDATE google_accounts SET drain_until = NOW() + INTERVAL 1 MINUTE WHERE user_id = :id')
            ->execute([':id' => $userId]);
    }

    /** @param array<string, mixed> $campos */
    private function tarefa(array $campos = []): int
    {
        $this->inserir('tasks', array_merge([
            'tenant_id'           => self::TENANT,
            'assigned_to'         => self::ANA,
            'created_by'          => self::ANA,
            'client_id'           => null,
            'title'               => 'Ligar para cliente',
            'description'         => null,
            'due_date'            => $this->quando(1, '10:00'),
            'priority'            => 'medium',
            'status'              => 'pending',
            'recurrence_type'     => 'none',
            'google_event_id'     => null,
            'google_owner_id'     => null,
            'google_etag'         => null,
            'google_duration_min' => null,
            'google_sync_pending' => 0,
        ], $campos));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * tarefa da ana vinculada a um evento da agenda dela, os dois com o mesmo etag.
     *
     * @param array<string, mixed> $tarefa
     * @param array<string, mixed> $evento
     */
    private function vinculada(string $eventId, array $tarefa = [], array $evento = []): int
    {
        $this->evento(self::ANA, $eventId, array_merge(['etag' => '"etag-1"', 'summary' => 'Ligar para cliente'], $evento));

        return $this->tarefa(array_merge([
            'google_event_id'     => $eventId,
            'google_owner_id'     => self::ANA,
            'google_etag'         => '"etag-1"',
            'google_duration_min' => 30,
        ], $tarefa));
    }

    /** @param array<string, mixed> $campos */
    private function evento(int $userId, string $id, array $campos = []): void
    {
        $this->http->putEvent($this->token($userId), array_merge([
            'id'        => $id,
            'summary'   => 'Evento do Google',
            'start'     => ['dateTime' => $this->rfc(1, '10:00'), 'timeZone' => self::SP],
            'end'       => ['dateTime' => $this->rfc(1, '10:30'), 'timeZone' => self::SP],
            'organizer' => ['email' => 'pessoa@example.com', 'self' => true],
            'eventType' => 'default',
            'htmlLink'  => 'https://www.google.com/calendar/event?eid=falso',
        ], $campos));
    }

    /** @return array<string, mixed> */
    private function linha(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /** @param array<string, mixed> $linha */
    private function inserir(string $tabela, array $linha): void
    {
        $colunas = implode(', ', array_keys($linha));
        $marcas  = implode(', ', array_map(fn (string $c): string => ":{$c}", array_keys($linha)));
        $valores = [];
        foreach ($linha as $coluna => $valor) {
            $valores[":{$coluna}"] = $valor;
        }
        $this->pdo->prepare("INSERT INTO {$tabela} ({$colunas}) VALUES ({$marcas})")->execute($valores);
    }

    /** dia da janela mais $dias, no formato Y-m-d */
    private function data(int $dias): string
    {
        return $this->dia->modify("+{$dias} days")->format('Y-m-d');
    }

    /** horario de sao paulo no formato da coluna due_date */
    private function quando(int $dias, string $hora): string
    {
        return $this->data($dias) . " {$hora}:00";
    }

    private function rfc(int $dias, string $hora, string $offset = '-03:00'): string
    {
        return $this->data($dias) . "T{$hora}:00{$offset}";
    }

    /** @return list<string> "METODO url" de cada chamada, na ordem */
    private function chamadas(): array
    {
        return array_map(
            fn (array $pedido): string => "{$pedido['method']} {$pedido['url']}",
            $this->http->requests(),
        );
    }

    /** @return list<array{method:string, url:string, headers:array<int, string>, body:?string, timeoutMs:int}> */
    private function pedidosDaLista(): array
    {
        return array_values(array_filter(
            $this->http->requests(),
            fn (array $pedido): bool => $pedido['method'] === 'GET' && str_starts_with($pedido['url'], self::EVENTS_URL . '?'),
        ));
    }

    /** @return list<string> ids dos eventos lidos um a um */
    private function getsDeEvento(): array
    {
        $ids = [];
        foreach ($this->http->requests() as $pedido) {
            if ($pedido['method'] === 'GET' && str_starts_with($pedido['url'], self::EVENTS_URL . '/')) {
                $ids[] = rawurldecode(substr($pedido['url'], strlen(self::EVENTS_URL) + 1));
            }
        }
        return $ids;
    }

    /** @return list<array{method:string, url:string, headers:array<int, string>, body:?string, timeoutMs:int}> */
    private function escritas(): array
    {
        return array_values(array_filter(
            $this->http->requests(),
            fn (array $pedido): bool => in_array($pedido['method'], ['PATCH', 'DELETE', 'POST'], true)
                && str_starts_with($pedido['url'], self::EVENTS_URL),
        ));
    }

    /**
     * @param list<array<string, mixed>> $linhas
     * @return list<int>
     */
    private static function ids(array $linhas): array
    {
        return array_map(fn (array $linha): int => (int) $linha['id'], $linhas);
    }

    /**
     * @param list<array<string, mixed>> $foreign
     * @return list<string>
     */
    private static function googleIds(array $foreign): array
    {
        return array_map(fn (array $item): string => (string) $item['google_id'], $foreign);
    }

    private function logs(): string
    {
        $texto = '';
        foreach (glob($this->logDir . DIRECTORY_SEPARATOR . '*') ?: [] as $arquivo) {
            $texto .= (string) file_get_contents($arquivo);
        }
        return $texto;
    }

    public function testAgendaFalsaFiltraPelaJanela(): void
    {
        // os testes de ausencia dependem da janela da lista falsa, entao ela segue a regra de sobreposicao do google.
        $this->evento(self::ANA, 'evtdentro', ['start' => ['dateTime' => $this->rfc(2, '10:00')], 'end' => ['dateTime' => $this->rfc(2, '11:00')]]);
        $this->evento(self::ANA, 'evtdepois', ['start' => ['dateTime' => $this->rfc(9, '10:00')], 'end' => ['dateTime' => $this->rfc(9, '11:00')]]);
        $this->evento(self::ANA, 'evtantes', ['start' => ['dateTime' => $this->rfc(-1, '10:00')], 'end' => ['dateTime' => $this->rfc(-1, '11:00')]]);
        $this->evento(self::ANA, 'evtatravessa', ['start' => ['date' => $this->data(-2)], 'end' => ['date' => $this->data(1)]]);
        $this->evento(self::ANA, 'evtterminanoinicio', ['start' => ['date' => $this->data(-1)], 'end' => ['date' => $this->data(0)]]);

        $query = http_build_query([
            'singleEvents' => 'true',
            'timeMin'      => $this->dia->format(DATE_RFC3339),
            'timeMax'      => $this->dia->modify('+7 days')->format(DATE_RFC3339),
        ]);
        $resposta = $this->http->send('GET', self::EVENTS_URL . '?' . $query, ['Authorization: Bearer ' . $this->token(self::ANA)], null, 1000);

        $ids = array_column(json_decode($resposta['body'], true)['items'], 'id');
        sort($ids);
        $this->assertSame(['evtatravessa', 'evtdentro'], $ids);
    }

    public function testMaterializaAntesDeEnviar(): void
    {
        $this->conta(self::ANA);
        // dia 1 do mes passado: a soma de um mes nunca transborda, e a serie tem 12 ocorrencias pela frente
        $molde = $this->tarefa([
            'title'               => 'Relatorio mensal',
            'due_date'            => (new \DateTimeImmutable('first day of last month'))->format('Y-m-d 09:00:00'),
            'recurrence_type'     => 'monthly',
            'google_sync_pending' => 0,
        ]);

        $this->abrir();

        $filhas = $this->pdo->query("SELECT * FROM tasks WHERE recurrence_parent_id = {$molde}")->fetchAll();
        $this->assertCount(12, $filhas);
        foreach ($filhas as $filha) {
            $this->assertSame(0, (int) $filha['google_sync_pending']);
            $this->assertNotNull($filha['google_event_id']);
        }
        $this->assertCount(12, $this->http->events($this->token(self::ANA)));
    }

    public function testAdminMaterializaSeriesDaEquipe(): void
    {
        $this->conta(self::ANA);
        $molde = $this->tarefa([
            'assigned_to'     => self::BIA,
            'due_date'        => (new \DateTimeImmutable('first day of last month'))->format('Y-m-d 09:00:00'),
            'recurrence_type' => 'monthly',
        ]);

        $this->abrir(isAdmin: true);

        $filhas = (int) $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE recurrence_parent_id = {$molde}")->fetchColumn();
        $this->assertSame(12, $filhas);
    }

    public function testTarefaSempreVemDoBanco(): void
    {
        $this->conta(self::ANA);
        $vinculada = $this->vinculada('evtvinculado1');
        $semEvento = $this->tarefa(['due_date' => $this->quando(2, '15:00')]);
        $this->http->respond('calendar/v3', 503, 'Service Unavailable');

        $janela = $this->abrir();

        $this->assertSame('degraded', $janela['status']);
        $this->assertSame([$vinculada, $semEvento], self::ids($janela['tasks']));
        $this->assertSame([], $janela['foreign']);
        $this->assertCount(1, $this->pedidosDaLista());
        // sem lista nada prova que a vinculada sumiu, entao ela nao vira get
        $this->assertSame([], $this->getsDeEvento());
        $this->assertSame('evtvinculado1', $this->linha($vinculada)['google_event_id']);
    }

    public function testRedeNoEnvioNaoTentaList(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa(['google_sync_pending' => 1]);
        $this->http->networkDown(true);

        $janela = $this->abrir();

        $this->assertSame('degraded', $janela['status']);
        $this->assertSame(['POST ' . self::EVENTS_URL], $this->chamadas());
        $this->assertSame([$id], self::ids($janela['tasks']));
    }

    public function testEnvioQuePedeReconexaoNaoTentaList(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa(['google_sync_pending' => 1]);
        $this->http->respond('calendar/v3', 403, FakeTransport::googleError(403, 'insufficientPermissions'));

        $janela = $this->abrir();

        $this->assertSame('reconnect', $janela['status']);
        $this->assertSame(['POST ' . self::EVENTS_URL], $this->chamadas());
        $this->assertSame([$id], self::ids($janela['tasks']));
    }

    public function testPaginaTodasAsPaginasDoList(): void
    {
        $this->conta(self::ANA);
        foreach (['evtpagina1', 'evtpagina2', 'evtpagina3'] as $i => $id) {
            $this->evento(self::ANA, $id, ['start' => ['dateTime' => $this->rfc($i + 1, '10:00')], 'end' => ['dateTime' => $this->rfc($i + 1, '11:00')]]);
        }
        $this->http->pageSize(1);

        $janela = $this->abrir();

        $this->assertSame('ok', $janela['status']);
        $this->assertSame(['evtpagina1', 'evtpagina2', 'evtpagina3'], self::googleIds($janela['foreign']));

        $pedidos = $this->pedidosDaLista();
        $this->assertCount(3, $pedidos);
        parse_str((string) parse_url($pedidos[0]['url'], PHP_URL_QUERY), $primeira);
        $this->assertSame('true', $primeira['singleEvents']);
        $this->assertSame($this->data(0) . 'T00:00:00-03:00', $primeira['timeMin']);
        $this->assertSame($this->data(7) . 'T00:00:00-03:00', $primeira['timeMax']);
        $this->assertArrayNotHasKey('pageToken', $primeira);
        parse_str((string) parse_url($pedidos[2]['url'], PHP_URL_QUERY), $terceira);
        $this->assertSame('2', $terceira['pageToken']);
        $this->assertSame($primeira['timeMin'], $terceira['timeMin']);
    }

    public function testOrcamentoDoListERespeitado(): void
    {
        $this->conta(self::ANA);
        for ($i = 1; $i <= 6; $i++) {
            $this->evento(self::ANA, "evtpagina{$i}", ['start' => ['dateTime' => $this->rfc(1, sprintf('%02d:00', $i + 8))], 'end' => ['dateTime' => $this->rfc(1, sprintf('%02d:30', $i + 8))]]);
        }
        // apagado no google: so uma lista completa prova a ausencia, e ai viria um get.
        $sumida = $this->vinculada('evtsumido1', ['due_date' => $this->quando(3, '10:00')], ['status' => 'cancelled']);
        $this->http->pageSize(1);
        $this->http->advanceMsPerCall(1000);

        $janela = $this->abrir();

        $this->assertSame([4000, 3000, 2000, 1000], array_column($this->pedidosDaLista(), 'timeoutMs'));
        $this->assertSame('degraded', $janela['status']);
        $this->assertCount(4, $janela['foreign']);
        $this->assertSame([], $this->getsDeEvento());
        $linha = $this->linha($sumida);
        $this->assertSame('pending', $linha['status']);
        $this->assertSame('evtsumido1', $linha['google_event_id']);
    }

    public function testListaPedeReconexaoERevogaAConta(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();
        $this->http->respond('calendar/v3', 401, FakeTransport::googleError(401, 'authError'));
        $this->http->respond('oauth2.googleapis.com/token', 200, ['access_token' => 'acesso-renovado-falso', 'expires_in' => 3599]);

        $janela = $this->abrir();

        $this->assertSame('reconnect', $janela['status']);
        $this->assertSame('revoked', $this->contaDe(self::ANA)['status']);
        $this->assertSame([$id], self::ids($janela['tasks']));
        $this->assertCount(2, $this->pedidosDaLista());
    }

    public function testEventoSemTarefaViraForeign(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtgoogle1', [
            'summary'     => 'Dentista',
            'description' => 'Levar os exames',
            'start'       => ['dateTime' => $this->rfc(2, '10:00'), 'timeZone' => self::SP],
            'end'         => ['dateTime' => $this->rfc(2, '11:15'), 'timeZone' => self::SP],
            'htmlLink'    => 'https://www.google.com/calendar/event?eid=evtgoogle1',
        ]);

        $janela = $this->abrir();

        $this->assertSame('ok', $janela['status']);
        $this->assertSame([[
            'id'          => 'g_evtgoogle1',
            'google_id'   => 'evtgoogle1',
            'title'       => 'Dentista',
            'start'       => $this->data(2) . 'T10:00:00',
            'end'         => $this->data(2) . 'T11:15:00',
            'all_day'     => false,
            'recurring'   => false,
            'description' => 'Levar os exames',
            'html_link'   => 'https://www.google.com/calendar/event?eid=evtgoogle1',
            'can_edit'    => true,
        ]], $janela['foreign']);
    }

    public function testEventoDeTarefaNaoDuplicaEmForeign(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtdatarefa1');
        $this->evento(self::ANA, 'evtsodogoogle1', ['start' => ['dateTime' => $this->rfc(2, '10:00')], 'end' => ['dateTime' => $this->rfc(2, '11:00')]]);

        $janela = $this->abrir();

        $this->assertSame([$id], self::ids($janela['tasks']));
        $this->assertSame(['evtsodogoogle1'], self::googleIds($janela['foreign']));
    }

    public function testEventoSemTituloDiaInteiroEVariosDias(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtferias1', ['summary' => null, 'start' => ['date' => $this->data(1)], 'end' => ['date' => $this->data(4)]]);
        $this->evento(self::ANA, 'evtviagem1', ['summary' => '   ', 'start' => ['date' => $this->data(-2)], 'end' => ['date' => $this->data(2)]]);

        $janela = $this->abrir();

        $this->assertSame('ok', $janela['status']);
        $porId = array_column($janela['foreign'], null, 'google_id');
        $this->assertSame(['evtferias1', 'evtviagem1'], array_keys($porId));
        $this->assertSame('(sem título)', $porId['evtferias1']['title']);
        $this->assertTrue($porId['evtferias1']['all_day']);
        $this->assertSame($this->data(1), $porId['evtferias1']['start']);
        $this->assertSame($this->data(4), $porId['evtferias1']['end']);
        $this->assertSame('(sem título)', $porId['evtviagem1']['title']);
        $this->assertSame($this->data(-2), $porId['evtviagem1']['start']);
        $this->assertSame($this->data(2), $porId['evtviagem1']['end']);
    }

    public function testItemCanceladoOuInvalidoNaoViraForeign(): void
    {
        $this->conta(self::ANA);
        $valido = ['id' => 'evtvalido1', 'etag' => '"e1"', 'status' => 'confirmed', 'summary' => 'Valido',
            'start' => ['dateTime' => $this->rfc(1, '10:00')], 'end' => ['dateTime' => $this->rfc(1, '11:00')]];
        $this->http->respondOnce('calendar/v3/calendars/primary/events?', 200, ['kind' => 'calendar#events', 'items' => [
            ['id' => 'evtcancelado1', 'status' => 'cancelled'],
            ['id' => 'evtlixo1', 'etag' => '"e2"', 'status' => 'confirmed', 'start' => ['dateTime' => 'lixo'], 'end' => ['dateTime' => 'lixo']],
            ['id' => 'evtsemhorario1', 'etag' => '"e3"', 'status' => 'confirmed'],
            ['etag' => '"e4"', 'status' => 'confirmed', 'start' => ['date' => $this->data(1)]],
            'nao e um evento',
            $valido,
        ]], 'GET');

        $janela = $this->abrir();

        $this->assertSame(['evtvalido1'], self::googleIds($janela['foreign']));
    }

    public function testOcorrenciaDeSerieMarcaRecurring(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtserie1_20300115T130000Z', ['recurringEventId' => 'evtserie1']);
        $this->evento(self::ANA, 'evtunico1');

        $janela = $this->abrir();

        $porId = array_column($janela['foreign'], 'recurring', 'google_id');
        $this->assertSame(['evtserie1_20300115T130000Z' => true, 'evtunico1' => false], $porId);
    }

    public function testNuncaLeAgendaDeOutro(): void
    {
        // a bia tem envio pendente, entao o drain termina com o token dela na mao
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $this->tarefa(['assigned_to' => self::BIA, 'google_sync_pending' => 1]);
        $this->evento(self::ANA, 'evtdaana1');
        $this->evento(self::BIA, 'evtdabia1');

        $janela = $this->abrir(isAdmin: true, team: true);

        $this->assertSame(['evtdaana1'], self::googleIds($janela['foreign']));
        $leituras = array_filter($this->http->requests(), fn (array $pedido): bool => $pedido['method'] === 'GET');
        $this->assertNotEmpty($leituras);
        foreach ($leituras as $pedido) {
            $this->assertContains('Authorization: Bearer ' . $this->token(self::ANA), $pedido['headers']);
        }
    }

    public function testEquipeExigeAdmin(): void
    {
        $this->conta(self::ANA);
        $daAna = $this->tarefa();
        $this->tarefa(['assigned_to' => self::BIA]);

        $janela = $this->abrir(isAdmin: false, team: true);

        $this->assertSame([$daAna], self::ids($janela['tasks']));
        $this->assertSame([], $janela['team']);
    }

    public function testEquipeVemDoCrm(): void
    {
        $this->conta(self::ANA);
        $daAna = $this->tarefa();
        $daBia = $this->tarefa(['assigned_to' => self::BIA, 'due_date' => $this->quando(2, '14:00')]);
        $this->tarefa(['tenant_id' => 2, 'assigned_to' => self::DUDA]);

        $janela = $this->abrir(isAdmin: true, team: true);

        $this->assertSame([$daAna], self::ids($janela['tasks']));
        $this->assertSame([$daBia], self::ids($janela['team']));
        $this->assertSame('Bia', $janela['team'][0]['assigned_name']);
    }

    public function testSemBotaoDaEquipeAdminVeSoAsProprias(): void
    {
        $this->conta(self::ANA);
        $daAna = $this->tarefa();
        $this->tarefa(['assigned_to' => self::BIA]);

        $janela = $this->abrir(isAdmin: true, team: false);

        $this->assertSame([$daAna], self::ids($janela['tasks']));
        $this->assertSame([], $janela['team']);
    }

    public function testTarefaMovidaNoCrmApareceNaDataDoCrm(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtmovido1', ['due_date' => $this->quando(3, '14:00'), 'google_sync_pending' => 1]);
        $this->travarEnvio(self::ANA);

        $janela = $this->abrir();

        $this->assertCount(1, $this->pedidosDaLista());
        $this->assertSame([], $this->escritas());
        $this->assertSame($this->quando(3, '14:00'), $janela['tasks'][0]['due_date']);
        $this->assertSame([$id], self::ids($janela['tasks']));
        $this->assertSame([], $janela['foreign']);
    }

    public function testMovidoNoGoogleAtualizaTarefa(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtmovido1', ['title' => 'Ligar']);
        $this->evento(self::ANA, 'evtmovido1', [
            'etag'    => '"etag-2"',
            'summary' => 'Ligar para a Ana',
            'start'   => ['dateTime' => $this->rfc(2, '15:00'), 'timeZone' => self::SP],
            'end'     => ['dateTime' => $this->rfc(2, '16:15'), 'timeZone' => self::SP],
        ]);

        $janela = $this->abrir();

        $this->assertSame('ok', $janela['status']);
        $linha = $this->linha($id);
        $this->assertSame($this->quando(2, '15:00'), $linha['due_date']);
        $this->assertSame('Ligar para a Ana', $linha['title']);
        $this->assertSame(75, (int) $linha['google_duration_min']);
        $this->assertSame('"etag-2"', $linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame($this->quando(2, '15:00'), $janela['tasks'][0]['due_date']);
        $this->assertSame([], $this->escritas());
    }

    public function testConverteOffsetParaSaoPaulo(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtfuso1');
        $this->evento(self::ANA, 'evtfuso1', [
            'etag'  => '"etag-2"',
            'start' => ['dateTime' => $this->rfc(2, '10:00', '-05:00'), 'timeZone' => 'America/Bogota'],
            'end'   => ['dateTime' => $this->rfc(2, '10:30', '-05:00'), 'timeZone' => 'America/Bogota'],
        ]);

        $this->abrir();

        $this->assertSame($this->quando(2, '12:00'), $this->linha($id)['due_date']);
    }

    /** @return array<string, array{mixed}> */
    public static function titulosVazios(): array
    {
        return ['vazio' => [''], 'so espacos' => ['   '], 'ausente' => [null], 'so a marca' => ['✓ ']];
    }

    #[DataProvider('titulosVazios')]
    public function testTituloVazioNaoAplica(mixed $titulo): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evttitulo1', ['title' => 'Título do CRM']);
        $this->evento(self::ANA, 'evttitulo1', [
            'etag'    => '"etag-2"',
            'summary' => $titulo,
            'start'   => ['dateTime' => $this->rfc(2, '10:00')],
            'end'     => ['dateTime' => $this->rfc(2, '10:30')],
        ]);

        $this->abrir();

        $linha = $this->linha($id);
        $this->assertSame('Título do CRM', $linha['title']);
        $this->assertSame($this->quando(2, '10:00'), $linha['due_date']);
        $this->assertSame('"etag-2"', $linha['google_etag']);
    }

    public function testTituloLongoCortaEm200Caracteres(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtlongo1');
        $this->evento(self::ANA, 'evtlongo1', ['etag' => '"etag-2"', 'summary' => str_repeat('á', 199) . 'éxyz']);

        $janela = $this->abrir();

        $this->assertSame('ok', $janela['status']);
        $this->assertSame(str_repeat('á', 199) . 'é', $this->linha($id)['title']);
    }

    public function testMarcaDeConcluidaNaoEntraNoTitulo(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtconcluida1', ['status' => 'done', 'title' => 'Enviar proposta']);
        $this->evento(self::ANA, 'evtconcluida1', ['etag' => '"etag-2"', 'summary' => '✓ Enviar proposta revisada']);

        $this->abrir();

        $linha = $this->linha($id);
        $this->assertSame('Enviar proposta revisada', $linha['title']);
        $this->assertSame('done', $linha['status']);
    }

    public function testDiaInteiroMantemHorarioDaTarefa(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtdiainteiro1', ['due_date' => $this->quando(1, '14:30'), 'google_duration_min' => 45]);
        $this->evento(self::ANA, 'evtdiainteiro1', ['etag' => '"etag-2"', 'start' => ['date' => $this->data(3)], 'end' => ['date' => $this->data(4)]]);

        $this->abrir();

        $linha = $this->linha($id);
        $this->assertSame($this->quando(3, '14:30'), $linha['due_date']);
        $this->assertSame(45, (int) $linha['google_duration_min']);
        $this->assertSame('"etag-2"', $linha['google_etag']);
    }

    public function testPendenciaGanhaDoGoogle(): void
    {
        $this->conta(self::ANA);
        $mudada = $this->vinculada('evtmudado1', ['title' => 'Título do CRM', 'google_sync_pending' => 1]);
        $this->evento(self::ANA, 'evtmudado1', [
            'etag' => '"etag-2"', 'summary' => 'Título do Google',
            'start' => ['dateTime' => $this->rfc(4, '10:00')], 'end' => ['dateTime' => $this->rfc(4, '10:30')],
        ]);
        $apagada = $this->vinculada('evtapagado1', ['due_date' => $this->quando(2, '10:00'), 'google_sync_pending' => 2], ['status' => 'cancelled']);
        $this->travarEnvio(self::ANA);

        $janela = $this->abrir();

        $this->assertCount(1, $this->pedidosDaLista());
        $linha = $this->linha($mudada);
        $this->assertSame('Título do CRM', $linha['title']);
        $this->assertSame($this->quando(1, '10:00'), $linha['due_date']);
        $this->assertSame('"etag-1"', $linha['google_etag']);
        $this->assertSame([], $this->getsDeEvento());
        $apagadaDepois = $this->linha($apagada);
        $this->assertSame('pending', $apagadaDepois['status']);
        $this->assertSame('evtapagado1', $apagadaDepois['google_event_id']);
        $this->assertSame([$mudada, $apagada], self::ids($janela['tasks']));
    }

    public function testListaVelhaNaoDesfazEdicao(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtcorrida1', ['title' => 'Título do CRM']);
        $this->evento(self::ANA, 'evtcorrida1', [
            'etag' => '"etag-lista"', 'summary' => 'Título da lista',
            'start' => ['dateTime' => $this->rfc(3, '10:00')], 'end' => ['dateTime' => $this->rfc(3, '10:30')],
        ]);
        $pdo = $this->pdo;
        // um envio de outra aba grava um etag novo depois que o mapa foi lido
        $tarefas = new class ($pdo, $id) extends Task {
            public function __construct(private \PDO $conexao, private int $alvo)
            {
                parent::__construct();
            }

            public function linkMap(int $userId): array
            {
                $mapa = parent::linkMap($userId);
                $this->conexao->prepare('UPDATE tasks SET google_etag = :etag WHERE id = :id')
                    ->execute([':etag' => '"etag-do-envio"', ':id' => $this->alvo]);
                return $mapa;
            }
        };

        $this->abrir(tasks: $tarefas);

        $linha = $this->linha($id);
        $this->assertSame('Título do CRM', $linha['title']);
        $this->assertSame($this->quando(1, '10:00'), $linha['due_date']);
        $this->assertSame('"etag-do-envio"', $linha['google_etag']);
    }

    public function testColisaoNaUniqueNaoDerrubaOFeed(): void
    {
        $this->conta(self::ANA);
        $mae = $this->tarefa(['due_date' => $this->quando(-30, '10:00')]);
        $filha = $this->vinculada('evtfilha1', ['recurrence_parent_id' => $mae, 'title' => 'Visita']);
        $irma = $this->vinculada('evtirma1', ['recurrence_parent_id' => $mae, 'due_date' => $this->quando(2, '10:00')], [
            'start' => ['dateTime' => $this->rfc(2, '10:00')], 'end' => ['dateTime' => $this->rfc(2, '10:30')],
        ]);
        // a filha foi movida no google para o horario da irma
        $this->evento(self::ANA, 'evtfilha1', [
            'etag' => '"etag-2"', 'summary' => 'Visita remarcada',
            'start' => ['dateTime' => $this->rfc(2, '10:00')], 'end' => ['dateTime' => $this->rfc(2, '11:00')],
        ]);

        $janela = $this->abrir();

        $this->assertSame('degraded', $janela['status']);
        $linha = $this->linha($filha);
        $this->assertSame($this->quando(1, '10:00'), $linha['due_date']);
        $this->assertSame('"etag-2"', $linha['google_etag']);
        $this->assertSame('Visita remarcada', $linha['title']);
        $this->assertSame(60, (int) $linha['google_duration_min']);
        $this->assertSame($this->quando(2, '10:00'), $this->linha($irma)['due_date']);
        $this->assertSame([$filha, $irma], self::ids($janela['tasks']));
        $this->assertStringContainsString('"task_id":' . $filha, $this->logs());
    }

    /** @return array<string, array{bool}> */
    public static function apagadoDeVerdade(): array
    {
        return ['404' => [false], 'cancelled com summary e start' => [true]];
    }

    #[DataProvider('apagadoDeVerdade')]
    public function testApagadoNoGoogleCancelaEDesvincula(bool $comDetalhes): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa([
            'google_event_id' => 'evtapagado1', 'google_owner_id' => self::ANA, 'google_etag' => '"etag-1"',
        ]);
        if ($comDetalhes) {
            $this->evento(self::ANA, 'evtapagado1', ['etag' => '"etag-2"', 'status' => 'cancelled']);
        }

        $janela = $this->abrir();

        $this->assertSame(['evtapagado1'], $this->getsDeEvento());
        $linha = $this->linha($id);
        $this->assertSame('cancelled', $linha['status']);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertNull($linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame([], $janela['tasks']);
        $this->assertSame('ok', $janela['status']);
    }

    public function testMovidoDeAgendaNaoCancela(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa([
            'status' => 'in_progress', 'google_event_id' => 'evtmudoudeagenda1', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-1"',
        ]);
        // so o id e o status sao garantidos no evento que saiu da agenda
        $this->http->putEvent($this->token(self::ANA), ['id' => 'evtmudoudeagenda1', 'status' => 'cancelled']);

        $janela = $this->abrir();

        $this->assertSame(['evtmudoudeagenda1'], $this->getsDeEvento());
        $linha = $this->linha($id);
        $this->assertSame('in_progress', $linha['status']);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertSame([$id], self::ids($janela['tasks']));
    }

    public function testGetEmOutraDataAplica(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtlonge1');
        $this->evento(self::ANA, 'evtlonge1', [
            'etag' => '"etag-2"',
            'start' => ['dateTime' => $this->rfc(20, '09:00')], 'end' => ['dateTime' => $this->rfc(20, '09:30')],
        ]);

        $janela = $this->abrir();

        $this->assertSame(['evtlonge1'], $this->getsDeEvento());
        $linha = $this->linha($id);
        $this->assertSame($this->quando(20, '09:00'), $linha['due_date']);
        $this->assertSame('pending', $linha['status']);
        $this->assertSame('"etag-2"', $linha['google_etag']);
        $this->assertSame([], $janela['tasks']);
    }

    public function testMoldeApagadoSoDesvincula(): void
    {
        $this->conta(self::ANA);
        $molde = $this->vinculada('evtmolde1', ['recurrence_type' => 'monthly'], ['status' => 'cancelled']);
        $this->travarEnvio(self::ANA);

        $this->abrir();

        $linha = $this->linha($molde);
        $this->assertSame('pending', $linha['status']);
        $this->assertSame('monthly', $linha['recurrence_type']);
        $this->assertNull($linha['google_event_id']);
        // o gerador para quando o molde e cancelado, e o molde continua aberto
        $filhas = (int) $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE recurrence_parent_id = {$molde}")->fetchColumn();
        $this->assertGreaterThan(0, $filhas);
    }

    public function testConcluidaApagadaSoDesvincula(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtconcluida1', ['status' => 'done'], ['status' => 'cancelled']);

        $this->abrir();

        $linha = $this->linha($id);
        $this->assertSame('done', $linha['status']);
        $this->assertNull($linha['google_event_id']);
    }

    public function testFilhaApagadaECancelada(): void
    {
        $this->conta(self::ANA);
        $mae = $this->tarefa(['due_date' => $this->quando(-30, '10:00')]);
        $filha = $this->vinculada('evtfilha1', ['recurrence_parent_id' => $mae], ['status' => 'cancelled']);

        $this->abrir();

        $this->assertSame('cancelled', $this->linha($filha)['status']);
    }

    public function testLimiteDeGetsFicaParaProxima(): void
    {
        $this->conta(self::ANA);
        $ids = [];
        for ($i = 0; $i < 11; $i++) {
            // nenhuma existe no google, entao cada get volta 404; a ordem de criacao e a inversa do prazo
            $ids[] = $this->tarefa([
                'due_date'        => $this->quando(5 - intdiv($i, 3), sprintf('%02d:00', 20 - $i)),
                'google_event_id' => "evtsumido{$i}",
                'google_owner_id' => self::ANA,
                'google_etag'     => '"etag-1"',
            ]);
        }

        $janela = $this->abrir();

        $this->assertCount(10, $this->getsDeEvento());
        $this->assertNotContains('evtsumido0', $this->getsDeEvento());
        $this->assertSame('evtsumido0', $this->linha($ids[0])['google_event_id']);
        $this->assertSame('pending', $this->linha($ids[0])['status']);
        foreach (array_slice($ids, 1) as $id) {
            $this->assertSame('cancelled', $this->linha($id)['status']);
        }
        $this->assertSame('ok', $janela['status']);
    }

    public function testOrcamentoDosGetsFicaParaProxima(): void
    {
        $this->conta(self::ANA);
        $primeira = $this->vinculada('evtsumido1', [], ['status' => 'cancelled']);
        $segunda = $this->vinculada('evtsumido2', ['due_date' => $this->quando(2, '10:00')], ['status' => 'cancelled']);
        $this->http->advanceMsPerCall(1800);

        $janela = $this->abrir();

        // o primeiro get sai com os 2000 ms do orcamento proprio, leva 1800, e os 200 que sobram nao bastam para o segundo
        $this->assertSame([2000], array_column(array_values(array_filter(
            $this->http->requests(),
            fn (array $p): bool => str_starts_with($p['url'], self::EVENTS_URL . '/'),
        )), 'timeoutMs'));
        $this->assertSame('cancelled', $this->linha($primeira)['status']);
        $this->assertSame('pending', $this->linha($segunda)['status']);
        $this->assertSame('ok', $janela['status']);
    }

    public function testGoogleForaDoArNoGetDegrada(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtsumido1', [], ['status' => 'cancelled']);
        $this->http->respondOnce('evtsumido1', 503, 'Service Unavailable', 'GET');

        $janela = $this->abrir();

        $this->assertSame('degraded', $janela['status']);
        $this->assertSame('pending', $this->linha($id)['status']);
        $this->assertSame('evtsumido1', $this->linha($id)['google_event_id']);
    }

    public function testExcecaoInesperadaDevolveTarefas(): void
    {
        $this->conta(self::ANA);
        $id = $this->vinculada('evtvinculado1');
        $tarefas = new class extends Task {
            public function linkMap(int $userId): array
            {
                throw new \RuntimeException('falha de banco falsa');
            }
        };

        $janela = $this->abrir(tasks: $tarefas);

        $this->assertSame('degraded', $janela['status']);
        $this->assertSame([$id], self::ids($janela['tasks']));
        $this->assertStringContainsString('falha de banco falsa', $this->logs());
    }

    public function testSemConfiguracaoSoLeOBanco(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa(['google_sync_pending' => 1]);
        $this->config = new GoogleConfig('', '', $this->chave, 'http://localhost:8000/google/callback');

        $janela = $this->abrir();

        $this->assertSame('ok', $janela['status']);
        $this->assertSame([$id], self::ids($janela['tasks']));
        $this->assertSame([], $this->http->requests());
    }

    /** @return array<string, array{array<string, mixed>, bool}> */
    public static function eventosParaCanEdit(): array
    {
        $proprio = ['id' => 'evt1', 'organizer' => ['email' => 'pessoa@example.com', 'self' => true], 'eventType' => 'default'];

        return [
            'organizador comum'      => [$proprio, true],
            'locked false'           => [$proprio + ['locked' => false], true],
            'de outro organizador'   => [['organizer' => ['email' => 'outra@example.com']] + $proprio, false],
            'self false'             => [['organizer' => ['email' => 'outra@example.com', 'self' => false]] + $proprio, false],
            'sem organizador'        => [['id' => 'evt1', 'eventType' => 'default'], false],
            'self como texto'        => [['organizer' => ['self' => 'true']] + $proprio, false],
            'locked'                 => [$proprio + ['locked' => true], false],
            'aniversario'            => [['eventType' => 'birthday'] + $proprio, false],
            'fora do escritorio'     => [['eventType' => 'outOfOffice'] + $proprio, false],
            'foco'                   => [['eventType' => 'focusTime'] + $proprio, false],
            'do gmail'               => [['eventType' => 'fromGmail'] + $proprio, false],
            'local de trabalho'      => [['eventType' => 'workingLocation'] + $proprio, false],
            'sem eventType'          => [['id' => 'evt1', 'organizer' => ['self' => true]], false],
        ];
    }

    /** @param array<string, mixed> $evento */
    #[DataProvider('eventosParaCanEdit')]
    public function testCanEditExigeOrganizadorTipoENaoLocked(array $evento, bool $esperado): void
    {
        $this->assertSame($esperado, GoogleCalendarSync::canEdit($evento));
    }

    public function testForeignLevaCanEdit(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtproprio1');
        $this->evento(self::ANA, 'evtconvite1', ['organizer' => ['email' => 'outra@example.com']]);

        $janela = $this->abrir();

        $this->assertSame(['evtproprio1' => true, 'evtconvite1' => false], array_column($janela['foreign'], 'can_edit', 'google_id'));
    }

    public function testPatchLevaSoCamposAlterados(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtproprio1', ['summary' => 'Dentista', 'description' => 'Levar exames']);

        $titulo = $this->sync()->updateForeignEvent(self::ANA, 'evtproprio1', ['title' => 'Dentista às 15h']);
        $horario = $this->sync()->updateForeignEvent(self::ANA, 'evtproprio1', [
            'start' => $this->data(2) . 'T15:00', 'end' => $this->data(2) . 'T16:30',
        ]);

        $this->assertSame(['ok' => true], $titulo);
        $this->assertSame(['ok' => true], $horario);
        $patches = array_values(array_filter($this->http->requests(), fn (array $p): bool => $p['method'] === 'PATCH'));
        $this->assertCount(2, $patches);
        $this->assertSame(self::EVENTS_URL . '/evtproprio1', $patches[0]['url']);
        $this->assertSame(['summary' => 'Dentista às 15h'], json_decode((string) $patches[0]['body'], true));
        $this->assertSame([
            'start' => ['date' => null, 'dateTime' => $this->rfc(2, '15:00'), 'timeZone' => self::SP],
            'end'   => ['date' => null, 'dateTime' => $this->rfc(2, '16:30'), 'timeZone' => self::SP],
        ], json_decode((string) $patches[1]['body'], true));

        $evento = $this->http->events($this->token(self::ANA))['evtproprio1'];
        $this->assertSame('Dentista às 15h', $evento['summary']);
        $this->assertSame('Levar exames', $evento['description']);
        $this->assertSame($this->rfc(2, '15:00'), $evento['start']['dateTime']);
    }

    public function testDescricaoEAlteradaSozinha(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtproprio1', ['summary' => 'Dentista']);

        $this->assertSame(['ok' => true], $this->sync()->updateForeignEvent(self::ANA, 'evtproprio1', ['description' => '']));

        $patch = array_values(array_filter($this->http->requests(), fn (array $p): bool => $p['method'] === 'PATCH'))[0];
        $this->assertSame(['description' => ''], json_decode((string) $patch['body'], true));
    }

    public function testDiaInteiroParaHorarioMandaDateNull(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtdiainteiro1', ['start' => ['date' => $this->data(1)], 'end' => ['date' => $this->data(2)]]);

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtdiainteiro1', [
            'all_day' => false, 'start' => $this->data(1) . 'T09:00', 'end' => $this->data(1) . 'T10:00',
        ]);

        $this->assertSame(['ok' => true], $resultado);
        $evento = $this->http->events($this->token(self::ANA))['evtdiainteiro1'];
        $this->assertArrayNotHasKey('date', $evento['start']);
        $this->assertSame($this->rfc(1, '09:00'), $evento['start']['dateTime']);
        $this->assertSame($this->rfc(1, '10:00'), $evento['end']['dateTime']);
    }

    public function testHorarioParaDiaInteiroMandaDateTimeNull(): void
    {
        // nao medido no google: o fake trata null como apagar o campo, como no patch que a tarefa 1 mediu com date
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtcomhorario1');

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtcomhorario1', [
            'all_day' => true, 'start' => $this->data(1), 'end' => $this->data(3),
        ]);

        $this->assertSame(['ok' => true], $resultado);
        $patch = array_values(array_filter($this->http->requests(), fn (array $p): bool => $p['method'] === 'PATCH'))[0];
        $this->assertSame([
            'start' => ['date' => $this->data(1), 'dateTime' => null],
            'end'   => ['date' => $this->data(3), 'dateTime' => null],
        ], json_decode((string) $patch['body'], true));
        $evento = $this->http->events($this->token(self::ANA))['evtcomhorario1'];
        $this->assertSame($this->data(1), $evento['start']['date']);
        $this->assertArrayNotHasKey('dateTime', $evento['start']);
    }

    public function testSoInicioDeDiaInteiroDeduzPeloFormato(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtdiainteiro1', ['start' => ['date' => $this->data(1)], 'end' => ['date' => $this->data(4)]]);

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtdiainteiro1', ['start' => $this->data(2)]);

        $this->assertSame(['ok' => true], $resultado);
        $patch = array_values(array_filter($this->http->requests(), fn (array $p): bool => $p['method'] === 'PATCH'))[0];
        $this->assertSame(['start' => ['date' => $this->data(2)]], json_decode((string) $patch['body'], true));
        $evento = $this->http->events($this->token(self::ANA))['evtdiainteiro1'];
        $this->assertSame(['date' => $this->data(2)], $evento['start']);
        $this->assertSame(['date' => $this->data(4)], $evento['end']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function mudancasQueMisturamTipos(): array
    {
        return [
            'so o inicio em data num evento com horario' => [['start' => 'DATA1']],
            'dia inteiro so com o inicio'                => [['all_day' => true, 'start' => 'DATA1']],
            'dia inteiro sem datas'                      => [['all_day' => true]],
        ];
    }

    /** @param array<string, mixed> $campos */
    #[DataProvider('mudancasQueMisturamTipos')]
    public function testMisturaDeTiposRecusada(array $campos): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtcomhorario1');
        $campos = array_map(fn (mixed $v): mixed => $v === 'DATA1' ? $this->data(1) : $v, $campos);

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtcomhorario1', $campos);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('dia inteiro', $resultado['error'] ?? '');
        $this->assertSame([], $this->escritas());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function camposInvalidos(): array
    {
        return [
            'dia que nao existe'          => [['start' => '2030-02-30T10:00', 'end' => '2030-02-30T11:00'], 'inválid'],
            'formato brasileiro'          => [['start' => '15/01/2030'], 'inválid'],
            'com segundos'                => [['start' => '2030-01-15T10:00:00'], 'inválid'],
            'lixo'                        => [['end' => 'amanha'], 'inválid'],
            'dia inteiro com horario'     => [['all_day' => true, 'start' => '2030-01-15T10:00', 'end' => '2030-01-15T11:00'], 'inválid'],
            'horario com data'            => [['all_day' => false, 'start' => '2030-01-15', 'end' => '2030-01-16'], 'inválid'],
            'fim antes do inicio'         => [['start' => '2030-01-15T11:00', 'end' => '2030-01-15T10:00'], 'fim'],
            'fim de dia inteiro no inicio' => [['start' => '2030-01-15', 'end' => '2030-01-15'], 'fim'],
            'nada para mudar'             => [[], 'alteração'],
        ];
    }

    /** @param array<string, mixed> $campos */
    #[DataProvider('camposInvalidos')]
    public function testCamposInvalidosRecusadosSemChamada(array $campos, string $motivo): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtproprio1');

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtproprio1', $campos);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString($motivo, $resultado['error'] ?? '');
        $this->assertSame([], $this->http->requests());
    }

    public function testFimAntesDoInicioComOFimDoGoogleRecusado(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtproprio1');

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtproprio1', ['start' => $this->data(1) . 'T11:00']);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('fim', $resultado['error'] ?? '');
        $this->assertSame([], $this->escritas());
    }

    public function testRecusaEditarEventoDeTarefa(): void
    {
        $this->conta(self::ANA);
        $this->vinculada('evtdatarefa1', [], ['summary' => 'Ligar para cliente']);

        $alterar = $this->sync()->updateForeignEvent(self::ANA, 'evtdatarefa1', ['title' => 'Outro título']);
        $excluir = $this->sync()->deleteForeignEvent(self::ANA, 'evtdatarefa1');

        $this->assertFalse($alterar['ok']);
        $this->assertStringContainsString('tarefa', $alterar['error'] ?? '');
        $this->assertFalse($excluir['ok']);
        $this->assertStringContainsString('tarefa', $excluir['error'] ?? '');
        $this->assertSame([], $this->escritas());
        $evento = $this->http->events($this->token(self::ANA))['evtdatarefa1'];
        $this->assertSame('Ligar para cliente', $evento['summary']);
        $this->assertSame('confirmed', $evento['status']);
    }

    public function testRecusaEditarSemCanEdit(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtconvite1', ['summary' => 'Reunião da outra', 'organizer' => ['email' => 'outra@example.com']]);

        $alterar = $this->sync()->updateForeignEvent(self::ANA, 'evtconvite1', ['title' => 'Mudei']);
        $excluir = $this->sync()->deleteForeignEvent(self::ANA, 'evtconvite1');

        $this->assertFalse($alterar['ok']);
        $this->assertStringContainsString('não pode', $alterar['error'] ?? '');
        $this->assertFalse($excluir['ok']);
        $this->assertStringContainsString('não pode', $excluir['error'] ?? '');
        $this->assertSame(['GET ' . self::EVENTS_URL . '/evtconvite1', 'GET ' . self::EVENTS_URL . '/evtconvite1'], $this->chamadas());
        $this->assertSame('Reunião da outra', $this->http->events($this->token(self::ANA))['evtconvite1']['summary']);
    }

    /** @return array<string, array{string}> */
    public static function idsInvalidos(): array
    {
        return [
            'vazio'          => [''],
            'barra'          => ['evt/1'],
            'subindo pasta'  => ['../evt1'],
            'espaco'         => ['evt 1'],
            'interrogacao'   => ['evt1?x=1'],
            'longo demais'   => [str_repeat('a', 1025)],
            'acento'         => ['evtá1'],
            'quebra no fim'  => ["evt1\n"],
        ];
    }

    #[DataProvider('idsInvalidos')]
    public function testIdInvalidoRecusado(string $id): void
    {
        $this->conta(self::ANA);

        $alterar = $this->sync()->updateForeignEvent(self::ANA, $id, ['title' => 'Mudei']);
        $excluir = $this->sync()->deleteForeignEvent(self::ANA, $id);

        $this->assertFalse($alterar['ok']);
        $this->assertFalse($excluir['ok']);
        $this->assertSame([], $this->http->requests());
    }

    public function testIdNoLimiteEAceito(): void
    {
        $this->conta(self::ANA);
        $id = 'evt_' . str_repeat('a', 1016) . '-_A9';
        $this->evento(self::ANA, $id);

        $this->assertSame(['ok' => true], $this->sync()->updateForeignEvent(self::ANA, $id, ['title' => 'Mudei']));
        $this->assertSame('Mudei', $this->http->events($this->token(self::ANA))[$id]['summary']);
    }

    public function testExcluiEventoProprio(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtproprio1');

        $resultado = $this->sync()->deleteForeignEvent(self::ANA, 'evtproprio1');

        $this->assertSame(['ok' => true], $resultado);
        $this->assertSame(['GET ' . self::EVENTS_URL . '/evtproprio1', 'DELETE ' . self::EVENTS_URL . '/evtproprio1'], $this->chamadas());
        $this->assertSame('cancelled', $this->http->events($this->token(self::ANA))['evtproprio1']['status']);
    }

    public function testExcluirEventoJaApagadoDevolveOk(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtapagado1', ['status' => 'cancelled']);

        $this->assertSame(['ok' => true], $this->sync()->deleteForeignEvent(self::ANA, 'evtapagado1'));
        $this->assertSame(['ok' => true], $this->sync()->deleteForeignEvent(self::ANA, 'evtnuncaexistiu1'));
        $this->assertSame([
            'GET ' . self::EVENTS_URL . '/evtapagado1',
            'GET ' . self::EVENTS_URL . '/evtnuncaexistiu1',
        ], $this->chamadas());
    }

    public function testAlterarEventoApagadoAvisa(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtapagado1', ['status' => 'cancelled']);

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtapagado1', ['title' => 'Mudei']);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('não existe', $resultado['error'] ?? '');
        $this->assertSame([], $this->escritas());
    }

    public function testOcorrenciaDeSerieAlteraSoElaPeloIdDaOcorrencia(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtserie1_20300115T130000Z', ['recurringEventId' => 'evtserie1']);

        $this->assertSame(['ok' => true], $this->sync()->updateForeignEvent(self::ANA, 'evtserie1_20300115T130000Z', ['title' => 'Só esta']));

        $this->assertSame('PATCH ' . self::EVENTS_URL . '/evtserie1_20300115T130000Z', $this->chamadas()[1]);
    }

    public function testEventoDeOutraContaNaoEAlcancado(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $this->evento(self::BIA, 'evtdabia1');

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtdabia1', ['title' => 'Mudei']);

        $this->assertFalse($resultado['ok']);
        foreach ($this->http->requests() as $pedido) {
            $this->assertContains('Authorization: Bearer ' . $this->token(self::ANA), $pedido['headers']);
        }
        $this->assertSame('Evento do Google', $this->http->events($this->token(self::BIA))['evtdabia1']['summary']);
    }

    public function testGoogleForaDoArNaAlteracaoDevolveErro(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtproprio1');
        $this->http->networkDown(true);

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtproprio1', ['title' => 'Mudei']);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('não respondeu', $resultado['error'] ?? '');
        $this->assertStringNotContainsString('acesso-falso', $resultado['error'] ?? '');
    }

    public function testAlteracaoComContaRevogadaPedeReconexao(): void
    {
        $this->conta(self::ANA, ['status' => 'revoked']);
        $this->evento(self::ANA, 'evtproprio1');

        $resultado = $this->sync()->updateForeignEvent(self::ANA, 'evtproprio1', ['title' => 'Mudei']);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('Reconecte', $resultado['error'] ?? '');
        $this->assertSame([], $this->http->requests());
    }
}
