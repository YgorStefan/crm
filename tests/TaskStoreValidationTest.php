<?php

use App\Controllers\TaskController;
use App\Models\Task;
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
        return $this->resposta(fn () => (new CapturingTaskController())->store());
    }

    private function updateResponse(int $id): StoreRespondeu
    {
        return $this->resposta(fn () => (new CapturingTaskController())->update(['id' => (string) $id]));
    }

    private function destroyResponse(int $id): StoreRespondeu
    {
        return $this->resposta(fn () => (new CapturingTaskController())->destroy(['id' => (string) $id]));
    }

    private function resposta(callable $acao): StoreRespondeu
    {
        try {
            $acao();
        } catch (StoreRespondeu $resposta) {
            return $resposta;
        } catch (\InvalidArgumentException | \PDOException $e) {
            $this->fail('a acao deixou a excecao subir: ' . $e->getMessage());
        }

        $this->fail('a acao terminou sem responder por json() nem redirect()');
    }

    private function assertNenhumaTarefaGravada(): void
    {
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    private function tarefaExistente(): int
    {
        $this->pdo->exec("INSERT INTO tasks (assigned_to, created_by, tenant_id, title, due_date, priority, status)
            VALUES (1, 1, 1, 'Tarefa existente', '2026-06-15 10:00:00', 'medium', 'pending')");
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function unicaTarefa(): array
    {
        $linhas = $this->pdo->query('SELECT title, due_date, priority FROM tasks')->fetchAll();
        $this->assertCount(1, $linhas);
        return $linhas[0];
    }

    private function assertRespondeu422(StoreRespondeu $resposta, string $mensagem): void
    {
        $this->assertRespondeuErro($resposta, 422, $mensagem);
    }

    private function assertRespondeuErro(StoreRespondeu $resposta, int $status, string $mensagem): void
    {
        $this->assertSame('json', $resposta->via);
        $this->assertSame($status, $resposta->status);
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

    public function testPrazoNoFormatoDoNavegadorGrava(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['priority'] = 'high';

        $resposta = $this->storeResponse();

        $this->assertSame('json', $resposta->via);
        $this->assertSame(200, $resposta->status);
        $this->assertSame(
            ['title' => 'Ligar para o cliente', 'due_date' => '2026-06-15 10:00:00', 'priority' => 'high'],
            $this->unicaTarefa(),
        );
    }

    public function testPrazoComEspacoNoLugarDoTGrava(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['due_date'] = '2026-06-15 10:00';

        $this->assertSame(200, $this->storeResponse()->status);
        $this->assertSame('2026-06-15 10:00:00', $this->unicaTarefa()['due_date']);
    }

    public function testPrioridadeForaDaListaViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['priority'] = 'urgente';

        $this->assertRespondeu422($this->storeResponse(), 'Prioridade inválida.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testPrazoMalformadoViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['due_date'] = 'amanha';

        $this->assertRespondeu422($this->storeResponse(), 'Prazo inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testPrazoComDiaInexistenteViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['due_date'] = '2026-02-30T10:00';

        $this->assertRespondeu422($this->storeResponse(), 'Prazo inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testPrazoInvalidoViaFormularioComClienteVoltaParaTarefas(): void
    {
        $_POST['due_date'] = '2026-06-15T10:00:00';
        $_POST['client_id'] = '10';

        $this->assertVoltouComFlash($this->storeResponse(), '/tasks', 'Prazo inválido.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testFormularioSemTituloComClienteDeOutroTenantVoltaParaTarefas(): void
    {
        $_POST['title'] = '';
        $_POST['client_id'] = '20';

        $this->assertVoltouComFlash($this->storeResponse(), '/tasks', 'Título e prazo são obrigatórios.');
        $this->assertNenhumaTarefaGravada();
    }

    public function testPrazoNoBuracoDoHorarioDeVeraoGuardaOHorarioDigitado(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['due_date'] = '2018-11-04T00:30';

        $this->assertSame(200, $this->storeResponse()->status);
        $this->assertSame('2018-11-04 00:30:00', $this->unicaTarefa()['due_date']);
    }

    public function testEdicaoComPrazoNoFormatoDoNavegadorGrava(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $id = $this->tarefaExistente();
        $_POST = ['due_date' => '2026-07-01T09:30'];

        $this->assertSame(200, $this->updateResponse($id)->status);
        $this->assertSame('2026-07-01 09:30:00', $this->unicaTarefa()['due_date']);
    }

    public function testEdicaoComPrazoMalformadoViaAjaxRecebe422(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $id = $this->tarefaExistente();
        $_POST = ['due_date' => 'amanha'];

        $this->assertRespondeu422($this->updateResponse($id), 'Prazo inválido.');
        $this->assertSame('2026-06-15 10:00:00', $this->unicaTarefa()['due_date']);
    }

    public function testEdicaoComPrazoComDiaInexistenteViaFormularioVoltaComFlash(): void
    {
        $id = $this->tarefaExistente();
        $_POST = ['due_date' => '2026-02-30T10:00'];

        $this->assertVoltouComFlash($this->updateResponse($id), '/tasks', 'Prazo inválido.');
        $this->assertSame('2026-06-15 10:00:00', $this->unicaTarefa()['due_date']);
    }

    public function testEdicaoDeTarefaInexistenteViaAjaxRecebe404(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST = ['status' => 'done'];

        $this->assertRespondeuErro($this->updateResponse(999), 404, 'Tarefa não encontrada.');
    }

    public function testEdicaoDeTarefaInexistenteViaFormularioVoltaComFlash(): void
    {
        $_POST = ['status' => 'done'];

        $this->assertVoltouComFlash($this->updateResponse(999), '/tasks', 'Tarefa não encontrada.');
    }

    public function testRemocaoDeTarefaInexistenteViaAjaxRecebe404(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST = [];

        $this->assertRespondeuErro($this->destroyResponse(999), 404, 'Tarefa não encontrada.');
    }

    public function testEdicaoPorSellerSemPermissaoRecebe403AntesDeValidarOPrazo(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $id = $this->tarefaExistente();
        $_SESSION['user'] = ['id' => 3, 'role' => 'seller'];
        $_POST = ['due_date' => 'amanha'];

        $this->assertRespondeuErro($this->updateResponse($id), 403, 'Acesso negado.');
        $this->assertSame('2026-06-15 10:00:00', $this->unicaTarefa()['due_date']);
    }

    public function testEdicaoDeTarefaInexistenteRecebe404AntesDeValidarOPrazo(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST = ['due_date' => 'amanha'];

        $this->assertRespondeuErro($this->updateResponse(999), 404, 'Tarefa não encontrada.');
    }

    private function codigoDaRecusaDoModel(callable $acao): int
    {
        try {
            $acao();
        } catch (\InvalidArgumentException $e) {
            return $e->getCode();
        }

        $this->fail('o model aceitou o dado invalido');
    }

    /** @param array<string, mixed> $campos */
    private function criarPeloModel(array $campos): int
    {
        return (new Task())->create(array_merge([
            'assigned_to' => 1,
            'title'       => 'Ligar para o cliente',
            'due_date'    => '2026-06-15 10:00:00',
            'created_by'  => 1,
        ], $campos));
    }

    public function testModelDizQueOClienteFalhou(): void
    {
        $this->assertSame(
            Task::INVALID_CLIENT,
            $this->codigoDaRecusaDoModel(fn () => $this->criarPeloModel(['client_id' => 11])),
        );
    }

    public function testModelDizQueOResponsavelFalhou(): void
    {
        $this->assertSame(
            Task::INVALID_ASSIGNEE,
            $this->codigoDaRecusaDoModel(fn () => $this->criarPeloModel(['assigned_to' => 2])),
        );
    }

    public function testModelConfereOClienteAntesDoResponsavel(): void
    {
        $this->assertSame(
            Task::INVALID_CLIENT,
            $this->codigoDaRecusaDoModel(fn () => $this->criarPeloModel(['client_id' => 20, 'assigned_to' => 2])),
        );
    }

    public function testModelDizQueOResponsavelFalhouNaEdicao(): void
    {
        $id = $this->tarefaExistente();

        $this->assertSame(
            Task::INVALID_ASSIGNEE,
            $this->codigoDaRecusaDoModel(fn () => (new Task())->update($id, ['assigned_to' => 2])),
        );
    }
}
