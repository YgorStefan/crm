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

    private function moldeSemanal(int $responsavel): int
    {
        $this->pdo->prepare(
            "INSERT INTO tasks (assigned_to, created_by, tenant_id, title, due_date, priority, status, recurrence_type)
             VALUES (:responsavel, 1, 1, 'Reuniao semanal', :prazo, 'medium', 'pending', 'weekly')"
        )->execute([':responsavel' => $responsavel, ':prazo' => (new \DateTime('+1 day'))->format('Y-m-d H:i:s')]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<int> um responsavel por ocorrencia gerada */
    private function responsaveisDasOcorrencias(int $molde): array
    {
        $stmt = $this->pdo->prepare('SELECT assigned_to FROM tasks WHERE recurrence_parent_id = :pid');
        $stmt->execute([':pid' => $molde]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function abrirCalendarioComoAdmin(): void
    {
        (new Task())->materializeRecurring(self::ANA, true);
    }

    public function testSerieDeResponsavelInativoNaoGeraOcorrencias(): void
    {
        $molde = $this->moldeSemanal(self::BIA);

        $this->abrirCalendarioComoAdmin();

        $this->assertSame([], $this->responsaveisDasOcorrencias($molde));
    }

    public function testSerieVoltaAGerarQuandoOResponsavelEReativado(): void
    {
        $molde = $this->moldeSemanal(self::BIA);
        $this->abrirCalendarioComoAdmin();
        $this->assertSame([], $this->responsaveisDasOcorrencias($molde));

        $this->pdo->exec('UPDATE users SET is_active = 1 WHERE id = ' . self::BIA);
        $this->abrirCalendarioComoAdmin();

        $this->assertNotEmpty($this->responsaveisDasOcorrencias($molde));
    }

    public function testSerieVoltaAGerarQuandoOAdminPassaAOutroResponsavelAtivo(): void
    {
        $molde = $this->moldeSemanal(self::BIA);
        $this->abrirCalendarioComoAdmin();
        $this->assertSame([], $this->responsaveisDasOcorrencias($molde));

        (new Task())->update($molde, ['assigned_to' => self::CAIO]);
        $this->abrirCalendarioComoAdmin();

        $responsaveis = $this->responsaveisDasOcorrencias($molde);
        $this->assertNotEmpty($responsaveis);
        $this->assertSame([self::CAIO], array_values(array_unique($responsaveis)));
    }

    public function testOcorrenciasJaCriadasFicamQuandoOResponsavelEDesativado(): void
    {
        $molde = $this->moldeSemanal(self::CAIO);
        $this->abrirCalendarioComoAdmin();
        $antes = $this->responsaveisDasOcorrencias($molde);
        $this->assertNotEmpty($antes);

        $this->pdo->exec('UPDATE users SET is_active = 0 WHERE id = ' . self::CAIO);
        $this->abrirCalendarioComoAdmin();

        $this->assertSame($antes, $this->responsaveisDasOcorrencias($molde));
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
