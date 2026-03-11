<?php
// ============================================================
// app/Models/PipelineStage.php — Model das Etapas do Funil
// ============================================================

namespace App\Models;

use Core\Model;

class PipelineStage extends Model
{
    protected string $table = 'pipeline_stages';

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
            ':name'     => $data['name'],
            ':color'    => $data['color'] ?? '#6366f1',
            ':position' => (int) $maxPos + 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Verifica se a etapa possui clientes vinculados.
     * Usado antes de tentar deletar (a FK RESTRICT já bloqueia no banco,
     * mas verificamos antes para dar uma mensagem amigável).
     */
    public function hasClients(int $stageId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM clients WHERE pipeline_stage_id = :id AND is_active = 1"
        );
        $stmt->execute([':id' => $stageId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
