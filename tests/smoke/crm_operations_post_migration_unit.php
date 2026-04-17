<?php
/**
 * Unit-style proxy test para Phase 1 / Test 3:
 * garante que operações core seguem tenant scoping no código.
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

/**
 * @return string
 */
$read = static function (string $path) use ($add): string {
    $content = @file_get_contents($path);
    if ($content === false) {
        $add('file.read', 'Nao foi possivel ler arquivo', false, ['path' => $path]);
        return '';
    }
    return $content;
};

$modelChecks = [
    [
        'name' => 'Client model tenant-scoped CRUD/list',
        'path' => ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Client.php',
        'mustContain' => [
            'public function findById(',
            'public function findAllWithRelations(',
            'public function create(',
            'public function update(',
            'public function softDelete(',
            'public function findGroupedByStage(',
            'currentTenantId()',
            'tenant_id',
        ],
    ],
    [
        'name' => 'Task model tenant-scoped CRUD/list',
        'path' => ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Task.php',
        'mustContain' => [
            'public function findAllWithRelations(',
            'public function create(',
            'public function update(',
            'public function findById(',
            'public function delete(',
            'currentTenantId()',
            'tenant_id',
        ],
    ],
    [
        'name' => 'Interaction model tenant-scoped CRUD/list',
        'path' => ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Interaction.php',
        'mustContain' => [
            'public function findById(',
            'public function findByClient(',
            'public function create(',
            'public function update(',
            'public function delete(',
            'currentTenantId()',
            'tenant_id',
        ],
    ],
    [
        'name' => 'PipelineStage model tenant-scoped CRUD/list',
        'path' => ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'PipelineStage.php',
        'mustContain' => [
            'public function findById(',
            'public function findAllOrdered(',
            'public function create(',
            'public function update(',
            'public function delete(',
            'currentTenantId()',
            'tenant_id',
        ],
    ],
];

foreach ($modelChecks as $check) {
    $content = $read($check['path']);
    if ($content === '') {
        continue;
    }

    $missing = [];
    foreach ($check['mustContain'] as $needle) {
        if (!str_contains($content, $needle)) {
            $missing[] = $needle;
        }
    }

    $ok = $missing === [];
    $add(
        'unit.' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($check['name'])),
        $ok ? $check['name'] . ' OK' : $check['name'] . ' com lacunas de escopo',
        $ok,
        $ok ? [] : ['missing' => $missing, 'path' => $check['path']]
    );
}

$add('done', $failed ? 'Unit test crm_operations_post_migration_unit falhou' : 'Unit test crm_operations_post_migration_unit OK', !$failed);
crm_smoke_emit($steps, $failed ? 1 : 0);
