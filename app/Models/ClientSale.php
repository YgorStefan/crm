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

    public function delete(int $saleId, int $clientId): bool
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

    /**
     * Busca cotas do cliente com status de pagamento calculado em PHP.
     * A cota está "em dia" se paid_at não for NULL e cair dentro do mês de referência.
     *
     * @param  int    $clientId
     * @param  int    $refMes   Mês de referência (1-12)
     * @param  int    $refAno   Ano de referência
     * @return array
     */
    public function findWithPaymentStatus(int $clientId, int $refMes, int $refAno): array
    {
        $stmt = $this->db->prepare(
            "SELECT cs.* FROM client_sales cs
             INNER JOIN clients c ON c.id = cs.client_id AND c.tenant_id = :tenant_id
             WHERE cs.client_id = :client_id ORDER BY cs.created_at ASC"
        );
        $stmt->execute([':client_id' => $clientId, ':tenant_id' => $this->currentTenantId()]);
        $sales = $stmt->fetchAll();

        $refStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $refAno, $refMes));
        $now      = new \DateTimeImmutable('now');

        foreach ($sales as &$sale) {
            $isPaid = false;
            $paidFormatted = null;

            if (!empty($sale['paid_at'])) {
                $paidDt = new \DateTimeImmutable($sale['paid_at']);
                if ($paidDt >= $refStart && $paidDt <= $now) {
                    $isPaid = true;
                    $paidFormatted = $paidDt->format('d/m/Y H:i');
                }
            }

            $sale['is_paid']           = $isPaid;
            $sale['paid_at_formatted'] = $paidFormatted;
        }
        unset($sale);

        return $sales;
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

    /**
     * Returns the configured payment cutoff day for the current tenant.
     * Falls back to 20 if missing.
     */
    public function getTenantCutoffDay(): int
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
     * dia >= cutoff → mês atual; dia < cutoff → mês anterior.
     *
     * @return array{mes: int, ano: int}
     */
    public function computeRefMonth(): array
    {
        $hoje    = new \DateTimeImmutable('now');
        $diaHoje = (int) $hoje->format('j');

        if ($diaHoje >= $this->getTenantCutoffDay()) {
            return ['mes' => (int) $hoje->format('n'), 'ano' => (int) $hoje->format('Y')];
        }

        $refDt = $hoje->modify('first day of last month');
        return ['mes' => (int) $refDt->format('n'), 'ano' => (int) $refDt->format('Y')];
    }

    /**
     * Retorna array de client_ids que possuem cotas em atraso no ciclo vigente.
     * Retorna [] antes do dia de corte configurado.
     *
     * @return int[]
     */
    public function findOverdueClientIds(): array
    {
        $ref     = $this->computeRefMonth();
        $hoje    = new \DateTimeImmutable('now');
        $diaHoje = (int) $hoje->format('j');

        if ($diaHoje < $this->getTenantCutoffDay()) {
            return [];
        }

        $rows     = $this->findAllForOverdueCheck();
        $refStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $ref['ano'], $ref['mes']));

        $overdueSet = [];
        foreach ($rows as $row) {
            $clientId = (int) $row['client_id'];
            if (isset($overdueSet[$clientId])) continue;

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
