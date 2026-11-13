<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

if ($pdo->query("SHOW TABLES LIKE 'google_accounts'")->fetch()) {
    echo "Migration 021: tabela google_accounts já existe, ignorado.\n";
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS google_accounts (
            user_id                 INT UNSIGNED NOT NULL,
            tenant_id               INT UNSIGNED NOT NULL,
            google_email            VARCHAR(255) NOT NULL,
            google_sub              VARCHAR(255) NOT NULL,
            refresh_token           TEXT         NULL,
            access_token            TEXT         NULL,
            access_token_expires_at DATETIME     NULL,
            status                  ENUM('active','revoked','disconnected') NOT NULL DEFAULT 'active',
            drain_until             DATETIME     NULL,
            connected_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            INDEX idx_google_accounts_tenant (tenant_id),
            CONSTRAINT fk_google_accounts_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_google_accounts_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Migration 021: tabela google_accounts criada.\n";
}

if ($pdo->query("SHOW TABLES LIKE 'google_event_deletions'")->fetch()) {
    echo "Migration 021: tabela google_event_deletions já existe, ignorado.\n";
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS google_event_deletions (
            id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            tenant_id       INT UNSIGNED  NOT NULL,
            user_id         INT UNSIGNED  NOT NULL,
            google_event_id VARCHAR(1024) NOT NULL,
            created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_google_event_deletions_tenant_user (tenant_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Migration 021: tabela google_event_deletions criada.\n";
}

$columns = [
    'google_event_id'     => 'VARCHAR(255) NULL AFTER tenant_id',
    'google_owner_id'     => 'INT UNSIGNED NULL AFTER google_event_id',
    'google_etag'         => 'VARCHAR(100) NULL AFTER google_owner_id',
    'google_duration_min' => 'INT UNSIGNED NULL AFTER google_etag',
];

foreach ($columns as $column => $definition) {
    if ($pdo->query("SHOW COLUMNS FROM tasks LIKE '{$column}'")->fetch()) {
        echo "Migration 021: coluna {$column} já existe em tasks, ignorado.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE tasks ADD COLUMN {$column} {$definition}");
    echo "Migration 021: coluna {$column} adicionada em tasks.\n";
}

if ($pdo->query("SHOW COLUMNS FROM tasks LIKE 'google_sync_pending'")->fetch()) {
    echo "Migration 021: coluna google_sync_pending já existe em tasks, ignorado.\n";
} else {
    $pdo->exec("
        ALTER TABLE tasks
            ADD COLUMN google_sync_pending INT UNSIGNED NOT NULL DEFAULT 1 AFTER google_duration_min
    ");
    // o zeramento so pode rodar no mesmo bloco que criou a coluna: rodado de
    // novo, com a fila ja em uso, apagaria pendencias reais de envio.
    $zeradas = $pdo->exec("
        UPDATE tasks SET google_sync_pending = 0
        WHERE status IN ('done', 'cancelled') OR due_date < CURDATE()
    ");
    echo "Migration 021: coluna google_sync_pending adicionada em tasks ({$zeradas} tarefas encerradas ou vencidas ficaram sem pendência).\n";
}

$indexes = [
    'idx_tasks_google_queue' => 'INDEX idx_tasks_google_queue (tenant_id, google_sync_pending, assigned_to, due_date)',
    'idx_tasks_google_event' => 'INDEX idx_tasks_google_event (tenant_id, google_event_id)',
];

foreach ($indexes as $indexName => $definition) {
    if ($pdo->query("SHOW INDEX FROM tasks WHERE Key_name = '{$indexName}'")->fetch()) {
        echo "Migration 021: índice {$indexName} já existe em tasks, ignorado.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE tasks ADD {$definition}");
    echo "Migration 021: índice {$indexName} criado em tasks.\n";
}

echo "Migration 021 concluída.\n";
