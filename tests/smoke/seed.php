<?php

/**
 * Seed E2E — roda antes da suíte Playwright (via global-setup.ts).
 *
 * Garante, de forma idempotente (pode rodar N vezes contra a mesma base
 * sem duplicar nada), os dados fixos que os specs esperam:
 *
 *   - admin@crm.local        → senha padrão do seed (Admin@1234) e
 *                              password_must_change = 1, usado APENAS pelo
 *                              spec de troca de senha obrigatória (o spec
 *                              troca a senha; este script a reseta a cada run).
 *   - e2e.seller@crm.local   → senha fixa, password_must_change = 0, usado
 *                              pelos demais specs (login direto, sem redirect).
 *   - Um cliente fixo em "Prospecção" (1ª etapa do funil) para o spec de
 *     drag-and-drop do Kanban, sempre resetado para a etapa inicial.
 *
 * Assume que o schema (database/schema.sql) já foi importado e que as
 * etapas padrão do funil (pipeline_stages) já existem para o tenant 1.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

const E2E_SELLER_EMAIL = 'e2e.seller@crm.local';
const E2E_SELLER_PASSWORD = 'E2eSeller@1234';
const E2E_CLIENT_EMAIL = 'e2e.pipeline.card@crm.local';

try {
    $pdo = Core\Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "[seed] ERRO conexão DB: {$e->getMessage()}\n");
    exit(1);
}

// 1) admin@crm.local sempre com a senha e o flag padrão do seed original,
//    para o spec de troca de senha obrigatória poder rodar de forma repetível.
// (PDO::ATTR_EMULATE_PREPARES está desligado — placeholders nomeados não
// podem se repetir na mesma query, por isso usamos um nome por ocorrência.)
//
// IMPORTANTE: nunca fixar `id` no INSERT — colide com a PRIMARY KEY de
// qualquer usuário pré-existente com esse id (ex.: id=1 pode já pertencer a
// outra conta real) e o ON DUPLICATE KEY UPDATE acaba sobrescrevendo a conta
// errada. O casamento correto de idempotência é pelo `email` (uq_users_email).
$adminHash = password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare('
    INSERT INTO users (name, email, password_hash, role, tenant_id, password_must_change, is_active)
    VALUES (\'Administrador E2E\', \'admin@crm.local\', :hash_insert, \'admin\', 1, 1, 1)
    ON DUPLICATE KEY UPDATE password_hash = :hash_update, password_must_change = 1, is_active = 1
');
$stmt->execute(['hash_insert' => $adminHash, 'hash_update' => $adminHash]);

// 2) Usuário fixo sem obrigatoriedade de troca de senha, para os specs gerais.
$sellerHash = password_hash(E2E_SELLER_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare('
    INSERT INTO users (name, email, password_hash, role, tenant_id, password_must_change, is_active)
    VALUES (\'E2E Seller\', :email, :hash_insert, \'admin\', 1, 0, 1)
    ON DUPLICATE KEY UPDATE password_hash = :hash_update, password_must_change = 0, is_active = 1
');
$stmt->execute(['email' => E2E_SELLER_EMAIL, 'hash_insert' => $sellerHash, 'hash_update' => $sellerHash]);

// 3) Etapa inicial do funil (menor `position` do tenant 1) para posicionar
//    o cliente de teste do drag-and-drop.
$firstStageId = (int) $pdo->query('
    SELECT id FROM pipeline_stages WHERE tenant_id = 1 ORDER BY position ASC LIMIT 1
')->fetchColumn();

if ($firstStageId === 0) {
    fwrite(STDERR, "[seed] ERRO: nenhuma pipeline_stage encontrada para tenant_id=1\n");
    exit(1);
}

$stmt = $pdo->prepare('
    INSERT INTO clients (name, email, pipeline_stage_id, deal_value, tenant_id, is_active)
    VALUES (\'Cliente E2E Kanban\', :email, :stage_id_insert, 1000, 1, 1)
    ON DUPLICATE KEY UPDATE pipeline_stage_id = :stage_id_update, is_active = 1
');
$stmt->execute([
    'email' => E2E_CLIENT_EMAIL,
    'stage_id_insert' => $firstStageId,
    'stage_id_update' => $firstStageId,
]);

echo "[seed] OK — admin@crm.local, e2e.seller@crm.local e cliente de kanban prontos.\n";
