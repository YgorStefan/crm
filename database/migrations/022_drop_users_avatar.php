<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// so pode rodar depois do deploy do codigo que parou de usar a coluna, porque o
// login antigo le users.avatar e quebraria sem ela.
if ($pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'")->fetch()) {
    $pdo->exec('ALTER TABLE users DROP COLUMN avatar');
    echo "Migration 022: coluna avatar removida de users.\n";
} else {
    echo "Migration 022: coluna avatar já não existe em users, ignorado.\n";
}

echo "Migration 022 concluída.\n";
