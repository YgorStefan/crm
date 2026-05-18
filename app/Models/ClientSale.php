<?php

namespace App\Models;

use Core\Model;

class ClientSale extends Model
{
    protected string $table = 'client_sales';

    /**
     * Retorna todas as cotas de consórcio de um cliente ordenadas por data de criação.
     *
     * @param  int  $clientId  ID do cliente
     * @return array
     */
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

    /**
     * Insere uma nova cota de consórcio para um cliente e retorna o ID gerado.
     *
     * @param  int    $clientId  ID do cliente
     * @param  array  $data      Dados da cota (grupo, cota, tipo, credito_contratado)
     * @return int
     */
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

    /**
     * Remove uma cota pelo ID, validando que pertence ao cliente e ao tenant.
     *
     * @param  int  $saleId    ID da cota
     * @param  int  $clientId  ID do cliente
     * @return bool
     */
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

    /**
     * Define paid_at = NOW() para uma cota, validando cliente e tenant.
     *
     * @param  int  $saleId    ID da cota
     * @param  int  $clientId  ID do cliente
     * @return bool
     */
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

    /**
     * Retorna client_id e paid_at de todas as cotas de clientes ativos do tenant.
     *
     * @return array
     */
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
