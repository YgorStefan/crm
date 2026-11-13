<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

// a chave primaria e user_id, sem coluna id: os findById e delete herdados do
// Core\Model nao servem para esta tabela.
class GoogleAccount extends Model
{
    protected string $table = 'google_accounts';

    public function findForUser(int $userId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM google_accounts WHERE user_id = :user_id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetch();
    }
}
