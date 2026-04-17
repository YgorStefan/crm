<?php

namespace App\Models;

use Core\Model;

class ColdContact extends Model
{
    protected string $table = 'cold_contacts';

    public function countFindMonthSummaries(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT imported_year_month)
            FROM cold_contacts
            WHERE tenant_id = :tenant_id
              AND archived_at IS NULL
        ");
        $stmt->execute([':tenant_id' => $this->currentTenantId()]);
        return (int) $stmt->fetchColumn();
    }

    public function findMonthSummaries(?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT
                imported_year_month AS mes_ano,
                COUNT(*)            AS total
            FROM cold_contacts
            WHERE tenant_id = :tenant_id
              AND archived_at IS NULL
            GROUP BY imported_year_month
            ORDER BY imported_year_month DESC
        ";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':tenant_id', $this->currentTenantId(), \PDO::PARAM_INT);
            $stmt->bindValue(':limit',     $limit,         \PDO::PARAM_INT);
            $stmt->bindValue(':offset',    $offset ?? 0,   \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':tenant_id' => $this->currentTenantId()]);
        }

        return $stmt->fetchAll();
    }

    public function countByMonth(string $yearMonth, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM cold_contacts
            WHERE imported_year_month = :year_month
              AND tenant_id = :tenant_id
        ";
        $params = [
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ];

        if (!empty($filters['dia'])) {
            $sql .= " AND DAY(data_mensagem) = :dia";
            $params[':dia'] = (int) $filters['dia'];
        }
        if (!empty($filters['telefone_enviado'])) {
            $sql .= " AND telefone_enviado LIKE :telefone_enviado";
            $params[':telefone_enviado'] = '%' . $filters['telefone_enviado'] . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findByMonth(string $yearMonth, array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT *
            FROM cold_contacts
            WHERE imported_year_month = :year_month
              AND tenant_id = :tenant_id
        ";
        $params = [
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ];

        if (!empty($filters['dia'])) {
            $sql .= " AND DAY(data_mensagem) = :dia";
            $params[':dia'] = (int) $filters['dia'];
        }
        if (!empty($filters['telefone_enviado'])) {
            $sql .= " AND telefone_enviado LIKE :telefone_enviado";
            $params[':telefone_enviado'] = '%' . $filters['telefone_enviado'] . '%';
        }

        $sql .= " ORDER BY id ASC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit',  $limit,       \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, \PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cold_contacts
                (phone, name, tipo_lista, telefone_enviado, data_mensagem, tenant_id, imported_at)
            VALUES
                (:phone, :name, :tipo_lista, :telefone_enviado, :data_mensagem, :tenant_id, NOW())
        ");
        $stmt->execute([
            ':phone'            => $data['phone'],
            ':name'             => $data['name'],
            ':tipo_lista'       => $data['tipo_lista'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem'    => $data['data_mensagem'] ?? null,
            ':tenant_id'        => $this->currentTenantId(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cold_contacts
            SET phone            = :phone,
                name             = :name,
                telefone_enviado = :telefone_enviado,
                data_mensagem    = :data_mensagem
            WHERE id = :id
              AND tenant_id = :tenant_id
        ");
        $stmt->execute([
            ':phone'            => $data['phone'],
            ':name'             => $data['name'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem'    => $data['data_mensagem'] ?? null,
            ':id'               => $id,
            ':tenant_id'        => $this->currentTenantId(),
        ]);
        return $stmt->rowCount() > 0;
    }

    public function destroy(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM cold_contacts WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByMonth(string $yearMonth): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM cold_contacts
             WHERE imported_year_month = :year_month
               AND tenant_id = :tenant_id"
        );
        $stmt->execute([
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ]);
        return $stmt->rowCount();
    }

    public function bulkAtualizarExtras(array $ids, ?string $telefone, ?string $dataMensagem): int
    {
        if (empty($ids)) return 0;

        $setClauses = [];
        $params     = [];

        if ($telefone !== null) {
            $setClauses[] = "telefone_enviado = ?";
            $params[] = $telefone === '' ? null : $telefone;
        }
        if ($dataMensagem !== null) {
            $setClauses[] = "data_mensagem = ?";
            $params[] = $dataMensagem === '' ? null : $dataMensagem;
        }

        if (empty($setClauses)) return 0;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE cold_contacts
                SET " . implode(', ', $setClauses) . "
                WHERE id IN ({$placeholders})
                  AND tenant_id = ?";

        $params = array_merge($params, array_map('intval', $ids));
        $params[] = $this->currentTenantId();

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function findForExport(string $yearMonth, array $filters = []): array
    {
        return $this->findByMonth($yearMonth, $filters);
    }

    public function archiveMonth(string $yearMonth): int
    {
        $stmt = $this->db->prepare("
            UPDATE cold_contacts
            SET archived_at = NOW()
            WHERE imported_year_month = :year_month
              AND tenant_id = :tenant_id
              AND archived_at IS NULL
        ");
        $stmt->execute([
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ]);
        return $stmt->rowCount();
    }
}
