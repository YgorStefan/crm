<?php

namespace App\Models;

use Core\Model;

class Interaction extends Model
{
    protected string $table = 'interactions';

    // isGlobal = true evita que Core\Model::findById() lance RuntimeException
    // pela ausência de tenant_id na tabela. A segurança de tenant é garantida
    // pelo override abaixo, que usa INNER JOIN com clients.
    // Remover este flag após rodar migration 009 e a coluna existir na tabela.
    protected bool $isGlobal = true;

    /**
     * Busca interação por ID garantindo que pertence ao tenant correto
     * via JOIN com a tabela clients.
     */
    public function findById(int $id): array|bool
    {
        $stmt = $this->db->prepare("
            SELECT i.*
            FROM interactions i
            INNER JOIN clients c ON c.id = i.client_id
            WHERE i.id = :id
              AND c.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id'        => $id,
            ':tenant_id' => $this->currentTenantId(),
        ]);
        return $stmt->fetch();
    }

    /**
     * Busca todas as interações de um cliente, do mais recente ao mais antigo.
     *
     * @param  int    $clientId
     * @return array
     */
    public function findByClient(int $clientId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                u.name AS user_name
            FROM interactions i
            INNER JOIN clients c ON c.id = i.client_id AND c.tenant_id = :tenant_id
            LEFT JOIN users u ON u.id = i.user_id
            WHERE i.client_id = :client_id
            ORDER BY i.occurred_at DESC
        ");
        $stmt->execute([':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetchAll();
    }

    /**
     * Cria uma nova interação no banco.
     *
     * @param  array  $data  ['client_id', 'user_id', 'type', 'description', 'occurred_at']
     * @return int    ID da interação criada
     */
    public function create(array $data): int
    {
        $clientId = (int) $data['client_id'];
        $tenantId = $this->currentTenantId();

        $check = $this->db->prepare(
            "SELECT id FROM clients WHERE id = :id AND tenant_id = :tenant_id AND is_active = 1"
        );
        $check->execute([':id' => $clientId, ':tenant_id' => $tenantId]);
        if (!$check->fetch()) {
            throw new \InvalidArgumentException("client_id não pertence ao tenant atual.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO interactions (client_id, user_id, type, description, occurred_at)
            VALUES (:client_id, :user_id, :type, :description, :occurred_at)
        ");
        $stmt->execute([
            ':client_id'   => $clientId,
            ':user_id'     => (int) $data['user_id'],
            ':type'        => $data['type'] ?? 'note',
            ':description' => $data['description'],
            ':occurred_at' => $data['occurred_at'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza os campos de uma interação existente.
     *
     * @param  int    $id
     * @param  array  $data  ['description', 'type', 'occurred_at'] — todos opcionais, apenas campos presentes são atualizados
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE interactions i
            INNER JOIN clients c ON c.id = i.client_id AND c.tenant_id = :tenant_id
            SET i.description = :description,
                i.type        = :type,
                i.occurred_at = :occurred_at
            WHERE i.id = :id
        ");
        $stmt->execute([
            ':description' => $data['description'],
            ':type'        => $data['type'],
            ':occurred_at' => $data['occurred_at'],
            ':id'          => $id,
            ':tenant_id'   => $this->currentTenantId(),
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna as interações recentes de todos os clientes (para o dashboard).
     *
     * @param  int  $limit  Quantidade máxima de registros
     * @return array
     */
    public function findRecent(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                c.name AS client_name,
                u.name AS user_name
            FROM interactions i
            INNER JOIN clients c ON c.id = i.client_id AND c.tenant_id = :tenant_id
            LEFT JOIN users  u ON u.id  = i.user_id
            ORDER BY i.occurred_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':tenant_id', $this->currentTenantId(), \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
