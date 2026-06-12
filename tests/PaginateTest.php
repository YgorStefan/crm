<?php

use Core\Controller;
use PHPUnit\Framework\TestCase;

class PaginateTestController extends Controller
{
    public function exposedPaginate(string $module): array
    {
        return $this->paginate($module);
    }
}

class PaginateTest extends TestCase
{
    private PaginateTestController $controller;

    protected function setUp(): void
    {
        $this->controller = new PaginateTestController();
        $_GET = [];
        unset($_SESSION['per_page_mod_a'], $_SESSION['per_page_mod_b']);
    }

    public function testDefaults(): void
    {
        $this->assertSame([1, 25, 0], $this->controller->exposedPaginate('mod_a'));
    }

    public function testPaginaEOffset(): void
    {
        $_GET['page'] = '3';
        $this->assertSame([3, 25, 50], $this->controller->exposedPaginate('mod_a'));
    }

    public function testPerPageValidoEhPersistidoNaSessao(): void
    {
        $_GET['per_page'] = '50';
        $this->assertSame([1, 50, 0], $this->controller->exposedPaginate('mod_a'));
        $this->assertSame(50, $_SESSION['per_page_mod_a']);

        // Próxima requisição sem per_page reutiliza a sessão
        $_GET = [];
        $this->assertSame([1, 50, 0], $this->controller->exposedPaginate('mod_a'));
    }

    public function testPerPageInvalidoEhIgnorado(): void
    {
        $_GET['per_page'] = '9999';
        $this->assertSame([1, 25, 0], $this->controller->exposedPaginate('mod_a'));
        $this->assertArrayNotHasKey('per_page_mod_a', $_SESSION);
    }

    public function testPaginaInvalidaViraUm(): void
    {
        $_GET['page'] = '-5';
        $this->assertSame([1, 25, 0], $this->controller->exposedPaginate('mod_a'));
    }

    public function testModulosNaoCompartilhamPerPage(): void
    {
        $_GET['per_page'] = '100';
        $this->controller->exposedPaginate('mod_a');

        $_GET = [];
        $this->assertSame([1, 25, 0], $this->controller->exposedPaginate('mod_b'));
    }
}
