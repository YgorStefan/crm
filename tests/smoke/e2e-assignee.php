<?php

// usuario proprio dos specs que desativam o responsavel depois que a tela carregou,
// para nao mexer nos usuarios de login. uso: php e2e-assignee.php on|off

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

const E2E_ASSIGNEE_EMAIL = 'e2e.responsavel@crm.local';
const E2E_ASSIGNEE_NAME = 'E2E Responsável Desativado';

$mode = $argv[1] ?? '';
if ($mode !== 'on' && $mode !== 'off') {
    fwrite(STDERR, "[e2e-assignee] uso: php e2e-assignee.php on|off\n");
    exit(2);
}
$active = $mode === 'on' ? 1 : 0;

try {
    $pdo = Core\Database::getInstance();
    // a senha e aleatoria porque ninguem entra com este usuario, ele so aparece no select.
    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, role, tenant_id, password_must_change, is_active)
        VALUES (:name, :email, :hash, \'seller\', 1, 0, :active_insert)
        ON DUPLICATE KEY UPDATE is_active = :active_update
    ');
    $stmt->execute([
        'name'          => E2E_ASSIGNEE_NAME,
        'email'         => E2E_ASSIGNEE_EMAIL,
        'hash'          => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
        'active_insert' => $active,
        'active_update' => $active,
    ]);
    echo "[e2e-assignee] {$mode}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[e2e-assignee] ERRO: {$e->getMessage()}\n");
    exit(1);
}
