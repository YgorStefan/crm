<?php

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
     * @param  array  $filters  ['stage_id', 'assigned_to', 'search']
     * @return array
     */
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

        if (!empty($filters['stage_id'])) {
            $sql .= " AND c.pipeline_stage_id = :stage_id";
            $params[':stage_id'] = (int) $filters['stage_id'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND c.assigned_to = :assigned_to";
            $params[':assigned_to'] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE :search OR c.company LIKE :search OR c.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['tipo_venda'])) {
            $sql .= " AND cs.tipo = :tipo_venda";
            $params[':tipo_venda'] = $filters['tipo_venda'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findAllWithRelations(array $filters = [], ?int $limit = null, ?int $offset = null): array
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

        // Filtro por tipo de venda (Imóvel, Veículo, Serviço)
        if (!empty($filters['tipo_venda'])) {
            $sql .= " AND cs.tipo = :tipo_venda";
            $params[':tipo_venda'] = $filters['tipo_venda'];
        }

        $sql .= " GROUP BY c.id";
        $sql .= " ORDER BY c.updated_at DESC";

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
        $overdueIds = array_flip($this->findAllOverdueSalesByClient());

        foreach ($rows as &$row) {
            // FRAG-03: identificação por coluna estruturada, não por nome de string
            $isWonStage = !empty($row['is_won_stage']);
            $row['has_overdue'] = $isWonStage && isset($overdueIds[(int) $row['id']]);
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
        $stmt = $this->db->prepare("
            INSERT INTO clients
                (tenant_id, name, email, phone, company, cnpj_cpf, address, city, state,
                 zip_code, pipeline_stage_id, assigned_to, deal_value, source, notes,
                 birth_date, referido_por, closed_at)
            VALUES
                (:tenant_id, :name, :email, :phone, :company, :cnpj_cpf, :address, :city, :state,
                 :zip_code, :pipeline_stage_id, :assigned_to, :deal_value, :source, :notes,
                 :birth_date, :referido_por, :closed_at)
        ");
        $stmt->execute([
            ':tenant_id' => $this->currentTenantId(),
            ':name' => $data['name'],
            ':email' => $data['email'] ?: null,
            ':phone' => $data['phone'] ?: null,
            ':company' => $data['company'] ?: null,
            ':cnpj_cpf' => $data['cnpj_cpf'] ?: null,
            ':address' => $data['address'] ?: null,
            ':city' => $data['city'] ?: null,
            ':state' => $data['state'] ?: null,
            ':zip_code' => $data['zip_code'] ?: null,
            ':pipeline_stage_id' => (int) $data['pipeline_stage_id'],
            ':assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            ':deal_value' => !empty($data['deal_value']) ? self::parseMoney($data['deal_value']) : 0,
            ':source' => $data['source'] ?: null,
            ':notes' => $data['notes'] ?: null,
            ':birth_date' => !empty($data['birth_date']) ? $data['birth_date'] : null,
            ':referido_por' => $data['referido_por'] ?: null,
            ':closed_at' => !empty($data['closed_at']) ? $data['closed_at'] : null,
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
                source = :source, notes = :notes,
                birth_date = :birth_date, referido_por = :referido_por,
                closed_at = :closed_at
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'] ?: null,
            ':phone' => $data['phone'] ?: null,
            ':company' => $data['company'] ?: null,
            ':cnpj_cpf' => $data['cnpj_cpf'] ?: null,
            ':address' => $data['address'] ?: null,
            ':city' => $data['city'] ?: null,
            ':state' => $data['state'] ?: null,
            ':zip_code' => $data['zip_code'] ?: null,
            ':pipeline_stage_id' => (int) $data['pipeline_stage_id'],
            ':assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            ':deal_value' => !empty($data['deal_value']) ? self::parseMoney($data['deal_value']) : 0,
            ':source' => $data['source'] ?: null,
            ':notes' => $data['notes'] ?: null,
            ':birth_date' => !empty($data['birth_date']) ? $data['birth_date'] : null,
            ':referido_por' => $data['referido_por'] ?: null,
            ':closed_at' => !empty($data['closed_at']) ? $data['closed_at'] : null,
            ':id' => $id,
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
     * Retorna todas as cotas de um cliente específico.
     */
    public function findSalesByClientId(int $clientId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cs.* FROM client_sales cs
             INNER JOIN clients c ON c.id = cs.client_id AND c.tenant_id = :tenant_id
             WHERE cs.client_id = :client_id ORDER BY cs.created_at ASC"
        );
        $stmt->execute([':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetchAll();
    }

    /**
     * Cria uma nova cota de consórcio para um cliente.
     *
     * @param  int    $clientId
     * @param  array  $data  Chaves: grupo, cota, tipo, credito_contratado
     * @return int    ID da cota criada
     */
    public function createSale(int $clientId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO client_sales (client_id, grupo, cota, tipo, credito_contratado)
            VALUES (:client_id, :grupo, :cota, :tipo, :credito_contratado)
        ");
        $stmt->execute([
            ':client_id' => $clientId,
            ':grupo' => $data['grupo'] ?: null,
            ':cota' => $data['cota'] ?: null,
            ':tipo' => $data['tipo'],
            ':credito_contratado' => !empty($data['credito_contratado'])
                ? self::parseMoney($data['credito_contratado'])
                : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Remove uma cota de consórcio pelo ID (verificando que pertence ao cliente).
     */
    public function deleteSale(int $saleId, int $clientId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE cs FROM client_sales cs
             INNER JOIN clients c ON c.id = cs.client_id AND c.tenant_id = :tenant_id
             WHERE cs.id = :id AND cs.client_id = :client_id"
        );
        $stmt->execute([':id' => $saleId, ':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Busca cotas do cliente com status de pagamento calculado em PHP.
     *   - dia atual >= cutoff → mês de referência = mês atual
     *   - dia atual < cutoff  → mês de referência = mês anterior
     * A cota está "em dia" se paid_at NÃO for NULL e cair dentro do mês de referência.
     * @param  int   $clientId
     * @return array  Cada elemento possui todos os campos de client_sales
     */
    public function findSalesWithPaymentStatus(int $clientId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cs.* FROM client_sales cs
             INNER JOIN clients c ON c.id = cs.client_id AND c.tenant_id = :tenant_id
             WHERE cs.client_id = :client_id ORDER BY cs.created_at ASC"
        );
        $stmt->execute([':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        $sales = $stmt->fetchAll();

        // Determina mês/ano de referência do ciclo vigente
        $ref = $this->computeRefMonth();
        $refMes = $ref['mes'];
        $refAno = $ref['ano'];

        foreach ($sales as &$sale) {
            $isPaid = false;
            $paidFormatted = null;

            if (!empty($sale['paid_at'])) {
                $paidDt  = new \DateTimeImmutable($sale['paid_at']);
                $refStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $refAno, $refMes));
                $now      = new \DateTimeImmutable('now');

                if ($paidDt >= $refStart && $paidDt <= $now) {
                    $isPaid = true;
                    $paidFormatted = $paidDt->format('d/m/Y H:i');
                }
            }

            $sale['is_paid'] = $isPaid;
            $sale['paid_at_formatted'] = $paidFormatted;
        }
        unset($sale);

        return $sales;
    }

    /**
     * Registra o pagamento da cota no mês de referência do ciclo vigente.
     * Grava paid_at com uma data dentro do mês de referência para que
     * findSalesWithPaymentStatus reconheça como pago corretamente.
     */
    public function updateSalePaidAt(int $saleId, int $clientId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE client_sales cs
             INNER JOIN clients c ON c.id = cs.client_id AND c.tenant_id = :tenant_id
             SET cs.paid_at = NOW()
             WHERE cs.id = :id AND cs.client_id = :client_id"
        );
        $stmt->execute([':id' => $saleId, ':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
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
     * Returns the configured payment cutoff day for the current tenant.
     * Reads tenants.payment_cutoff_day; falls back to 20 if missing.
     */
    private function getTenantCutoffDay(): int
    {
        try {
            $stmt = $this->db->prepare('SELECT payment_cutoff_day FROM tenants WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $this->currentTenantId()]);
            $value = $stmt->fetchColumn();
            return ((int) $value) ?: 20;
        } catch (\RuntimeException $e) {
            return 20;
        }
    }

    /**
     * Determina o mês/ano de referência do ciclo vigente de pagamentos.
     * dia >= cutoff → mês atual; dia < cutoff → mês anterior. (D-04)
     *
     * @return array{mes: int, ano: int}
     */
    private function computeRefMonth(): array
    {
        $hoje = new \DateTimeImmutable('now');
        $diaHoje = (int) $hoje->format('j');

        if ($diaHoje >= $this->getTenantCutoffDay()) {
            return ['mes' => (int) $hoje->format('n'), 'ano' => (int) $hoje->format('Y')];
        }

        $refDt = $hoje->modify('first day of last month');
        return ['mes' => (int) $refDt->format('n'), 'ano' => (int) $refDt->format('Y')];
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
     * Retorna lista de client_ids que possuem ao menos uma cota em atraso no ciclo vigente.
     * Usado por findAllWithRelations para injetar has_overdue sem N+1 queries.
     *
     * @return array  Array de int client_ids com cota em atraso
     */
    public function findAllOverdueSalesByClient(): array
    {
        $ref = $this->computeRefMonth();
        $refMes = $ref['mes'];
        $refAno = $ref['ano'];

        // Busca todas as cotas ativas (de clientes is_active=1)
        $t = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT cs.client_id, cs.paid_at
            FROM client_sales cs
            INNER JOIN clients c ON c.id = cs.client_id
                AND c.is_active = 1
                AND c.tenant_id = :tenant_id
        ");
        $stmt->execute([':tenant_id' => $t]);
        $rows = $stmt->fetchAll();

        // Só exibe em atraso a partir do dia de corte configurado do tenant sem pagamento
        $hoje    = new \DateTimeImmutable('now');
        $diaHoje = (int) $hoje->format('j');
        if ($diaHoje < $this->getTenantCutoffDay()) {
            return [];
        }

        $refStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $refAno, $refMes));

        $overdueSet = [];
        foreach ($rows as $row) {
            $clientId = (int) $row['client_id'];
            if (isset($overdueSet[$clientId]))
                continue;

            $isPaid = false;
            if (!empty($row['paid_at'])) {
                $paidDt = new \DateTimeImmutable($row['paid_at']);
                if ($paidDt >= $refStart && $paidDt <= $hoje) {
                    $isPaid = true;
                }
            }

            if (!$isPaid) {
                $overdueSet[$clientId] = true;
            }
        }

        return array_keys($overdueSet);
    }
}
