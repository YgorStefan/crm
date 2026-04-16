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
        $t = $this->currentTenantId();
        $stmt = $this->db->prepare(
            "SELECT * FROM pipeline_stages WHERE tenant_id = :t ORDER BY position ASC"
        );
        $stmt->execute([':t' => $t]);
        return $stmt->fetchAll();
    }

    /**
     * Cria uma nova etapa no funil.
     */
    public function create(array $data): int
    {
        $t = $this->currentTenantId();
        // Descobre qual é a maior posição atual para colocar a nova etapa no final
        $maxPos = $this->db->prepare(
            "SELECT COALESCE(MAX(position), 0) FROM pipeline_stages WHERE tenant_id = :t"
        );
        $maxPos->execute([':t' => $t]);
        $pos = (int) $maxPos->fetchColumn();

        $stmt = $this->db->prepare(
            "INSERT INTO pipeline_stages (name, color, position, tenant_id) VALUES (:name, :color, :position, :t)"
        );
        $stmt->execute([
            ':name'     => $data['name'],
            ':color'    => $data['color'] ?? '#6366f1',
            ':position' => $pos + 1,
            ':t'        => $t,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Verifica se a etapa possui clientes vinculados.
     */
    public function hasClients(int $stageId): bool
    {
        $t = $this->currentTenantId();
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM clients WHERE pipeline_stage_id = :id AND tenant_id = :t AND is_active = 1"
        );
        $stmt->execute([':id' => $stageId, ':t' => $t]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Atualiza o nome e a cor de uma etapa.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE pipeline_stages SET name = :name, color = :color WHERE id = :id AND tenant_id = :t"
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':color' => $data['color'],
            ':id'   => $id,
            ':t'    => $this->currentTenantId(),
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

        $t = $this->currentTenantId();

        // Busca a etapa atual (escopo ao tenant)
        $stmt = $this->db->prepare(
            "SELECT id, position FROM pipeline_stages WHERE id = :id AND tenant_id = :t"
        );
        $stmt->execute([':id' => $id, ':t' => $t]);
        $current = $stmt->fetch();

        if (!$current) {
            return false;
        }

        $currentPos = (int) $current['position'];
        $neighborPos = $direction === 'up' ? $currentPos - 1 : $currentPos + 1;

        // Busca a etapa vizinha (escopo ao tenant)
        $stmt = $this->db->prepare(
            "SELECT id, position FROM pipeline_stages WHERE position = :neighbor_pos AND tenant_id = :t LIMIT 1"
        );
        $stmt->execute([':neighbor_pos' => $neighborPos, ':t' => $t]);
        $neighbor = $stmt->fetch();

        if (!$neighbor) {
            return false;
        }

        // Troca as posições dentro de uma transação (escopo ao tenant)
        try {
            $this->db->beginTransaction();

            $up1 = $this->db->prepare(
                "UPDATE pipeline_stages SET position = :new_pos WHERE id = :id AND tenant_id = :t"
            );
            $up1->execute([':new_pos' => $neighborPos, ':id' => $id, ':t' => $t]);

            $up2 = $this->db->prepare(
                "UPDATE pipeline_stages SET position = :old_pos WHERE id = :neighbor_id AND tenant_id = :t"
            );
            $up2->execute([':old_pos' => $currentPos, ':neighbor_id' => $neighbor['id'], ':t' => $t]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Marca ou desmarca uma etapa como "etapa de venda fechada" (FRAG-03).
     */
    public function setWonStage(int $id, bool $isWon): bool
    {
        $t = $this->currentTenantId();
        try {
            $this->db->beginTransaction();

            if ($isWon) {
                // Limpa todas as etapas do tenant (exclusividade mútua)
                $clear = $this->db->prepare(
                    'UPDATE pipeline_stages SET is_won_stage = 0 WHERE tenant_id = :t'
                );
                $clear->execute([':t' => $t]);
            }

            // Define a etapa alvo
            $set = $this->db->prepare(
                'UPDATE pipeline_stages SET is_won_stage = :is_won WHERE id = :id AND tenant_id = :t'
            );
            $set->execute([':is_won' => $isWon ? 1 : 0, ':id' => $id, ':t' => $t]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
