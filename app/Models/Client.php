<?php

namespace App\Models;

use Core\Model;

class Client extends Model
{
    protected string $table = 'clients';

    // Colunas graváveis via create()/update(). Campo novo de cliente entra
    // aqui (e em bindClientParams se precisar de normalização própria).
    private const COLUMNS = [
        'name', 'email', 'phone', 'company', 'cnpj_cpf',
        'address', 'address_number', 'address_complement',
        'neighborhood', 'city', 'state', 'zip_code',
        'pipeline_stage_id', 'assigned_to', 'deal_value',
        'source', 'notes', 'birth_date', 'referido_por', 'closed_at',
    ];

    /**
     * Normaliza os dados do formulário para os parâmetros PDO.
     * Strings vazias viram NULL; numéricos são convertidos.
     *
     * @param  array  $data  Dados vindos do controller
     * @return array  Parâmetros nomeados (':coluna' => valor)
     */
    private function bindClientParams(array $data): array
    {
        $params = [];
        foreach (self::COLUMNS as $col) {
            $value = $data[$col] ?? null;
            $params[':' . $col] = match ($col) {
                'name'              => $value,
                'pipeline_stage_id' => (int) $value,
                'assigned_to'       => !empty($value) ? (int) $value : null,
                'deal_value'        => !empty($value) ? self::parseMoney((string) $value) : 0,
                default             => $value ?: null,
            };
        }
        return $params;
    }

    /**
     * Monta cláusulas WHERE e parâmetros PDO a partir dos filtros da listagem.
     * Reutilizado por countAllWithRelations e findAllWithRelations.
     *
     * @param  array  $filters  ['stage_id', 'assigned_to', 'search', 'tipo_venda']
     * @return array{sql: string, params: array}
     */
    private function buildClientFilters(array $filters): array
    {
        $sql    = '';
        $params = [];

        if (!empty($filters['stage_id'])) {
            $sql .= " AND c.pipeline_stage_id = :stage_id";
            $params[':stage_id'] = (int) $filters['stage_id'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND c.assigned_to = :assigned_to";
            $params[':assigned_to'] = (int) $filters['assigned_to'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE :search1 OR c.company LIKE :search2 OR c.email LIKE :search3)";
            $params[':search1'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['tipo_venda'])) {
            $sql .= " AND cs.tipo = :tipo_venda";
            $params[':tipo_venda'] = $filters['tipo_venda'];
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Conta o total de clientes ativos que correspondem aos filtros fornecidos.
     * Usa os mesmos JOINs de findAllWithRelations, contando DISTINCT c.id.
     *
     * @param  array  $filters  ['stage_id', 'assigned_to', 'search', 'tipo_venda']
     * @return int
     */
    public function countAllWithRelations(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(DISTINCT c.id)
            FROM clients c
            LEFT JOIN client_sales cs ON cs.client_id = c.id
            WHERE c.is_active = 1
        ";
        $params = [];
        $tenantId = $this->currentTenantId();
        $sql .= " AND c.tenant_id = :tenant_id";
        $params[':tenant_id'] = $tenantId;

        $clauses = $this->buildClientFilters($filters);
        $sql    .= $clauses['sql'];
        $params  = array_merge($params, $clauses['params']);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findAllWithRelations(array $filters = [], ?int $limit = null, ?int $offset = null, array $overdueClientIds = []): array
    {
        // Query base com JOINs
        $sql = "
            SELECT
                c.*,
                ps.name  AS stage_name,
                ps.color AS stage_color,
                ps.is_won_stage,
                u.name   AS assigned_name,
                MAX(cs.tipo) AS tipo_venda
            FROM clients c
            LEFT JOIN pipeline_stages ps ON ps.id = c.pipeline_stage_id
            LEFT JOIN users u            ON u.id  = c.assigned_to
            LEFT JOIN client_sales cs    ON cs.client_id = c.id
            WHERE c.is_active = 1
        ";
        $params = [];
        $tenantId = $this->currentTenantId();
        $sql .= " AND c.tenant_id = :tenant_id";
        $params[':tenant_id'] = $tenantId;

        $clauses = $this->buildClientFilters($filters);
        $sql    .= $clauses['sql'];
        $params  = array_merge($params, $clauses['params']);

        $sql .= " GROUP BY c.id";
        $allowedSorts = [
            'name'  => 'c.name',
            'stage' => 'ps.name',
            'value' => 'c.deal_value',
        ];
        $sortCol = $allowedSorts[$filters['sort'] ?? ''] ?? 'c.name';
        $sortDir = ($filters['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sortCol} {$sortDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->db->prepare($sql);

        // Bind named params primeiro
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, \PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll();

        $overdueSet = array_flip($overdueClientIds);
        foreach ($rows as &$row) {
            $isWonStage      = !empty($row['is_won_stage']);
            $row['has_overdue'] = $isWonStage && isset($overdueSet[(int) $row['id']]);
        }
        unset($row);

        return $rows;
    }

    /**
     * Busca um cliente com todas as relações (para a tela de detalhes).
     */
    public function findByIdWithRelations(int $id): array|bool
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                ps.name  AS stage_name,
                ps.color AS stage_color,
                ps.is_won_stage,
                u.name   AS assigned_name
            FROM clients c
            LEFT JOIN pipeline_stages ps ON ps.id = c.pipeline_stage_id
            LEFT JOIN users u            ON u.id  = c.assigned_to
            WHERE c.id = :id AND c.is_active = 1 AND c.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId()]);
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
        $cols         = implode(', ', self::COLUMNS);
        $placeholders = ':' . implode(', :', self::COLUMNS);

        $stmt = $this->db->prepare("
            INSERT INTO clients (tenant_id, {$cols})
            VALUES (:tenant_id, {$placeholders})
        ");
        $stmt->execute($this->bindClientParams($data) + [
            ':tenant_id' => $this->currentTenantId(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza os dados de um cliente existente.
     */
    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn(string $c): string => "{$c} = :{$c}", self::COLUMNS));

        $stmt = $this->db->prepare("
            UPDATE clients SET {$set}
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute($this->bindClientParams($data) + [
            ':id'        => $id,
            ':tenant_id' => $this->currentTenantId(),
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete: marca o cliente como inativo em vez de apagá-lo.
     * Mantém o histórico de interações e tarefas preservado.
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE clients SET is_active = 0 WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Atualiza apenas a etapa do funil de um cliente.
     * Chamado via AJAX pelo drag & drop do Kanban.
     */
    public function updateStage(int $clientId, int $stageId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE clients SET pipeline_stage_id = :stage WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':stage' => $stageId, ':id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna clientes agrupados por etapa para o Kanban.
     * Estrutura retornada: ['stage_id' => [cliente, cliente, ...], ...]
     */
    public function findGroupedByStage(): array
    {
        $t = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT
                c.id, c.name, c.company, c.deal_value, c.email, c.phone,
                c.pipeline_stage_id,
                u.name AS assigned_name
            FROM clients c
            LEFT JOIN users u ON u.id = c.assigned_to
            WHERE c.is_active = 1
              AND c.tenant_id = :tenant_id
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([':tenant_id' => $t]);
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
        $t = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT ps.name, ps.color, ps.is_won_stage, COUNT(c.id) AS total, COALESCE(SUM(c.deal_value), 0) AS total_value
            FROM pipeline_stages ps
            LEFT JOIN clients c ON c.pipeline_stage_id = ps.id
                AND c.is_active = 1
                AND c.tenant_id = :tenant_id_c
            WHERE ps.tenant_id = :tenant_id_ps
            GROUP BY ps.id, ps.name, ps.color, ps.is_won_stage
            ORDER BY ps.position
        ");
        $stmt->execute([':tenant_id_ps' => $t, ':tenant_id_c' => $t]);
        return $stmt->fetchAll();
    }

    /**
     * Conta clientes por etapa filtrados pelo mês de criação (YYYY-MM).
     * Usado pelo Acompanhamento de Prospecção com histórico mensal.
     */
    public function countByStageAndMonth(string $yearMonth): array
    {
        $t = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT ps.name, ps.color, ps.is_won_stage, COUNT(c.id) AS total, COALESCE(SUM(c.deal_value), 0) AS total_value
            FROM pipeline_stages ps
            LEFT JOIN clients c ON c.pipeline_stage_id = ps.id
                AND c.is_active = 1
                AND c.tenant_id = :tenant_id_c
                AND DATE_FORMAT(c.created_at, '%Y-%m') = :year_month
            WHERE ps.tenant_id = :tenant_id_ps
            GROUP BY ps.id, ps.name, ps.color, ps.is_won_stage
            ORDER BY ps.position
        ");
        $stmt->execute([':tenant_id_c' => $t, ':tenant_id_ps' => $t, ':year_month' => $yearMonth]);
        return $stmt->fetchAll();
    }

    /**
     * Busca cliente ativo pelo telefone (para verificar duplicata).
     */
    public function findByPhone(string $phone): array|bool
    {
        $stmt = $this->db->prepare(
            "SELECT id, name FROM clients WHERE phone = :phone AND is_active = 1 AND tenant_id = :tenant_id LIMIT 1"
        );
        $stmt->execute([':phone' => $phone, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetch();
    }

    /**
     * Normaliza valor monetário para float.
     * - Com vírgula (BR): "60.000,00" ou "60000,00" → remove pontos, troca vírgula
     * - Sem vírgula (JS pré-processou): "60000.00" ou "60000" → usa direto
     */
    public static function parseMoney(string $value): float
    {
        $value = trim($value);
        if ($value === '') return 0.0;

        if (str_contains($value, ',')) {
            // Formato brasileiro: remove separador de milhar, converte decimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        // Sem vírgula: já está em formato neutro (ex: "60000.00" ou "60000")
        return (float) $value;
    }

    /**
     * Atualiza apenas o campo notes do cliente.
     *
     * @param  int     $id
     * @param  string  $notes  Conteúdo das notas (pode ser string vazia)
     * @return bool
     */
    public function updateNotes(int $id, string $notes): bool
    {
        $stmt = $this->db->prepare("
            UPDATE clients SET notes = :notes WHERE id = :id AND tenant_id = :tenant_id
        ");
        return $stmt->execute([
            ':notes'     => $notes,
            ':id'        => $id,
            ':tenant_id' => $this->currentTenantId(),
        ]);
    }

    /**
     * Retorna clientes com aniversário hoje (mesmo dia e mês, qualquer ano).
     *
     * @return array  Array de ['id' => int, 'name' => string]
     */
    public function findBirthdaysToday(): array
    {
        $t = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT id, name
            FROM clients
            WHERE is_active = 1
              AND tenant_id = :tenant_id
              AND birth_date IS NOT NULL
              AND MONTH(birth_date) = MONTH(CURDATE())
              AND DAY(birth_date) = DAY(CURDATE())
        ");
        $stmt->execute([':tenant_id' => $t]);
        return $stmt->fetchAll();
    }

}
