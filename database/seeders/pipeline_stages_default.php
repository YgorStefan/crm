<?php
/**
 * Seed dos 6 estágios padrão de pipeline para um tenant específico.
 * Chamado pelo TenantController::store() ao criar novo tenant.
 *
 * Usage: seedDefaultPipelineStages($pdo, $tenantId)
 */

declare(strict_types=1);

function seedDefaultPipelineStages(PDO $pdo, int $tenantId): void
{
    $stages = [
        ['Prospecção',       '#6366f1', 1],
        ['Qualificação',     '#f59e0b', 2],
        ['Proposta',         '#3b82f6', 3],
        ['Negociação',       '#8b5cf6', 4],
        ['Fechado - Ganho',  '#10b981', 5],
        ['Fechado - Perdido','#ef4444', 6],
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO pipeline_stages (name, color, position, tenant_id) VALUES (:name, :color, :position, :tenant_id)"
    );

    foreach ($stages as [$name, $color, $position]) {
        $stmt->execute([
            ':name'      => $name,
            ':color'     => $color,
            ':position'  => $position,
            ':tenant_id' => $tenantId,
        ]);
    }
}
