<?php

namespace App\Models;

use Core\Model;

class PipelineStage extends Model
{
    protected string $table = 'pipeline_stages';

    // pipeline_stages é uma tabela global (sem tenant_id), compartilhada por todos os tenants
    protected bool $isGlobal = true;

    /**
     * Retorna todas as etapas ordenadas pela posição (coluna do Kanban).
     */
    public function findAllOrdered(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM pipeline_stages ORDER BY position ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Cria uma nova etapa no funil.
     */
    public function create(array $data): int
    {
        // Descobre qual é a maior posição atual para colocar a nova etapa no final
        $maxPos = $this->db->query("SELECT COALESCE(MAX(position), 0) FROM pipeline_stages")->fetchColumn();

        $stmt = $this->db->prepare(
            "INSERT INTO pipeline_stages (name, color, position) VALUES (:name, :color, :position)"
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':color' => $data['color'] ?? '#6366f1',
            ':position' => (int) $maxPos + 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Verifica se a etapa possui clientes vinculados.
     */
    public function hasClients(int $stageId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM clients WHERE pipeline_stage_id = :id AND is_active = 1"
        );
        $stmt->execute([':id' => $stageId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Atualiza o nome e a cor de uma etapa.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE pipeline_stages SET name = :name, color = :color WHERE id = :id"
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':color' => $data['color'],
            ':id' => $id,
        ]);
        return (bool) ($stmt->rowCount() > 0);
    }

    /**
     * Move a etapa uma posição para cima ou para baixo trocando com a vizinha.
     */
    public function movePosition(int $id, string $direction): bool
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            return false;
        }

        // Busca a etapa atual
        $stmt = $this->db->prepare(
            "SELECT id, position FROM pipeline_stages WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch();

        if (!$current) {
            return false;
        }

        $currentPos = (int) $current['position'];
        $neighborPos = $direction === 'up' ? $currentPos - 1 : $currentPos + 1;

        // Busca a etapa vizinha
        $stmt = $this->db->prepare(
            "SELECT id, position FROM pipeline_stages WHERE position = :neighbor_pos LIMIT 1"
        );
        $stmt->execute([':neighbor_pos' => $neighborPos]);
        $neighbor = $stmt->fetch();

        if (!$neighbor) {
            return false;
        }

        // Troca as posições dentro de uma transação
        try {
            $this->db->beginTransaction();

            $up1 = $this->db->prepare(
                "UPDATE pipeline_stages SET position = :new_pos WHERE id = :id"
            );
            $up1->execute([':new_pos' => $neighborPos, ':id' => $id]);

            $up2 = $this->db->prepare(
                "UPDATE pipeline_stages SET position = :old_pos WHERE id = :neighbor_id"
            );
            $up2->execute([':old_pos' => $currentPos, ':neighbor_id' => $neighbor['id']]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
