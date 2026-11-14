<?php

// Middlewares fake registrados no namespace que o Router resolve.
namespace Core\Middleware {
    class SpyMiddlewareBase
    {
        public static array $executionLog = [];

        public function handle(): void
        {
            self::$executionLog[] = static::class;
        }
    }

    class SpyDefaultMiddleware extends SpyMiddlewareBase {}
    class SpyRouteMiddleware extends SpyMiddlewareBase {}
    class SpyGlobalMiddleware extends SpyMiddlewareBase {}
}

// Controller fake registrado no namespace que o Router resolve.
namespace App\Controllers {
    class RouterSpyController
    {
        public static ?string $calledAction = null;
        public static array $receivedParams = [];

        public function __call(string $action, array $args): void
        {
            self::$calledAction = $action;
            self::$receivedParams = $args[0] ?? [];
        }

        public static function reset(): void
        {
            self::$calledAction = null;
            self::$receivedParams = [];
        }
    }
}

namespace {

use App\Controllers\RouterSpyController;
use Core\Middleware\SpyDefaultMiddleware;
use Core\Middleware\SpyGlobalMiddleware;
use Core\Middleware\SpyMiddlewareBase;
use Core\Middleware\SpyRouteMiddleware;
use Core\Router;
use PHPUnit\Framework\TestCase;

// o exit do fim da resposta encerraria o processo do phpunit com codigo 0 e sem resumo,
// e a suite pareceria verde com testes que nem rodaram; aqui ele so marca que a resposta acabou.
class RouterSemSaida extends Router
{
    public bool $terminou = false;

    protected function terminate(): void
    {
        $this->terminou = true;
    }
}

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        RouterSpyController::reset();
        SpyMiddlewareBase::$executionLog = [];
    }

    protected function tearDown(): void
    {
        http_response_code(200);
    }

    private function dispatch(string $method, string $uri): RouterSemSaida
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $router = new RouterSemSaida();
        return $router;
    }

    public function testRotaInexistenteResponde404SemEncerrarOProcesso(): void
    {
        $router = $this->dispatch('GET', '/crm/public/rota-que-nao-existe');
        $router->get('/clients', 'RouterSpyController', 'index');

        $this->expectOutputRegex('/Página não encontrada/');
        $router->dispatch();

        $this->assertSame(404, http_response_code());
        $this->assertTrue($router->terminou);
        $this->assertNull(RouterSpyController::$calledAction);
    }

    public function testRotaSimplesChamaActionCorreta(): void
    {
        $router = $this->dispatch('GET', '/crm/public/clients');
        $router->get('/clients', 'RouterSpyController', 'index');
        $router->dispatch();

        $this->assertSame('index', RouterSpyController::$calledAction);
        $this->assertSame([], RouterSpyController::$receivedParams);
    }

    public function testParametroIdEhExtraidoComoNumero(): void
    {
        $router = $this->dispatch('GET', '/crm/public/clients/42/edit');
        $router->get('/clients/{id}/edit', 'RouterSpyController', 'edit');
        $router->dispatch();

        $this->assertSame('edit', RouterSpyController::$calledAction);
        $this->assertSame(['id' => '42'], RouterSpyController::$receivedParams);
    }

    public function testQueryStringEhIgnoradaNoMatch(): void
    {
        $router = $this->dispatch('GET', '/crm/public/clients?search=foo&page=2');
        $router->get('/clients', 'RouterSpyController', 'index');
        $router->dispatch();

        $this->assertSame('index', RouterSpyController::$calledAction);
    }

    public function testMetodoHttpDiferenciaRotasComMesmoPadrao(): void
    {
        $router = $this->dispatch('POST', '/crm/public/clients/store');
        $router->get('/clients/store', 'RouterSpyController', 'naoDeveriaChamar');
        $router->post('/clients/store', 'RouterSpyController', 'store');
        $router->dispatch();

        $this->assertSame('store', RouterSpyController::$calledAction);
    }

    public function testMultiplosParametrosNomeados(): void
    {
        $router = $this->dispatch('POST', '/crm/public/clients/7/sales/13/delete');
        $router->post('/clients/{id}/sales/{sale_id}/delete', 'RouterSpyController', 'destroySale');
        $router->dispatch();

        $this->assertSame('destroySale', RouterSpyController::$calledAction);
        $this->assertSame(['id' => '7', 'sale_id' => '13'], RouterSpyController::$receivedParams);
    }

    public function testParametroSlugAceitaTexto(): void
    {
        $router = $this->dispatch('POST', '/crm/public/cold-contacts/month/2026-05/delete');
        $router->post('/cold-contacts/month/{year_month}/delete', 'RouterSpyController', 'deleteMonth');
        $router->dispatch();

        $this->assertSame(['year_month' => '2026-05'], RouterSpyController::$receivedParams);
    }

    public function testMiddlewaresExecutamNaOrdemDefaultRotaGlobal(): void
    {
        $router = $this->dispatch('GET', '/crm/public/clients');
        $router->setDefaultMiddlewares(['SpyDefaultMiddleware']);
        $router->setGlobalMiddlewares(['SpyGlobalMiddleware']);
        $router->get('/clients', 'RouterSpyController', 'index', ['SpyRouteMiddleware']);
        $router->dispatch();

        $this->assertSame(
            [SpyDefaultMiddleware::class, SpyRouteMiddleware::class, SpyGlobalMiddleware::class],
            SpyMiddlewareBase::$executionLog
        );
        $this->assertSame('index', RouterSpyController::$calledAction);
    }

    public function testRotaPublicaNaoExecutaMiddlewareDefault(): void
    {
        $router = $this->dispatch('GET', '/crm/public/login');
        $router->setDefaultMiddlewares(['SpyDefaultMiddleware']);
        $router->setGlobalMiddlewares(['SpyGlobalMiddleware']);
        $router->get('/login', 'RouterSpyController', 'loginForm', [], public: true);
        $router->dispatch();

        $this->assertSame([SpyGlobalMiddleware::class], SpyMiddlewareBase::$executionLog);
        $this->assertSame('loginForm', RouterSpyController::$calledAction);
    }

    public function testHeadChamaAActionDaRotaGet(): void
    {
        $router = $this->dispatch('HEAD', '/crm/public/privacidade');
        $router->get('/privacidade', 'RouterSpyController', 'index', [], public: true);
        $router->dispatch();

        $this->assertSame('index', RouterSpyController::$calledAction);
    }

    public function testHeadNaoCasaRotaPostComOMesmoPadrao(): void
    {
        $router = $this->dispatch('HEAD', '/crm/public/clients/store');
        $router->post('/clients/store', 'RouterSpyController', 'store');
        $router->get('/clients/store', 'RouterSpyController', 'show');
        $router->dispatch();

        $this->assertSame('show', RouterSpyController::$calledAction);
    }

    public function testHeadEmRotaProtegidaExecutaOsMesmosMiddlewaresDoGet(): void
    {
        $router = $this->dispatch('HEAD', '/crm/public/clients');
        $router->setDefaultMiddlewares(['SpyDefaultMiddleware']);
        $router->setGlobalMiddlewares(['SpyGlobalMiddleware']);
        $router->get('/clients', 'RouterSpyController', 'index', ['SpyRouteMiddleware']);
        $router->dispatch();

        $this->assertSame(
            [SpyDefaultMiddleware::class, SpyRouteMiddleware::class, SpyGlobalMiddleware::class],
            SpyMiddlewareBase::$executionLog
        );
        $this->assertSame('index', RouterSpyController::$calledAction);
    }

    public function testHeadEmRotaPublicaNaoExecutaMiddlewareDefault(): void
    {
        $router = $this->dispatch('HEAD', '/crm/public/login');
        $router->setDefaultMiddlewares(['SpyDefaultMiddleware']);
        $router->setGlobalMiddlewares(['SpyGlobalMiddleware']);
        $router->get('/login', 'RouterSpyController', 'loginForm', [], public: true);
        $router->dispatch();

        $this->assertSame([SpyGlobalMiddleware::class], SpyMiddlewareBase::$executionLog);
        $this->assertSame('loginForm', RouterSpyController::$calledAction);
    }
}

}
