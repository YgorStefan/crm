<?php

use App\Controllers\AboutController;
use PHPUnit\Framework\TestCase;

class AboutPageTest extends TestCase
{
    protected function setUp(): void
    {
        // o layout blank imprime o nonce que o CspMiddleware define na requisicao,
        // e o teste chama o controller direto, sem passar pelo middleware.
        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', 'nonce-de-teste');
        }
        // sem cookie a pagina destroi a sessao, que aqui e a do processo inteiro
        // do phpunit. o caminho sem cookie e conferido no e2e, pelo cabecalho.
        $_COOKIE[session_name()] = 'cookie-de-teste';
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[session_name()]);
    }

    private function renderPage(): string
    {
        ob_start();
        try {
            (new AboutController())->index();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    public function testSeApresentaComONomeDaTelaDeConsentimentoDoGoogle(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('<h1 class="text-xl font-bold text-white">CRM</h1>', $html);
    }

    public function testDizOQueOCrmFaz(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('Um sistema de gestão de clientes para equipes de vendas.', $html);
        $this->assertStringContainsString('funil de vendas', $html);
    }

    public function testExplicaAPermissaoDoGoogleAgendaEComoDesconectar(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('Ver, criar, mudar e excluir eventos nas suas agendas do Google', $html);
        $this->assertStringContainsString('as suas tarefas viram eventos na sua agenda principal', $html);
        $this->assertStringContainsString('use "Desconectar" no calendário do CRM', $html);
        $this->assertStringContainsString('href="https://myaccount.google.com/permissions"', $html);
    }

    public function testLinkaAPoliticaDePrivacidadeEOLogin(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('href="' . APP_URL . '/privacidade"', $html);
        $this->assertStringContainsString('href="' . APP_URL . '/login"', $html);
    }

    public function testTemOResumoEmInglesParaQuemAnalisaOApp(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringContainsString('Connecting Google Calendar is optional.', $html);
    }
}
