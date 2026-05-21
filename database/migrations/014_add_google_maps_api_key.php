<?php
/**
 * Migration 014 — Adiciona google_maps_api_key em tenants.
 *
 * Armazena a chave da API Google Maps criptografada com AES-256-CBC + IV.
 * Formato: base64(iv):ciphertext
 *
 * Execute: php database/migrations/014_add_google_maps_api_key.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$col = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'google_maps_api_key'")->fetch();
if (!$col) {
    $pdo->exec("
        ALTER TABLE tenants
        ADD COLUMN google_maps_api_key VARCHAR(500) NULL DEFAULT NULL
        COMMENT 'Chave API Google Maps — criptografada AES-256-CBC+IV'
    ");
    echo "Migration 014: coluna google_maps_api_key adicionada em tenants.\n";
} else {
    echo "Migration 014: coluna google_maps_api_key já existe — ignorado.\n";
}

echo "Migration 014 concluída.\n";
