<?php

declare(strict_types=1);

use App\Controllers\GoogleController;
use App\Services\Google\GoogleAuth;
use App\Services\Google\GoogleConfig;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';
require_once __DIR__ . '/Support/FakeTransport.php';

final class GoogleControllerFinishTest extends TestCase
{
    private const ENV = ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'APP_KEY'];

    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $this->pdo->exec("INSERT INTO users (id, name, tenant_id) VALUES (1, 'Ana', 1)");

        $_ENV['GOOGLE_CLIENT_ID']     = 'cliente-falso.apps.googleusercontent.com';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'segredo-falso';
        $_ENV['APP_KEY']              = 'base64:' . base64_encode(str_repeat('k', 32));
        $_SESSION['tenant_id'] = 1;
        $_SESSION['user']      = ['id' => 1, 'role' => 'admin'];
        $_SESSION['google_oauth_state'] = ['value' => 'state-falso', 'expires_at' => time() + 600];
        $_GET = ['state' => 'state-falso', 'code' => 'codigo-falso'];
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        foreach (self::ENV as $chave) {
            unset($_ENV[$chave]);
            putenv($chave);
        }
        unset($_SESSION['tenant_id'], $_SESSION['user'], $_SESSION['google_oauth_state'], $_SESSION['flash']);
        $_GET = [];
    }

    /**
     * o redirect de verdade da exit, e aqui ele so guarda o destino. a troca do
     * code vai ao transporte falso, nunca ao endpoint de token do google.
     */
    private function controller(?FakeTransport $http = null): GoogleController
    {
        return new class ($http ?? new FakeTransport()) extends GoogleController {
            public ?string $destino = null;

            public function __construct(private FakeTransport $http)
            {
            }

            protected function redirect(string $path): void
            {
                $this->destino = $path;
            }

            protected function googleAuth(GoogleConfig $config): GoogleAuth
            {
                return new GoogleAuth($config, $this->http);
            }
        };
    }

    /** o endpoint de token devolve uma troca valida, com o escopo de agenda e o refresh_token */
    private function trocaValida(): FakeTransport
    {
        $base64url = fn (string $bruto): string => rtrim(strtr(base64_encode($bruto), '+/', '-_'), '=');
        $idToken = $base64url('{"alg":"RS256","typ":"JWT"}') . '.'
            . $base64url((string) json_encode(['sub' => 'sub-falso-1', 'email' => 'pessoa@example.com'])) . '.assinatura-falsa';

        $http = new FakeTransport();
        $http->respond('oauth2.googleapis.com/token', 200, [
            'access_token'  => 'acesso-novo-falso',
            'expires_in'    => 3599,
            'refresh_token' => 'refresh-novo-falso',
            'scope'         => 'openid ' . GoogleAuth::SCOPE_CALENDAR,
            'token_type'    => 'Bearer',
            'id_token'      => $idToken,
        ]);
        return $http;
    }

    private function contas(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM google_accounts')->fetchColumn();
    }

    public function testConexaoGravadaAvisaConectado(): void
    {
        $controller = $this->controller($this->trocaValida());

        $controller->finish();

        $this->assertSame('/tasks', $controller->destino);
        $this->assertSame('success', $_SESSION['flash']['type']);
        $this->assertStringStartsWith('Google Agenda conectado.', $_SESSION['flash']['message']);
        $this->assertSame(1, $this->contas());
    }

    public function testUsuarioRebaixadoComSessaoAntigaRecebeAviso(): void
    {
        // a sessao ainda diz admin, mas o banco ja tem o usuario como leitor.
        $this->pdo->exec("UPDATE users SET role = 'viewer' WHERE id = 1");
        $controller = $this->controller($this->trocaValida());

        $controller->finish();

        $this->assertSame('/tasks', $controller->destino);
        $this->assertSame([
            'type'    => 'warning',
            'message' => 'Seu usuário não pode conectar o Google Agenda.',
        ], $_SESSION['flash']);
        $this->assertSame(0, $this->contas());
    }

    public function testErroInesperadoVoltaParaTarefasComAviso(): void
    {
        // o banco sem a tabela da migration 021 e o erro que o primeiro deploy pode ter
        $this->pdo->exec('DROP TABLE google_accounts');
        $controller = $this->controller();

        $controller->finish();

        $this->assertSame('/tasks', $controller->destino);
        $this->assertSame([
            'type'    => 'error',
            'message' => 'Não foi possível concluir a conexão com o Google Agenda. Tente de novo em alguns instantes.',
        ], $_SESSION['flash']);
    }
}
