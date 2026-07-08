<?php
/**
 * Router script para PHP built-in server.
 * Substitui o mod_rewrite do .htaccess:
 *  - Se o arquivo existir fisicamente em public/ → serve diretamente (assets, imagens)
 *  - Qualquer outra rota → passa para o front controller (public/index.php)
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$file = __DIR__ . '/public' . $uri;
if ($uri !== '/' && is_file($file)) {
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'css'         => 'text/css',
        'js'          => 'application/javascript',
        'png'         => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'         => 'image/gif',
        'svg'         => 'image/svg+xml',
        'ico'         => 'image/x-icon',
        'woff'        => 'font/woff',
        'woff2'       => 'font/woff2',
        'map'         => 'application/json',
        default       => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($file);
    exit;
}

require_once __DIR__ . '/public/index.php';
