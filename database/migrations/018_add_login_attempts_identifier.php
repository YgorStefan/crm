<?php
/**
 * Migration 018 — Adiciona login_attempts.identifier (lockout por conta).
 *
 * Por que existe:
 *   O rate limit de login era apenas por IP. Um atacante distribuido (varios
 *   IPs) podia testar senhas de uma mesma conta sem disparar o limite. Esta
 *   coluna guarda o email tentado para o RateLimitMiddleware aplicar tambem
 *   um bucket por conta.
 *
 * Idempotente: pode ser executada multiplas vezes sem efeito colateral.
 *
 * Execute: php database/migrations/018_add_login_attempts_identifier.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$col = $pdo->query("SHOW COLUMNS FROM login_attempts LIKE 'identifier'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE login_attempts ADD COLUMN identifier VARCHAR(150) NULL AFTER ip");
    echo "Migration 018: coluna login_attempts.identifier criada.\n";
} else {
    echo "Migration 018: coluna login_attempts.identifier ja existe — ignorado.\n";
}

$idx = $pdo->query("SHOW INDEX FROM login_attempts WHERE Key_name = 'idx_login_attempts_identifier_time'")->fetch();
if (!$idx) {
    $pdo->exec("CREATE INDEX idx_login_attempts_identifier_time ON login_attempts(identifier, attempted_at)");
    echo "Migration 018: indice idx_login_attempts_identifier_time criado.\n";
}

echo "Migration 018 concluida.\n";
