<?php
/**
 * Migration 017 — Tabela prospecting_requests (throttle por tenant).
 *
 * Por que existe:
 *   O endpoint POST /api/prospecting/search consome a Google Places API, que
 *   e paga. Sem limite, um loop no front ou abuso de um tenant pode gerar
 *   custo descontrolado. Esta tabela registra cada chamada por tenant para
 *   o ProspectingRateLimitMiddleware aplicar uma janela deslizante.
 *
 * Idempotente: pode ser executada multiplas vezes sem efeito colateral.
 *
 * Execute: php database/migrations/017_add_prospecting_requests_table.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$exists = $pdo->query("SHOW TABLES LIKE 'prospecting_requests'")->fetch();
if ($exists) {
    echo "Migration 017: tabela prospecting_requests ja existe — ignorado.\n";
    echo "Migration 017 concluida.\n";
    return;
}

$pdo->exec("
    CREATE TABLE prospecting_requests (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id    INT UNSIGNED NOT NULL,
        requested_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_prospecting_tenant_time (tenant_id, requested_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "Migration 017: tabela prospecting_requests criada.\n";
echo "Migration 017 concluida.\n";
