<?php
/**
 * Migration 007 — Adicionar colunas password_must_change e is_system_admin em users.
 * Execute: php database/migrations/007_add_users_flags.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// Adicionar password_must_change se não existir
$col = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_must_change'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE users ADD COLUMN password_must_change TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = forçar troca na sessão seguinte'");
    echo "Coluna password_must_change adicionada.\n";
} else {
    echo "Coluna password_must_change já existe.\n";
}

// Adicionar is_system_admin se não existir
$col2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_system_admin'")->fetch();
if (!$col2) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_system_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = acesso a /admin/tenants'");
    echo "Coluna is_system_admin adicionada.\n";
} else {
    echo "Coluna is_system_admin já existe.\n";
}

// Marcar o admin seed (id=1) com password_must_change = 1
$pdo->exec("UPDATE users SET password_must_change = 1 WHERE id = 1 AND role = 'admin'");
echo "Admin seed marcado para troca de senha.\n";
