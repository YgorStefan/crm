<?php

use Core\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * TestableAuthMiddleware sobrescreve redirect() (extraído de AuthMiddleware
 * exatamente para isso) para capturar o destino do redirecionamento em vez
 * de encerrar o processo do PHPUnit com exit.
 */
class TestableAuthMiddleware extends AuthMiddleware
{
    public ?string $redirectedTo = null;
    public bool $sessionDestroyed = false;

    protected function redirect(string $url): void
    {
        $this->redirectedTo = $url;
    }

    protected function destroySession(): void
    {
        // Não destrói de fato a sessão real do processo do PHPUnit —
        // apenas simula o efeito esperado em $_SESSION (assim como o
        // session_unset() real faria) e registra que foi chamado.
        $this->sessionDestroyed = true;
        $_SESSION = [];
    }
}

class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/clients';
    }

    public function testRedirecionaParaLoginQuandoNaoAutenticado(): void
    {
        $middleware = new TestableAuthMiddleware();
        $middleware->handle();

        $this->assertSame(APP_URL . '/login', $middleware->redirectedTo);
        $this->assertSame('/clients', $_SESSION['redirect_after_login']);
    }

    public function testPermiteAcessoComSessaoValida(): void
    {
        $_SESSION['user'] = ['id' => 1, 'name' => 'Ana'];
        $_SESSION['last_activity'] = time();

        $middleware = new TestableAuthMiddleware();
        $middleware->handle();

        $this->assertNull($middleware->redirectedTo);
    }

    public function testRedirecionaParaLoginComTimeoutAposInatividade(): void
    {
        $_SESSION['user'] = ['id' => 1, 'name' => 'Ana'];
        $_SESSION['last_activity'] = time() - (SESSION_LIFETIME + 100);

        $middleware = new TestableAuthMiddleware();
        $middleware->handle();

        $this->assertSame(APP_URL . '/login?timeout=1', $middleware->redirectedTo);
        $this->assertTrue($middleware->sessionDestroyed);
    }

    public function testAtualizaUltimaAtividadeQuandoSessaoValida(): void
    {
        $_SESSION['user'] = ['id' => 1, 'name' => 'Ana'];
        $_SESSION['last_activity'] = time() - 10;

        $before = $_SESSION['last_activity'];
        $middleware = new TestableAuthMiddleware();
        $middleware->handle();

        $this->assertGreaterThan($before, $_SESSION['last_activity']);
    }

    public function testForcaTrocaDeSenhaQuandoFlagAtiva(): void
    {
        $_SESSION['user'] = ['id' => 1, 'name' => 'Ana', 'password_must_change' => true];
        $_SESSION['last_activity'] = time();
        $_SERVER['REQUEST_URI'] = '/clients';

        $middleware = new TestableAuthMiddleware();
        $middleware->handle();

        $this->assertSame(APP_URL . '/profile/change-password', $middleware->redirectedTo);
    }

    public function testNaoBloqueiaRotaDeTrocaDeSenhaQuandoFlagAtiva(): void
    {
        $_SESSION['user'] = ['id' => 1, 'name' => 'Ana', 'password_must_change' => true];
        $_SESSION['last_activity'] = time();
        $_SERVER['REQUEST_URI'] = '/profile/change-password';

        $middleware = new TestableAuthMiddleware();
        $middleware->handle();

        $this->assertNull($middleware->redirectedTo);
    }

    public function testNaoBloqueiaLogoutQuandoFlagAtiva(): void
    {
        $_SESSION['user'] = ['id' => 1, 'name' => 'Ana', 'password_must_change' => true];
        $_SESSION['last_activity'] = time();
        $_SERVER['REQUEST_URI'] = '/logout';

        $middleware = new TestableAuthMiddleware();
        $middleware->handle();

        $this->assertNull($middleware->redirectedTo);
    }
}
