<?php

namespace Core\Http;

use Core\Middleware\CsrfMiddleware;

class ApiResponse
{
    /**
     * Monta payload JSON de sucesso, opcionalmente com CSRF token renovado.
     *
     * @param  array  $data   Dados adicionais a mesclar no payload
     * @param  bool   $token  Se true, inclui 'csrf_token' com token renovado
     * @return array
     */
    public static function success(array $data = [], bool $token = false): array
    {
        $payload = array_merge(['success' => true], $data);
        if ($token) {
            $payload['csrf_token'] = CsrfMiddleware::getToken();
        }
        return $payload;
    }

    /**
     * Monta payload JSON de erro com shape consistente para o frontend.
     *
     * @param  string  $message  Mensagem de erro legível pelo usuário
     * @return array
     */
    public static function error(string $message): array
    {
        return ['success' => false, 'error' => $message];
    }
}
