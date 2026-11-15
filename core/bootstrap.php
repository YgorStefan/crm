<?php
// core/bootstrap.php — Inicialização do sistema
// Responsável por:
//   1. Carregar configurações globais (app.php)
//   2. Configurar e iniciar a sessão PHP de forma segura
//   3. Registrar o autoloader PSR-4
//   4. Incluir helpers globais (core/helpers.php)

// ------------------------------------------------------------------
// 1. Configurações globais
// ------------------------------------------------------------------
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

// ------------------------------------------------------------------
// 2. Sessão segura
//    As constantes SESSION_NAME e SESSION_LIFETIME são definidas em
//    config/app.php, por isso o require acima vem primeiro.
// ------------------------------------------------------------------
ini_set('session.cookie_httponly', '1');
// lax e nao strict: com strict, o link do crm clicado no gmail, no whatsapp ou
// no google agenda chegava sem o cookie, e a sessao nova trocava o cookie e
// deslogava o usuario em todas as abas. o lax so manda o cookie de outro site
// em navegacao de pagina com get, e o post continua protegido pelo csrf.
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

session_name(SESSION_NAME);

// ------------------------------------------------------------------
// 3. Autoloader PSR-4
//    Mapeamento de namespaces para diretórios:
//      Core\*  → core/*.php
//      App\*   → app/*.php
// ------------------------------------------------------------------
spl_autoload_register(function (string $className): void {
    $relativePath = str_replace('\\', DS, $className) . '.php';

    $namespaceMap = [
        'Core' . DS => CORE_PATH . DS,
        'App' . DS  => APP_PATH . DS,
    ];

    foreach ($namespaceMap as $prefix => $baseDir) {
        if (str_starts_with($relativePath, $prefix)) {
            $file = $baseDir . substr($relativePath, strlen($prefix));
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// a recusa vem antes do session_start, que por isso espera o autoloader: a
// sessao nova de um post de outro site, que chega sem o cookie, mandaria um
// set-cookie que troca o do usuario e o desloga em todas as abas.
if (\Core\Http\CrossSiteRequest::isUnsafe($_SERVER)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ação bloqueada: o pedido veio de outro site.';
    exit;
}

session_start();

// ------------------------------------------------------------------
// 4. Helpers globais
// ------------------------------------------------------------------
require_once CORE_PATH . DS . 'helpers.php';

// Garantir proteção do diretório de logs desde o bootstrap (não lazy)
$_logDir     = ROOT_PATH . '/storage/logs';
$_htaccessLog = $_logDir . '/.htaccess';
if (!is_dir($_logDir)) {
    mkdir($_logDir, 0755, true);
}
if (!file_exists($_htaccessLog)) {
    file_put_contents($_htaccessLog, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
}
unset($_logDir, $_htaccessLog);
