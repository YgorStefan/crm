<?php
/**
 * Migration 011 — Adiciona neighborhood em clients e corrige is_won_stage.
 *
 * 1. Adiciona coluna neighborhood (bairro) em clients, preenchida via ViaCEP.
 * 2. Garante que a etapa "Fechado - Ganho" tenha is_won_stage = 1 no banco
 *    existente (sem isso os cards de Cotas/Pagamentos não aparecem no show).
 *
 * Execute: php database/migrations/011_add_neighborhood_to_clients.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// 1. Adiciona neighborhood se ainda não existir
$col = $pdo->query("SHOW COLUMNS FROM clients LIKE 'neighborhood'")->fetch();
if (!$col) {
    $pdo->exec("
        ALTER TABLE clients
        ADD COLUMN neighborhood VARCHAR(100) NULL COMMENT 'Bairro — preenchido via ViaCEP'
        AFTER address
    ");
    echo "Migration 011: coluna neighborhood adicionada em clients.\n";
} else {
    echo "Migration 011: coluna neighborhood já existe — ignorado.\n";
}

// 2. Corrige is_won_stage para "Fechado - Ganho" em bancos existentes
$stmt = $pdo->prepare("
    UPDATE pipeline_stages
    SET is_won_stage = 1
    WHERE name = 'Fechado - Ganho'
      AND is_won_stage = 0
");
$stmt->execute();
$updated = $stmt->rowCount();
if ($updated > 0) {
    echo "Migration 011: {$updated} etapa(s) 'Fechado - Ganho' corrigida(s) — is_won_stage = 1.\n";
} else {
    echo "Migration 011: is_won_stage já correto ou etapa não encontrada.\n";
}

echo "Migration 011 concluída.\n";
