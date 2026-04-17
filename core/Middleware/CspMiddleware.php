<?php

namespace Core\Middleware;

class CspMiddleware
{
    public function handle(): void
    {
        $nonce = bin2hex(random_bytes(16));
        define('CSP_NONCE', $nonce);

        $csp = "default-src 'self'; " .
               "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "img-src 'self' data:; " .
               "font-src 'self' data: https://fonts.gstatic.com; " .
               "connect-src 'self' https://cdn.jsdelivr.net; " .
               "frame-ancestors 'none'; " .
               "base-uri 'none'; " .
               "form-action 'self'";

        header("Content-Security-Policy: " . $csp);
    }
}
