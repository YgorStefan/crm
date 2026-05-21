<?php

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Http\ApiResponse;
use Core\Middleware\CsrfMiddleware;
use App\Services\PlacesApiService;

/**
 * ProspectingController — Prospecção de leads via Google Places API v1.
 * Acesso restrito a roles admin e seller.
 */
class ProspectingController extends Controller
{
    public function index(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);

        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        $db       = Database::getInstance();
        $stmt     = $db->prepare('SELECT google_maps_api_key FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $tenantId]);
        $row = $stmt->fetch();

        $this->render('prospecting/index', [
            'pageTitle'     => 'Prospecção de Leads',
            'title'         => 'Prospecção — ' . APP_NAME,
            'has_gmaps_key' => !empty($row['google_maps_api_key']),
            'csrf_token'    => CsrfMiddleware::getToken(),
        ]);
    }

    public function search(array $params = []): void
    {
        $this->requireRole(['admin', 'seller']);

        $tenantId      = (int) ($_SESSION['tenant_id'] ?? 0);
        $term          = trim($_POST['term'] ?? '');
        $location      = trim($_POST['location'] ?? '');
        $pageToken     = trim($_POST['pageToken'] ?? '') ?: null;
        $onlyWithPhone = ($_POST['onlyWithPhone'] ?? 'false') === 'true';

        if ($term === '' || $location === '') {
            $this->json(ApiResponse::error('Termo de busca e localidade são obrigatórios.'), 422);
            return;
        }

        try {
            $service = new PlacesApiService();
            $service->loadApiKey($tenantId);
            $result = $service->search($term, $location, $pageToken, $onlyWithPhone);
            $this->json(ApiResponse::success($result));
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            match (true) {
                $msg === 'APP_KEY_MISSING'        => $this->json(ApiResponse::error('APP_KEY não configurada. Contate o administrador do servidor.'), 500),
                $msg === 'API_KEY_NOT_CONFIGURED' => $this->json(ApiResponse::error('Chave de API não configurada. Peça ao admin para configurar em Configurações.'), 403),
                $msg === 'API_KEY_INVALID'        => $this->json(ApiResponse::error('Chave de API inválida ou sem permissão para Places API.'), 422),
                $msg === 'RATE_LIMIT'             => $this->json(ApiResponse::error('Limite de requisições atingido. Aguarde e tente novamente.'), 429),
                $msg === 'BILLING_DISABLED'       => $this->json(ApiResponse::error('A conta de faturamento do Google Cloud está inativa. Verifique o Google Cloud Console.'), 402),
                default                           => $this->json(ApiResponse::error('Erro ao conectar com o Google Maps. Tente novamente.'), 502),
            };
        }
    }
}
