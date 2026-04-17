<?php
/**
 * Smoke TEN-02 — onboarding transacional (D-01/D-02): serviço atômico, slug único e rollback.
 * Rotas HTTP oficiais: GET/POST /admin/tenants/create|store|success (Auth + TenantContext + CSRF em POST).
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'tenant_phase2_fixture.php';

use App\Services\TenantOnboardingService;

$steps = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

try {
    $pdo = crm_smoke_pdo();
    tenant_phase2_assert_wave0_schema($pdo);
} catch (\Throwable $e) {
    $add('ten02.prelude', $e->getMessage(), false, ['type' => get_class($e)]);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
}

$suffix = tenant_phase2_random_suffix();
$emailOk = 'onb+' . $suffix . '@test.local';
$tenantId = 0;

try {
    $svc = new TenantOnboardingService($pdo);
    $result = $svc->onboard([
        'tenant_name' => 'Org Smoke ' . $suffix,
        'admin_name' => 'Admin Smoke',
        'admin_email' => $emailOk,
        'password_hash' => TENANT_PHASE2_PLACEHOLDER_HASH,
    ]);
    $tenantId = (int) ($result['tenant_id'] ?? 0);
    $slug = (string) ($result['slug'] ?? '');
    $add('ten02.service.commit', 'TenantOnboardingService concluiu transação', $tenantId > 0 && $slug !== '', [
        'tenant_id' => $tenantId,
        'slug' => $slug,
    ]);
} catch (\Throwable $e) {
    $add('ten02.service.commit', 'Falha no onboarding: ' . $e->getMessage(), false);
}

if ($tenantId > 0) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ? AND role = ?');
    $q->execute([$tenantId, $emailOk, 'admin']);
    $userOk = (int) $q->fetchColumn() === 1;
    $add('ten02.assert.user', $userOk ? 'Usuário admin presente no tenant' : 'Usuário admin ausente após commit', $userOk);

    $q2 = $pdo->prepare('SELECT COUNT(*) FROM pipeline_stages WHERE tenant_id = ?');
    $q2->execute([$tenantId]);
    $stageCount = (int) $q2->fetchColumn();
    $stageOk = $stageCount === 6;
    $add('ten02.assert.stages', $stageOk ? 'Seis etapas padrão do funil criadas' : 'Etapas esperadas: 6, obtido: ' . $stageCount, $stageOk);

    tenant_phase2_teardown_tenant($pdo, $tenantId);
    $tenantId = 0;
}

// Slug com colisão: reserva slug fixo e exige sufixo incremental
$collisionBase = 'coll-' . $suffix;
try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)')->execute(['Holder', $collisionBase]);
    $holderId = (int) $pdo->lastInsertId();
    $pdo->commit();

    $svc = new TenantOnboardingService($pdo);
    $res = $svc->onboard([
        'tenant_name' => $collisionBase,
        'admin_name' => 'Admin Coll',
        'admin_email' => 'coll+' . $suffix . '@test.local',
        'password_hash' => TENANT_PHASE2_PLACEHOLDER_HASH,
    ]);
    $expected = $collisionBase . '-2';
    $got = (string) ($res['slug'] ?? '');
    $collOk = $got === $expected;
    $add('ten02.slug.suffix', $collOk ? 'Slug único com sufixo após colisão' : "Esperado {$expected}, obtido {$got}", $collOk);
    tenant_phase2_teardown_tenant($pdo, (int) $res['tenant_id']);
    $pdo->prepare('DELETE FROM tenants WHERE id = ?')->execute([$holderId]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $add('ten02.slug.suffix', 'Falha no teste de colisão: ' . $e->getMessage(), false);
}

$add('done', $failed ? 'Smoke TEN-02 falhou' : 'Smoke TEN-02 OK', !$failed);
fwrite(STDOUT, ($failed ? 'FAIL' : 'PASS') . "\n");
crm_smoke_emit($steps, $failed ? 1 : 0);
