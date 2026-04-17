<?php
/**
 * Migration 006 — Criar tabela login_attempts para rate limiting.
 * Execute: php database/migrations/006_add_login_attempts_table.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip           VARCHAR(45)  NOT NULL,
        attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_login_attempts_ip_time (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Tabela login_attempts criada.\n";
