<?php

declare(strict_types=1);

use App\Controllers\UserController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

final class UserGoogleDisconnectTest extends TestCase
{
    // sem estas tres a integracao conta como desligada, e o teste nunca chama o google.
    private const ENV = ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'APP_KEY'];

    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        foreach (self::ENV as $chave) {
            unset($_ENV[$chave]);
            putenv($chave);
        }
        $this->pdo = GoogleTestDb::setUp();
        $this->pdo->exec("INSERT INTO users (id, name, tenant_id, is_active) VALUES (1, 'Ana', 1, 1), (2, 'Bia', 1, 1)");
        $this->pdo->exec("INSERT INTO google_accounts
            (user_id, tenant_id, google_email, google_sub, refresh_token, access_token, access_token_expires_at, status)
            VALUES (2, 1, 'pessoa@example.com', 'sub-falso-2', 'refresh-cifrado-falso', 'acesso-cifrado-falso',
                    NOW() + INTERVAL 1 HOUR, 'active')");

        $_SESSION['tenant_id'] = 1;
        $_SESSION['user']      = ['id' => 1, 'role' => 'admin'];
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        unset($_SESSION['tenant_id'], $_SESSION['user'], $_SESSION['flash']);
    }

    /** o redirect de verdade da exit, e aqui ele so guarda o destino */
    private function controller(): UserController
    {
        return new class extends UserController {
            public ?string $destino = null;

            protected function redirect(string $path): void
            {
                $this->destino = $path;
            }
        };
    }

    public function testDesativarComIntegracaoDesligadaApagaOsTokens(): void
    {
        $controller = $this->controller();

        $controller->destroy(['id' => 2]);

        $this->assertSame('/admin/users', $controller->destino);
        $conta = $this->pdo->query('SELECT * FROM google_accounts WHERE user_id = 2')->fetch();
        $this->assertSame('disconnected', $conta['status']);
        $this->assertNull($conta['refresh_token']);
        $this->assertNull($conta['access_token']);
        $this->assertNull($conta['access_token_expires_at']);
        $this->assertSame('sub-falso-2', $conta['google_sub']);
    }
}
