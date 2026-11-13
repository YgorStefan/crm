<?php
// tests/bootstrap.php — Bootstrap mínimo para testes unitários.
// Não carrega config/app.php (evita .env e sessão de produção); define
// apenas as constantes que o código sob teste usa.

date_default_timezone_set('America/Sao_Paulo');

// copia do env() de config/app.php, que este arquivo nao carrega, sem a leitura
// de $_SERVER: os testes configuram pelo $_ENV ou pelo ambiente do processo.
function env(string $key, mixed $default = null): mixed
{
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
}

// nome do banco descartavel dos testes de integracao; vai direto no CREATE DATABASE e no
// USE, que nao aceitam placeholder, entao so passa o que um identificador simples aceita.
function testDatabaseName(): string
{
    $name = getenv('DB_TEST_NAME') ?: 'crm_test';
    if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
        throw new \InvalidArgumentException('DB_TEST_NAME so aceita letras, numeros e _: ' . $name);
    }
    return $name;
}

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
