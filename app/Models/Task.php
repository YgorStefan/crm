<?php

namespace App\Models;

use Core\Model;

class Task extends Model
{
    protected string $table = 'tasks';

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
            WHERE t.assigned_to IN (
                SELECT id FROM users WHERE tenant_id = :tenant_id_u
            )
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
              AND t.assigned_to IN (SELECT id FROM users WHERE tenant_id = :tenant_id_u)
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

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($setClauses))
            return false;

        $sql = "UPDATE tasks SET " . implode(', ', $setClauses) .
               " WHERE id = :id AND assigned_to IN (SELECT id FROM users WHERE tenant_id = :tenant_id_u)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
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
                  AND assigned_to IN (SELECT id FROM users WHERE tenant_id = :tenant_id_u)";
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
     */
    public function findOverdue(?int $userId = null): array
    {
        $tenantId = $this->currentTenantId();
        $sql = "
            SELECT t.*, c.name AS client_name, u.name AS assigned_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            LEFT JOIN users   u ON u.id = t.assigned_to
            WHERE t.due_date < NOW()
              AND t.status IN ('pending','in_progress')
              AND t.assigned_to IN (
                  SELECT id FROM users WHERE tenant_id = :tenant_id_u
              )
        ";
        $params = [':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId];
        if ($userId) {
            $sql .= " AND t.assigned_to = :uid";
            $params[':uid'] = $userId;
        }
        $sql .= " ORDER BY t.due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retorna tarefas formatadas para o feed JSON do FullCalendar.
     * Filtra tarefas nao canceladas do usuario (ou todas se admin).
     */
    public function findForCalendar(int $userId, bool $isAdmin = false): array
    {
        $tenantId = $this->currentTenantId();
        $sql = "
            SELECT t.id, t.title, t.due_date, t.priority, t.status, t.client_id,
                   t.description, t.assigned_to, t.created_by,
                   t.recurrence_type, t.recurrence_parent_id,
                   c.name AS client_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
            WHERE t.status NOT IN ('cancelled')
              AND t.assigned_to IN (
                  SELECT id FROM users WHERE tenant_id = :tenant_id_u
              )
        ";
        $params = [':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId];
        if (!$isAdmin) {
            $sql .= " AND t.assigned_to = :uid";
            $params[':uid'] = $userId;
        }
        $sql .= " ORDER BY CASE WHEN t.status = 'done' THEN 1 ELSE 0 END ASC, t.due_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        $horizon   = (new \DateTime())->modify('+12 months');
        $generated = false;
        foreach ($tasks as $task) {
            if ($task['recurrence_type'] !== 'none' && $task['recurrence_parent_id'] === null) {
                if ($this->generateRecurringInstances(
                    (int) $task['id'],
                    $task['recurrence_type'],
                    new \DateTime($task['due_date']),
                    $horizon,
                    $task
                )) {
                    $generated = true;
                }
            }
        }

        if ($generated) {
            $stmt->closeCursor();
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
        }

        return $tasks;
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
               AND assigned_to IN (SELECT id FROM users WHERE tenant_id = :tn)"
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

        $insert = $this->db->prepare(
            "INSERT INTO tasks
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

        $stmt = $this->db->prepare(
            "DELETE FROM tasks
             WHERE recurrence_parent_id = :pid
               AND status = 'pending'
               AND assigned_to IN (SELECT id FROM users WHERE tenant_id = :tn)"
        );
        $stmt->execute([':pid' => $parentId, ':tn' => $tenantId]);

        $stmt = $this->db->prepare(
            "UPDATE tasks SET recurrence_type = 'none'
             WHERE id = :id
               AND assigned_to IN (SELECT id FROM users WHERE tenant_id = :tn)"
        );
        $stmt->execute([':id' => $parentId, ':tn' => $tenantId]);
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
              AND assigned_to IN (SELECT id FROM users WHERE tenant_id = :tenant_id_u)
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
              AND t.assigned_to IN (
                  SELECT id FROM users WHERE tenant_id = :tenant_id_u
              )
        ");
        $stmt->execute([':id' => $id, ':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId]);
        return $stmt->fetch();
    }
}
