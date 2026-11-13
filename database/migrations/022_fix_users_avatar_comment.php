<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$stmt = $pdo->prepare("
    SELECT c.COLUMN_TYPE, c.IS_NULLABLE, c.COLUMN_DEFAULT, c.COLLATION_NAME, c.COLUMN_COMMENT,
           t.TABLE_COLLATION
    FROM information_schema.COLUMNS c
    JOIN information_schema.TABLES t
      ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
    WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = 'users' AND c.COLUMN_NAME = 'avatar'
");
$stmt->execute();
$col = $stmt->fetch();

if ($col === false) {
    throw new RuntimeException('Migration 022: coluna users.avatar não encontrada.');
}

if ($col['COLUMN_COMMENT'] === 'URL http(s) da foto de perfil, informada pelo admin') {
    echo "Migration 022: comentário de users.avatar já corrigido, ignorado.\n";
} else {
    // o modify reescreve a coluna inteira, entao so roda se ela ainda e a do
    // schema.sql; com outra definicao ele trocaria tipo, default ou collation
    // sem aviso. o mariadb devolve o default nulo como o texto NULL.
    $default = $col['COLUMN_DEFAULT'];
    if ($col['COLUMN_TYPE'] !== 'varchar(255)'
        || $col['IS_NULLABLE'] !== 'YES'
        || ($default !== null && $default !== 'NULL')
        || $col['COLLATION_NAME'] !== $col['TABLE_COLLATION']
    ) {
        throw new RuntimeException('Migration 022: users.avatar não está como VARCHAR(255) NULL na collation da tabela; revise antes de aplicar.');
    }

    $pdo->exec("
        ALTER TABLE users
            MODIFY avatar VARCHAR(255) NULL COMMENT 'URL http(s) da foto de perfil, informada pelo admin'
    ");
    echo "Migration 022: comentário de users.avatar corrigido.\n";
}

echo "Migration 022 concluída.\n";
