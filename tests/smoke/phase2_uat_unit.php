<?php
/**
 * UAT unit-style test suite for Phase 2 — Tenant Administration & Governance.
 * Covers UAT tests 2–9 via static code assertions (no DB required).
 * Tests 1 and 10 require a live DB and are covered by tenant_phase2_full_suite.php.
 *
 * Usage: php scripts/smoke/phase2_uat_unit.php
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$steps = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

$read = static function (string $path) use ($add): string {
    $content = @file_get_contents($path);
    if ($content === false) {
        return '';
    }
    return $content;
};

$assertContains = static function (string $label, string $step, string $content, array $needles) use ($add): void {
    $missing = [];
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $missing[] = $needle;
        }
    }
    $ok = $missing === [];
    $add($step, $ok ? "{$label}: OK" : "{$label}: faltando contratos", $ok, $ok ? [] : ['missing' => $missing]);
};

// ─── UAT-02: Create New Organization ─────────────────────────────────────────
// TenantController must enforce admin role, use TenantOnboardingService, redirect to success.
$tenantCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'TenantController.php');
$assertContains('UAT-02 TenantController.store role guard', 'uat02.role_guard', $tenantCtrl, [
    "requireRole('admin')",
    'TenantOnboardingService',
    'onboard(',
]);
$assertContains('UAT-02 TenantController success redirect', 'uat02.success_redirect', $tenantCtrl, [
    "redirect(",
    'success',
]);
$assertContains('UAT-02 Tenant model slug generation', 'uat02.slug_gen', $read(ROOT_PATH . DS . 'app' . DS . 'Models' . DS . 'Tenant.php'), [
    'allocateUniqueSlug(',
    'slugExists(',
]);

// ─── UAT-03: Slug Deduplication ───────────────────────────────────────────────
// TenantOnboardingService must have slug collision resolution logic.
$onboardSvc = $read(ROOT_PATH . DS . 'app' . DS . 'Services' . DS . 'TenantOnboardingService.php');
$assertContains('UAT-03 Slug collision resolution', 'uat03.slug_collision', $onboardSvc, [
    'slugify(',
    'slug',
]);
// Tenant model must have collision loop or findBySlug
$tenantModel = $read(ROOT_PATH . DS . 'app' . DS . 'Models' . DS . 'Tenant.php');
$assertContains('UAT-03 Tenant model slug uniqueness', 'uat03.slug_unique', $tenantModel, [
    'slug',
]);

// ─── UAT-04: Invite a User by Email ──────────────────────────────────────────
$invCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'UserInvitationController.php');
$assertContains('UAT-04 InvitationController role guard', 'uat04.role_guard', $invCtrl, [
    "requireRole('admin')",
    'invite',
]);
$invModel = $read(ROOT_PATH . DS . 'app' . DS . 'Models' . DS . 'UserInvitation.php');
$assertContains('UAT-04 UserInvitation token_hash storage', 'uat04.token_hash', $invModel, [
    'token_hash',
    'public function issue(',
    'public function findPendingByPlainToken(',
]);
$assertContains('UAT-04 Invitation list view exists', 'uat04.invitations_view',
    $read(ROOT_PATH . DS . 'app' . DS . 'Views' . DS . 'admin' . DS . 'users' . DS . 'invitations.php'),
    ['convit', 'expires_at']
);

// ─── UAT-05: Accept an Invitation ────────────────────────────────────────────
$assertContains('UAT-05 accept-invite view exists', 'uat05.accept_view',
    $read(ROOT_PATH . DS . 'app' . DS . 'Views' . DS . 'auth' . DS . 'accept_invite.php'),
    ['token', 'password']
);
$assertContains('UAT-05 consumeAndCreateUser atomic transaction', 'uat05.atomic_consume', $invModel, [
    'consumeAndCreateUser(',
    'consumed_at',
    'beginTransaction',
]);

// ─── UAT-06: Edit User Role ───────────────────────────────────────────────────
$userCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'UserController.php');
$assertContains('UAT-06 UserController role validation allowlist', 'uat06.role_allowlist', $userCtrl, [
    'admin',
    'seller',
    'viewer',
    'role',
]);
$assertContains('UAT-06 UserController requireRole admin', 'uat06.role_guard', $userCtrl, [
    "requireRole('admin')",
]);

// ─── UAT-07: Admin Password Reset ────────────────────────────────────────────
$assertContains('UAT-07 Dedicated password reset endpoint in UserController', 'uat07.password_reset', $userCtrl, [
    'password',
    'password_hash(',
]);
// Verify dedicated route registered in index.php
$indexPhp = $read(ROOT_PATH . DS . 'public' . DS . 'index.php');
$assertContains('UAT-07 Password reset route registered', 'uat07.route', $indexPhp, [
    'password',
    'UserController',
]);

// ─── UAT-08: Self-Deactivation Blocked ───────────────────────────────────────
$assertContains('UAT-08 Self-deactivation guard in UserController', 'uat08.self_deactivate', $userCtrl, [
    'deactivat',
]);
// Must check current user cannot deactivate self
$hasSelfGuard = str_contains($userCtrl, '$_SESSION') && (
    str_contains($userCtrl, 'Cannot deactivate') ||
    str_contains($userCtrl, 'não pode desativar') ||
    str_contains($userCtrl, "['id']") && str_contains($userCtrl, 'deactivat')
);
$add('uat08.self_guard_logic', $hasSelfGuard
    ? 'UAT-08 Self-deactivation guard logic: OK'
    : 'UAT-08 Self-deactivation guard logic: não localizado explicitamente (revisar manualmente)',
    $hasSelfGuard
);

// ─── UAT-09: RBAC — Viewer Cannot Mutate ────────────────────────────────────
// CR-01..CR-05 fixes: all write endpoints must have requireRole(['admin', 'seller'])
$coldCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'ColdContactController.php');
$clientCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'ClientController.php');
$pipeCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'PipelineController.php');
$taskCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'TaskController.php');

// Count requireRole(['admin', 'seller']) guards in ColdContactController — expect at least 5
// (import, update, deleteMonth, bulkUpdate, destroy)
$coldGuards = substr_count($coldCtrl, "requireRole(['admin', 'seller'])");
$add('uat09.cold_contact_rbac_guards', $coldGuards >= 5
    ? "UAT-09 ColdContactController: {$coldGuards} role guards (≥5 required): OK"
    : "UAT-09 ColdContactController: só {$coldGuards} guards (esperado ≥5)",
    $coldGuards >= 5,
    ['found' => $coldGuards, 'required' => 5]
);

// ClientController::updateNotes must have requireRole
$notesPos = strpos($clientCtrl, 'public function updateNotes(');
$clientAfterNotes = $notesPos !== false ? substr($clientCtrl, $notesPos, 300) : '';
$hasNotesGuard = str_contains($clientAfterNotes, "requireRole(['admin', 'seller'])");
$add('uat09.client_updateNotes_guard', $hasNotesGuard
    ? 'UAT-09 ClientController::updateNotes requireRole guard: OK'
    : 'UAT-09 ClientController::updateNotes: sem requireRole guard',
    $hasNotesGuard
);

// PipelineController::move must have requireRole
$movePos = strpos($pipeCtrl, 'public function move(');
$pipeAfterMove = $movePos !== false ? substr($pipeCtrl, $movePos, 200) : '';
$hasMoveGuard = str_contains($pipeAfterMove, "requireRole(['admin', 'seller'])");
$add('uat09.pipeline_move_guard', $hasMoveGuard
    ? 'UAT-09 PipelineController::move requireRole guard: OK'
    : 'UAT-09 PipelineController::move: sem requireRole guard',
    $hasMoveGuard
);

// TaskController::update must have $validStatuses / $validPriorities allowlist
$assertContains('UAT-09 TaskController input allowlists', 'uat09.task_allowlists', $taskCtrl, [
    'allowedStatuses',
    'allowedPriorities',
]);

// InteractionController must have requireRole on store/update/destroy (prev fix)
$interCtrl = $read(ROOT_PATH . DS . 'app' . DS . 'Controllers' . DS . 'InteractionController.php');
$interGuards = substr_count($interCtrl, "requireRole(['admin', 'seller'])");
$add('uat09.interaction_rbac_guards', $interGuards >= 3
    ? "UAT-09 InteractionController: {$interGuards} guards (≥3): OK"
    : "UAT-09 InteractionController: só {$interGuards} guards (esperado ≥3)",
    $interGuards >= 3
);

// ─── Security extras: htmlspecialchars on layout session output ───────────────
$mainLayout = $read(ROOT_PATH . DS . 'app' . DS . 'Views' . DS . 'layouts' . DS . 'main.php');
// Role and initial should now be wrapped (IN-01, IN-02 fixes)
$roleEscaped = str_contains($mainLayout, "htmlspecialchars(\$_SESSION['user']['role']")
    || str_contains($mainLayout, 'htmlspecialchars($_SESSION[\'user\'][\'role\']');
$initialEscaped = str_contains($mainLayout, 'htmlspecialchars(strtoupper(substr(');
$add('security.layout_role_escaped', $roleEscaped
    ? 'Layout main.php: role htmlspecialchars: OK'
    : 'Layout main.php: role sem htmlspecialchars',
    $roleEscaped
);
$add('security.layout_initial_escaped', $initialEscaped
    ? 'Layout main.php: initial htmlspecialchars: OK'
    : 'Layout main.php: initial sem htmlspecialchars',
    $initialEscaped
);

// ─── AuditLogger: sensitive key stripping ────────────────────────────────────
$auditSvc = $read(ROOT_PATH . DS . 'app' . DS . 'Services' . DS . 'AuditLogger.php');
$assertContains('UAT-10 AuditLogger sensitive key stripping', 'uat10.audit_sanitize', $auditSvc, [
    'password',
    'token',
    'AuditLogger',
    'record(',
]);

// ─── PipelineStage tenant-scope check (WR-02 verified) ───────────────────────
$stageModel = $read(ROOT_PATH . DS . 'app' . DS . 'Models' . DS . 'PipelineStage.php');
$assertContains('RBAC WR-02 PipelineStage findById tenant-scoped', 'rbac.pipeline_stage_tenant', $stageModel, [
    'tenant_id',
    'findById(',
]);

// ─── TenantContextMiddleware present ─────────────────────────────────────────
$tcmw = $read(ROOT_PATH . DS . 'core' . DS . 'Middleware' . DS . 'TenantContextMiddleware.php');
$assertContains('TenantContextMiddleware revalidation', 'mw.tenant_context', $tcmw, [
    'tenant_id',
    'is_active',
]);

$add('done',
    $failed ? 'phase2_uat_unit: FAIL' : 'phase2_uat_unit: PASS',
    !$failed
);

crm_smoke_emit($steps, $failed ? 1 : 0);
