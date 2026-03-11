<?php
// ============================================================
// app/Models/Client.php — Model de Clientes
// ============================================================

namespace App\Models;

use Core\Model;

class Client extends Model
{
    protected string $table = 'clients';

    /**
     * Busca todos os clientes com JOIN nas tabelas relacionadas.
     * Retorna também o nome da etapa do funil e do vendedor responsável
     * para exibição na listagem sem precisar de queries adicionais.
     *
     * @param  array  $filters  Filtros opcionais: ['stage_id', 'assigned_to', 'search']
     * @return array
     */
    public function findAllWithRelations(array $filters = []): array
    {
        // Query base com JOINs
        $sql = "
            SELECT
                c.*,
                ps.name  AS stage_name,
                ps.color AS stage_color,
                u.name   AS assigned_name
            FROM clients c
            LEFT JOIN pipeline_stages ps ON ps.id = c.pipeline_stage_id
            LEFT JOIN users u            ON u.id  = c.assigned_to
            WHERE c.is_active = 1
        ";
        $params = [];

        // Filtro por etapa do funil
        if (!empty($filters['stage_id'])) {
            $sql .= " AND c.pipeline_stage_id = :stage_id";
            $params[':stage_id'] = (int) $filters['stage_id'];
        }

        // Filtro por vendedor responsável
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND c.assigned_to = :assigned_to";
            $params[':assigned_to'] = (int) $filters['assigned_to'];
        }

        // Busca por nome, empresa ou e-mail (pesquisa livre)
        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE :search OR c.company LIKE :search OR c.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY c.updated_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Busca um cliente com todas as relações (para a tela de detalhes).
     */
    public function findByIdWithRelations(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                ps.name  AS stage_name,
                ps.color AS stage_color,
                u.name   AS assigned_name
            FROM clients c
            LEFT JOIN pipeline_stages ps ON ps.id = c.pipeline_stage_id
            LEFT JOIN users u            ON u.id  = c.assigned_to
            WHERE c.id = :id AND c.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Cria um novo cliente no banco.
     *
     * @param  array  $data  Campos do formulário validados
     * @return int    ID do cliente criado
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO clients
                (name, email, phone, company, cnpj_cpf, address, city, state,
                 zip_code, pipeline_stage_id, assigned_to, deal_value, source, notes)
            VALUES
                (:name, :email, :phone, :company, :cnpj_cpf, :address, :city, :state,
                 :zip_code, :pipeline_stage_id, :assigned_to, :deal_value, :source, :notes)
        ");
        $stmt->execute([
            ':name'              => $data['name'],
            ':email'             => $data['email']             ?: null,
            ':phone'             => $data['phone']             ?: null,
            ':company'           => $data['company']           ?: null,
            ':cnpj_cpf'          => $data['cnpj_cpf']          ?: null,
            ':address'           => $data['address']           ?: null,
            ':city'              => $data['city']              ?: null,
            ':state'             => $data['state']             ?: null,
            ':zip_code'          => $data['zip_code']          ?: null,
            ':pipeline_stage_id' => (int) $data['pipeline_stage_id'],
            ':assigned_to'       => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            ':deal_value'        => !empty($data['deal_value'])  ? (float) str_replace(',', '.', $data['deal_value']) : 0,
            ':source'            => $data['source']            ?: null,
            ':notes'             => $data['notes']             ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza os dados de um cliente existente.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE clients SET
                name = :name, email = :email, phone = :phone, company = :company,
                cnpj_cpf = :cnpj_cpf, address = :address, city = :city, state = :state,
                zip_code = :zip_code, pipeline_stage_id = :pipeline_stage_id,
                assigned_to = :assigned_to, deal_value = :deal_value,
                source = :source, notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'              => $data['name'],
            ':email'             => $data['email']             ?: null,
            ':phone'             => $data['phone']             ?: null,
            ':company'           => $data['company']           ?: null,
            ':cnpj_cpf'          => $data['cnpj_cpf']          ?: null,
            ':address'           => $data['address']           ?: null,
            ':city'              => $data['city']              ?: null,
            ':state'             => $data['state']             ?: null,
            ':zip_code'          => $data['zip_code']          ?: null,
            ':pipeline_stage_id' => (int) $data['pipeline_stage_id'],
            ':assigned_to'       => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            ':deal_value'        => !empty($data['deal_value'])  ? (float) str_replace(',', '.', $data['deal_value']) : 0,
            ':source'            => $data['source']            ?: null,
            ':notes'             => $data['notes']             ?: null,
            ':id'                => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete: marca o cliente como inativo em vez de apagá-lo.
     * Mantém o histórico de interações e tarefas preservado.
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE clients SET is_active = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Atualiza apenas a etapa do funil de um cliente.
     * Chamado via AJAX pelo drag & drop do Kanban.
     */
    public function updateStage(int $clientId, int $stageId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE clients SET pipeline_stage_id = :stage WHERE id = :id"
        );
        $stmt->execute([':stage' => $stageId, ':id' => $clientId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna clientes agrupados por etapa para o Kanban.
     * Estrutura retornada: ['stage_id' => [cliente, cliente, ...], ...]
     */
    public function findGroupedByStage(): array
    {
        $stmt = $this->db->query("
            SELECT
                c.id, c.name, c.company, c.deal_value, c.email, c.phone,
                c.pipeline_stage_id,
                u.name AS assigned_name
            FROM clients c
            LEFT JOIN users u ON u.id = c.assigned_to
            WHERE c.is_active = 1
            ORDER BY c.updated_at DESC
        ");
        $rows = $stmt->fetchAll();

        // Agrupa os clientes por stage_id para montar as colunas do Kanban
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['pipeline_stage_id']][] = $row;
        }
        return $grouped;
    }

    /**
     * Conta clientes por etapa (para o dashboard).
     */
    public function countByStage(): array
    {
        $stmt = $this->db->query("
            SELECT ps.name, ps.color, COUNT(c.id) AS total, COALESCE(SUM(c.deal_value), 0) AS total_value
            FROM pipeline_stages ps
            LEFT JOIN clients c ON c.pipeline_stage_id = ps.id AND c.is_active = 1
            GROUP BY ps.id, ps.name, ps.color
            ORDER BY ps.position
        ");
        return $stmt->fetchAll();
    }
}
