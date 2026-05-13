<?php

namespace Core\Middleware;

class CspMiddleware
{
    public function handle(): void
    {
        $nonce = bin2hex(random_bytes(16));
        define('CSP_NONCE', $nonce);

        // Em produção, o CDN da Hostinger (hcdn) sobrescreve este header por
        // "upgrade-insecure-requests", então o CSP estrito abaixo não chega ao
        // navegador. Mantemos o valor original aqui para preservar o comportamento
        // já validado em produção.
        // Em ambientes locais/dev não há CDN no caminho, e o CSP estrito bloqueia
        // atributos style="..." inline (usados pelas cores do pipeline/clients).
        // Por isso espelhamos o CSP efetivo de produção apenas com
        // "upgrade-insecure-requests".
        if (defined('APP_ENV') && APP_ENV === 'production') {
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'; " .
                   "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com; " .
                   "img-src 'self' data:; " .
                   "font-src 'self' data: https://fonts.gstatic.com; " .
                   "connect-src 'self' https://viacep.com.br; " .
                   "frame-ancestors 'none'; " .
                   "base-uri 'none'; " .
                   "form-action 'self'";
        } else {
            $csp = "upgrade-insecure-requests";
        }

        header("Content-Security-Policy: " . $csp);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
