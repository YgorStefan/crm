<?php
// ============================================================
// config/database.php — Credenciais de Conexão com o MySQL
// ============================================================
// Lê as credenciais de conexão diretamente do .env através
// do getenv() configurado no config/app.php
// ============================================================

return [
    'host'    => getenv('DB_HOST') ?: 'localhost',
    'port'    => getenv('DB_PORT') ?: '3306',
    'dbname'  => getenv('DB_NAME') ?: 'crm_db',
    'user'    => getenv('DB_USER') ?: 'root',
    'pass'    => getenv('DB_PASS') ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];
