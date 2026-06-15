<?php
/**
 * Migration 016 — Garante UNIQUE global em users.email.
 *
 * Por que existe:
 *   O login (User::findByEmail) resolve o usuario por email em todos os
 *   tenants com LIMIT 1. O schema.sql ja declara UNIQUE KEY uq_users_email,
 *   mas bancos provisionados antes dessa garantia podem ter perdido o indice,
 *   permitindo o mesmo email em dois tenants — caso em que o login escolhe
 *   uma linha silenciosamente. Esta migration restaura a constraint.
 *
 *   Se ja existirem emails duplicados, NAO cria a constraint: apenas reporta
 *   os conflitos para resolucao manual (criar a UNIQUE falharia de qualquer
 *   forma e bloquearia o deploy).
 *
 * Idempotente: pode ser executada multiplas vezes sem efeito colateral.
 *
 * Execute: php database/migrations/016_ensure_users_email_unique.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_email'")->fetch();
if ($idx) {
    echo "Migration 016: UNIQUE uq_users_email ja existe — ignorado.\n";
    echo "Migration 016 concluida.\n";
    return;
}

$dups = $pdo->query("
    SELECT email, COUNT(*) AS total
    FROM users
    GROUP BY email
    HAVING total > 1
")->fetchAll();

if ($dups) {
    echo "Migration 016: AVISO — emails duplicados encontrados; constraint NAO criada.\n";
    foreach ($dups as $d) {
        echo "  - {$d['email']} ({$d['total']} ocorrencias)\n";
    }
    echo "Migration 016: resolva os duplicados e rode novamente.\n";
    return;
}

$pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)");
echo "Migration 016: UNIQUE uq_users_email criada.\n";
echo "Migration 016 concluida.\n";
