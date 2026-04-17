<?php
/**
 * Migration 004 — Decodificar htmlentities gravados erroneamente no banco.
 *
 * Problema: Controller::input() aplicava htmlspecialchars() antes de gravar,
 * corrompendo dados. Ex: "Smith & Co" → "Smith &amp; Co".
 *
 * Esta migration percorre as colunas de texto afetadas e aplica html_entity_decode()
 * apenas quando o valor atual difere do decodificado.
 *
 * ATENÇÃO: Fazer backup antes de rodar. Rodar em staging primeiro.
 *
 * Execute: php database/migrations/004_decode_htmlentities.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$columns = [
    'clients'       => ['name', 'company', 'email', 'phone', 'address', 'notes'],
    'interactions'  => ['description'],
    'tasks'         => ['title', 'description'],
    'cold_contacts' => ['name', 'phone', 'notes'],
    'users'         => ['name'],
];

$totalFixed = 0;

foreach ($columns as $table => $cols) {
    foreach ($cols as $col) {
        $check = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'")->fetch();
        if (!$check) continue;

        $rows = $pdo->query("SELECT id, `{$col}` FROM `{$table}` WHERE `{$col}` IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE `{$table}` SET `{$col}` = :val WHERE id = :id");

        foreach ($rows as $row) {
            $decoded = html_entity_decode($row[$col], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded !== $row[$col]) {
                $stmt->execute([':val' => $decoded, ':id' => $row['id']]);
                $totalFixed++;
                echo "Corrigido: {$table}.{$col} id={$row['id']}\n";
            }
        }
    }
}

echo "\nTotal de campos corrigidos: {$totalFixed}\n";
echo "Migration 004 concluída.\n";
