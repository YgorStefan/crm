<?php

use Core\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

class CsrfTokenTest extends TestCase
{
    public function testGenerateTokenRetornaHexDe64Caracteres(): void
    {
        $token = CsrfMiddleware::generateToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateTokenEhUnicoPorChamada(): void
    {
        $this->assertNotSame(CsrfMiddleware::generateToken(), CsrfMiddleware::generateToken());
    }

    public function testGetTokenReutilizaTokenDaSessao(): void
    {
        $_SESSION['csrf_token'] = str_repeat('a', 64);
        $this->assertSame(str_repeat('a', 64), CsrfMiddleware::getToken());
        unset($_SESSION['csrf_token']);
    }

    public function testGetTokenGeraQuandoSessaoVazia(): void
    {
        unset($_SESSION['csrf_token']);
        $token = CsrfMiddleware::getToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $_SESSION['csrf_token']);
        unset($_SESSION['csrf_token']);
    }
}
