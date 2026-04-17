<?php
/**
 * Unit-style test (sem PHPUnit) para contrato de tenant em Core\Model.
 * Valida currentTenantId() com e sem tenant_id em sessão.
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once ROOT_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Model.php';

final class TenantModelProbe extends \Core\Model
{
    public function __construct()
    {
        // Evita conexão de banco no teste unitário; só exercita currentTenantId().
    }

    public function resolveTenantId(): int
    {
        return $this->currentTenantId();
    }
}

$steps = [];
$failed = false;

$add = static function (string $step, string $message, bool $ok, array $data = []) use (&$steps, &$failed): void {
    $steps[] = crm_smoke_step($step, $message, $ok, $data);
    if (!$ok) {
        $failed = true;
    }
};

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
$probe = new TenantModelProbe();

try {
    $_SESSION['tenant_id'] = 17;
    $resolved = $probe->resolveTenantId();
    $add(
        'unit.currentTenantId.with_session_tenant',
        $resolved === 17 ? 'Retorna tenant_id da sessão' : 'Retorno inesperado para tenant_id da sessão',
        $resolved === 17,
        ['expected' => 17, 'actual' => $resolved]
    );
} catch (\Throwable $e) {
    $add(
        'unit.currentTenantId.with_session_tenant',
        'Exceção inesperada com tenant_id presente',
        false,
        ['error' => $e->getMessage()]
    );
}

$_SESSION = [];
try {
    $probe->resolveTenantId();
    $add(
        'unit.currentTenantId.without_session_tenant',
        'Esperava RuntimeException sem tenant_id',
        false
    );
} catch (\RuntimeException $e) {
    $add(
        'unit.currentTenantId.without_session_tenant',
        'Lança RuntimeException sem tenant_id',
        true,
        ['error' => $e->getMessage()]
    );
} catch (\Throwable $e) {
    $add(
        'unit.currentTenantId.without_session_tenant',
        'Tipo de exceção inesperado sem tenant_id',
        false,
        ['error' => $e->getMessage(), 'type' => get_class($e)]
    );
}

$add('done', $failed ? 'Unit test tenant_model_unit falhou' : 'Unit test tenant_model_unit OK', !$failed);
crm_smoke_emit($steps, $failed ? 1 : 0);
