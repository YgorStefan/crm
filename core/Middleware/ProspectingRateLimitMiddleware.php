<?php

namespace Core\Middleware;

use Core\Database;
use Core\Http\ApiResponse;
use Core\Logger;

/**
 * Throttle por tenant para o endpoint pago de prospecting (Google Places).
 * Janela deslizante: no maximo MAX_REQUESTS chamadas por WINDOW_SECONDS por
 * tenant. Ao estourar, responde 429 em JSON e encerra a requisicao.
 */
class ProspectingRateLimitMiddleware
{
    private const MAX_REQUESTS   = 30;
    private const WINDOW_SECONDS = 60;

    public function handle(): void
    {
        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tenantId === 0) {
            return; // AuthMiddleware ja garante login; sem tenant nao ha o que limitar.
        }

        $pdo = Database::getInstance();

        // Limpa registros antigos (mantem a tabela enxuta).
        $pdo->prepare("DELETE FROM prospecting_requests WHERE requested_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")
            ->execute();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM prospecting_requests
            WHERE tenant_id = :tid
              AND requested_at > DATE_SUB(NOW(), INTERVAL :secs SECOND)
        ");
        $stmt->execute([':tid' => $tenantId, ':secs' => self::WINDOW_SECONDS]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= self::MAX_REQUESTS) {
            (new Logger())->warning("Throttle de prospecting atingido para tenant {$tenantId}");
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(ApiResponse::error('Limite de buscas atingido. Aguarde um instante antes de tentar novamente.'));
            exit;
        }

        $pdo->prepare("INSERT INTO prospecting_requests (tenant_id) VALUES (:tid)")
            ->execute([':tid' => $tenantId]);
    }
}
