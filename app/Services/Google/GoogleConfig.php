<?php

declare(strict_types=1);

namespace App\Services\Google;

final class GoogleConfig
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $appKey,
        public readonly string $redirectUri,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            (string) env('GOOGLE_CLIENT_ID', ''),
            (string) env('GOOGLE_CLIENT_SECRET', ''),
            (string) env('APP_KEY', ''),
            APP_URL . '/google/callback',
        );
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->appKey !== '';
    }

    public function eventIdFor(int $tenantId, int $taskId): string
    {
        // o id nao pode mudar entre versoes, porque o reenvio depende dele para
        // cair no mesmo evento. o rotulo na mensagem evita publicar na agenda um
        // resumo da chave que sirva em outro uso dela.
        return substr(hash_hmac('sha256', "google-event|{$tenantId}|{$taskId}", $this->appKey), 0, 40);
    }
}
