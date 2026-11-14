<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class GoogleEventDeletion extends Model
{
    protected string $table = 'google_event_deletions';

    public function enqueue(int $userId, string $eventId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO google_event_deletions (tenant_id, user_id, google_event_id)
             VALUES (:tenant_id, :user_id, :event_id)'
        );
        $stmt->execute([
            ':tenant_id' => $this->currentTenantId(),
            ':user_id'   => $userId,
            ':event_id'  => $eventId,
        ]);
    }

    /** @return list<array{id:int,user_id:int,google_event_id:string}> */
    public function forUser(int $userId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, google_event_id FROM google_event_deletions
             WHERE tenant_id = :tenant_id AND user_id = :user_id
             ORDER BY id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':tenant_id', $this->currentTenantId(), \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $row): array => [
            'id'              => (int) $row['id'],
            'user_id'         => (int) $row['user_id'],
            'google_event_id' => (string) $row['google_event_id'],
        ], $stmt->fetchAll());
    }

    public function remove(int $id): void
    {
        $this->delete($id);
    }

    public function purgeForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM google_event_deletions WHERE tenant_id = :tenant_id AND user_id = :user_id'
        );
        $stmt->execute([':tenant_id' => $this->currentTenantId(), ':user_id' => $userId]);
    }
}
