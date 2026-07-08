<?php

namespace App\Services;

use Core\Database;

/**
 * Integração com Google Places API v1 (New).
 *
 * Usa searchText com X-Goog-FieldMask para retornar nome e telefone
 * em uma única chamada por página — sem Place Details separados.
 * Reduz custo de ~$1,10 para ~$0,12 por busca completa de 3 páginas.
 */
class PlacesApiService
{
    private string $apiKey = '';
    private string $appKey;

    public function __construct()
    {
        $this->appKey = (string) env('APP_KEY', '');
        if ($this->appKey === '') {
            throw new \RuntimeException('APP_KEY_MISSING');
        }
    }

    /**
     * Deriva a chave AES-256-CBC a partir da APP_KEY.
     *
     * Se APP_KEY começa com 'base64:', usa os 32 bytes brutos diretamente
     * (entropia máxima, estilo Laravel). Caso contrário, deriva via SHA-256
     * como fallback robusto para qualquer formato de string.
     */
    private function deriveAesKey(): string
    {
        if (str_starts_with($this->appKey, 'base64:')) {
            $raw = base64_decode(substr($this->appKey, 7));
            if (strlen($raw) === 32) {
                return $raw;
            }
        }
        return hash('sha256', $this->appKey, true);
    }

    /**
     * Lê e descriptografa a chave da API do banco para o tenant.
     *
     * @throws \RuntimeException API_KEY_NOT_CONFIGURED | API_KEY_DECRYPT_FAILED
     */
    public function loadApiKey(int $tenantId): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT google_maps_api_key FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $tenantId]);
        $row  = $stmt->fetch();

        if (!$row || empty($row['google_maps_api_key'])) {
            throw new \RuntimeException('API_KEY_NOT_CONFIGURED');
        }

        $parts = explode(':', $row['google_maps_api_key'], 2);
        if (count($parts) !== 2) {
            throw new \RuntimeException('API_KEY_DECRYPT_FAILED');
        }

        $iv        = base64_decode($parts[0]);
        $decrypted = openssl_decrypt($parts[1], 'aes-256-cbc', $this->deriveAesKey(), 0, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('API_KEY_DECRYPT_FAILED');
        }

        $this->apiKey = $decrypted;
    }

    /**
     * Busca lugares via Places API v1 searchText.
     *
     * @param  string       $term          Termo de busca (ex: "Dentista")
     * @param  string       $location      Cidade/estado (ex: "Curitiba PR")
     * @param  string|null  $pageToken     Token da próxima página (null = primeira página)
     * @param  bool         $onlyWithPhone Filtrar apenas leads com telefone
     * @return array{places: array, nextPageToken: string|null, total: int}
     * @throws \RuntimeException CURL_ERROR | API_KEY_INVALID | RATE_LIMIT | BILLING_DISABLED | API_ERROR
     */
    public function search(string $term, string $location, ?string $pageToken, bool $onlyWithPhone): array
    {
        $url  = 'https://places.googleapis.com/v1/places:searchText';
        $body = [
            'textQuery'    => $term . ' em ' . $location,
            'languageCode' => 'pt-BR',
            'regionCode'   => 'BR',
        ];

        if ($pageToken !== null && $pageToken !== '') {
            $body['pageToken'] = $pageToken;
        }

        $headers = [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $this->apiKey,
            'X-Goog-FieldMask: places.id,places.displayName,places.nationalPhoneNumber,nextPageToken',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        if ($raw === false || $curlErr !== '') {
            throw new \RuntimeException('CURL_ERROR: ' . $curlErr);
        }

        $data = json_decode((string) $raw, true) ?? [];

        if ($httpCode !== 200) {
            $status  = $data['error']['status']  ?? '';
            $message = $data['error']['message'] ?? '';
            if (in_array($status, ['PERMISSION_DENIED', 'REQUEST_DENIED'], true)) {
                throw new \RuntimeException('API_KEY_INVALID');
            }
            if ($status === 'RESOURCE_EXHAUSTED') {
                throw new \RuntimeException('RATE_LIMIT');
            }
            if ($status === 'FAILED_PRECONDITION' || str_contains(strtolower($message), 'billing')) {
                throw new \RuntimeException('BILLING_DISABLED');
            }
            throw new \RuntimeException('API_ERROR: ' . ($message ?: 'Unknown'));
        }

        $rawPlaces = $data['places'] ?? [];
        $places    = [];

        foreach ($rawPlaces as $p) {
            $name  = $p['displayName']['text'] ?? '';
            $phone = $p['nationalPhoneNumber'] ?? '';

            if ($onlyWithPhone && $phone === '') {
                continue;
            }

            $places[] = [
                'name'   => $name,
                'phone'  => $phone,
                'status' => $phone !== '' ? 'com_telefone' : 'sem_telefone',
            ];
        }

        return [
            'places'        => $places,
            'nextPageToken' => $data['nextPageToken'] ?? null,
            'total'         => count($places),
        ];
    }

    /**
     * Criptografa uma chave de API para armazenamento no banco.
     * Retorna string no formato base64(iv):ciphertext.
     * Usa a mesma lógica de deriveAesKey() para consistência.
     */
    public static function encryptKey(string $apiKey, string $appKey): string
    {
        // Mesma lógica do deriveAesKey() — deve permanecer sincronizada
        if (str_starts_with($appKey, 'base64:')) {
            $raw = base64_decode(substr($appKey, 7));
            $key = strlen($raw) === 32 ? $raw : hash('sha256', $appKey, true);
        } else {
            $key = hash('sha256', $appKey, true);
        }

        $ivLen      = openssl_cipher_iv_length('aes-256-cbc');
        $iv         = openssl_random_pseudo_bytes($ivLen);
        $ciphertext = openssl_encrypt($apiKey, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv) . ':' . $ciphertext;
    }
}
