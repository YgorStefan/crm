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

final class GoogleDrainTest extends TestCase
{
    private const TENANT  = 1;
    private const ANA     = 1; // tenant 1, admin
    private const BIA     = 2; // tenant 1, seller
    private const CAIO    = 3; // tenant 1, desativado
    private const DUDA    = 4; // tenant 2
    private const CLIENTE = 10;

    private const EVENTS_URL = FakeTransport::EVENTS_URL;
    private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';

    private ?\PDO $pdo = null;
    private string $chave = '';
    private GoogleConfig $config;
    private FakeTransport $http;
    private float $t0 = 0.0;
    private string $logDir = '';

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $this->chave = 'base64:' . base64_encode(str_repeat('k', 32));
        $this->config = new GoogleConfig(
            'cliente-falso.apps.googleusercontent.com', 'segredo-falso', $this->chave, 'http://localhost:8000/google/callback'
        );
        $this->http = new FakeTransport();
        $this->t0 = ($this->http->now())();
        $this->logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-drain-log-' . bin2hex(random_bytes(6));
        $_SESSION['tenant_id'] = self::TENANT;

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

    private function sync(?Task $tasks = null, ?GoogleConfig $config = null): GoogleCalendarSync
    {
        $config ??= $this->config;
        $auth = new GoogleAuth($config, $this->http, now: $this->http->now());

        return new GoogleCalendarSync(
            $config,
            $this->http,
            $auth,
            tasks: $tasks ?? new Task(),
            now: $this->http->now(),
            logger: new Logger($this->logDir),
        );
    }

    private function token(int $userId): string
    {
        return "acesso-falso-{$userId}";
    }

    /** horario relativo ao relogio do transporte falso, no formato da coluna */
    private function aposT0(int $segundos): string
    {
        return date('Y-m-d H:i:s', (int) floor($this->t0) + $segundos);
    }

    private function em(string $quando): string
    {
        return (new \DateTime($quando))->format('Y-m-d H:i:s');
    }

    /** o token renovado continua na agenda da mesma conta, como no google */
    private function renovacaoDa(int $userId): void
    {
        $this->http->tokenOf("conta-{$userId}", 'acesso-renovado-falso');
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaRenovacao());
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
            'access_token_expires_at' => $this->aposT0(3600),
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
            'due_date'            => $this->em('+1 day'),
            'priority'            => 'medium',
            'status'              => 'pending',
            'google_event_id'     => null,
            'google_owner_id'     => null,
            'google_etag'         => null,
            'google_duration_min' => null,
            'google_sync_pending' => 1,
        ], $campos));

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function linha(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    private function pendencia(int $id): int
    {
        return (int) $this->linha($id)['google_sync_pending'];
    }

    private function remocao(int $userId, string $eventId, int $tenantId = self::TENANT): void
    {
        $this->inserir('google_event_deletions', [
            'tenant_id'       => $tenantId,
            'user_id'         => $userId,
            'google_event_id' => $eventId,
        ]);
    }

    /** @return list<string> */
    private function remocoes(): array
    {
        return $this->pdo->query('SELECT google_event_id FROM google_event_deletions ORDER BY id')
            ->fetchAll(\PDO::FETCH_COLUMN);
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

    private function idDe(int $taskId): string
    {
        return $this->config->eventIdFor(self::TENANT, $taskId);
    }

    /** @return list<string> "METODO url" de cada chamada, na ordem */
    private function chamadas(): array
    {
        return array_map(
            fn (array $pedido): string => "{$pedido['method']} {$pedido['url']}",
            $this->http->requests(),
        );
    }

    /** @return array<string, mixed> */
    private function corpo(int $indice): array
    {
        $corpo = json_decode((string) $this->http->requests()[$indice]['body'], true);
        $this->assertIsArray($corpo);
        return $corpo;
    }

    /** @param array<string, mixed> $campos */
    private function evento(int $userId, string $id, array $campos = []): void
    {
        $inicio = (new \DateTimeImmutable('+1 day'))->setTime(10, 0);
        $this->http->putEvent($this->token($userId), array_merge([
            'id'      => $id,
            'summary' => 'Evento antigo',
            'start'   => ['dateTime' => $inicio->format(DATE_RFC3339), 'timeZone' => 'America/Sao_Paulo'],
            'end'     => ['dateTime' => $inicio->modify('+30 minutes')->format(DATE_RFC3339), 'timeZone' => 'America/Sao_Paulo'],
        ], $campos));
    }

    private function logs(): string
    {
        $texto = '';
        foreach (glob($this->logDir . DIRECTORY_SEPARATOR . '*') ?: [] as $arquivo) {
            $texto .= (string) file_get_contents($arquivo);
        }
        return $texto;
    }

    /** @return array<string, mixed> */
    private static function respostaDaRenovacao(): array
    {
        return ['access_token' => 'acesso-renovado-falso', 'expires_in' => 3599, 'token_type' => 'Bearer'];
    }

    /** @return array<string, array{int, string, string}> */
    public static function linhasDoClassify(): array
    {
        $erro = FakeTransport::googleError(...);
        $escopoSoEmDetails = (string) json_encode(['error' => [
            'code'    => 403,
            'message' => 'Request had insufficient authentication scopes.',
            'status'  => 'PERMISSION_DENIED',
            'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'ACCESS_TOKEN_SCOPE_INSUFFICIENT']],
        ]]);

        return [
            '200'                                => [200, '{}', 'ok'],
            '204 do delete'                      => [204, '', 'ok'],
            '404'                                => [404, $erro(404, 'notFound'), 'gone'],
            '410'                                => [410, $erro(410, 'deleted'), 'gone'],
            '409'                                => [409, $erro(409, 'duplicate'), 'conflict'],
            '401'                                => [401, $erro(401, 'authError'), 'reconnect'],
            '400'                                => [400, $erro(400, 'invalid'), 'drop'],
            'rede'                               => [0, '', 'stop'],
            '429'                                => [429, $erro(429, 'rateLimitExceeded'), 'stop'],
            '500'                                => [500, '', 'stop'],
            '503 sem json'                       => [503, 'Service Unavailable', 'stop'],
            '403 rateLimitExceeded'              => [403, $erro(403, 'rateLimitExceeded'), 'stop'],
            '403 userRateLimitExceeded'          => [403, $erro(403, 'userRateLimitExceeded'), 'stop'],
            '403 quotaExceeded'                  => [403, $erro(403, 'quotaExceeded'), 'stop'],
            '403 dailyLimitExceeded'             => [403, $erro(403, 'dailyLimitExceeded'), 'stop'],
            '403 insufficientPermissions'        => [403, $erro(403, 'insufficientPermissions'), 'reconnect'],
            '403 ACCESS_TOKEN_SCOPE_INSUFFICIENT' => [403, $erro(403, 'ACCESS_TOKEN_SCOPE_INSUFFICIENT'), 'reconnect'],
            '403 de escopo so em details'        => [403, $escopoSoEmDetails, 'reconnect'],
            '403 forbiddenForNonOrganizer'       => [403, $erro(403, 'forbiddenForNonOrganizer'), 'drop'],
            '403 forbidden'                      => [403, $erro(403, 'forbidden'), 'drop'],
            '403 requiredAccessLevel'            => [403, $erro(403, 'requiredAccessLevel'), 'drop'],
            '403 desconhecido'                   => [403, $erro(403, 'motivoNovoFalso'), 'stop'],
            '403 sem json'                       => [403, '<html>Forbidden</html>', 'stop'],
            '405 fora da tabela'                 => [405, $erro(405, 'httpMethodNotAllowed'), 'drop'],
            '412 fora da tabela'                 => [412, $erro(412, 'conditionNotMet'), 'drop'],
            '413 fora da tabela'                 => [413, $erro(413, 'requestTooLarge'), 'drop'],
            '422 fora da tabela'                 => [422, '', 'drop'],
            '408 passageiro'                     => [408, '', 'stop'],
            '429 sem json'                       => [429, '', 'stop'],
            '3xx fora da tabela'                 => [304, '', 'stop'],
        ];
    }

    #[DataProvider('linhasDoClassify')]
    public function testClassifyCobreCadaLinha(int $status, string $corpo, string $esperado): void
    {
        $this->assertSame($esperado, GoogleCalendarSync::classify($status, $corpo));
    }

    public function testAgendaFalsaSegueOMedido(): void
    {
        // os testes de envio confiam nesta agenda, entao ela e conferida contra a tabela medida na tarefa 1
        $cabecalhos = ['Authorization: Bearer ' . $this->token(self::ANA), 'Content-Type: application/json'];
        $comHorario = fn (string $id): string => (string) json_encode([
            'id'    => $id,
            'start' => ['date' => null, 'dateTime' => '2030-01-15T10:00:00-03:00', 'timeZone' => 'America/Sao_Paulo'],
            'end'   => ['date' => null, 'dateTime' => '2030-01-15T10:30:00-03:00', 'timeZone' => 'America/Sao_Paulo'],
        ]);
        $enviar = fn (string $metodo, string $url, ?string $corpo = null): array
            => $this->http->send($metodo, $url, $cabecalhos, $corpo, 1000);
        $motivo = fn (array $resposta): ?string => json_decode($resposta['body'], true)['error']['errors'][0]['reason'] ?? null;

        $this->assertSame(200, $enviar('POST', self::EVENTS_URL, $comHorario('evtsonda1'))['status']);
        $etagCriado = $this->http->events($this->token(self::ANA))['evtsonda1']['etag'];
        $this->assertSame(204, $enviar('DELETE', self::EVENTS_URL . '/evtsonda1')['status']);
        $this->assertSame('cancelled', json_decode($enviar('GET', self::EVENTS_URL . '/evtsonda1')['body'], true)['status']);

        $deNovo = $enviar('DELETE', self::EVENTS_URL . '/evtsonda1');
        $this->assertSame([410, 'deleted'], [$deNovo['status'], $motivo($deNovo)]);
        $nunca = $enviar('DELETE', self::EVENTS_URL . '/evtnuncaexistiu');
        $this->assertSame([404, 'notFound'], [$nunca['status'], $motivo($nunca)]);
        $repetido = $enviar('POST', self::EVENTS_URL, $comHorario('evtsonda1'));
        $this->assertSame([409, 'duplicate'], [$repetido['status'], $motivo($repetido)]);
        $this->assertSame(404, $enviar('PATCH', self::EVENTS_URL . '/evtnuncaexistiu', '{}')['status']);

        $restaurado = $enviar('PATCH', self::EVENTS_URL . '/evtsonda1', '{"status":"confirmed"}');
        $this->assertSame(200, $restaurado['status']);
        $this->assertSame('confirmed', $this->http->events($this->token(self::ANA))['evtsonda1']['status']);
        $this->assertNotSame($etagCriado, json_decode($restaurado['body'], true)['etag']);

        $this->assertSame(200, $enviar('POST', self::EVENTS_URL, (string) json_encode([
            'id' => 'evtsonda7', 'start' => ['date' => '2030-01-15'], 'end' => ['date' => '2030-01-16'],
        ]))['status']);
        $semDateNull = $enviar('PATCH', self::EVENTS_URL . '/evtsonda7', '{"start":{"dateTime":"2030-01-15T10:00:00-03:00"}}');
        $this->assertSame([400, 'invalid'], [$semDateNull['status'], $motivo($semDateNull)]);
        $comDateNull = $enviar('PATCH', self::EVENTS_URL . '/evtsonda7', (string) json_encode([
            'start' => ['date' => null, 'dateTime' => '2030-01-15T10:00:00-03:00', 'timeZone' => 'America/Sao_Paulo'],
            'end'   => ['date' => null, 'dateTime' => '2030-01-15T10:30:00-03:00', 'timeZone' => 'America/Sao_Paulo'],
        ]));
        $this->assertSame(200, $comDateNull['status']);
        $this->assertArrayNotHasKey('date', $this->http->events($this->token(self::ANA))['evtsonda7']['start']);

        // a lista pagina e deixa de fora os eventos apagados.
        $this->assertSame(204, $enviar('DELETE', self::EVENTS_URL . '/' . 'evtsonda1')['status']);
        $this->assertSame(200, $enviar('PATCH', self::EVENTS_URL . '/evtsonda1', '{"status":"confirmed"}')['status']);
        $this->assertSame(200, $enviar('POST', self::EVENTS_URL, $comHorario('evtsonda9'))['status']);
        $this->assertSame(204, $enviar('DELETE', self::EVENTS_URL . '/evtsonda9')['status']);
        $this->http->pageSize(1);
        $pagina1 = json_decode($enviar('GET', self::EVENTS_URL . '?singleEvents=true')['body'], true);
        $pagina2 = json_decode($enviar('GET', self::EVENTS_URL . '?singleEvents=true&pageToken=' . $pagina1['nextPageToken'])['body'], true);
        $this->assertSame(['evtsonda1'], array_column($pagina1['items'], 'id'));
        $this->assertSame(['evtsonda7'], array_column($pagina2['items'], 'id'));
        $this->assertArrayNotHasKey('nextPageToken', $pagina2);
    }

    public function testCriaEventoEGravaVinculo(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $eventId = $this->idDe($id);
        $this->assertSame(['POST ' . self::EVENTS_URL], $this->chamadas());
        $this->assertSame($eventId, $this->corpo(0)['id']);
        $this->assertSame(['useDefault' => true], $this->corpo(0)['reminders']);

        $eventos = $this->http->events($this->token(self::ANA));
        $this->assertSame([$eventId], array_keys($eventos));
        $linha = $this->linha($id);
        $this->assertSame($eventId, $linha['google_event_id']);
        $this->assertSame(self::ANA, (int) $linha['google_owner_id']);
        $this->assertSame($eventos[$eventId]['etag'], $linha['google_etag']);
        $this->assertSame(30, (int) $linha['google_duration_min']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertNull($this->contaDe(self::ANA)['drain_until']);
    }

    public function testEventoLevaFusoStatusEDuracaoPadrao(): void
    {
        $this->conta(self::ANA);
        $dia      = (new \DateTimeImmutable('+3 days'))->setTime(14, 0);
        $padrao   = $this->tarefa(['due_date' => $dia->format('Y-m-d H:i:s')]);
        $outroDia = $dia->modify('+1 day');
        $longa    = $this->tarefa(['due_date' => $outroDia->format('Y-m-d H:i:s'), 'google_duration_min' => 45]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $eventos = $this->http->events($this->token(self::ANA));
        $evento  = $eventos[$this->idDe($padrao)];
        $this->assertSame($dia->format('Y-m-d') . 'T14:00:00-03:00', $evento['start']['dateTime']);
        $this->assertSame('America/Sao_Paulo', $evento['start']['timeZone']);
        $this->assertSame($dia->format('Y-m-d') . 'T14:30:00-03:00', $evento['end']['dateTime']);
        $this->assertSame('America/Sao_Paulo', $evento['end']['timeZone']);
        $this->assertSame('confirmed', $evento['status']);

        $this->assertSame($outroDia->format('Y-m-d') . 'T14:45:00-03:00', $eventos[$this->idDe($longa)]['end']['dateTime']);
        $this->assertSame(45, (int) $this->linha($longa)['google_duration_min']);
    }

    public function testEventoLevaDescricaoEClienteSemLink(): void
    {
        $this->conta(self::ANA);
        $comCliente = $this->tarefa(['client_id' => self::CLIENTE, 'description' => 'Levar a proposta impressa']);
        $semCliente = $this->tarefa(['due_date' => $this->em('+2 days')]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $eventos = $this->http->events($this->token(self::ANA));
        $evento  = $eventos[$this->idDe($comCliente)];
        $this->assertSame("Levar a proposta impressa\n\nCliente: Cliente Teste", $evento['description']);
        $this->assertStringNotContainsString('/clients/', $evento['description']);
        $this->assertStringNotContainsString(APP_URL, $evento['description']);
        $this->assertSame(['useDefault' => true], $evento['reminders']);
        $this->assertStringNotContainsString('/clients/', $eventos[$this->idDe($semCliente)]['description'] ?? '');
    }

    public function testCorpoLevaDateNullEmStartEEnd(): void
    {
        $this->conta(self::ANA);
        $dia = (new \DateTimeImmutable('+2 days'))->setTime(9, 0);
        // alguem passou o evento para dia inteiro no google, e o patch de horario volta 400 sem date null
        $this->evento(self::ANA, 'evtdiainteiro1', [
            'start' => ['date' => $dia->format('Y-m-d')],
            'end'   => ['date' => $dia->modify('+1 day')->format('Y-m-d')],
        ]);
        $vinculada = $this->tarefa([
            'due_date'        => $dia->format('Y-m-d H:i:s'),
            'google_event_id' => 'evtdiainteiro1',
            'google_owner_id' => self::ANA,
            'google_etag'     => '"etag-velho"',
        ]);
        $nova = $this->tarefa(['due_date' => $dia->modify('+1 day')->format('Y-m-d H:i:s')]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $this->assertSame(
            ['PATCH ' . self::EVENTS_URL . '/evtdiainteiro1', 'POST ' . self::EVENTS_URL],
            $this->chamadas(),
        );
        foreach ([0, 1] as $indice) {
            $corpo = $this->corpo($indice);
            foreach (['start', 'end'] as $ponta) {
                $this->assertArrayHasKey('date', $corpo[$ponta]);
                $this->assertNull($corpo[$ponta]['date']);
            }
        }

        $evento = $this->http->events($this->token(self::ANA))['evtdiainteiro1'];
        $this->assertArrayNotHasKey('date', $evento['start']);
        $this->assertSame($dia->format('Y-m-d') . 'T09:00:00-03:00', $evento['start']['dateTime']);
        $this->assertSame(0, $this->pendencia($vinculada));
        $this->assertSame(0, $this->pendencia($nova));
    }

    public function test401RenovaERepeteUmaVez(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();
        $this->http->respondOnce('calendar/v3', 401, FakeTransport::googleError(401, 'authError'));
        $this->renovacaoDa(self::ANA);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame(
            ['POST ' . self::EVENTS_URL, 'POST ' . self::TOKEN_URL, 'POST ' . self::EVENTS_URL],
            $this->chamadas(),
        );
        $pedidos = $this->http->requests();
        $this->assertContains('Authorization: Bearer ' . $this->token(self::ANA), $pedidos[0]['headers']);
        parse_str((string) $pedidos[1]['body'], $renovacao);
        $this->assertSame('refresh_token', $renovacao['grant_type']);
        $this->assertContains('Authorization: Bearer acesso-renovado-falso', $pedidos[2]['headers']);

        // a agenda e a da conta, qualquer que seja o token usado para chegar nela.
        $this->assertArrayHasKey($this->idDe($id), $this->http->events($this->token(self::ANA)));
        $this->assertSame(0, $this->pendencia($id));
        $this->assertSame('active', $this->contaDe(self::ANA)['status']);
    }

    public function testVinculadaCom401RenovaEFazPatchNoMesmoEvento(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtvinculado1');
        $id = $this->tarefa([
            'title'           => 'Título editado',
            'google_event_id' => 'evtvinculado1',
            'google_owner_id' => self::ANA,
            'google_etag'     => '"etag-velho"',
        ]);
        $this->http->respondOnce('calendar/v3', 401, FakeTransport::googleError(401, 'authError'));
        $this->renovacaoDa(self::ANA);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame([
            'PATCH ' . self::EVENTS_URL . '/evtvinculado1',
            'POST ' . self::TOKEN_URL,
            'PATCH ' . self::EVENTS_URL . '/evtvinculado1',
        ], $this->chamadas());
        $evento = $this->http->events($this->token(self::ANA))['evtvinculado1'];
        $this->assertSame('Título editado', $evento['summary']);
        $linha = $this->linha($id);
        $this->assertSame('evtvinculado1', $linha['google_event_id']);
        $this->assertSame($evento['etag'], $linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
    }

    public function testSegundo401PedeReconexao(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();
        $this->http->respondOnce('calendar/v3', 401, FakeTransport::googleError(401, 'authError'));
        $this->http->respondOnce('calendar/v3', 401, FakeTransport::googleError(401, 'authError'));
        $this->renovacaoDa(self::ANA);

        $this->assertSame('reconnect', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertCount(3, $this->http->requests());
        $conta = $this->contaDe(self::ANA);
        $this->assertSame('revoked', $conta['status']);
        $this->assertNull($conta['drain_until']);
        $this->assertSame(1, $this->pendencia($id));
    }

    public function testRenovaUmaVezPorConta(): void
    {
        // com o token recem-renovado, um novo 401 na mesma conta ja e o segundo
        $this->conta(self::ANA);
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);
        $this->renovacaoDa(self::ANA);
        // as respostas de uma vez saem na ordem: 401 na primeira, sucesso no reenvio, 401 na segunda
        $this->http->respondOnce('calendar/v3', 401, FakeTransport::googleError(401, 'authError'));
        $this->http->respondOnce('calendar/v3', 200, ['etag' => '"etag-falso-reenvio"']);
        $this->http->respondOnce('calendar/v3', 401, FakeTransport::googleError(401, 'authError'));

        $this->assertSame('reconnect', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED, self::ANA));

        $this->assertSame(
            ['POST ' . self::EVENTS_URL, 'POST ' . self::TOKEN_URL, 'POST ' . self::EVENTS_URL, 'POST ' . self::EVENTS_URL],
            $this->chamadas(),
        );
        $this->assertSame(0, $this->pendencia($primeira));
        $this->assertSame(1, $this->pendencia($segunda));
        $this->assertSame('revoked', $this->contaDe(self::ANA)['status']);
    }

    public function testConcluidaLevaMarcaNoTitulo(): void
    {
        $this->conta(self::ANA);
        $concluida = $this->tarefa(['status' => 'done', 'title' => 'Enviar contrato']);
        $aberta    = $this->tarefa(['status' => 'in_progress', 'title' => 'Revisar contrato', 'due_date' => $this->em('+2 days')]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $eventos = $this->http->events($this->token(self::ANA));
        $this->assertSame('✓ Enviar contrato', $eventos[$this->idDe($concluida)]['summary']);
        $this->assertSame('confirmed', $eventos[$this->idDe($concluida)]['status']);
        $this->assertSame('Revisar contrato', $eventos[$this->idDe($aberta)]['summary']);
    }

    public function testInsertRepetidoViraPatch(): void
    {
        $this->conta(self::ANA);
        $id      = $this->tarefa(['title' => 'Título novo']);
        $eventId = $this->idDe($id);
        // um insert que estourou o tempo criou o evento, e depois ele foi apagado no google
        $this->evento(self::ANA, $eventId, ['status' => 'cancelled', 'summary' => 'Título velho']);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $this->assertSame(['POST ' . self::EVENTS_URL, 'PATCH ' . self::EVENTS_URL . '/' . $eventId], $this->chamadas());
        $this->assertSame(['useDefault' => true], $this->corpo(0)['reminders']);
        $this->assertArrayNotHasKey('reminders', $this->corpo(1));
        $evento = $this->http->events($this->token(self::ANA))[$eventId];
        $this->assertSame('confirmed', $evento['status']);
        $this->assertSame('Título novo', $evento['summary']);
        $linha = $this->linha($id);
        $this->assertSame($eventId, $linha['google_event_id']);
        $this->assertSame($evento['etag'], $linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
    }

    public function testPatchNaoMandaLembreteEMantemODaPessoa(): void
    {
        // a api so aceita overrides com useDefault false, e o patch mescla o
        // corpo no evento, entao mandar o lembrete padrao quebraria a edicao.
        $this->conta(self::ANA);
        $lembreteDaPessoa = ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 10]]];
        $this->evento(self::ANA, 'evtcomlembrete1', ['reminders' => $lembreteDaPessoa]);
        $id = $this->tarefa([
            'title'           => 'Título editado',
            'google_event_id' => 'evtcomlembrete1',
            'google_owner_id' => self::ANA,
            'google_etag'     => '"etag-velho"',
        ]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame(['PATCH ' . self::EVENTS_URL . '/evtcomlembrete1'], $this->chamadas());
        $this->assertArrayNotHasKey('reminders', $this->corpo(0));
        $evento = $this->http->events($this->token(self::ANA))['evtcomlembrete1'];
        $this->assertSame('Título editado', $evento['summary']);
        $this->assertSame($lembreteDaPessoa, $evento['reminders']);
        $this->assertSame(0, $this->pendencia($id));
    }

    /** @return array<string, array{int}> */
    public static function eventoSumido(): array
    {
        return ['404' => [404], '410' => [410]];
    }

    #[DataProvider('eventoSumido')]
    public function testPatchDeEventoSumidoViraInsert(int $status): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa([
            'google_event_id' => 'evtsumido1', 'google_owner_id' => self::ANA, 'google_etag' => '"etag-velho"',
        ]);
        if ($status === 410) {
            $this->http->respondOnce('evtsumido1', 410, FakeTransport::googleError(410, 'deleted'), 'PATCH');
        }

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $this->assertSame(['PATCH ' . self::EVENTS_URL . '/evtsumido1', 'POST ' . self::EVENTS_URL], $this->chamadas());
        $this->assertArrayNotHasKey('reminders', $this->corpo(0));
        $this->assertSame('evtsumido1', $this->corpo(1)['id']);
        $this->assertSame(['useDefault' => true], $this->corpo(1)['reminders']);
        $evento = $this->http->events($this->token(self::ANA))['evtsumido1'];
        $this->assertSame('confirmed', $evento['status']);
        $linha = $this->linha($id);
        $this->assertSame('evtsumido1', $linha['google_event_id']);
        $this->assertSame($evento['etag'], $linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
    }

    public function testCanceladaRemoveEvento(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtcancelar1');
        $id = $this->tarefa([
            'status' => 'cancelled', 'google_event_id' => 'evtcancelar1', 'google_owner_id' => self::ANA,
            'google_etag' => '"etag-velho"',
        ]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $this->assertSame(['DELETE ' . self::EVENTS_URL . '/evtcancelar1'], $this->chamadas());
        $this->assertSame('cancelled', $this->http->events($this->token(self::ANA))['evtcancelar1']['status']);
        $linha = $this->linha($id);
        $this->assertSame('cancelled', $linha['status']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertNull($linha['google_etag']);
    }

    public function testCanceladaSemIdUsaIdDeterministico(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa(['status' => 'cancelled']);
        // o insert que estourou o tempo pode ter criado o evento com o id deterministico
        $this->evento(self::ANA, $this->idDe($id));

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $this->assertSame(['DELETE ' . self::EVENTS_URL . '/' . $this->idDe($id)], $this->chamadas());
        $this->assertSame('cancelled', $this->http->events($this->token(self::ANA))[$this->idDe($id)]['status']);
        $this->assertSame(0, $this->pendencia($id));
    }

    public function testRemocoesVemAntesDasTarefas(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();
        $this->evento(self::ANA, 'evtremover1');
        $this->remocao(self::ANA, 'evtremover1');

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $this->assertSame(['DELETE ' . self::EVENTS_URL . '/evtremover1', 'POST ' . self::EVENTS_URL], $this->chamadas());
        $this->assertSame([], $this->remocoes());
        $this->assertSame(0, $this->pendencia($id));
    }

    public function testRemocaoDeEventoInexistenteSaiDaFila(): void
    {
        $this->conta(self::ANA);
        $this->evento(self::ANA, 'evtjaapagado', ['status' => 'cancelled']);
        $this->evento(self::ANA, 'evtexiste');
        // a agenda responde 404 ao id que nunca existiu e 410 ao ja apagado.
        $this->remocao(self::ANA, 'evtnuncaexistiu');
        $this->remocao(self::ANA, 'evtjaapagado');
        $this->remocao(self::ANA, 'evtexiste');

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $this->assertSame([
            'DELETE ' . self::EVENTS_URL . '/evtnuncaexistiu',
            'DELETE ' . self::EVENTS_URL . '/evtjaapagado',
            'DELETE ' . self::EVENTS_URL . '/evtexiste',
        ], $this->chamadas());
        $this->assertSame([], $this->remocoes());
        $this->assertSame('cancelled', $this->http->events($this->token(self::ANA))['evtexiste']['status']);
    }

    public function testTravaOcupadaNaoChamaGoogle(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();
        $this->pdo->exec('UPDATE google_accounts SET drain_until = NOW() + INTERVAL 1 MINUTE WHERE user_id = 1');

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame([], $this->http->requests());
        $this->assertSame(1, $this->pendencia($id));
        // a trava e de outro envio, e quem nao a pegou nao a solta
        $this->assertNotNull($this->contaDe(self::ANA)['drain_until']);
    }

    public function testRedeParaTudoEMantemPendencia(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $daAna = $this->tarefa();
        $daBia = $this->tarefa(['assigned_to' => self::BIA]);
        $this->http->networkDown(true);

        $this->assertSame('degraded', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $this->assertCount(1, $this->http->requests());
        $this->assertSame(1, $this->pendencia($daAna));
        $this->assertSame(1, $this->pendencia($daBia));
        foreach ([self::ANA, self::BIA] as $userId) {
            $conta = $this->contaDe($userId);
            $this->assertSame('active', $conta['status']);
            $this->assertNull($conta['drain_until']);
        }
    }

    public function testRedeComFocoDevolveDegraded(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();
        $this->http->networkDown(true);

        $this->assertSame('degraded', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED, self::ANA));

        $this->assertCount(1, $this->http->requests());
        $this->assertSame(1, $this->pendencia($id));
    }

    public function testParadaEmOutraContaNaoMudaFoco(): void
    {
        // um item de outra conta que sempre para o envio nao pode deixar o feed
        // de todo mundo degradado, entao o foco ve so o que aconteceu com ele.
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $daAna = $this->tarefa();
        $daBia = $this->tarefa(['assigned_to' => self::BIA]);
        $this->http->respondOnce('calendar/v3', 200, ['etag' => '"etag-falso-ana"']);
        $this->http->respondOnce('calendar/v3', 403, FakeTransport::googleError(403, 'motivoNovoFalso'));

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED, self::ANA));

        $this->assertCount(2, $this->http->requests());
        $this->assertSame(0, $this->pendencia($daAna));
        $this->assertSame(1, $this->pendencia($daBia));
        $this->assertSame('active', $this->contaDe(self::BIA)['status']);
    }

    /** @return array<string, array{int, string}> */
    public static function limitesDoGoogle(): array
    {
        return [
            '429'              => [429, FakeTransport::googleError(429, 'rateLimitExceeded')],
            '403 de limite'    => [403, FakeTransport::googleError(403, 'userRateLimitExceeded')],
            '503'              => [503, ''],
            '403 desconhecido' => [403, FakeTransport::googleError(403, 'motivoNovoFalso')],
        ];
    }

    #[DataProvider('limitesDoGoogle')]
    public function testLimite429ParaEMantem(int $status, string $corpo): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);
        $daBia    = $this->tarefa(['assigned_to' => self::BIA]);
        $this->http->respond('calendar/v3', $status, $corpo);

        $this->assertSame('degraded', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $this->assertCount(1, $this->http->requests());
        foreach ([$primeira, $segunda, $daBia] as $id) {
            $this->assertSame(1, $this->pendencia($id));
        }
        $this->assertSame('active', $this->contaDe(self::ANA)['status']);
        $this->assertSame('active', $this->contaDe(self::BIA)['status']);
    }

    public function testInvalidGrantPedeReconexao(): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $id = $this->tarefa();
        $this->http->respond('oauth2.googleapis.com/token', 400, [
            'error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.',
        ]);

        $this->assertSame('reconnect', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame(['POST ' . self::TOKEN_URL], $this->chamadas());
        $conta = $this->contaDe(self::ANA);
        $this->assertSame('revoked', $conta['status']);
        $this->assertNull($conta['drain_until']);
        $this->assertSame(1, $this->pendencia($id));
    }

    public function testErroDeContaNaoEsvaziaAFila(): void
    {
        $this->conta(self::ANA);
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);
        $this->remocao(self::ANA, 'evtremover1');
        $this->http->respond('calendar/v3', 403, FakeTransport::googleError(403, 'insufficientPermissions'));

        $this->assertSame('reconnect', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED, self::ANA));

        $this->assertCount(1, $this->http->requests());
        $this->assertSame('revoked', $this->contaDe(self::ANA)['status']);
        $this->assertSame(1, $this->pendencia($primeira));
        $this->assertSame(1, $this->pendencia($segunda));
        $this->assertSame(['evtremover1'], $this->remocoes());
    }

    /** @return array<string, array{int, string}> */
    public static function errosDoEvento(): array
    {
        return [
            '400'                => [400, 'invalid'],
            '412 fora da tabela' => [412, 'conditionNotMet'],
        ];
    }

    #[DataProvider('errosDoEvento')]
    public function testErroDoEventoTiraSoEleDaFila(int $status, string $motivo): void
    {
        $this->conta(self::ANA);
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);
        $this->http->respondOnce('calendar/v3', $status, FakeTransport::googleError($status, $motivo));

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $linha = $this->linha($primeira);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertNull($linha['google_event_id']);
        $eventos = $this->http->events($this->token(self::ANA));
        $this->assertSame([$this->idDe($segunda)], array_keys($eventos));
        $this->assertSame(0, $this->pendencia($segunda));

        // o descarte fica registrado, e o log nao leva o token.
        $log = $this->logs();
        $this->assertStringContainsString('"task_id":' . $primeira, $log);
        $this->assertStringNotContainsString('acesso-falso', $log);
        $this->assertStringNotContainsString('refresh-falso', $log);
    }

    public function testFalhaDeOutraContaNaoMudaFoco(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $daAna = $this->tarefa();
        $daBia = $this->tarefa(['assigned_to' => self::BIA]);
        $this->http->respond('oauth2.googleapis.com/token', 400, ['error' => 'invalid_grant']);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED, self::ANA));

        $this->assertSame('revoked', $this->contaDe(self::BIA)['status']);
        $this->assertSame('active', $this->contaDe(self::ANA)['status']);
        $this->assertSame(0, $this->pendencia($daAna));
        $this->assertSame(1, $this->pendencia($daBia));
    }

    public function testSemFocoDevolveOPiorStatus(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $this->tarefa();
        $this->tarefa(['assigned_to' => self::BIA]);
        $this->http->respond('oauth2.googleapis.com/token', 400, ['error' => 'invalid_grant']);

        $this->assertSame('reconnect', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));
    }

    public function testContaEmFocoEnviaPrimeiro(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $this->tarefa();
        $this->tarefa(['assigned_to' => self::BIA]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED, self::BIA));

        $pedidos = $this->http->requests();
        $this->assertCount(2, $pedidos);
        $this->assertContains('Authorization: Bearer ' . $this->token(self::BIA), $pedidos[0]['headers']);
        $this->assertContains('Authorization: Bearer ' . $this->token(self::ANA), $pedidos[1]['headers']);
    }

    public function testOrcamentoParaEntreItens(): void
    {
        $this->conta(self::ANA);
        $ids = [];
        foreach (['+1 day', '+2 days', '+3 days', '+4 days'] as $quando) {
            $ids[] = $this->tarefa(['due_date' => $this->em($quando)]);
        }
        $this->http->advanceMsPerCall(800);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        // a primeira leva 800 dos 1500 ms e a segunda sai com 700, que o curl corta
        // no teto. o google ja respondeu neste envio, entao o corte e orcamento, nao queda.
        $this->assertSame([1500, 700], array_column($this->http->requests(), 'timeoutMs'));
        $this->assertSame([0, 1, 1, 1], array_map(fn (int $id): int => $this->pendencia($id), $ids));
        $this->assertSame('active', $this->contaDe(self::ANA)['status']);
        $this->assertNull($this->contaDe(self::ANA)['drain_until']);
    }

    public function testTimeoutRespeitaOQueResta(): void
    {
        // o token vencido obriga a renovar, e a renovacao entra no orcamento.
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $this->renovacaoDa(self::ANA);
        for ($dia = 1; $dia <= 12; $dia++) {
            $this->tarefa(['due_date' => $this->em("+{$dia} days")]);
        }
        $this->http->advanceMsPerCall(300);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED, self::ANA));

        $pedidos = $this->http->requests();
        $this->assertSame(self::TOKEN_URL, $pedidos[0]['url']);
        // cada chamada leva 300 ms, entao a de indice i recebe o que sobra dos 3000.
        $this->assertSame(
            [3000, 2700, 2400, 2100, 1800, 1500, 1200, 900, 600, 300],
            array_column($pedidos, 'timeoutMs'),
        );
    }

    public function testNaoComecaChamadaComMenosQueOMinimo(): void
    {
        // com 200 ms a chamada quase certamente estouraria, e o corte pareceria queda do google.
        $this->conta(self::ANA);
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);
        $this->http->advanceMsPerCall(1300);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame([1500], array_column($this->http->requests(), 'timeoutMs'));
        $this->assertSame(0, $this->pendencia($primeira));
        $this->assertSame(1, $this->pendencia($segunda));
    }

    public function testPrimeiraChamadaSemRespostaDegrada(): void
    {
        // host bloqueado: nada responde, e o corte no teto e o google fora do ar.
        $this->conta(self::ANA);
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);
        $this->http->advanceMsPerCall(60000);

        $this->assertSame('degraded', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame([1500], array_column($this->http->requests(), 'timeoutMs'));
        $this->assertEqualsWithDelta(1.5, ($this->http->now())() - $this->t0, 0.001);
        $this->assertSame(1, $this->pendencia($primeira));
        $this->assertSame(1, $this->pendencia($segunda));
    }

    public function testRedeQueCaiNoMeioDoEnvioDegrada(): void
    {
        // falha sem resposta antes do fim do orcamento e queda, mesmo com o google ja tendo respondido.
        $this->conta(self::ANA);
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);
        $this->http->respondOnce('calendar/v3', 200, ['etag' => '"etag-falso-primeira"']);
        $this->http->respondOnce('calendar/v3', 0, '');

        $this->assertSame('degraded', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertCount(2, $this->http->requests());
        $this->assertSame(0, $this->pendencia($primeira));
        $this->assertSame(1, $this->pendencia($segunda));
    }

    public function testRenovacaoCortadaPeloOrcamentoDevolveOk(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $daAna = $this->tarefa();
        $daBia = $this->tarefa(['assigned_to' => self::BIA]);
        $this->renovacaoDa(self::BIA);
        $this->http->advanceMsPerCall(1000);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        // a ana responde em 1000 ms, e a renovacao da bia sai com 500 e e cortada no teto.
        $this->assertSame(['POST ' . self::EVENTS_URL, 'POST ' . self::TOKEN_URL], $this->chamadas());
        $this->assertSame([1500, 500], array_column($this->http->requests(), 'timeoutMs'));
        $this->assertSame(0, $this->pendencia($daAna));
        $this->assertSame(1, $this->pendencia($daBia));
        $this->assertSame('active', $this->contaDe(self::BIA)['status']);
        $this->assertNull($this->contaDe(self::BIA)['drain_until']);
    }

    public function testLimiteDeItensContaRemocoes(): void
    {
        $this->conta(self::ANA);
        $this->remocao(self::ANA, 'evtremover1');
        $this->remocao(self::ANA, 'evtremover2');
        $primeira = $this->tarefa(['due_date' => $this->em('+1 day')]);
        $segunda  = $this->tarefa(['due_date' => $this->em('+2 days')]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $this->assertSame([
            'DELETE ' . self::EVENTS_URL . '/evtremover1',
            'DELETE ' . self::EVENTS_URL . '/evtremover2',
            'POST ' . self::EVENTS_URL,
        ], $this->chamadas());
        $this->assertSame(0, $this->pendencia($primeira));
        $this->assertSame(1, $this->pendencia($segunda));
    }

    public function testContaInativaNaoEnvia(): void
    {
        $this->conta(self::CAIO);
        $id = $this->tarefa(['assigned_to' => self::CAIO]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $this->assertSame([], $this->http->requests());
        $this->assertSame(1, $this->pendencia($id));
    }

    public function testNaoEnviaDeOutroTenant(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::DUDA, ['tenant_id' => 2]);
        $daAna       = $this->tarefa();
        $daDuda      = $this->tarefa(['tenant_id' => 2, 'assigned_to' => self::DUDA]);
        $outroTenant = $this->tarefa(['tenant_id' => 2, 'assigned_to' => self::ANA, 'due_date' => $this->em('+2 days')]);
        $this->remocao(self::ANA, 'evtdeoutrotenant', 2);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_FEED));

        $this->assertSame(['POST ' . self::EVENTS_URL], $this->chamadas());
        $this->assertSame($this->idDe($daAna), $this->corpo(0)['id']);
        $this->assertSame(1, $this->pendencia($daDuda));
        $this->assertSame(1, $this->pendencia($outroTenant));
        $this->assertSame(['evtdeoutrotenant'], $this->remocoes());
    }

    public function testTituloComEmojiEUtf8Invalido(): void
    {
        $json = GoogleCalendarSync::encodeBody(['summary' => "Reunião 🚀 \xC3\x28 fim"]);

        $this->assertNotSame('', $json);
        $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame("Reunião 🚀 \u{FFFD}( fim", $dados['summary']);

        // o emoji que vem do banco chega inteiro ao google pelo envio.
        $this->conta(self::ANA);
        $id = $this->tarefa(['title' => 'Café com cliente 🚀']);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE));

        $this->assertSame('Café com cliente 🚀', $this->http->events($this->token(self::ANA))[$this->idDe($id)]['summary']);
    }

    public function testAdminEditaTarefaDoSellerUsaTokenDoSeller(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $id = $this->tarefa(['assigned_to' => self::BIA, 'created_by' => self::ANA]);

        $this->assertSame('ok', $this->sync()->drain(GoogleCalendarSync::PROFILE_AFTER_SAVE, self::ANA));

        $pedidos = $this->http->requests();
        $this->assertCount(1, $pedidos);
        $this->assertContains('Authorization: Bearer ' . $this->token(self::BIA), $pedidos[0]['headers']);
        $this->assertArrayHasKey($this->idDe($id), $this->http->events($this->token(self::BIA)));
        $this->assertSame([], $this->http->events($this->token(self::ANA)));
        $this->assertSame(self::BIA, (int) $this->linha($id)['google_owner_id']);
    }

    public function testSemConfiguracaoNaoEnvia(): void
    {
        $this->conta(self::ANA);
        $id = $this->tarefa();
        $semCredencial = new GoogleConfig('', '', $this->chave, 'http://localhost:8000/google/callback');

        $this->assertSame('ok', $this->sync(config: $semCredencial)->drain(GoogleCalendarSync::PROFILE_FEED, self::ANA));

        $this->assertSame([], $this->http->requests());
        $this->assertSame(1, $this->pendencia($id));
    }

    public function testExcecaoDeUmaContaViraDegradedESoltaATrava(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $daAna = $this->tarefa();
        $daBia = $this->tarefa(['assigned_to' => self::BIA]);
        $tarefas = new class extends Task {
            public function pendingForUser(int $userId, int $limit): array
            {
                if ($userId === 1) {
                    throw new \RuntimeException('falha de banco falsa');
                }
                return parent::pendingForUser($userId, $limit);
            }
        };

        $this->assertSame('degraded', $this->sync($tarefas)->drain(GoogleCalendarSync::PROFILE_FEED));

        $this->assertSame(1, $this->pendencia($daAna));
        $this->assertNull($this->contaDe(self::ANA)['drain_until']);
        $this->assertSame(0, $this->pendencia($daBia));
        $this->assertStringContainsString('falha de banco falsa', $this->logs());
    }
}
