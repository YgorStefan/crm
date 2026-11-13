<?php

use Core\Controller;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ControllerRenderTest extends TestCase
{
    private mixed $requestUriAnterior = null;

    protected function setUp(): void
    {
        // os layouts imprimem o nonce que o CspMiddleware define na requisicao,
        // e o teste chama o render direto, sem passar pelo middleware.
        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', 'nonce-de-teste');
        }
        // o layout main le a uri para marcar o item ativo do menu, e no phpunit
        // ela nao existe.
        $this->requestUriAnterior = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/crm/public/privacidade';
    }

    protected function tearDown(): void
    {
        if ($this->requestUriAnterior === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $this->requestUriAnterior;
        }
    }

    /** @return array<string, array{string}> */
    public static function layouts(): array
    {
        return [
            'main'  => ['main'],
            'blank' => ['blank'],
        ];
    }

    private function renderPage(array $data, string $layout): string
    {
        $controller = new class extends Controller {
            public function show(string $view, array $data, string $layout): void
            {
                $this->render($view, $data, $layout);
            }
        };

        ob_start();
        try {
            $controller->show('privacy/index', $data, $layout);
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    #[DataProvider('layouts')]
    public function testTituloDaTelaChegaAoLayout(string $layout): void
    {
        $html = $this->renderPage(['title' => 'Clientes | CRM Teste'], $layout);

        $this->assertStringContainsString('<title>Clientes | CRM Teste</title>', $html);
    }

    #[DataProvider('layouts')]
    public function testTituloSaiEscapadoNoLayout(string $layout): void
    {
        $html = $this->renderPage(['title' => 'Vendas & <b>Metas</b>'], $layout);

        $this->assertStringContainsString('<title>Vendas &amp; &lt;b&gt;Metas&lt;/b&gt;</title>', $html);
    }

    #[DataProvider('layouts')]
    public function testSemTituloOLayoutUsaONomeDoApp(string $layout): void
    {
        $html = $this->renderPage([], $layout);

        $this->assertStringContainsString('<title>CRM Teste</title>', $html);
        $this->assertStringContainsString('Política de Privacidade', $html);
    }
}
