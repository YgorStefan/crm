<?php

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
use Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        RouterSpyController::reset();
    }

    private function dispatch(string $method, string $uri): Router
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $router = new Router();
        return $router;
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
}

}
