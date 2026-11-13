<?php

declare(strict_types=1);

use App\Models\GoogleAccount;
use App\Services\Google\GoogleAccessDenied;
use App\Services\Google\GoogleAuth;
use App\Services\Google\GoogleConfig;
use App\Services\Google\GoogleReconnectRequired;
use App\Services\Google\GoogleUnavailable;
use App\Services\PlacesApiService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';
require_once __DIR__ . '/Support/FakeTransport.php';

final class GoogleAuthTest extends TestCase
{
    private const TENANT = 1;
    private const ANA    = 1; // tenant 1
    private const BIA    = 2; // tenant 1
    private const DUDA   = 4; // tenant 2

    private const CLIENT_ID    = 'cliente-falso.apps.googleusercontent.com';
    private const REDIRECT_URI = 'http://localhost:8000/google/callback';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL   = 'https://oauth2.googleapis.com/revoke';

    private ?\PDO $pdo = null;
    private string $chave = '';
    private GoogleConfig $config;
    private FakeTransport $http;
    private float $t0 = 0.0;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $this->chave = 'base64:' . base64_encode(str_repeat('k', 32));
        $this->config = new GoogleConfig(self::CLIENT_ID, 'segredo-falso', $this->chave, self::REDIRECT_URI);
        $this->http = new FakeTransport();
        $this->t0 = ($this->http->now())();
        $_SESSION['tenant_id'] = self::TENANT;

        $this->pdo->exec("INSERT INTO users (id, name, tenant_id) VALUES
            (1, 'Ana', 1), (2, 'Bia', 1), (4, 'Duda', 2)");
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        unset($_SESSION['tenant_id']);
    }

    private function auth(): GoogleAuth
    {
        return new GoogleAuth($this->config, $this->http, now: $this->http->now());
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

    private function cifrado(string $valor): string
    {
        return PlacesApiService::encryptKey($valor, $this->chave);
    }

    private function decifrado(?string $guardado): ?string
    {
        return $guardado === null ? null : PlacesApiService::decryptKey($guardado, $this->chave);
    }

    /** iv fixo, para o resultado com a chave errada ser sempre o mesmo e o teste nao oscilar */
    private static function cifradoComOutraChave(string $valor): string
    {
        $iv = str_repeat('i', 16);
        $cifra = openssl_encrypt($valor, 'aes-256-cbc', hash('sha256', 'outra-chave-falsa', true), 0, $iv);
        return base64_encode($iv) . ':' . $cifra;
    }

    /** @param array<string, mixed> $campos */
    private function conta(int $userId, array $campos = []): void
    {
        $linha = array_merge([
            'user_id'                 => $userId,
            'tenant_id'               => self::TENANT,
            'google_email'            => 'pessoa@example.com',
            'google_sub'              => "sub-falso-{$userId}",
            'refresh_token'           => $this->cifrado('refresh-falso'),
            'access_token'            => $this->cifrado('acesso-falso'),
            'access_token_expires_at' => $this->aposT0(3600),
            'status'                  => 'active',
        ], $campos);
        $this->inserir('google_accounts', $linha);
    }

    /** @return array<string, mixed>|false */
    private function contaDe(int $userId): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM google_accounts WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch();
    }

    /** @param array<string, mixed> $campos */
    private function tarefa(array $campos = []): int
    {
        $linha = array_merge([
            'tenant_id'           => self::TENANT,
            'assigned_to'         => self::ANA,
            'created_by'          => self::ANA,
            'title'               => 'Ligar para cliente',
            'due_date'            => $this->em('+1 day'),
            'priority'            => 'medium',
            'status'              => 'pending',
            'google_event_id'     => null,
            'google_owner_id'     => null,
            'google_etag'         => null,
            'google_sync_pending' => 0,
        ], $campos);
        $this->inserir('tasks', $linha);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function linha(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
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
    private function remocoesDe(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT google_event_id FROM google_event_deletions WHERE user_id = :id ORDER BY google_event_id'
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
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

    private static function base64url(string $bruto): string
    {
        return rtrim(strtr(base64_encode($bruto), '+/', '-_'), '=');
    }

    /** @param array<string, mixed> $claims */
    private static function idToken(array $claims): string
    {
        return self::base64url('{"alg":"RS256","kid":"chave-falsa","typ":"JWT"}')
            . '.' . self::base64url((string) json_encode($claims))
            . '.assinatura-falsa';
    }

    /** @return array<string, mixed> */
    private static function claims(): array
    {
        return [
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::CLIENT_ID,
            'sub'            => 'sub-falso-1',
            'email'          => 'pessoa@example.com',
            'email_verified' => true,
            'iat'            => 1700000000,
            'exp'            => 1700003600,
        ];
    }

    /**
     * resposta do endpoint de token na troca do code; campo null sai da resposta
     *
     * @param array<string, mixed> $campos
     * @return array<string, mixed>
     */
    private static function respostaDaTroca(array $campos = []): array
    {
        $resposta = array_merge([
            'access_token'  => 'acesso-novo-falso',
            'expires_in'    => 3599,
            'refresh_token' => 'refresh-novo-falso',
            'scope'         => 'openid https://www.googleapis.com/auth/userinfo.email ' . GoogleAuth::SCOPE_CALENDAR,
            'token_type'    => 'Bearer',
            'id_token'      => self::idToken(self::claims()),
        ], $campos);

        return array_filter($resposta, fn (mixed $valor): bool => $valor !== null);
    }

    /** @return array<string, mixed> */
    private static function respostaDaRenovacao(): array
    {
        return [
            'access_token' => 'acesso-renovado-falso',
            'expires_in'   => 3599,
            'scope'        => 'openid https://www.googleapis.com/auth/userinfo.email ' . GoogleAuth::SCOPE_CALENDAR,
            'token_type'   => 'Bearer',
        ];
    }

    /** @return array{email:string,sub:string,refresh_token:string,access_token:string,expires_in:int} */
    private function trocado(string $sub = 'sub-falso-1'): array
    {
        return [
            'email'         => 'pessoa@example.com',
            'sub'           => $sub,
            'refresh_token' => 'refresh-novo-falso',
            'access_token'  => 'acesso-novo-falso',
            'expires_in'    => 3599,
        ];
    }

    private function capturar(\Closure $acao): \Throwable
    {
        try {
            $acao();
        } catch (\Throwable $e) {
            return $e;
        }
        $this->fail('nenhuma excecao foi lancada');
    }

    /** @return array<string, string> */
    private static function formulario(?string $corpo): array
    {
        parse_str((string) $corpo, $campos);
        ksort($campos);
        return $campos;
    }

    public function testConsentUrlPedeOfflineEConsent(): void
    {
        $url = $this->auth()->consentUrl('estado-falso');

        $partes = parse_url($url);
        $this->assertSame('https', $partes['scheme']);
        $this->assertSame('accounts.google.com', $partes['host']);
        $this->assertSame('/o/oauth2/v2/auth', $partes['path']);
        parse_str($partes['query'], $query);
        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame(self::REDIRECT_URI, $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('estado-falso', $query['state']);
        $this->assertSame(['openid', 'email', GoogleAuth::SCOPE_CALENDAR], explode(' ', $query['scope']));
        $this->assertSame([], $this->http->requests());
    }

    public function testExchangeDevolveContaDoIdToken(): void
    {
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaTroca());

        $conta = $this->auth()->exchangeCode('codigo-falso');

        $this->assertSame([
            'email'         => 'pessoa@example.com',
            'sub'           => 'sub-falso-1',
            'refresh_token' => 'refresh-novo-falso',
            'access_token'  => 'acesso-novo-falso',
            'expires_in'    => 3599,
        ], $conta);

        $pedidos = $this->http->requests();
        $this->assertCount(1, $pedidos);
        $this->assertSame('POST', $pedidos[0]['method']);
        $this->assertSame(self::TOKEN_URL, $pedidos[0]['url']);
        $this->assertContains('Content-Type: application/x-www-form-urlencoded', $pedidos[0]['headers']);
        $this->assertSame([
            'client_id'     => self::CLIENT_ID,
            'client_secret' => 'segredo-falso',
            'code'          => 'codigo-falso',
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => self::REDIRECT_URI,
        ], self::formulario($pedidos[0]['body']));
    }

    public function testExchangeSemEscopoDeAgendaEhNegado(): void
    {
        // o escopo pedido e prefixo deste, entao so a comparacao exata recusa.
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaTroca([
            'scope' => 'openid https://www.googleapis.com/auth/userinfo.email ' . GoogleAuth::SCOPE_CALENDAR . '.readonly',
        ]));

        $this->expectException(GoogleAccessDenied::class);
        $this->auth()->exchangeCode('codigo-falso');
    }

    public function testExchangeSemRefreshTokenEhNegado(): void
    {
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaTroca(['refresh_token' => null]));

        $erro = $this->capturar(fn () => $this->auth()->exchangeCode('codigo-falso'));

        $this->assertInstanceOf(GoogleAccessDenied::class, $erro);
        $this->assertStringNotContainsString('acesso-novo-falso', $erro->getMessage());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function respostasQueNaoIdentificamAConta(): array
    {
        $semSub   = self::claims();
        $semEmail = self::claims();
        unset($semSub['sub'], $semEmail['email']);

        return [
            'sem id_token'           => [['id_token' => null]],
            'id_token sem 3 partes'  => [['id_token' => 'lixo']],
            'payload que nao e json' => [['id_token' => self::base64url('{"alg":"none"}') . '.' . self::base64url('nao-json') . '.assinatura-falsa']],
            'payload sem sub'        => [['id_token' => self::idToken($semSub)]],
            'payload sem email'      => [['id_token' => self::idToken($semEmail)]],
            'sem access_token'       => [['access_token' => null]],
        ];
    }

    /** @param array<string, mixed> $campos */
    #[DataProvider('respostasQueNaoIdentificamAConta')]
    public function testExchangeIncompletoEhNegado(array $campos): void
    {
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaTroca($campos));

        $this->expectException(GoogleAccessDenied::class);
        $this->auth()->exchangeCode('codigo-falso');
    }

    public function testExchangeRecusadoPeloGoogleEhNegado(): void
    {
        $this->http->respond('oauth2.googleapis.com/token', 400, ['error' => 'invalid_grant', 'error_description' => 'Bad Request']);

        $this->expectException(GoogleAccessDenied::class);
        $this->auth()->exchangeCode('codigo-falso');
    }

    /** @return array<string, array{int}> */
    public static function falhasPassageiras(): array
    {
        return ['rede' => [0], 'http 503' => [503], 'http 429' => [429]];
    }

    #[DataProvider('falhasPassageiras')]
    public function testExchangeComGoogleForDoArFicaIndisponivel(int $status): void
    {
        if ($status === 0) {
            $this->http->networkDown(true);
        } else {
            $this->http->respond('oauth2.googleapis.com/token', $status, ['error' => 'backend_error']);
        }

        $this->expectException(GoogleUnavailable::class);
        $this->auth()->exchangeCode('codigo-falso');
    }

    public function testPrimeiraConexaoMarcaTarefas(): void
    {
        $aberta    = $this->tarefa();
        $concluida = $this->tarefa(['status' => 'done']);
        $vencida   = $this->tarefa(['due_date' => $this->em('-2 days')]);
        $daBia     = $this->tarefa(['assigned_to' => self::BIA]);

        $this->auth()->completeConnection(self::ANA, $this->trocado());

        $this->assertSame(1, (int) $this->linha($aberta)['google_sync_pending']);
        $this->assertSame(0, (int) $this->linha($concluida)['google_sync_pending']);
        $this->assertSame(0, (int) $this->linha($vencida)['google_sync_pending']);
        $this->assertSame(0, (int) $this->linha($daBia)['google_sync_pending']);

        $conta = $this->contaDe(self::ANA);
        $this->assertSame(self::TENANT, (int) $conta['tenant_id']);
        $this->assertSame('pessoa@example.com', $conta['google_email']);
        $this->assertSame('sub-falso-1', $conta['google_sub']);
        $this->assertSame('active', $conta['status']);
        $this->assertStringNotContainsString('refresh-novo-falso', $conta['refresh_token']);
        $this->assertStringNotContainsString('acesso-novo-falso', $conta['access_token']);
        $this->assertSame('refresh-novo-falso', $this->decifrado($conta['refresh_token']));
        $this->assertSame('acesso-novo-falso', $this->decifrado($conta['access_token']));
        $this->assertSame($this->aposT0(3599), $conta['access_token_expires_at']);
    }

    public function testMesmoSubMantemVinculos(): void
    {
        $this->conta(self::ANA, ['status' => 'revoked']);
        $vinculada = $this->tarefa([
            'google_event_id' => 'evento-falso-1', 'google_owner_id' => self::ANA, 'google_etag' => '"etag-falso"',
        ]);
        $solta = $this->tarefa();
        $this->remocao(self::ANA, 'evento-falso-velho');

        $this->auth()->completeConnection(self::ANA, $this->trocado('sub-falso-1'));

        $linha = $this->linha($vinculada);
        $this->assertSame('evento-falso-1', $linha['google_event_id']);
        $this->assertSame(self::ANA, (int) $linha['google_owner_id']);
        $this->assertSame('"etag-falso"', $linha['google_etag']);
        $this->assertSame(0, (int) $linha['google_sync_pending']);
        $this->assertSame(1, (int) $this->linha($solta)['google_sync_pending']);
        $this->assertSame(['evento-falso-velho'], $this->remocoesDe(self::ANA));

        $conta = $this->contaDe(self::ANA);
        $this->assertSame('active', $conta['status']);
        $this->assertSame('refresh-novo-falso', $this->decifrado($conta['refresh_token']));
    }

    public function testSubDiferenteLimpaVinculosERemocoes(): void
    {
        $this->conta(self::ANA);
        $this->conta(self::BIA);
        $vinculada = $this->tarefa([
            'google_event_id' => 'evento-falso-1', 'google_owner_id' => self::ANA, 'google_etag' => '"etag-falso"',
        ]);
        $daBia = $this->tarefa([
            'assigned_to' => self::BIA, 'google_event_id' => 'evento-falso-2', 'google_owner_id' => self::BIA,
        ]);
        $this->remocao(self::ANA, 'evento-falso-velho');
        $this->remocao(self::BIA, 'evento-falso-da-bia');

        $this->auth()->completeConnection(self::ANA, $this->trocado('sub-falso-outra-conta'));

        $linha = $this->linha($vinculada);
        $this->assertNull($linha['google_event_id']);
        $this->assertNull($linha['google_owner_id']);
        $this->assertNull($linha['google_etag']);
        // sem vinculo e aberta, ela entra na fila para nascer na conta nova
        $this->assertSame(1, (int) $linha['google_sync_pending']);
        $this->assertSame('evento-falso-2', $this->linha($daBia)['google_event_id']);
        $this->assertSame([], $this->remocoesDe(self::ANA));
        $this->assertSame(['evento-falso-da-bia'], $this->remocoesDe(self::BIA));
        $this->assertSame('sub-falso-outra-conta', $this->contaDe(self::ANA)['google_sub']);
    }

    public function testDesconectarEOutroSubLimpa(): void
    {
        $this->conta(self::ANA);
        $vinculada = $this->tarefa(['google_event_id' => 'evento-falso-1', 'google_owner_id' => self::ANA]);
        $this->remocao(self::ANA, 'evento-falso-velho');
        $this->http->respond('oauth2.googleapis.com/revoke', 200, '');

        $this->auth()->disconnect(self::ANA);
        $this->auth()->completeConnection(self::ANA, $this->trocado('sub-falso-outra-conta'));

        $this->assertNull($this->linha($vinculada)['google_event_id']);
        $this->assertSame([], $this->remocoesDe(self::ANA));
    }

    public function testConectarUsuarioDeOutroTenantNaoGrava(): void
    {
        $this->auth()->completeConnection(self::DUDA, $this->trocado());

        $this->assertFalse($this->contaDe(self::DUDA));
    }

    public function testDesconectarGuardaSubEApagaTokens(): void
    {
        $this->conta(self::ANA);
        $this->http->respond('oauth2.googleapis.com/revoke', 200, '');

        $this->auth()->disconnect(self::ANA);

        $conta = $this->contaDe(self::ANA);
        $this->assertNull($conta['refresh_token']);
        $this->assertNull($conta['access_token']);
        $this->assertNull($conta['access_token_expires_at']);
        $this->assertSame('disconnected', $conta['status']);
        $this->assertSame('sub-falso-1', $conta['google_sub']);

        $pedidos = $this->http->requests();
        $this->assertCount(1, $pedidos);
        $this->assertSame('POST', $pedidos[0]['method']);
        // o token vai no corpo: a url e o que acaba em log
        $this->assertSame(self::REVOKE_URL, $pedidos[0]['url']);
        $this->assertSame(['token' => 'refresh-falso'], self::formulario($pedidos[0]['body']));
    }

    public function testDesconectarSemRedeNaoLancaEApagaTokens(): void
    {
        $this->conta(self::ANA);
        $this->http->networkDown(true);

        $this->auth()->disconnect(self::ANA);

        $conta = $this->contaDe(self::ANA);
        $this->assertSame('disconnected', $conta['status']);
        $this->assertNull($conta['refresh_token']);
    }

    public function testDesconectarComTokenIlegivelNaoChamaGoogle(): void
    {
        $this->conta(self::ANA, ['refresh_token' => 'lixo', 'access_token' => 'lixo']);

        $this->auth()->disconnect(self::ANA);

        $this->assertSame([], $this->http->requests());
        $this->assertSame('disconnected', $this->contaDe(self::ANA)['status']);
    }

    public function testTokenValidoNaoChamaGoogle(): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(3600)]);

        $this->assertSame('acesso-falso', $this->auth()->accessTokenFor(self::ANA, 1500));
        $this->assertSame([], $this->http->requests());
    }

    public function testTokenPertoDeVencerRenova(): void
    {
        // vencendo em segundos, ele caducaria no meio do envio e o 401 viraria pedido de reconexao.
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(30)]);
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaRenovacao());

        $this->assertSame('acesso-renovado-falso', $this->auth()->accessTokenFor(self::ANA, 1500));
        $this->assertCount(1, $this->http->requests());
    }

    public function testRenovacaoSemRefreshTokenMantemOSalvo(): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $refreshAntes = $this->contaDe(self::ANA)['refresh_token'];
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaRenovacao());

        $this->assertSame('acesso-renovado-falso', $this->auth()->accessTokenFor(self::ANA, 1234));

        $conta = $this->contaDe(self::ANA);
        $this->assertSame($refreshAntes, $conta['refresh_token']);
        $this->assertSame('refresh-falso', $this->decifrado($conta['refresh_token']));
        $this->assertSame('acesso-renovado-falso', $this->decifrado($conta['access_token']));
        $this->assertSame($this->aposT0(3599), $conta['access_token_expires_at']);
        $this->assertSame('active', $conta['status']);

        $pedidos = $this->http->requests();
        $this->assertCount(1, $pedidos);
        $this->assertSame('POST', $pedidos[0]['method']);
        $this->assertSame(self::TOKEN_URL, $pedidos[0]['url']);
        $this->assertSame(1234, $pedidos[0]['timeoutMs']);
        $this->assertSame([
            'client_id'     => self::CLIENT_ID,
            'client_secret' => 'segredo-falso',
            'grant_type'    => 'refresh_token',
            'refresh_token' => 'refresh-falso',
        ], self::formulario($pedidos[0]['body']));

        // o renovado fica salvo e a chamada seguinte nao volta ao google
        $this->assertSame('acesso-renovado-falso', $this->auth()->accessTokenFor(self::ANA, 1234));
        $this->assertCount(1, $this->http->requests());
    }

    public function testForcarRenovacaoIgnoraTokenValido(): void
    {
        // o 401 da agenda chega com o token salvo ainda no prazo, e so a renovacao diz se a conta caiu
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(3600)]);
        $refreshAntes = $this->contaDe(self::ANA)['refresh_token'];
        $this->http->respond('oauth2.googleapis.com/token', 200, self::respostaDaRenovacao());

        $this->assertSame('acesso-renovado-falso', $this->auth()->accessTokenFor(self::ANA, 1234, true));

        $pedidos = $this->http->requests();
        $this->assertCount(1, $pedidos);
        $this->assertSame(self::TOKEN_URL, $pedidos[0]['url']);
        $this->assertSame(1234, $pedidos[0]['timeoutMs']);
        $this->assertSame('refresh_token', self::formulario($pedidos[0]['body'])['grant_type']);

        $conta = $this->contaDe(self::ANA);
        $this->assertSame('acesso-renovado-falso', $this->decifrado($conta['access_token']));
        $this->assertSame($this->aposT0(3599), $conta['access_token_expires_at']);
        $this->assertSame($refreshAntes, $conta['refresh_token']);
        $this->assertSame('active', $conta['status']);
    }

    public function testForcarRenovacaoComInvalidGrantPedeReconexao(): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(3600)]);
        $this->http->respond('oauth2.googleapis.com/token', 400, [
            'error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.',
        ]);

        $erro = $this->capturar(fn () => $this->auth()->accessTokenFor(self::ANA, 1500, true));

        $this->assertInstanceOf(GoogleReconnectRequired::class, $erro);
        $this->assertSame('revoked', $this->contaDe(self::ANA)['status']);
        $this->assertCount(1, $this->http->requests());
    }

    public function testInvalidGrantMarcaRevokedELanca(): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $this->http->respond('oauth2.googleapis.com/token', 400, [
            'error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.',
        ]);

        $erro = $this->capturar(fn () => $this->auth()->accessTokenFor(self::ANA, 1500));

        $this->assertInstanceOf(GoogleReconnectRequired::class, $erro);
        $this->assertStringNotContainsString('refresh-falso', $erro->getMessage());
        $this->assertSame('revoked', $this->contaDe(self::ANA)['status']);
    }

    public function testRedeLancaUnavailableSemRevogar(): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $this->http->networkDown(true);

        $erro = $this->capturar(fn () => $this->auth()->accessTokenFor(self::ANA, 1500));

        $this->assertInstanceOf(GoogleUnavailable::class, $erro);
        $this->assertSame('active', $this->contaDe(self::ANA)['status']);
    }

    /** @return array<string, array{int, array<string, mixed>|string}> */
    public static function falhasQueNaoSaoDaConta(): array
    {
        return [
            'http 503'                => [503, ['error' => 'backend_error']],
            'http 429'                => [429, ['error' => 'rate_limit_exceeded']],
            'cliente mal configurado' => [401, ['error' => 'invalid_client', 'error_description' => 'Unauthorized']],
            '200 que nao e json'      => [200, 'nao-json'],
            '200 sem access_token'    => [200, ['expires_in' => 3599, 'token_type' => 'Bearer']],
        ];
    }

    /** @param array<string, mixed>|string $corpo */
    #[DataProvider('falhasQueNaoSaoDaConta')]
    public function testFalhaQueNaoEDaContaNaoRevoga(int $status, array|string $corpo): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(-10)]);
        $this->http->respond('oauth2.googleapis.com/token', $status, $corpo);

        $erro = $this->capturar(fn () => $this->auth()->accessTokenFor(self::ANA, 1500));

        $this->assertInstanceOf(GoogleUnavailable::class, $erro);
        $this->assertSame('active', $this->contaDe(self::ANA)['status']);
    }

    public function testFalhaAoDecifrarPedeReconexao(): void
    {
        // a appkey trocada deixa os dois tokens ilegiveis, e o access ainda nao venceu
        $this->conta(self::ANA, [
            'refresh_token'           => self::cifradoComOutraChave('refresh-falso'),
            'access_token'            => self::cifradoComOutraChave('acesso-falso'),
            'access_token_expires_at' => $this->aposT0(3600),
        ]);

        $erro = $this->capturar(fn () => $this->auth()->accessTokenFor(self::ANA, 1500));

        $this->assertInstanceOf(GoogleReconnectRequired::class, $erro);
        $this->assertSame('revoked', $this->contaDe(self::ANA)['status']);
        $this->assertSame([], $this->http->requests());
    }

    /** @return array<string, array{?string}> */
    public static function contasQueNaoEstaoAtivas(): array
    {
        return ['revogada' => ['revoked'], 'desconectada' => ['disconnected'], 'sem conta' => [null]];
    }

    #[DataProvider('contasQueNaoEstaoAtivas')]
    public function testContaNaoAtivaPedeReconexaoSemChamar(?string $status): void
    {
        if ($status !== null) {
            $this->conta(self::ANA, ['status' => $status, 'access_token_expires_at' => $this->aposT0(-10)]);
        }

        $erro = $this->capturar(fn () => $this->auth()->accessTokenFor(self::ANA, 1500));

        $this->assertInstanceOf(GoogleReconnectRequired::class, $erro);
        $this->assertSame([], $this->http->requests());
    }

    public function testSemOrcamentoNaoRenova(): void
    {
        $this->conta(self::ANA, ['access_token_expires_at' => $this->aposT0(-10)]);

        $erro = $this->capturar(fn () => $this->auth()->accessTokenFor(self::ANA, 0));

        $this->assertInstanceOf(GoogleUnavailable::class, $erro);
        $this->assertSame([], $this->http->requests());
        $this->assertSame('active', $this->contaDe(self::ANA)['status']);
    }

    public function testDecryptKeyDesfazEncryptKey(): void
    {
        foreach ([$this->chave, 'chave-em-texto-falsa'] as $chave) {
            $guardado = PlacesApiService::encryptKey('token-falso', $chave);
            $this->assertSame('token-falso', PlacesApiService::decryptKey($guardado, $chave));
        }

        $iv = base64_encode(str_repeat('i', 16));
        $this->assertNull(PlacesApiService::decryptKey('', $this->chave));
        $this->assertNull(PlacesApiService::decryptKey('lixo', $this->chave));
        $this->assertNull(PlacesApiService::decryptKey('YWJj:YWJj', $this->chave));
        $this->assertNull(PlacesApiService::decryptKey("{$iv}:%%%", $this->chave));
        $this->assertNull(PlacesApiService::decryptKey("{$iv}:", $this->chave));
        $this->assertNull(PlacesApiService::decryptKey(self::cifradoComOutraChave('token-falso'), $this->chave));
    }

    public function testAcquireDrainSoUmaVez(): void
    {
        $this->conta(self::ANA);
        $contas = new GoogleAccount();

        $this->assertTrue($contas->acquireDrain(self::ANA));
        $this->assertFalse($contas->acquireDrain(self::ANA));
        $contas->releaseDrain(self::ANA);
        $this->assertTrue($contas->acquireDrain(self::ANA));

        // trava vencida, de um envio que morreu no meio, nao segura a conta
        $this->pdo->exec('UPDATE google_accounts SET drain_until = NOW() - INTERVAL 1 SECOND WHERE user_id = 1');
        $this->assertTrue($contas->acquireDrain(self::ANA));
    }

    public function testAcquireDrainNaoPegaContaDeOutroTenant(): void
    {
        $this->conta(self::DUDA, ['tenant_id' => 2]);

        $this->assertFalse((new GoogleAccount())->acquireDrain(self::DUDA));
        $this->assertNull($this->contaDe(self::DUDA)['drain_until']);
    }

    public function testUserIdsWithWorkSoContasAtivasComTrabalho(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, tenant_id, is_active) VALUES
            (3, 'Caio', 1, 0), (5, 'Eva', 1, 1), (6, 'Fabio', 1, 1), (7, 'Gil', 1, 1),
            (8, 'Helo', 1, 1), (9, 'Ivo', 1, 1)");

        // so voltam a ana (tarefa a enviar), a bia (so remocao) e o ivo (vencida
        // com evento, que o envio ainda atualiza). ficam fora o caio desativado,
        // a duda de outro tenant, a eva revogada, o fabio desconectado, o gil,
        // cujo trabalho e todo de outro tenant, e a helo, cuja unica pendencia
        // venceu sem evento e o envio nunca pega.
        $this->conta(self::ANA);
        $this->tarefa(['assigned_to' => self::ANA, 'google_sync_pending' => 1]);
        $this->conta(self::BIA);
        $this->remocao(self::BIA, 'evento-falso-da-bia');
        $this->conta(3);
        $this->tarefa(['assigned_to' => 3, 'google_sync_pending' => 1]);
        $this->conta(self::DUDA, ['tenant_id' => 2]);
        $this->tarefa(['tenant_id' => 2, 'assigned_to' => self::DUDA, 'google_sync_pending' => 1]);
        $this->conta(5, ['status' => 'revoked']);
        $this->tarefa(['assigned_to' => 5, 'google_sync_pending' => 1]);
        $this->conta(6, ['status' => 'disconnected']);
        $this->tarefa(['assigned_to' => 6, 'google_sync_pending' => 1]);
        $this->conta(7);
        $this->tarefa(['assigned_to' => 7, 'google_sync_pending' => 0]);
        $this->tarefa(['tenant_id' => 2, 'assigned_to' => 7, 'google_sync_pending' => 1]);
        $this->remocao(7, 'evento-falso-de-outro-tenant', 2);
        $this->conta(8);
        $this->tarefa(['assigned_to' => 8, 'due_date' => $this->em('-2 days'), 'google_sync_pending' => 1]);
        $this->conta(9);
        $this->tarefa([
            'assigned_to' => 9, 'due_date' => $this->em('-2 days'), 'google_sync_pending' => 1,
            'google_event_id' => 'evento-falso-9', 'google_owner_id' => 9,
        ]);

        $this->assertSame([self::ANA, self::BIA, 9], (new GoogleAccount())->userIdsWithWork());
    }

    public function testContaDesconectadaNaoRecebeTokenNemViraRevogada(): void
    {
        $this->conta(self::ANA, [
            'status' => 'disconnected', 'refresh_token' => null, 'access_token' => null, 'access_token_expires_at' => null,
        ]);
        $contas = new GoogleAccount();

        // uma renovacao que termina depois do desconectar nao pode devolver credencial a conta
        $contas->storeAccessToken(self::ANA, $this->cifrado('acesso-falso'), $this->aposT0(3600));
        $contas->markRevoked(self::ANA);

        $conta = $this->contaDe(self::ANA);
        $this->assertNull($conta['access_token']);
        $this->assertSame('disconnected', $conta['status']);
    }
}
