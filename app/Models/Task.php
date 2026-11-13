<?php

namespace App\Models;

use App\Services\Google\GoogleConfig;
use Core\Model;

class Task extends Model
{
    protected string $table = 'tasks';

    private const CALENDAR_COLUMNS = "
        t.id, t.title, t.due_date, t.priority, t.status, t.client_id,
        t.description, t.assigned_to, t.created_by,
        t.recurrence_type, t.recurrence_parent_id,
        t.google_event_id, t.google_owner_id, t.google_etag,
        t.google_duration_min, t.google_sync_pending,
        c.name AS client_name";

    private const DELETION_COLUMNS = 'id, assigned_to, google_event_id, google_owner_id, google_sync_pending';

    /**
     * Constrói cláusulas SQL e parâmetros para filtros de tarefas.
     *
     * @param  array  $filters  ['status', 'assigned_to', 'priority']
     * @return array ['sql' => string, 'params' => array]
     */
    private function buildTaskFilters(array $filters): array
    {
        $sql    = '';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = :assigned_to";
            $params[':assigned_to'] = (int) $filters['assigned_to'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Retorna todas as tarefas com relações (cliente, responsável, criador).
     *
     * @param  array  $filters  ['status', 'assigned_to', 'priority']
     * @return array
     */
    public function findAllWithRelations(array $filters = []): array
    {
        $tenantId = $this->currentTenantId();
        $sql = "
            SELECT
                t.*,
                c.name  AS client_name,
                u.name  AS assigned_name,
                cb.name AS created_by_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            LEFT JOIN users   u ON u.id = t.assigned_to
            LEFT JOIN users  cb ON cb.id = t.created_by
            WHERE t.tenant_id = :tenant_id_u
        ";
        $params = [':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId];

        $clauses  = $this->buildTaskFilters($filters);
        $sql     .= $clauses['sql'];
        $params   = array_merge($params, $clauses['params']);

        // Ordena por: tarefas atrasadas primeiro, depois por prazo crescente
        $sql .= " ORDER BY t.due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Tarefas vinculadas a um cliente específico (para a tela de detalhes).
     */
    public function findByClient(int $clientId): array
    {
        $tenantId = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT t.*, u.name AS assigned_name
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            WHERE t.client_id = :client_id
              AND t.tenant_id = :tenant_id_u
            ORDER BY t.due_date ASC
        ");
        $stmt->execute([':client_id' => $clientId, ':tenant_id_u' => $tenantId]);
        return $stmt->fetchAll();
    }

    /**
     * Cria uma nova tarefa.
     *
     * @param  array  $data
     * @return int    ID da tarefa
     */
    public function create(array $data): int
    {
        $tenantId   = $this->currentTenantId();
        $assignedTo = (int) $data['assigned_to'];

        $check = $this->db->prepare(
            "SELECT id FROM users WHERE id = :uid AND tenant_id = :tenant_id_u"
        );
        $check->execute([':uid' => $assignedTo, ':tenant_id_u' => $tenantId]);
        if (!$check->fetch()) {
            throw new \InvalidArgumentException("assigned_to não pertence ao tenant atual.");
        }

        if (!empty($data['client_id'])) {
            $checkClient = $this->db->prepare(
                "SELECT id FROM clients WHERE id = :cid AND tenant_id = :tenant_id_c AND is_active = 1"
            );
            $checkClient->execute([':cid' => (int) $data['client_id'], ':tenant_id_c' => $tenantId]);
            if (!$checkClient->fetch()) {
                throw new \InvalidArgumentException("client_id não pertence ao tenant atual.");
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO tasks
                (client_id, assigned_to, title, description, due_date, priority, status,
                 recurrence_type, recurrence_parent_id, created_by, tenant_id)
            VALUES
                (:client_id, :assigned_to, :title, :description, :due_date, :priority, 'pending',
                 :recurrence_type, :recurrence_parent_id, :created_by, :tenant_id)
        ");
        $stmt->execute([
            ':client_id'             => !empty($data['client_id']) ? (int) $data['client_id'] : null,
            ':assigned_to'           => $assignedTo,
            ':title'                 => $data['title'],
            ':description'           => $data['description'] ?? null,
            ':due_date'              => $data['due_date'],
            ':priority'              => $data['priority'] ?? 'medium',
            ':recurrence_type'       => $data['recurrence_type'] ?? 'none',
            ':recurrence_parent_id'  => isset($data['recurrence_parent_id']) ? (int) $data['recurrence_parent_id'] : null,
            ':created_by'            => (int) $data['created_by'],
            ':tenant_id'             => $tenantId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza o status e/ou outros campos de uma tarefa.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['title', 'description', 'due_date', 'priority', 'status', 'assigned_to'];
        $setClauses = [];
        $tenantId = $this->currentTenantId();
        $params = [':id' => $id, ':tenant_id_u' => $tenantId];

        if (array_key_exists('assigned_to', $data)) {
            $check = $this->db->prepare(
                "SELECT id FROM users WHERE id = :uid AND tenant_id = :tenant_id_u"
            );
            $check->execute([':uid' => (int) $data['assigned_to'], ':tenant_id_u' => $tenantId]);
            if (!$check->fetch()) {
                throw new \InvalidArgumentException("assigned_to não pertence ao tenant atual.");
            }
        }

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($setClauses))
            return false;

        // soma em vez de marcar 1 porque o envio so zera se o contador ainda for
        // o que ele leu, e assim uma edicao feita durante o envio nao se perde.
        $setClauses[] = 'google_sync_pending = google_sync_pending + 1';

        $this->db->beginTransaction();
        try {
            if (array_key_exists('assigned_to', $data)) {
                $setClauses = array_merge(
                    $setClauses,
                    $this->releaseGoogleEventOnReassign($id, $tenantId, (int) $data['assigned_to'])
                );
            }

            $sql = "UPDATE tasks SET " . implode(', ', $setClauses) .
                   " WHERE id = :id AND tenant_id = :tenant_id_u";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $updated = $stmt->rowCount() > 0;
            $this->db->commit();
            return $updated;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @return list<string> clausulas que limpam o vinculo, vazia quando o evento fica onde esta
     */
    private function releaseGoogleEventOnReassign(int $id, int $tenantId, int $newAssignee): array
    {
        $stmt = $this->db->prepare(
            "SELECT assigned_to, google_event_id, google_owner_id FROM tasks
             WHERE id = :id AND tenant_id = :tenant_id
             FOR UPDATE"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        if (!$row
            || (int) $row['assigned_to'] === $newAssignee
            || $row['google_event_id'] === null
            || $row['google_owner_id'] === null
        ) {
            return [];
        }

        // o evento mora na agenda do dono antigo: sai de la, e o envio cria
        // outro na agenda do novo responsavel.
        (new GoogleEventDeletion())->enqueue((int) $row['google_owner_id'], (string) $row['google_event_id']);
        return ['google_event_id = NULL', 'google_owner_id = NULL', 'google_etag = NULL'];
    }

    public function delete(int $id): bool
    {
        $tenantId = $this->currentTenantId();

        $this->db->beginTransaction();
        try {
            // as filhas somem junto pelo cascade da fk, entao os eventos delas
            // precisam entrar na fila aqui, antes do delete.
            $stmt = $this->db->prepare(
                "SELECT " . self::DELETION_COLUMNS . " FROM tasks
                 WHERE tenant_id = :tenant_id
                   AND (id = :id OR recurrence_parent_id = :parent_id)
                 FOR UPDATE"
            );
            $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':parent_id' => $id]);
            $this->enqueueGoogleDeletions($tenantId, $stmt->fetchAll());

            $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = :id AND tenant_id = :tenant_id");
            $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * roda dentro da transacao de quem chama, com as linhas ja travadas, para a
     * copia e o delete enxergarem as mesmas linhas.
     *
     * @param  array<int, array<string, mixed>>  $rows  colunas de DELETION_COLUMNS
     */
    private function enqueueGoogleDeletions(int $tenantId, array $rows): void
    {
        $deletions  = new GoogleEventDeletion();
        $accounts   = new GoogleAccount();
        $config     = null;
        $hasAccount = [];

        foreach ($rows as $row) {
            if ($row['google_event_id'] !== null && $row['google_owner_id'] !== null) {
                $deletions->enqueue((int) $row['google_owner_id'], (string) $row['google_event_id']);
                continue;
            }

            // sem id salvo, um insert que estourou o tempo pode ter criado o
            // evento no google mesmo assim, e ele so pode estar na agenda do
            // responsavel, com o id deterministico.
            if ((int) $row['google_sync_pending'] === 0) {
                continue;
            }
            $assignee = (int) $row['assigned_to'];
            $hasAccount[$assignee] ??= $accounts->findForUser($assignee) !== false;
            if ($hasAccount[$assignee]) {
                $config ??= GoogleConfig::fromEnv();
                $deletions->enqueue($assignee, $config->eventIdFor($tenantId, (int) $row['id']));
            }
        }
    }

    /**
     * Conta tarefas pendentes (para o badge no menu e no dashboard).
     *
     * @param  int|null  $userId  null = todas; int = apenas do usuário
     * @return int
     */
    public function countPending(?int $userId = null): int
    {
        $tenantId = $this->currentTenantId();
        $sql = "SELECT COUNT(*) FROM tasks
                WHERE status IN ('pending','in_progress')
                  AND tenant_id = :tenant_id_u";
        $params = [':tenant_id_u' => $tenantId];
        if ($userId) {
            $sql .= " AND assigned_to = :uid";
            $params[':uid'] = $userId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna tarefas com prazo vencido e ainda abertas.
     *
     * @param  int|null  $limit  null = sem limite (páginas/telas completas);
     *                           usar um valor no polling de notificações, que
     *                           roda a cada 60s para cada usuário logado.
     */
    public function findOverdue(?int $userId = null, ?int $limit = null): array
    {
        $tenantId = $this->currentTenantId();
        $sql = "
            SELECT t.*, c.name AS client_name, u.name AS assigned_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            LEFT JOIN users   u ON u.id = t.assigned_to
            WHERE t.due_date < NOW()
              AND t.status IN ('pending','in_progress')
              AND t.tenant_id = :tenant_id_u
        ";
        $params = [':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId];
        if ($userId) {
            $sql .= " AND t.assigned_to = :uid";
            $params[':uid'] = $userId;
        }
        $sql .= " ORDER BY t.due_date ASC";
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retorna tarefas formatadas para o feed JSON do FullCalendar.
     * Filtra tarefas nao canceladas do usuario (ou todas se admin).
     *
     * @param  string|null  $start  Início da janela visível (Y-m-d H:i:s), null = sem limite
     * @param  string|null  $end    Fim da janela visível (Y-m-d H:i:s), null = sem limite
     */
    public function findForCalendar(int $userId, bool $isAdmin = false, ?string $start = null, ?string $end = null): array
    {
        $tenantId = $this->currentTenantId();

        // A geração de instâncias recorrentes NÃO pode depender da janela
        // visível: se dependesse, navegar para um mês futuro sem nunca ter
        // "passado" pelo mês da tarefa-pai deixaria a recorrência sem
        // materializar. Por isso busca os "moldes" (poucas linhas, leve)
        // separado da consulta de exibição (que aí sim usa start/end).
        $this->ensureRecurringInstancesGenerated($tenantId, $userId, $isAdmin);

        $sql = "
            SELECT " . self::CALENDAR_COLUMNS . "
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            WHERE t.status NOT IN ('cancelled')
              AND t.tenant_id = :tenant_id_u
        ";
        $params = [':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId];
        if (!$isAdmin) {
            $sql .= " AND t.assigned_to = :uid";
            $params[':uid'] = $userId;
        }
        if ($start !== null) {
            $sql .= " AND t.due_date >= :start";
            $params[':start'] = $start;
        }
        if ($end !== null) {
            $sql .= " AND t.due_date < :end";
            $params[':end'] = $end;
        }
        $sql .= " ORDER BY CASE WHEN t.status = 'done' THEN 1 ELSE 0 END ASC, t.due_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findTeamForCalendar(int $userId, ?string $start, ?string $end): array
    {
        $tenantId = $this->currentTenantId();
        // materializa as series do tenant inteiro, porque as filhas de outro
        // responsavel so nascem quando alguem abre o calendario.
        $this->ensureRecurringInstancesGenerated($tenantId, $userId, true);

        $sql = "
            SELECT " . self::CALENDAR_COLUMNS . ", u.name AS assigned_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            LEFT JOIN users   u ON u.id = t.assigned_to AND u.tenant_id = :tenant_id_us
            WHERE t.status NOT IN ('cancelled')
              AND t.tenant_id = :tenant_id_u
              AND t.assigned_to <> :uid
        ";
        $params = [
            ':tenant_id_c'  => $tenantId,
            ':tenant_id_us' => $tenantId,
            ':tenant_id_u'  => $tenantId,
            ':uid'          => $userId,
        ];
        if ($start !== null) {
            $sql .= " AND t.due_date >= :start";
            $params[':start'] = $start;
        }
        if ($end !== null) {
            $sql .= " AND t.due_date < :end";
            $params[':end'] = $end;
        }
        $sql .= " ORDER BY CASE WHEN t.status = 'done' THEN 1 ELSE 0 END ASC, t.due_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function materializeRecurring(int $userId, bool $isAdmin): void
    {
        $this->ensureRecurringInstancesGenerated($this->currentTenantId(), $userId, $isAdmin);
    }

    /**
     * Garante que as próximas instâncias de tarefas recorrentes (até 12 meses
     * à frente) já estão materializadas. Opera só sobre os "moldes"
     * (recurrence_type != 'none' AND recurrence_parent_id IS NULL), não sobre
     * todas as tarefas — dataset tipicamente pequeno por tenant.
     */
    private function ensureRecurringInstancesGenerated(int $tenantId, int $userId, bool $isAdmin): void
    {
        $sql = "
            SELECT id, client_id, assigned_to, title, description, due_date, priority, created_by, recurrence_type
            FROM tasks
            WHERE tenant_id = :tenant_id
              AND recurrence_type != 'none'
              AND recurrence_parent_id IS NULL
              AND status NOT IN ('cancelled')
        ";
        $params = [':tenant_id' => $tenantId];
        if (!$isAdmin) {
            $sql .= " AND assigned_to = :uid";
            $params[':uid'] = $userId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $recurringParents = $stmt->fetchAll();

        if (!$recurringParents) return;

        $horizon = (new \DateTime())->modify('+12 months');
        // Geracao materializa varias linhas: agrupa em transacao para evitar
        // insercoes parciais caso uma das tarefas-pai falhe no meio.
        $this->db->beginTransaction();
        try {
            foreach ($recurringParents as $task) {
                $this->generateRecurringInstances(
                    (int) $task['id'],
                    $task['recurrence_type'],
                    new \DateTime($task['due_date']),
                    $horizon,
                    $task
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function generateRecurringInstances(
        int $parentId,
        string $type,
        \DateTime $parentDate,
        \DateTime $horizon,
        array $parentData
    ): bool {
        $interval = match ($type) {
            'weekly'  => new \DateInterval('P7D'),
            'monthly' => new \DateInterval('P1M'),
            'yearly'  => new \DateInterval('P1Y'),
            default   => null,
        };
        if ($interval === null) return false;

        $tenantId = $this->currentTenantId();

        $stmt = $this->db->prepare(
            "SELECT MAX(due_date) FROM tasks
             WHERE recurrence_parent_id = :pid
               AND tenant_id = :tn"
        );
        $stmt->execute([':pid' => $parentId, ':tn' => $tenantId]);
        $latestDate = $stmt->fetchColumn();

        $next = $latestDate
            ? (new \DateTime($latestDate))->add($interval)
            : (clone $parentDate)->add($interval);

        // On first generation, skip past instances to avoid flooding calendar with overdue rows
        if (!$latestDate) {
            $now = new \DateTime();
            while ($next < $now) {
                $next->add($interval);
            }
        }

        if ($next > $horizon) return false;
        $rows     = [];
        $params   = [];
        $i        = 0;

        while ($next <= $horizon) {
            $rows[] = "(:ci{$i},:at{$i},:ti{$i},:de{$i},:dd{$i},:pr{$i},'pending','none',:pa{$i},:cb{$i},:tn{$i})";
            $params[":ci{$i}"] = $parentData['client_id'];
            $params[":at{$i}"] = $parentData['assigned_to'];
            $params[":ti{$i}"] = $parentData['title'];
            $params[":de{$i}"] = $parentData['description'] ?? null;
            $params[":dd{$i}"] = $next->format('Y-m-d H:i:s');
            $params[":pr{$i}"] = $parentData['priority'];
            $params[":pa{$i}"] = $parentId;
            $params[":cb{$i}"] = $parentData['created_by'];
            $params[":tn{$i}"] = $tenantId;
            $next->add($interval);
            $i++;
        }

        // INSERT IGNORE: sob concorrência, duas requisições podem calcular o
        // mesmo "next" a partir do mesmo MAX(due_date) — a UNIQUE
        // (recurrence_parent_id, due_date) rejeita o duplicado sem derrubar
        // a transação inteira.
        $insert = $this->db->prepare(
            "INSERT IGNORE INTO tasks
                (client_id, assigned_to, title, description, due_date, priority, status,
                 recurrence_type, recurrence_parent_id, created_by, tenant_id)
             VALUES " . implode(', ', $rows)
        );
        $insert->execute($params);
        return true;
    }

    public function cancelRecurrence(int $parentId): void
    {
        $tenantId = $this->currentTenantId();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT " . self::DELETION_COLUMNS . " FROM tasks
                 WHERE recurrence_parent_id = :pid
                   AND status = 'pending'
                   AND tenant_id = :tn
                 FOR UPDATE"
            );
            $stmt->execute([':pid' => $parentId, ':tn' => $tenantId]);
            $this->enqueueGoogleDeletions($tenantId, $stmt->fetchAll());

            $stmt = $this->db->prepare(
                "DELETE FROM tasks
                 WHERE recurrence_parent_id = :pid
                   AND status = 'pending'
                   AND tenant_id = :tn"
            );
            $stmt->execute([':pid' => $parentId, ':tn' => $tenantId]);

            $stmt = $this->db->prepare(
                "UPDATE tasks SET recurrence_type = 'none'
                 WHERE id = :id
                   AND tenant_id = :tn"
            );
            $stmt->execute([':id' => $parentId, ':tn' => $tenantId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function pendingForUser(int $userId, int $limit): array
    {
        $tenantId = $this->currentTenantId();
        // tarefa vencida sem evento nao vale o envio, mas a que ja tem evento
        // precisa levar a edicao ao google. concluida e cancelada entram, porque
        // o envio marca uma e remove o evento da outra.
        $stmt = $this->db->prepare("
            SELECT t.*, c.name AS client_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            WHERE t.tenant_id = :tenant_id_u
              AND t.assigned_to = :uid
              AND t.google_sync_pending > 0
              AND (t.google_event_id IS NOT NULL OR t.due_date >= CURDATE())
            ORDER BY t.due_date ASC, t.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':tenant_id_c', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id_u', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markSynced(int $id, int $seenPending, string $eventId, int $ownerId, ?string $etag, int $durationMin): bool
    {
        // tudo ou nada: com o contador mudado, gravar o vinculo poderia prender
        // a tarefa a agenda de um responsavel que ja saiu, e o proximo envio
        // reencontra o evento pelo id deterministico.
        $stmt = $this->db->prepare(
            "UPDATE tasks
             SET google_event_id = :event_id, google_owner_id = :owner_id, google_etag = :etag,
                 google_duration_min = :duration_min, google_sync_pending = 0
             WHERE id = :id AND tenant_id = :tenant_id AND google_sync_pending = :seen"
        );
        $stmt->execute([
            ':event_id'     => $eventId,
            ':owner_id'     => $ownerId,
            ':etag'         => $etag,
            ':duration_min' => $durationMin,
            ':id'           => $id,
            ':tenant_id'    => $this->currentTenantId(),
            ':seen'         => $seenPending,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function dropFromQueue(int $id, int $seenPending): void
    {
        $stmt = $this->db->prepare(
            "UPDATE tasks SET google_sync_pending = 0
             WHERE id = :id AND tenant_id = :tenant_id AND google_sync_pending = :seen"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId(), ':seen' => $seenPending]);
    }

    /**
     * @return array<string, array<string, mixed>> google_event_id => linha
     */
    public function linkMap(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, title, due_date, status, recurrence_type, recurrence_parent_id,
                    google_event_id, google_etag, google_duration_min, google_sync_pending
             FROM tasks
             WHERE tenant_id = :tenant_id
               AND google_owner_id = :uid
               AND google_event_id IS NOT NULL
               AND status <> 'cancelled'"
        );
        $stmt->execute([':tenant_id' => $this->currentTenantId(), ':uid' => $userId]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['google_event_id']] = $row;
        }
        return $map;
    }

    public function applyGoogleChange(int $id, ?string $etagRead, array $fields, string $newEtag): bool
    {
        $setClauses = ['google_etag = :new_etag'];
        $params = [
            ':new_etag'  => $newEtag,
            ':id'        => $id,
            ':tenant_id' => $this->currentTenantId(),
            ':etag_read' => $etagRead,
        ];
        foreach (['title', 'due_date', 'google_duration_min'] as $field) {
            if (array_key_exists($field, $fields)) {
                $setClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $fields[$field];
            }
        }

        // nao soma na fila, senao o envio devolveria ao google o que veio dele.
        // a pendencia e o etag no where fazem a edicao do crm e uma leitura mais
        // nova ganharem desta escrita.
        $stmt = $this->db->prepare(
            "UPDATE tasks SET " . implode(', ', $setClauses) . "
             WHERE id = :id AND tenant_id = :tenant_id
               AND google_sync_pending = 0
               AND google_etag <=> :etag_read"
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function unlinkGoogle(int $id, ?string $newStatus = null): void
    {
        $setClauses = ['google_event_id = NULL', 'google_owner_id = NULL', 'google_etag = NULL'];
        $params = [':id' => $id, ':tenant_id' => $this->currentTenantId()];
        if ($newStatus !== null) {
            $setClauses[] = 'status = :status';
            $params[':status'] = $newStatus;
        }

        // nao soma, senao o envio recriaria o evento que sumiu do google. com
        // pendencia a mudanca do crm ganha e o vinculo fica para o envio refazer
        // o evento, em vez de a tarefa recem editada ser cancelada.
        $stmt = $this->db->prepare(
            "UPDATE tasks SET " . implode(', ', $setClauses) . "
             WHERE id = :id AND tenant_id = :tenant_id AND google_sync_pending = 0"
        );
        $stmt->execute($params);
    }

    public function clearGoogleLinksForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE tasks SET google_event_id = NULL, google_owner_id = NULL, google_etag = NULL
             WHERE tenant_id = :tenant_id AND google_owner_id = :uid"
        );
        $stmt->execute([':tenant_id' => $this->currentTenantId(), ':uid' => $userId]);
    }

    public function markOpenUnlinkedPending(int $userId): int
    {
        // as vinculadas ficam de fora porque reconectar nao pode sobrescrever o
        // que mudou no google desde o ultimo envio.
        $stmt = $this->db->prepare(
            "UPDATE tasks SET google_sync_pending = google_sync_pending + 1
             WHERE tenant_id = :tenant_id
               AND assigned_to = :uid
               AND status IN ('pending', 'in_progress')
               AND due_date >= CURDATE()
               AND google_event_id IS NULL"
        );
        $stmt->execute([':tenant_id' => $this->currentTenantId(), ':uid' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Retorna tarefas com due_date entre $from e $to (para notificacoes).
     * Usado pelo polling JS para alertar tarefas nos proximos 15 minutos.
     */
    public function findUpcoming(int $userId, string $from, string $to, bool $isAdmin = false): array
    {
        $tenantId = $this->currentTenantId();
        $sql = "
            SELECT id, title, due_date, priority
            FROM tasks
            WHERE due_date BETWEEN :from AND :to
              AND status IN ('pending', 'in_progress')
              AND tenant_id = :tenant_id_u
        ";
        $params = [':from' => $from, ':to' => $to, ':tenant_id_u' => $tenantId];
        if (!$isAdmin) {
            $sql .= " AND assigned_to = :uid";
            $params[':uid'] = $userId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Tarefas pendentes de um usuário com vencimento nos próximos N dias
     * (widget "Próximas tarefas" do dashboard). Filtro e limite aplicados em
     * SQL — antes carregava TODAS as pendentes do usuário e filtrava em PHP.
     */
    public function findUpcomingForDashboard(int $userId, int $days = 7, int $limit = 10): array
    {
        $tenantId = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT t.title, t.due_date, t.priority, c.name AS client_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            WHERE t.tenant_id = :tenant_id_u
              AND t.status = 'pending'
              AND t.assigned_to = :uid
              AND t.due_date <= DATE_ADD(NOW(), INTERVAL :days DAY)
            ORDER BY t.due_date ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':tenant_id_c', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id_u', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retorna uma tarefa pelo ID.
     */
    public function findById(int $id): array|bool
    {
        $tenantId = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT t.*, c.name AS client_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            WHERE t.id = :id
              AND t.tenant_id = :tenant_id_u
        ");
        $stmt->execute([':id' => $id, ':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId]);
        return $stmt->fetch();
    }
}
