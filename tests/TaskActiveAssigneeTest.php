<?php

declare(strict_types=1);

use App\Models\Task;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/GoogleTestDb.php';

final class TaskActiveAssigneeTest extends TestCase
{
    private const ANA  = 1; // ativa
    private const BIA  = 2; // inativa, mesmo tenant
    private const CAIO = 3; // ativo

    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = GoogleTestDb::setUp();
        $_SESSION['tenant_id'] = 1;

        $this->pdo->exec("INSERT INTO users (id, name, tenant_id, is_active) VALUES
            (1, 'Ana', 1, 1), (2, 'Bia', 1, 0), (3, 'Caio', 1, 1)");
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            GoogleTestDb::tearDown($this->pdo);
            $this->pdo = null;
        }
        unset($_SESSION['tenant_id']);
    }

    private function criar(int $responsavel): int
    {
        return (new Task())->create([
            'assigned_to' => $responsavel,
            'title'       => 'Ligar para cliente',
            'due_date'    => '2026-06-15 10:00:00',
            'priority'    => 'medium',
            'created_by'  => self::ANA,
        ]);
    }

    private function responsavelDe(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT assigned_to FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function testCreateAceitaResponsavelAtivo(): void
    {
        $id = $this->criar(self::CAIO);

        $this->assertSame(self::CAIO, $this->responsavelDe($id));
    }

    public function testCreateRecusaResponsavelInativo(): void
    {
        try {
            $this->criar(self::BIA);
            $this->fail('create aceitou um responsavel inativo');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    public function testUpdateRecusaResponsavelInativo(): void
    {
        $id = $this->criar(self::ANA);

        try {
            (new Task())->update($id, ['assigned_to' => self::BIA]);
            $this->fail('update aceitou um responsavel inativo');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(self::ANA, $this->responsavelDe($id));
    }
}
