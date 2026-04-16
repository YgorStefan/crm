<?php

namespace App\Models;

use Core\Model;

class Interaction extends Model
{
    protected string $table = 'interactions';

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
            LEFT JOIN users u ON u.id = i.user_id
            WHERE i.client_id = :client_id
            ORDER BY i.occurred_at DESC
        ");
        $stmt->execute([':client_id' => $clientId]);
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
        $stmt = $this->db->prepare("
            INSERT INTO interactions (client_id, user_id, type, description, occurred_at)
            VALUES (:client_id, :user_id, :type, :description, :occurred_at)
        ");
        $stmt->execute([
            ':client_id' => (int) $data['client_id'],
            ':user_id' => (int) $data['user_id'],
            ':type' => $data['type'] ?? 'note',
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
            UPDATE interactions
            SET description  = :description,
                type         = :type,
                occurred_at  = :occurred_at
            WHERE id = :id
        ");
        return $stmt->execute([
            ':description' => $data['description'],
            ':type'        => $data['type'],
            ':occurred_at' => $data['occurred_at'],
            ':id'          => $id,
        ]);
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
            LEFT JOIN clients c ON c.id = i.client_id
            LEFT JOIN users  u ON u.id  = i.user_id
            ORDER BY i.occurred_at DESC
            LIMIT :limit
        ");
        // bindValue é necessário para parâmetros LIMIT (PDO não aceita :limit => 10 como string)
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
