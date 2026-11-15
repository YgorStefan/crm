<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * com o cookie samesite strict, o link do crm clicado em outro site chega sem
 * o cookie, e a sessao nova que o bootstrap abriria mandaria um set-cookie que
 * troca o do usuario e o desloga em todas as abas. a pagina de recarga sai sem
 * sessao e pede a mesma url a partir do proprio crm, e essa segunda navegacao
 * ja leva o cookie. um 302 nao serve: o redirect continua a navegacao que veio
 * de fora, e o cookie strict continua sem vir.
 */
final class CrossSiteArrival
{
    /**
     * so navegacao de pagina: imagem, fetch e iframe de outro site nao trocam
     * de pagina, e recarregar dentro de um iframe continuaria sem o cookie.
     * navegador sem os cabecalhos sec-fetch segue o caminho de antes.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     */
    public static function needsReload(array $server, array $cookies, string $sessionName): bool
    {
        return in_array($server['REQUEST_METHOD'] ?? null, ['GET', 'HEAD'], true)
            && ($server['HTTP_SEC_FETCH_SITE'] ?? null) === 'cross-site'
            && ($server['HTTP_SEC_FETCH_MODE'] ?? null) === 'navigate'
            && ($server['HTTP_SEC_FETCH_DEST'] ?? null) === 'document'
            && !isset($cookies[$sessionName]);
    }

    /**
     * a origem vem do app_url e o caminho do request_uri, que ja traz a
     * subpasta. as barras do comeco caem porque //outro.site viraria um
     * endereco de outro host.
     */
    public static function reloadUrl(string $requestUri, string $appUrl): string
    {
        $path  = '/' . ltrim($requestUri, '/\\');
        $parts = parse_url($appUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $path;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port . $path;
    }

    public static function reloadPage(string $url): string
    {
        $target = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta http-equiv="refresh" content="0;url=' . $target . '">'
            . '<title>CRM</title></head><body>'
            . '<p><a href="' . $target . '">Continuar para o CRM</a></p>'
            . '</body></html>';
    }
}
