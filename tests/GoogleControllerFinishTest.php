<?php

declare(strict_types=1);

use App\Controllers\GoogleController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

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

    /** o redirect de verdade da exit, e aqui ele so guarda o destino */
    private function controller(): GoogleController
    {
        return new class extends GoogleController {
            public ?string $destino = null;

            protected function redirect(string $path): void
            {
                $this->destino = $path;
            }
        };
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
