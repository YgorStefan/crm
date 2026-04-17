<?php
/**
 * Smoke TEN-03 — governança: mesmo e-mail em tenants distintos (OK) e convite com token_hash único (D-03/D-04).
 * Rotas alinhadas: /admin/users/invite*, /accept-invite (POST com CSRF), governança com Auth + TenantContext.
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'tenant_phase2_fixture.php';

$steps = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

$tenantA = 0;
$tenantB = 0;
$userAdminA = 0;

try {
    $pdo = crm_smoke_pdo();
    tenant_phase2_assert_wave0_schema($pdo);
} catch (\Throwable $e) {
    $add('ten03.prelude', $e->getMessage(), false);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
}

$suffix = tenant_phase2_random_suffix();
$sharedEmail = 'shared+' . $suffix . '@governance.test';

try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)')->execute(['Gov A ' . $suffix, 'gov-a-' . $suffix]);
    $tenantA = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)'
    )->execute([$tenantA, 'Admin A', 'admin-a+' . $suffix . '@test.local', TENANT_PHASE2_PLACEHOLDER_HASH, 'admin']);
    $userAdminA = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $add('ten03.seed.a', 'Falha ao criar tenant A: ' . $e->getMessage(), false);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)')->execute(['Gov B ' . $suffix, 'gov-b-' . $suffix]);
    $tenantB = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)'
    )->execute([$tenantB, 'Admin B', 'admin-b+' . $suffix . '@test.local', TENANT_PHASE2_PLACEHOLDER_HASH, 'admin']);
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tenant_phase2_teardown_tenant($pdo, $tenantA);
    $add('ten03.seed.b', 'Falha ao criar tenant B: ' . $e->getMessage(), false);
    fwrite(STDOUT, "FAIL\n");
    crm_smoke_emit($steps, 1);
}

try {
    $pdo->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)'
    )->execute([$tenantA, 'User Shared A', $sharedEmail, TENANT_PHASE2_PLACEHOLDER_HASH, 'seller']);
    $pdo->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)'
    )->execute([$tenantB, 'User Shared B', $sharedEmail, TENANT_PHASE2_PLACEHOLDER_HASH, 'seller']);
    $add('ten03.email.cross_tenant', 'Mesmo e-mail em dois tenant_id distintos aceito (unicidade composta)', true);
} catch (\Throwable $e) {
    $add('ten03.email.cross_tenant', 'Esperava sucesso cross-tenant: ' . $e->getMessage(), false);
}

$dupFailed = false;
try {
    $pdo->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)'
    )->execute([$tenantA, 'Duplicata', $sharedEmail, TENANT_PHASE2_PLACEHOLDER_HASH, 'viewer']);
} catch (\Throwable $e) {
    $dupFailed = true;
}
$add(
    'ten03.email.same_tenant',
    $dupFailed ? 'Segundo usuário mesmo tenant+e-mail rejeitado pelo banco' : 'Esperava violação de unicidade tenant+e-mail',
    $dupFailed
);

$tokenHash = hash('sha256', 'invite-secret-' . $suffix);
$expiresAt = (new \DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');

try {
    $pdo->prepare(
        'INSERT INTO tenant_user_invitations
        (tenant_id, invited_email, role, token_hash, expires_at, consumed_at, invited_by)
        VALUES (?,?,?,?,?,?,?)'
    )->execute([$tenantA, 'invitee+' . $suffix . '@test.local', 'seller', $tokenHash, $expiresAt, null, $userAdminA]);
    $add('ten03.invite.insert', 'Convite persistido com token_hash (sem token bruto)', true);
} catch (\Throwable $e) {
    $add('ten03.invite.insert', 'Falha ao inserir convite: ' . $e->getMessage(), false);
}

$dupTokenRejected = false;
try {
    $pdo->prepare(
        'INSERT INTO tenant_user_invitations
        (tenant_id, invited_email, role, token_hash, expires_at, consumed_at, invited_by)
        VALUES (?,?,?,?,?,?,?)'
    )->execute([$tenantA, 'outro+' . $suffix . '@test.local', 'viewer', $tokenHash, $expiresAt, null, $userAdminA]);
} catch (\Throwable $e) {
    $dupTokenRejected = true;
}
$add(
    'ten03.invite.token_unique',
    $dupTokenRejected ? 'Segundo convite com mesmo token_hash rejeitado' : 'Esperava UNIQUE em token_hash',
    $dupTokenRejected
);

$pdo->prepare(
    'UPDATE tenant_user_invitations SET consumed_at = NOW() WHERE token_hash = ?'
)->execute([$tokenHash]);
$row = $pdo->prepare('SELECT consumed_at IS NOT NULL AS used FROM tenant_user_invitations WHERE token_hash = ?');
$row->execute([$tokenHash]);
$consumed = (bool) $row->fetchColumn();
$add(
    'ten03.invite.consume',
    $consumed ? 'Marca de consumo único aplicável (uso único)' : 'Convite não marcado como consumido',
    $consumed
);

if (!function_exists('crm_smoke_register_psr4_autoload')) {
    function crm_smoke_register_psr4_autoload(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        spl_autoload_register(function (string $className): void {
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
            $namespaceMap = [
                'Core' . DIRECTORY_SEPARATOR => CORE_PATH . DIRECTORY_SEPARATOR,
                'App' . DIRECTORY_SEPARATOR => APP_PATH . DIRECTORY_SEPARATOR,
            ];
            foreach ($namespaceMap as $prefix => $baseDir) {
                if (str_starts_with($relativePath, $prefix)) {
                    $file = $baseDir . substr($relativePath, strlen($prefix));
                    if (file_exists($file)) {
                        require_once $file;
                        return;
                    }
                }
            }
        });
    }
}

try {
    crm_smoke_register_psr4_autoload();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = ['tenant_id' => $tenantA, 'user' => ['id' => $userAdminA, 'role' => 'admin']];
    $invModel = new \App\Models\UserInvitation();
    $issued = $invModel->issue('modelflow+' . $suffix . '@test.local', 'seller', $userAdminA);
    $plain = $issued['token'];
    $chk = $pdo->prepare('SELECT token_hash FROM tenant_user_invitations WHERE id = ?');
    $chk->execute([(int) $issued['id']]);
    $storedHash = (string) $chk->fetchColumn();
    $rawBytes = hex2bin($plain);
    $expected = is_string($rawBytes) && strlen($rawBytes) === 32 ? hash('sha256', $rawBytes) : '';
    $hashOk = $storedHash !== '' && $expected !== '' && hash_equals($storedHash, $expected) && !hash_equals($storedHash, $plain);
    $add(
        'ten03.model.token_is_hash',
        $hashOk ? 'token_hash persiste SHA-256 do segredo; token em claro não armazenado' : 'Hash do token inconsistente com o emitido pelo modelo',
        $hashOk
    );

    $r1 = $invModel->consumeAndCreateUser($plain, 'Modelflow User', 'password12345');
    $add(
        'ten03.model.consume_creates_user',
        ($r1['ok'] ?? false) ? 'Consumo cria usuário no tenant do convite' : ('Falha: ' . ($r1['message'] ?? '')),
        (bool) ($r1['ok'] ?? false)
    );

    $r2 = $invModel->consumeAndCreateUser($plain, 'X', 'password12345');
    $add(
        'ten03.model.consume_single_use',
        !($r2['ok'] ?? false) ? 'Segunda consumição do mesmo token rejeitada' : 'Esperava falha no reuso do token',
        !($r2['ok'] ?? false)
    );

    $rawExp = random_bytes(32);
    $plainExp = bin2hex($rawExp);
    $hashExp = hash('sha256', $rawExp);
    $pdo->prepare(
        'INSERT INTO tenant_user_invitations
        (tenant_id, invited_email, role, token_hash, expires_at, consumed_at, invited_by)
        VALUES (?,?,?,?,?,?,?)'
    )->execute([$tenantA, 'expired+' . $suffix . '@test.local', 'viewer', $hashExp, '2000-01-01 00:00:00', null, $userAdminA]);
    $r3 = $invModel->consumeAndCreateUser($plainExp, 'Expired User', 'password12345');
    $add(
        'ten03.model.expired_rejected',
        !($r3['ok'] ?? false) ? 'Convite expirado não aceito' : 'Esperava rejeição de convite expirado',
        !($r3['ok'] ?? false)
    );
} catch (\Throwable $e) {
    $add('ten03.model.flow', 'Exceção no fluxo UserInvitation: ' . $e->getMessage(), false);
}
$_SESSION = [];

// Cross-tenant: UPDATE users não deve afetar linha de outro tenant (D-06 / T-2-11)
$stCross = $pdo->prepare('UPDATE users SET name = ? WHERE id = ? AND tenant_id = ?');
$stCross->execute(['CrossTenantProbe', $userAdminA, $tenantB]);
$crossRows = $stCross->rowCount();
$nm = $pdo->prepare('SELECT name FROM users WHERE id = ?');
$nm->execute([$userAdminA]);
$nameAfter = (string) $nm->fetchColumn();
$crossTenantBlocked = $crossRows === 0 && $nameAfter !== 'CrossTenantProbe';
$add(
    'ten03.cross_tenant.update_blocked',
    $crossTenantBlocked ? 'UPDATE com tenant_id errado não altera usuário' : 'Esperava 0 linhas afetadas e nome preservado',
    $crossTenantBlocked
);

$ucPathGov = ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'UserController.php';
$ucGov = is_file($ucPathGov) ? (string) file_get_contents($ucPathGov) : '';
$add(
    'ten03.controller.lifecycle',
    (str_contains($ucGov, 'function resetPassword') && str_contains($ucGov, 'não pode desativar a própria'))
        ? 'UserController: reset dedicado de senha e bloqueio de auto-desativação (D-04)'
        : 'UserController sem controles esperados de ciclo de vida',
    str_contains($ucGov, 'function resetPassword') && str_contains($ucGov, 'não pode desativar a própria')
);

tenant_phase2_teardown_tenant($pdo, $tenantB);
tenant_phase2_teardown_tenant($pdo, $tenantA);

$add('done', $failed ? 'Smoke TEN-03 falhou' : 'Smoke TEN-03 OK', !$failed);
fwrite(STDOUT, ($failed ? 'FAIL' : 'PASS') . "\n");
crm_smoke_emit($steps, $failed ? 1 : 0);
