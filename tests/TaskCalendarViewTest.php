<?php

use Core\Controller;
use PHPUnit\Framework\TestCase;

class TaskCalendarViewTest extends TestCase
{
    private mixed $userAnterior = null;

    protected function setUp(): void
    {
        // a view imprime o nonce que o CspMiddleware define na requisicao, e o
        // teste chama o render direto, sem passar pelo middleware.
        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', 'nonce-de-teste');
        }
        $this->userAnterior = $_SESSION['user'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->userAnterior === null) {
            unset($_SESSION['user']);
        } else {
            $_SESSION['user'] = $this->userAnterior;
        }
    }

    private function renderTasks(string $role, bool $enabled, ?string $connection, ?string $email): string
    {
        $_SESSION['user'] = ['id' => 5, 'role' => $role, 'name' => 'Pessoa Teste'];

        $controller = new class extends Controller {
            public function show(array $data): void
            {
                $this->render('tasks/index', $data, 'blank');
            }
        };

        ob_start();
        try {
            $controller->show([
                'overdue'          => [],
                'users'            => [],
                'googleEnabled'    => $enabled,
                'googleConnection' => $connection,
                'googleEmail'      => $email,
            ]);
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    public function testSemCredencialNaoTemBarraDoGoogle(): void
    {
        $html = $this->renderTasks('admin', false, null, null);

        $this->assertStringNotContainsString('data-google-bar', $html);
        $this->assertStringNotContainsString('/google/connect', $html);
        $this->assertStringNotContainsString('data-google-team', $html);
    }

    public function testModalDoEventoEAvisoDoFeedExistemMesmoSemCredencial(): void
    {
        $html = $this->renderTasks('seller', false, null, null);

        $this->assertStringContainsString('id="modalGoogleEvent"', $html);
        $this->assertStringContainsString('id="googleFeedNotice"', $html);
    }

    public function testSemContaMostraConectarComAvisoDaPermissao(): void
    {
        $html = $this->renderTasks('seller', true, null, null);

        $this->assertStringContainsString('data-google-bar', $html);
        $this->assertStringContainsString('href="http://localhost/crm/public/google/connect"', $html);
        $this->assertStringContainsString('Conectar Google Agenda', $html);
        $this->assertStringContainsString('data-google-hint', $html);
        $this->assertStringContainsString('marque a permissão', $html);
        $this->assertStringNotContainsString('data-google-disconnect', $html);
    }

    public function testSemContaLinkaAPrivacidadeNoAvisoEAoLadoDoBotao(): void
    {
        $html = $this->renderTasks('seller', true, null, null);

        $this->assertStringContainsString('Como o CRM usa a sua agenda está na', $html);
        $this->assertSame(2, substr_count($html, 'href="http://localhost/crm/public/privacidade" target="_blank" rel="noopener noreferrer"'));
    }

    public function testContaAtivaNaoRepeteOLinkDaPrivacidade(): void
    {
        $html = $this->renderTasks('seller', true, 'active', 'pessoa@example.com');

        $this->assertStringNotContainsString('/privacidade', $html);
    }

    public function testContaAtivaMostraEmailEDesconectar(): void
    {
        $html = $this->renderTasks('seller', true, 'active', 'pessoa@example.com');

        $this->assertStringContainsString('Conectado como', $html);
        $this->assertStringContainsString('pessoa@example.com', $html);
        $this->assertStringContainsString('data-google-disconnect', $html);
        $this->assertStringNotContainsString('Conectar Google Agenda', $html);
        $this->assertStringNotContainsString('data-google-hint', $html);
    }

    public function testEmailSaiEscapado(): void
    {
        $html = $this->renderTasks('seller', true, 'active', 'x"><b>@example.com');

        $this->assertStringContainsString('x&quot;&gt;&lt;b&gt;@example.com', $html);
        $this->assertStringNotContainsString('x"><b>@example.com', $html);
    }

    public function testContaRevogadaPedeReconectarEDeixaDesconectar(): void
    {
        $html = $this->renderTasks('seller', true, 'revoked', 'pessoa@example.com');

        $this->assertStringContainsString('Reconectar', $html);
        $this->assertStringContainsString('href="http://localhost/crm/public/google/connect"', $html);
        $this->assertStringContainsString('data-google-disconnect', $html);
        $this->assertStringContainsString('Desconectar', $html);
    }

    public function testEquipeSoParaAdminConectado(): void
    {
        $this->assertStringContainsString('data-google-team', $this->renderTasks('admin', true, 'active', 'pessoa@example.com'));
        $this->assertStringNotContainsString('data-google-team', $this->renderTasks('seller', true, 'active', 'pessoa@example.com'));
        $this->assertStringNotContainsString('data-google-team', $this->renderTasks('admin', true, null, null));
        $this->assertStringNotContainsString('data-google-team', $this->renderTasks('admin', true, 'revoked', 'pessoa@example.com'));
    }
}
