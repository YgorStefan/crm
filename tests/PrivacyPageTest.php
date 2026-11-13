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
        unset($_ENV['APP_CONTACT_EMAIL']);
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_CONTACT_EMAIL']);
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

    public function testEmailDeContatoSaiEscapado(): void
    {
        $_ENV['APP_CONTACT_EMAIL'] = 'x"><script>@example.com';

        $html = $this->renderPage();

        $this->assertStringNotContainsString('x"><script>', $html);
        $this->assertStringContainsString('x&quot;&gt;&lt;script&gt;@example.com', $html);
    }
}
