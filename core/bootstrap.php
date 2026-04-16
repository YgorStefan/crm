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
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('session.cookie_secure', '1');
}

session_name(SESSION_NAME);
session_start();

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

// ------------------------------------------------------------------
// 4. Helpers globais
// ------------------------------------------------------------------
require_once CORE_PATH . DS . 'helpers.php';
