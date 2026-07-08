<?php

namespace Core\Middleware;

class CspMiddleware
{
    public function handle(): void
    {
        $nonce = bin2hex(random_bytes(16));
        define('CSP_NONCE', $nonce);

        $csp = self::buildPolicy($nonce);

        // Em produção o CDN da Hostinger (hcdn) sobrescreve este header por
        // "upgrade-insecure-requests" antes de chegar ao navegador — a mesma
        // política também é emitida via <meta http-equiv="Content-Security-Policy">
        // nos layouts (app/Views/layouts/main.php e blank.php) usando o mesmo
        // nonce. Múltiplas políticas CSP (header + meta) são combinadas pelo
        // navegador em interseção, então a política do <meta> continua valendo
        // como reforço quando o header é perdido no caminho até o navegador.
        header("Content-Security-Policy: " . $csp);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Emitidos via PHP para nao dependerem de mod_headers no .htaccess
        // (em hosts sem o modulo as regras la viram no-op silencioso).
        header('X-Frame-Options: DENY');

        // HSTS apenas sob HTTPS — evita "travar" o acesso em http local/dev.
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Monta a política CSP efetiva (mesma em dev e produção).
     *
     * script-src é estrito (nonce + strict-dynamic): só executa script com o
     * nonce correto ou carregado dinamicamente por um script já confiável
     * (cobre os CDNs de Chart.js/FullCalendar, que já carregam com nonce).
     *
     * style-src precisa de 'unsafe-inline': várias views usam atributos
     * style="..." dinâmicos vindos do servidor (ex.: cor da etapa do pipeline
     * em app/Views/pipeline/index.php) e nonce não se aplica a atributos
     * style, só a tags <style>/<script>. É uma concessão deliberada — CSS
     * injection é um risco bem menor que XSS via script, que continua
     * totalmente bloqueado fora do nonce da requisição.
     */
    public static function buildPolicy(string $nonce): string
    {
        return "default-src 'self'; " .
               "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "img-src 'self' data:; " .
               "font-src 'self' data: https://fonts.gstatic.com; " .
               "connect-src 'self' https://viacep.com.br; " .
               "frame-ancestors 'none'; " .
               "base-uri 'none'; " .
               "form-action 'self'";
    }
}
