<?php
// --- Fuso horário ---
date_default_timezone_set('America/Sao_Paulo');

// --- Caminhos absolutos no servidor ---
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));          // raiz do projeto (crm/)
define('APP_PATH', ROOT_PATH . DS . 'app');
define('CORE_PATH', ROOT_PATH . DS . 'core');
define('VIEW_PATH', APP_PATH . DS . 'Views');
define('PUBLIC_PATH', ROOT_PATH . DS . 'public');
define('UPLOAD_PATH', PUBLIC_PATH . DS . 'uploads');

// --- Helper: env() ---
// Lê variáveis do $_ENV em caso de provedores (Hostinger) que bloqueiam putenv()
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '')
            return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '')
            return $_SERVER[$key];
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : $default;
    }
}

// --- Carregar variáveis de ambiente (.env) ---
$envFile = ROOT_PATH . DS . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#'))
            continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1], ' "\'');
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                @putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// --- URL base da aplicação ---
define('APP_URL', env('APP_URL', 'http://localhost/crm/public'));
define('APP_NAME', env('APP_NAME', 'CRM Empresarial'));
define('APP_ENV', env('APP_ENV', 'development')); // 'production' em deploy

// --- Sessão ---
define('SESSION_NAME', env('SESSION_NAME', 'crm_session'));
define('SESSION_LIFETIME', env('SESSION_LIFETIME', 7200)); // 2 horas em segundos

// --- Segurança ---
define('MIN_PASSWORD_LENGTH', 8);

// --- Exibição de erros ---
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
