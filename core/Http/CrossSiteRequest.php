<?php

declare(strict_types=1);

namespace Core\Http;

final class CrossSiteRequest
{
    /**
     * post, put, patch e delete vindos de outro site chegam sem o cookie lax e
     * nunca sao legitimos, porque o token csrf mora na sessao. navegador que
     * nao manda o sec-fetch-site segue para o csrf, como antes.
     *
     * @param array<string, mixed> $server
     */
    public static function isUnsafe(array $server): bool
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        return !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
            && ($server['HTTP_SEC_FETCH_SITE'] ?? null) === 'cross-site';
    }
}
