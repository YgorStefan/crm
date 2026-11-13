<?php

declare(strict_types=1);

use Core\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

// o exit do bloqueio encerraria o processo do phpunit com codigo 0 e sem resumo,
// e a suite pareceria verde com testes que nem rodaram; aqui ele so marca que a resposta acabou.
class RateLimitSemSaida extends RateLimitMiddleware
{
    public bool $terminou = false;

    protected function terminate(): void
    {
        $this->terminou = true;
    }
}

final class RateLimitMiddlewareTest extends TestCase
{
    private const IP = '203.0.113.9';

    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $_SERVER['REMOTE_ADDR'] = self::IP;
        $_POST = [];
        unset($_SESSION['flash']);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        http_response_code(200);
        $_POST = [];
        unset($_SERVER['REMOTE_ADDR'], $_SESSION['flash']);
    }

    private function registrarTentativas(int $quantidade, string $ip, ?string $identifier = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO login_attempts (ip, identifier) VALUES (:ip, :id)');
        for ($i = 0; $i < $quantidade; $i++) {
            $stmt->execute([':ip' => $ip, ':id' => $identifier]);
        }
    }

    private function totalDeTentativas(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn();
    }

    public function testBloqueiaOIpDepoisDeCincoTentativasNoMinuto(): void
    {
        $this->registrarTentativas(5, self::IP);

        $middleware = new RateLimitSemSaida();
        $middleware->handle();

        $this->assertTrue($middleware->terminou);
        // o Location do bloqueio nao e legivel no cli, mas o php troca o status por 302 ao enviar um.
        $this->assertSame(302, http_response_code());
        $this->assertSame(
            ['type' => 'error', 'message' => 'Muitas tentativas. Aguarde 1 minuto antes de tentar novamente.'],
            $_SESSION['flash']
        );
    }

    public function testBloqueiaAContaDepoisDeDezTentativasDeIpsDiferentes(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->registrarTentativas(1, "198.51.100.{$i}", 'ana@exemplo.teste');
        }
        $_POST['email'] = ' ANA@exemplo.teste ';

        $middleware = new RateLimitSemSaida();
        $middleware->handle();

        $this->assertTrue($middleware->terminou);
        $this->assertSame(302, http_response_code());
        $this->assertSame(
            ['type' => 'error', 'message' => 'Muitas tentativas para esta conta. Aguarde 15 minutos antes de tentar novamente.'],
            $_SESSION['flash']
        );
    }

    public function testDeixaPassarAbaixoDoLimiteERegistraATentativa(): void
    {
        $this->registrarTentativas(4, self::IP);

        $middleware = new RateLimitSemSaida();
        $middleware->handle();

        $this->assertFalse($middleware->terminou);
        $this->assertArrayNotHasKey('flash', $_SESSION);
        $this->assertSame(5, $this->totalDeTentativas());
    }
}
