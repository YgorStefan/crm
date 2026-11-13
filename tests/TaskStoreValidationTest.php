<?php

use App\Controllers\TaskController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

class StoreRespondeu extends \RuntimeException
{
    public function __construct(
        public readonly string $via,
        public readonly mixed $payload,
        public readonly int $status,
    ) {
        parent::__construct($via);
    }
}

// json() e redirect() terminam em exit, que derrubaria o processo do phpunit; aqui eles lancam
// StoreRespondeu, que para o fluxo de store() no mesmo ponto em que o exit pararia e entrega a resposta ao teste.
class CapturingTaskController extends TaskController
{
    protected function json(mixed $data, int $status = 200): void
    {
        throw new StoreRespondeu('json', $data, $status);
    }

    protected function redirect(string $path): void
    {
        throw new StoreRespondeu('redirect', $path, 302);
    }
}

class TaskStoreValidationTest extends TestCase
{
    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $this->pdo->exec("INSERT INTO users (id, name, tenant_id) VALUES (1, 'Ana', 1), (2, 'Bob', 2)");
        $this->pdo->exec("INSERT INTO clients (id, name, tenant_id, is_active) VALUES
            (10, 'Cliente A', 1, 1), (11, 'Cliente Inativo', 1, 0), (20, 'Cliente B', 2, 1)");

        $_SESSION = [
            'tenant_id' => 1,
            'user'      => ['id' => 1, 'role' => 'admin'],
        ];
        $_POST = [
            'title'    => 'Ligar para o cliente',
            'due_date' => '2026-06-15T10:00',
        ];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    private function storeResponse(): StoreRespondeu
    {
        try {
            (new CapturingTaskController())->store();
        } catch (StoreRespondeu $resposta) {
            return $resposta;
        } catch (\InvalidArgumentException $e) {
            $this->fail('store() deixou a excecao subir: ' . $e->getMessage());
        }

        $this->fail('store() terminou sem responder por json() nem redirect()');
    }

    private function assertNenhumaTarefaGravada(): void
    {
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    private function assertRespondeu422(StoreRespondeu $resposta, string $mensagem): void
    {
        $this->assertSame('json', $resposta->via);
        $this->assertSame(422, $resposta->status);
        $this->assertSame(['success' => false, 'error' => $mensagem], $resposta->payload);
    }

    private function assertVoltouComFlash(StoreRespondeu $resposta, string $destino, string $mensagem): void
    {
        $this->assertSame('redirect', $resposta->via);
        $this->assertSame($destino, $resposta->payload);
        $this->assertSame(['type' => 'error', 'message' => $mensagem], $_SESSION['flash']);
    }

    public function testResponsavelDeOutroTenantViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['assigned_to'] = '2';

        $this->assertRespondeu422($this->storeResponse(), 'Responsável inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testClienteInativoViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['client_id'] = '11';

        $this->assertRespondeu422($this->storeResponse(), 'Cliente inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testClienteDeOutroTenantViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['client_id'] = '20';

        $this->assertRespondeu422($this->storeResponse(), 'Cliente inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testTituloVazioViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['title'] = '';

        $this->assertRespondeu422($this->storeResponse(), 'Título e prazo são obrigatórios.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testResponsavelInvalidoViaFormularioVoltaComFlash(): void
    {
        $_POST['assigned_to'] = '2';

        $this->assertVoltouComFlash($this->storeResponse(), '/tasks', 'Responsável inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testResponsavelInvalidoComClienteValidoViaFormularioVoltaParaATelaDoCliente(): void
    {
        $_POST['assigned_to'] = '2';
        $_POST['client_id'] = '10';

        $this->assertVoltouComFlash($this->storeResponse(), '/clients/10', 'Responsável inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testClienteInativoViaFormularioVoltaParaTarefas(): void
    {
        $_POST['client_id'] = '11';

        $this->assertVoltouComFlash($this->storeResponse(), '/tasks', 'Cliente inválido.');
        $this->assertNenhumaTarefaGravada();
    }
}
