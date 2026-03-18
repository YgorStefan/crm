<?php
// ============================================================
// config/app.php — Configurações Globais da Aplicação
// ============================================================
// Este arquivo define constantes que são usadas em todo o
// sistema. Carregue-o no início da aplicação (public/index.php)
// antes de qualquer outra coisa.
// ============================================================

// --- Fuso horário ---
// Garante que todas as operações de data/hora usem o horário
// correto do Brasil. Essencial para tarefas e interações.
date_default_timezone_set('America/Sao_Paulo');

// --- URL base da aplicação ---
// Usada para montar links (href, action de forms, redirects).
// Em produção, troque pelo domínio real (ex.: https://meucrm.com.br/public).
define('APP_URL',   getenv('APP_URL') ?: 'http://localhost/crm/public');
define('APP_NAME',  getenv('APP_NAME') ?: 'CRM Empresarial');
define('APP_ENV',   getenv('APP_ENV') ?: 'development'); // 'production' em deploy

// --- Caminhos absolutos no servidor ---
// DS = DIRECTORY_SEPARATOR (\ no Windows, / no Linux)
define('DS',        DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));          // raiz do projeto (crm/)

// --- Carregar variáveis de ambiente (.env) ---
$envFile = ROOT_PATH . DS . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1], ' "\''); // remove possíveis aspas
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
define('APP_PATH',  ROOT_PATH . DS . 'app');
define('CORE_PATH', ROOT_PATH . DS . 'core');
define('VIEW_PATH', APP_PATH  . DS . 'Views');
define('PUBLIC_PATH', ROOT_PATH . DS . 'public');
define('UPLOAD_PATH', PUBLIC_PATH . DS . 'uploads');

// --- Sessão ---
define('SESSION_NAME',     getenv('SESSION_NAME') ?: 'crm_session');
define('SESSION_LIFETIME', getenv('SESSION_LIFETIME') ?: 7200); // 2 horas em segundos

// --- Segurança ---
// Tamanho mínimo de senha exigido no cadastro de usuários
define('MIN_PASSWORD_LENGTH', 8);

// --- Exibição de erros ---
// Em desenvolvimento mostramos erros; em produção, silenciamos.
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
