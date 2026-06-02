<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$col = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'recurrence_type'")->fetch();
if (!$col) {
    $pdo->exec("
        ALTER TABLE tasks
            ADD COLUMN recurrence_type ENUM('none','weekly','monthly','yearly')
                NOT NULL DEFAULT 'none' AFTER status,
            ADD COLUMN recurrence_parent_id INT UNSIGNED NULL AFTER recurrence_type,
            ADD CONSTRAINT fk_task_recurrence_parent
                FOREIGN KEY (recurrence_parent_id) REFERENCES tasks(id) ON DELETE CASCADE
    ");
    echo "Migration 015: colunas recurrence_type e recurrence_parent_id adicionadas em tasks.\n";
} else {
    echo "Migration 015: colunas já existem — ignorado.\n";
}

echo "Migration 015 concluída.\n";
