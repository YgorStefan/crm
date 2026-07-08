<?php
// tests/bootstrap.php — Bootstrap mínimo para testes unitários.
// Não carrega config/app.php (evita .env e sessão de produção); define
// apenas as constantes que o código sob teste usa.

define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DS . 'app');
define('CORE_PATH', ROOT_PATH . DS . 'core');
define('VIEW_PATH', APP_PATH . DS . 'Views');
define('APP_URL', 'http://localhost/crm/public');
define('APP_NAME', 'CRM Teste');
define('SESSION_LIFETIME', 7200);

// Mesmo autoloader PSR-4 de core/bootstrap.php
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

require_once CORE_PATH . DS . 'helpers.php';

// Sessão iniciada cedo (antes de qualquer output do PHPUnit) para que
// código que usa $_SESSION possa ser testado.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
