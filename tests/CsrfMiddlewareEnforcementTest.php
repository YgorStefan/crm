<?php

use Core\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * TestableCsrfMiddleware sobrescreve reject() (extraído de CsrfMiddleware
 * exatamente para isso) para capturar a rejeição em vez de encerrar o
 * processo do PHPUnit com die().
 */
class TestableCsrfMiddleware extends CsrfMiddleware
{
    public bool $rejected = false;
    public ?string $rejectMessage = null;

    protected function reject(string $message): void
    {
        $this->rejected = true;
        $this->rejectMessage = $message;
    }
}

class CsrfMiddlewareEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SESSION['csrf_token'], $_POST['_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function testAceitaRequisicaoComTokenValidoNoFormulario(): void
    {
        $_SESSION['csrf_token'] = str_repeat('a', 64);
        $_POST['_csrf_token']   = str_repeat('a', 64);

        $middleware = new TestableCsrfMiddleware();
        $middleware->handle();

        $this->assertFalse($middleware->rejected);
    }

    public function testAceitaRequisicaoComTokenValidoNoHeader(): void
    {
        $_SESSION['csrf_token']         = str_repeat('b', 64);
        $_SERVER['HTTP_X_CSRF_TOKEN']   = str_repeat('b', 64);

        $middleware = new TestableCsrfMiddleware();
        $middleware->handle();

        $this->assertFalse($middleware->rejected);
    }

    public function testRejeitaQuandoTokenAusente(): void
    {
        $_SESSION['csrf_token'] = str_repeat('c', 64);
        // Nenhum token enviado via POST/header

        $middleware = new TestableCsrfMiddleware();
        $middleware->handle();

        $this->assertTrue($middleware->rejected);
    }

    public function testRejeitaQuandoTokenNaoConfereComOSalvoNaSessao(): void
    {
        $_SESSION['csrf_token'] = str_repeat('d', 64);
        $_POST['_csrf_token']   = str_repeat('e', 64);

        $middleware = new TestableCsrfMiddleware();
        $middleware->handle();

        $this->assertTrue($middleware->rejected);
    }

    public function testRejeitaQuandoNaoHaTokenNaSessao(): void
    {
        unset($_SESSION['csrf_token']);
        $_POST['_csrf_token'] = str_repeat('f', 64);

        $middleware = new TestableCsrfMiddleware();
        $middleware->handle();

        $this->assertTrue($middleware->rejected);
    }
}
