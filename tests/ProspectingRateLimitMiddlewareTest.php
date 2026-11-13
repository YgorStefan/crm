<?php

declare(strict_types=1);

use Core\Middleware\ProspectingRateLimitMiddleware;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

// o exit do bloqueio encerraria o processo do phpunit com codigo 0 e sem resumo,
// e a suite pareceria verde com testes que nem rodaram; aqui ele so marca que a resposta acabou.
class ProspectingRateLimitSemSaida extends ProspectingRateLimitMiddleware
{
    public bool $terminou = false;

    protected function terminate(): void
    {
        $this->terminou = true;
    }
}

final class ProspectingRateLimitMiddlewareTest extends TestCase
{
    private const TENANT = 7;
    private const OUTRO_TENANT = 8;

    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $_SESSION['tenant_id'] = self::TENANT;
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        http_response_code(200);
        unset($_SESSION['tenant_id']);
    }

    private function registrarChamadas(int $quantidade, int $tenant): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO prospecting_requests (tenant_id) VALUES (:tid)');
        for ($i = 0; $i < $quantidade; $i++) {
            $stmt->execute([':tid' => $tenant]);
        }
    }

    private function totalDeChamadas(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM prospecting_requests')->fetchColumn();
    }

    public function testResponde429EmJsonQuandoOTenantEstouraAJanela(): void
    {
        $this->registrarChamadas(30, self::TENANT);

        $middleware = new ProspectingRateLimitSemSaida();
        $this->expectOutputString(
            '{"success":false,"error":"Limite de buscas atingido. Aguarde um instante antes de tentar novamente."}'
        );
        $middleware->handle();

        $this->assertTrue($middleware->terminou);
        $this->assertSame(429, http_response_code());
        // a chamada bloqueada nao conta na janela, como em producao, onde o exit vem antes do INSERT.
        $this->assertSame(30, $this->totalDeChamadas());
    }

    public function testDeixaPassarAbaixoDoLimiteERegistraAChamada(): void
    {
        $this->registrarChamadas(29, self::TENANT);

        $middleware = new ProspectingRateLimitSemSaida();
        $this->expectOutputString('');
        $middleware->handle();

        $this->assertFalse($middleware->terminou);
        $this->assertSame(30, $this->totalDeChamadas());
    }

    public function testChamadasDeOutroTenantNaoContam(): void
    {
        $this->registrarChamadas(30, self::OUTRO_TENANT);

        $middleware = new ProspectingRateLimitSemSaida();
        $this->expectOutputString('');
        $middleware->handle();

        $this->assertFalse($middleware->terminou);
    }
}
