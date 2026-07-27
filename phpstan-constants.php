<?php

// Simbolos que o config/app.php cria em runtime e que o PHPStan nao descobre
// sozinho: as constantes vem de define() com valor de env(), e o proprio env()
// e declarado dentro de um if (!function_exists(...)).
//
// Este arquivo NAO e carregado pela aplicacao - so pelo bootstrapFiles do
// phpstan.neon. Os valores existem para dar o tipo, nao o conteudo; as que
// mudam por ambiente estao em dynamicConstantNames no phpstan.neon, senao o
// PHPStan conclui que comparar APP_ENV com 'production' e sempre falso.

define('ROOT_PATH', __DIR__);
define('APP_PATH', __DIR__ . '/app');
define('CORE_PATH', __DIR__ . '/core');
define('VIEW_PATH', __DIR__ . '/app/Views');
define('PUBLIC_PATH', __DIR__ . '/public');
define('UPLOAD_PATH', __DIR__ . '/public/uploads');
define('APP_URL', 'http://localhost');
define('APP_NAME', 'CRM Empresarial');
define('APP_ENV', 'development');
define('SESSION_NAME', 'crm_session');
define('SESSION_LIFETIME', 7200);
define('MIN_PASSWORD_LENGTH', 8);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}
