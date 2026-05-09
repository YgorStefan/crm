<?php
/**
 * Migration 012 — Adiciona address_number e address_complement em clients.
 *
 * Adiciona os campos Número e Complemento na tabela clients,
 * separando-os do campo address (logradouro).
 *
 * Execute: php database/migrations/012_add_address_fields_to_clients.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// Adiciona address_number se ainda não existir
$col = $pdo->query("SHOW COLUMNS FROM clients LIKE 'address_number'")->fetch();
if (!$col) {
    $pdo->exec("
        ALTER TABLE clients
        ADD COLUMN address_number VARCHAR(20) NULL COMMENT 'Número do endereço'
        AFTER address
    ");
    echo "Migration 012: coluna address_number adicionada em clients.\n";
} else {
    echo "Migration 012: coluna address_number já existe — ignorado.\n";
}

// Adiciona address_complement se ainda não existir
$col2 = $pdo->query("SHOW COLUMNS FROM clients LIKE 'address_complement'")->fetch();
if (!$col2) {
    $pdo->exec("
        ALTER TABLE clients
        ADD COLUMN address_complement VARCHAR(100) NULL COMMENT 'Complemento do endereço'
        AFTER address_number
    ");
    echo "Migration 012: coluna address_complement adicionada em clients.\n";
} else {
    echo "Migration 012: coluna address_complement já existe — ignorado.\n";
}

echo "Migration 012 concluída.\n";
