<?php

namespace App\Models;

use Core\Model;

class ColdContact extends Model
{
    protected string $table = 'cold_contacts';

    /**
     * Conta o total de grupos mês/ano de importação.
     *
     * @return int
     */
    public function countFindMonthSummaries(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(DISTINCT DATE_FORMAT(imported_at, '%Y-%m'))
            FROM cold_contacts
        ");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna contatos agrupados por mês/ano de importação.
     * Ordenado do mais recente para o mais antigo.
     * Suporta paginação via $limit/$offset.
     */
    public function findMonthSummaries(?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT
                DATE_FORMAT(imported_at, '%Y-%m') AS mes_ano,
                COUNT(*)                          AS total
            FROM cold_contacts
            GROUP BY DATE_FORMAT(imported_at, '%Y-%m')
            ORDER BY DATE_FORMAT(imported_at, '%Y-%m') DESC
        ";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->db->query($sql);
        }

        return $stmt->fetchAll();
    }

    /**
     * Conta contatos de um mês específico (YYYY-MM) com filtros opcionais.
     *
     * @param  string  $yearMonth  Formato YYYY-MM
     * @param  array   $filters    ['dia', 'telefone_enviado']
     * @return int
     */
    public function countByMonth(string $yearMonth, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM cold_contacts
            WHERE DATE_FORMAT(imported_at, '%Y-%m') = :year_month
        ";
        $params = [':year_month' => $yearMonth];

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

    /**
     * Busca contatos de um mês específico (YYYY-MM) com filtros opcionais.
     * Filtro dia: filtra por dia do campo data_mensagem (1-31).
     * Filtro phone: busca parcial no campo phone do contato.
     * Ordenado por id ASC (ordem de importação).
     * Suporta paginação via $limit/$offset.
     */
    public function findByMonth(string $yearMonth, array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT *
            FROM cold_contacts
            WHERE DATE_FORMAT(imported_at, '%Y-%m') = :year_month
        ";
        $params = [':year_month' => $yearMonth];

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
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, \PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Insere um contato frio.
     * Retorna o ID inserido.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cold_contacts (phone, name, tipo_lista, telefone_enviado, data_mensagem, imported_at)
            VALUES (:phone, :name, :tipo_lista, :telefone_enviado, :data_mensagem, NOW())
        ");
        $stmt->execute([
            ':phone' => $data['phone'],
            ':name' => $data['name'],
            ':tipo_lista' => $data['tipo_lista'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem' => $data['data_mensagem'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza campos editáveis de um contato.
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
        ");
        $stmt->execute([
            ':phone' => $data['phone'],
            ':name' => $data['name'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem' => $data['data_mensagem'] ?? null,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deleta um contato pelo ID.
     */
    public function destroy(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM cold_contacts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deleta todos os contatos de um mês (formato YYYY-MM).
     * Retorna o número de linhas deletadas.
     */
    public function deleteByMonth(string $yearMonth): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM cold_contacts WHERE DATE_FORMAT(imported_at, '%Y-%m') = :year_month"
        );
        $stmt->execute([':year_month' => $yearMonth]);
        return $stmt->rowCount();
    }

    /**
     * Atualiza telefone_enviado e/ou data_mensagem em lote para os IDs fornecidos.
     * Retorna número de linhas afetadas.
     */
    public function bulkAtualizarExtras(array $ids, ?string $telefone, ?string $dataMensagem): int
    {
        if (empty($ids)) return 0;
        
        $setClauses = [];
        $params = [];
        
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
        $sql = "UPDATE cold_contacts SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, array_map('intval', $ids)));
        return $stmt->rowCount();
    }

    /**
     * Alias findByMonth para exportação — mesma query, usada pelo export endpoint.
     */
    public function findForExport(string $yearMonth, array $filters = []): array
    {
        return $this->findByMonth($yearMonth, $filters);
    }

}
