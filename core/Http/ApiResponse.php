<?php

namespace Core\Http;

use Core\Middleware\CsrfMiddleware;

class ApiResponse
{
    public static function success(array $data = [], bool $token = false): array
    {
        $payload = array_merge(['success' => true], $data);
        if ($token) {
            $payload['csrf_token'] = CsrfMiddleware::getToken();
        }
        return $payload;
    }

    public static function error(string $message): array
    {
        return ['success' => false, 'error' => $message];
    }
}
