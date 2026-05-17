<?php

namespace App\Models;

use Core\Model;

class ClientSale extends Model
{
    protected string $table = 'client_sales';

    public function findByClientId(int $clientId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cs.* FROM client_sales cs
             INNER JOIN clients c ON c.id = cs.client_id AND c.tenant_id = :tenant_id
             WHERE cs.client_id = :client_id ORDER BY cs.created_at ASC"
        );
        $stmt->execute([':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetchAll();
    }

    public function create(int $clientId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO client_sales (client_id, grupo, cota, tipo, credito_contratado)
            VALUES (:client_id, :grupo, :cota, :tipo, :credito_contratado)
        ");
        $stmt->execute([
            ':client_id'          => $clientId,
            ':grupo'              => $data['grupo'] ?: null,
            ':cota'               => $data['cota'] ?: null,
            ':tipo'               => $data['tipo'],
            ':credito_contratado' => !empty($data['credito_contratado'])
                ? Client::parseMoney($data['credito_contratado'])
                : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteBySaleAndClient(int $saleId, int $clientId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE cs FROM client_sales cs
             INNER JOIN clients c ON c.id = cs.client_id AND c.tenant_id = :tenant_id
             WHERE cs.id = :id AND cs.client_id = :client_id"
        );
        $stmt->execute([':id' => $saleId, ':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }

    public function updatePaidAt(int $saleId, int $clientId): bool
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

    public function findAllForOverdueCheck(): array
    {
        $stmt = $this->db->prepare("
            SELECT cs.client_id, cs.paid_at
            FROM client_sales cs
            INNER JOIN clients c ON c.id = cs.client_id
                AND c.is_active = 1
                AND c.tenant_id = :tenant_id
        ");
        $stmt->execute([':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetchAll();
    }

}
