<?php

declare(strict_types=1);

use App\Controllers\ClientController;
use App\Models\Task;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

final class ClientArchiveGoogleTest extends TestCase
{
    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $this->pdo->exec("INSERT INTO users (id, name, tenant_id, role) VALUES (1, 'Ana', 1, 'admin')");
        $this->pdo->exec("INSERT INTO clients (id, name, tenant_id) VALUES (10, 'Arquivado', 1), (11, 'Outro', 1)");

        $_SESSION['tenant_id'] = 1;
        $_SESSION['user']      = ['id' => 1, 'role' => 'admin'];
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        unset($_SESSION['tenant_id'], $_SESSION['user'], $_SESSION['flash']);
    }

    /** o redirect de verdade da exit, e aqui ele so guarda o destino */
    private function controller(): ClientController
    {
        return new class extends ClientController {
            public ?string $destino = null;

            protected function redirect(string $path): void
            {
                $this->destino = $path;
            }
        };
    }

    private function tarefa(int $clientId, ?string $eventId, string $status, string $due = '+1 day'): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tasks
            (client_id, assigned_to, title, due_date, priority, status, created_by, tenant_id,
             google_event_id, google_sync_pending, google_changed_fields)
            VALUES (:cid, 1, 'Visita', NOW() + INTERVAL :dias DAY, 'medium', :status, 1, 1, :evt, 0, 0)");
        $stmt->execute([':cid' => $clientId, ':dias' => (int) $due, ':status' => $status, ':evt' => $eventId]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{0:int, 1:int} pendencia e campos marcados */
    private function marca(int $id): array
    {
        $linha = $this->pdo->query("SELECT google_sync_pending, google_changed_fields FROM tasks WHERE id = {$id}")->fetch();
        return [(int) $linha['google_sync_pending'], (int) $linha['google_changed_fields']];
    }

    public function testArquivarReenviaADescricaoDasTarefasAbertasDoClienteNaAgenda(): void
    {
        $aberta     = $this->tarefa(10, 'evtaberta', 'pending');
        $atrasada   = $this->tarefa(10, 'evtatrasada', 'in_progress', '-3 days');
        $concluida  = $this->tarefa(10, 'evtconcluida', 'done');
        $semEvento  = $this->tarefa(10, null, 'pending');
        $deOutro    = $this->tarefa(11, 'evtoutro', 'pending');
        $controller = $this->controller();

        $controller->destroy(['id' => 10]);

        $this->assertSame('/clients', $controller->destino);
        $this->assertSame([1, Task::GOOGLE_DESCRIPTION], $this->marca($aberta));
        $this->assertSame([1, Task::GOOGLE_DESCRIPTION], $this->marca($atrasada));
        $this->assertSame([0, 0], $this->marca($concluida));
        $this->assertSame([0, 0], $this->marca($semEvento));
        $this->assertSame([0, 0], $this->marca($deOutro));
    }
}
