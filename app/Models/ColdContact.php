<?php

namespace App\Models;

use Core\Model;

class ColdContact extends Model
{
    protected string $table = 'cold_contacts';

    /**
     * Retorna a contagem de meses distintos de importação não arquivados.
     *
     * @return int
     */
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

    /**
     * Retorna resumo agrupado por mês (mes_ano + total) com paginação opcional.
     *
     * @param  int|null  $limit   Máximo de registros (null = todos)
     * @param  int|null  $offset  Deslocamento para paginação
     * @return array
     */
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

    private function buildContactFilters(array $filters): array
    {
        $sql    = '';
        $params = [];

        if (!empty($filters['tipo_lista'])) {
            $sql .= " AND tipo_lista LIKE :tipo_lista";
            $params[':tipo_lista'] = '%' . $filters['tipo_lista'] . '%';
        }
        if (!empty($filters['dia'])) {
            $sql .= " AND DAY(data_mensagem) = :dia";
            $params[':dia'] = (int) $filters['dia'];
        }
        if (!empty($filters['telefone_enviado'])) {
            $sql .= " AND telefone_enviado LIKE :telefone_enviado";
            $params[':telefone_enviado'] = '%' . $filters['telefone_enviado'] . '%';
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Conta contatos de um mês específico aplicando filtros opcionais.
     *
     * @param  string  $yearMonth  Mês no formato 'YYYY-MM'
     * @param  array   $filters    Filtros: nome, dia, telefone_enviado
     * @return int
     */
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

        $clauses  = $this->buildContactFilters($filters);
        $sql     .= $clauses['sql'];
        $params   = array_merge($params, $clauses['params']);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna contatos de um mês com filtros e paginação opcionals.
     *
     * @param  string    $yearMonth  Mês no formato 'YYYY-MM'
     * @param  array     $filters    Filtros: nome, dia, telefone_enviado
     * @param  int|null  $limit      Máximo de registros
     * @param  int|null  $offset     Deslocamento
     * @return array
     */
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

        $clauses  = $this->buildContactFilters($filters);
        $sql     .= $clauses['sql'];
        $params   = array_merge($params, $clauses['params']);

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

    /**
     * Insere um novo contato frio e retorna o ID gerado.
     *
     * @param  array  $data  Dados do contato (phone, name, tipo_lista, telefone_enviado, data_mensagem)
     * @return int
     */
    public function create(array $data): int
    {
        // imported_year_month é coluna GENERATED (schema.sql + migration 019),
        // derivada de imported_at pelo próprio banco — não deve ser inserida
        // explicitamente (MySQL/MariaDB rejeitam INSERT com valor em coluna
        // GENERATED).
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

    /**
     * Atualiza phone, name, telefone_enviado e data_mensagem de um contato.
     *
     * @param  int    $id    ID do contato
     * @param  array  $data  Dados a atualizar
     * @return bool
     */
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

    /**
     * Remove permanentemente um contato pelo ID.
     *
     * @param  int  $id  ID do contato
     * @return bool
     */
    public function destroy(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM cold_contacts WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Remove todos os contatos de um mês e retorna o número de linhas afetadas.
     *
     * @param  string  $yearMonth  Mês no formato 'YYYY-MM'
     * @return int
     */
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

    /**
     * Atualiza em lote telefone_enviado e/ou data_mensagem em múltiplos contatos.
     *
     * @param  array        $ids           IDs dos contatos a atualizar
     * @param  string|null  $telefone      Novo valor de telefone_enviado (null = não alterar)
     * @param  string|null  $dataMensagem  Nova data_mensagem (null = não alterar)
     * @return int  Número de linhas afetadas
     */
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

    /**
     * Retorna contatos de um mês para exportação, aplicando filtros.
     *
     * @param  string  $yearMonth  Mês no formato 'YYYY-MM'
     * @param  array   $filters    Filtros opcionais
     * @return array
     */
    public function findForExport(string $yearMonth, array $filters = []): array
    {
        return $this->findByMonth($yearMonth, $filters);
    }

    /**
     * Arquiva todos os contatos não arquivados de um mês (define archived_at = NOW()).
     *
     * @param  string  $yearMonth  Mês no formato 'YYYY-MM'
     * @return int  Número de linhas arquivadas
     */
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
