<?php

namespace App\Services;

use App\Models\ClientSale;
use Core\Database;

class ClientService
{
    public function __construct(
        private ClientSale $sales = new ClientSale(),
    ) {}

    /**
     * Retorna as cotas do cliente com status de pagamento calculado para o mês de referência.
     *
     * @param  int  $clientId  ID do cliente
     * @return array  Cotas com campos 'is_paid' e 'paid_at_formatted' adicionados
     */
    public function getSalesWithPaymentStatus(int $clientId): array
    {
        $sales  = $this->sales->findByClientId($clientId);
        $ref    = $this->computeRefMonth();
        $refMes = $ref['mes'];
        $refAno = $ref['ano'];

        foreach ($sales as &$sale) {
            $isPaid       = false;
            $paidFormatted = null;

            if (!empty($sale['paid_at'])) {
                $paidDt   = new \DateTimeImmutable($sale['paid_at']);
                $refStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $refAno, $refMes));
                $now      = new \DateTimeImmutable('now');

                if ($paidDt >= $refStart && $paidDt <= $now) {
                    $isPaid        = true;
                    $paidFormatted = $paidDt->format('d/m/Y H:i');
                }
            }

            $sale['is_paid']          = $isPaid;
            $sale['paid_at_formatted'] = $paidFormatted;
        }
        unset($sale);

        return $sales;
    }

    /**
     * Retorna IDs de clientes que possuem cota não paga no mês de referência.
     *
     * @return array<int>
     */
    public function getOverdueClientIds(): array
    {
        $hoje    = new \DateTimeImmutable('now');
        $diaHoje = (int) $hoje->format('j');

        if ($diaHoje < $this->getTenantCutoffDay()) {
            return [];
        }

        $ref      = $this->computeRefMonth();
        $refStart = sprintf('%04d-%02d-01 00:00:00', $ref['ano'], $ref['mes']);

        return $this->sales->findOverdueClientIds($refStart, $hoje->format('Y-m-d H:i:s'));
    }

    private function computeRefMonth(): array
    {
        $hoje    = new \DateTimeImmutable('now');
        $diaHoje = (int) $hoje->format('j');

        if ($diaHoje >= $this->getTenantCutoffDay()) {
            return ['mes' => (int) $hoje->format('n'), 'ano' => (int) $hoje->format('Y')];
        }

        $refDt = $hoje->modify('first day of last month');
        return ['mes' => (int) $refDt->format('n'), 'ano' => (int) $refDt->format('Y')];
    }

    private function getTenantCutoffDay(): int
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare('SELECT payment_cutoff_day FROM tenants WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) ($_SESSION['tenant_id'] ?? 0)]);
            $value = $stmt->fetchColumn();
            return ((int) $value) ?: 20;
        } catch (\RuntimeException) {
            return 20;
        }
    }
}
