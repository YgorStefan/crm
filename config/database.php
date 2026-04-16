<?php
// Lê as credenciais de conexão diretamente do .env através
// do getenv() configurado no config/app.php

return [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', '3306'),
    'dbname' => env('DB_NAME', 'crm_db'),
    'user' => env('DB_USER', 'root'),
    'pass' => env('DB_PASS', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];
