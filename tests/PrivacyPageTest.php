<?php

use App\Controllers\PrivacyController;
use PHPUnit\Framework\TestCase;

class PrivacyPageTest extends TestCase
{
    protected function setUp(): void
    {
        // o layout blank imprime o nonce que o CspMiddleware define na requisicao,
        // e o teste chama o controller direto, sem passar pelo middleware.
        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', 'nonce-de-teste');
        }
        // o env() dos testes cai no getenv quando o $_ENV nao tem a chave, entao
        // o ambiente do processo tambem e limpo.
        unset($_ENV['APP_CONTACT_EMAIL']);
        putenv('APP_CONTACT_EMAIL');
        // sem cookie a pagina destroi a sessao, que aqui e a do processo inteiro
        // do phpunit. o caminho sem cookie e conferido no e2e, pelo cabecalho.
        $_COOKIE[session_name()] = 'cookie-de-teste';
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_CONTACT_EMAIL'], $_COOKIE[session_name()]);
        putenv('APP_CONTACT_EMAIL');
    }

    private function renderPage(): string
    {
        ob_start();
        try {
            (new PrivacyController())->index();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    public function testComEmailDeContatoMostraLinkMailto(): void
    {
        $_ENV['APP_CONTACT_EMAIL'] = 'contato@example.com';

        $html = $this->renderPage();

        $this->assertStringContainsString('href="mailto:contato@example.com"', $html);
        $this->assertStringNotContainsString('Fale com o administrador da sua empresa no CRM.', $html);
    }

    public function testSemEmailDeContatoOrientaProcurarOAdministrador(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('Fale com o administrador da sua empresa no CRM.', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }

    public function testEmailDeContatoInvalidoOrientaProcurarOAdministrador(): void
    {
        $_ENV['APP_CONTACT_EMAIL'] = 'x"><script>@example.com';

        $html = $this->renderPage();

        $this->assertStringContainsString('Fale com o administrador da sua empresa no CRM.', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('<script>@', $html);
    }

    public function testEmailDeContatoDoAmbienteDoProcessoTambemValeEPassaPelaValidacao(): void
    {
        putenv('APP_CONTACT_EMAIL=nao-e-email');

        $html = $this->renderPage();

        $this->assertStringContainsString('Fale com o administrador da sua empresa no CRM.', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }

    public function testEmailDeContatoValidoSaiEscapado(): void
    {
        $_ENV['APP_CONTACT_EMAIL'] = "o'neil&co@example.com";

        $html = $this->renderPage();

        $this->assertStringContainsString('href="mailto:o&#039;neil&amp;co@example.com"', $html);
        $this->assertStringNotContainsString("o'neil&co@", $html);
    }

    public function testTextoDeclaraOQueOCrmGuardaECompartilha(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('Nome, e-mail e senha (guardada só como hash) dos usuários', $html);
        $this->assertStringNotContainsString('foto de perfil', $html);
        $this->assertStringContainsString('registra o IP e o e-mail digitado em cada tentativa de login', $html);
        $this->assertStringContainsString('dois tokens, um de acesso e um de renovação, guardados cifrados', $html);
        $this->assertStringContainsString('Com quem os dados são compartilhados.', $html);
        $this->assertStringNotContainsString('O que não fazemos.', $html);
        $this->assertStringContainsString('ao ViaCEP, os CEPs consultados no cadastro', $html);
        $this->assertStringContainsString('da jsDelivr e da SheetJS', $html);
        $this->assertStringContainsString('incluindo os requisitos de Uso Limitado', $html);
        $this->assertStringContainsString('Para apagá-los, use o contato abaixo.', $html);
    }

    public function testEventoDoGoogleNaoLevaOLinkDaFicha(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('os eventos das suas tarefas, com a descrição e o nome do cliente;', $html);
        $this->assertStringNotContainsString('link da ficha', $html);
        $this->assertStringContainsString('Atualizada em 14/11/2026.', $html);
    }
}
