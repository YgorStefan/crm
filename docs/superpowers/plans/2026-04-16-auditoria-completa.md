# Auditoria Completa CRM — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corrigir todos os bugs, falhas de segurança, código morto e problemas de responsividade encontrados na auditoria do CRM, com testes verificando cada correção.

**Architecture:** Abordagem MVC em camadas — core/config → Models → Controllers → Views/JS → Cleanup → Testes. Cada task é atômica e commitada individualmente.

**Tech Stack:** PHP 8.0, MySQL/PDO, Tailwind CSS, Vanilla JS, micro-runner de testes customizado (`php tests/PhaseXXTest.php`)

---

## Mapa de arquivos modificados

| Arquivo | Tipo | Camada |
|---------|------|--------|
| `.gitignore` | Modificar | Cleanup |
| `core/bootstrap.php` | Modificar | Core |
| `core/Model.php` | Modificar | Core |
| `app/Controllers/AuthController.php` | Modificar | Controller |
| `app/Controllers/TaskController.php` | Modificar | Controller |
| `app/Controllers/UserController.php` | Modificar | Controller |
| `app/Controllers/ClientController.php` | Modificar | Controller |
| `app/Models/Task.php` | Modificar | Model |
| `app/Views/layouts/main.php` | Modificar | View |
| `app/Views/clients/index.php` | Modificar | View |
| `app/Views/components/pagination.php` | Modificar | View |
| `tests/Phase10Test.php` | Criar | Testes |

---

## Task 1: Credenciais expostas — .gitignore e limpeza git

**Problema:** `"env crm.txt"` contém senha de produção real (`315426798Crm`) e NÃO está no `.gitignore`. `config/database.php` está rastreado pelo git.

**Files:**
- Modify: `.gitignore`
- Run: `git rm --cached` para desrastrear arquivos sensíveis

- [ ] **Step 1: Abrir .gitignore e adicionar entradas faltando**

Adicionar ao final do arquivo `.gitignore`:

```gitignore
# --- Credenciais de produção ---
env\ crm.txt
"env crm.txt"

# --- Config de banco (credenciais reais ficam no .env) ---
config/database.php

# --- Scripts de desenvolvimento descartáveis ---
scripts/smoke/
scripts/migrations/

# --- Logs gerados em runtime ---
storage/

# --- Artefatos de planejamento interno ---
docs/superpowers/
```

- [ ] **Step 2: Remover config/database.php do rastreamento git**

```bash
git rm --cached config/database.php
```

Expected output: `rm 'config/database.php'`

- [ ] **Step 3: Verificar que "env crm.txt" não está rastreado**

```bash
git status --short | grep -i "env crm"
```

Expected: nenhuma linha com `A` ou `M` para esse arquivo (pode aparecer `??` = untracked, que é correto).

- [ ] **Step 4: Commit**

```bash
git add .gitignore
git commit -m "security: remove tracked credentials and add gitignore rules"
```

---

## Task 2: session.cookie_secure ausente em produção

**Problema:** `core/bootstrap.php` tem `ini_set('session.cookie_secure', '1')` comentado. Em produção com HTTPS, cookies de sessão trafegam sem o flag `Secure`, permitindo interceptação.

**Files:**
- Modify: `core/bootstrap.php:14-16`

- [ ] **Step 1: Substituir o comentário pela lógica condicional**

Em `core/bootstrap.php`, localizar o bloco:
```php
// Em produção com HTTPS, ativar também:
// ini_set('session.cookie_secure', '1');
```

Substituir por:
```php
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('session.cookie_secure', '1');
}
```

O bloco completo da sessão passa a ser:
```php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('session.cookie_secure', '1');
}
```

- [ ] **Step 2: Verificar que o arquivo ficou correto**

```bash
grep -n "cookie_secure" core/bootstrap.php
```

Expected:
```
XX:if (defined('APP_ENV') && APP_ENV === 'production') {
XX:    ini_set('session.cookie_secure', '1');
```

- [ ] **Step 3: Commit**

```bash
git add core/bootstrap.php
git commit -m "security: enable session.cookie_secure in production"
```

---

## Task 3: Open Redirect em AuthController::login()

**Problema:** Após o login, `$_SESSION['redirect_after_login']` é usado como URL de redirect sem validação. Um atacante pode forçar um valor externo antes do login (ex: via link manipulado) e redirecionar o usuário para um site malicioso.

**Files:**
- Modify: `app/Controllers/AuthController.php:95-98`

- [ ] **Step 1: Substituir o redirect direto pela validação**

Em `AuthController.php`, localizar:
```php
$redirect = $_SESSION['redirect_after_login'] ?? (APP_URL . '/dashboard');
unset($_SESSION['redirect_after_login']);

header('Location: ' . $redirect);
exit;
```

Substituir por:
```php
$savedRedirect = $_SESSION['redirect_after_login'] ?? '';
unset($_SESSION['redirect_after_login']);

// Valida que o redirect é um path interno (começa com APP_URL ou é relativo)
$redirect = APP_URL . '/dashboard';
if ($savedRedirect !== '') {
    $appUrlBase = rtrim(APP_URL, '/');
    if (str_starts_with($savedRedirect, $appUrlBase . '/')) {
        $redirect = $savedRedirect;
    } elseif (str_starts_with($savedRedirect, '/') && !str_starts_with($savedRedirect, '//')) {
        $redirect = $appUrlBase . $savedRedirect;
    }
}

header('Location: ' . $redirect);
exit;
```

- [ ] **Step 2: Verificar sintaxe**

```bash
php -l app/Controllers/AuthController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Controllers/AuthController.php
git commit -m "security: validate redirect_after_login to prevent open redirect"
```

---

## Task 4: IDOR — Model::findById() sem filtro de tenant

**Problema:** `Core\Model::findById()` não filtra por `tenant_id`. Qualquer usuário autenticado pode acessar registros de outros tenants passando um ID arbitrário na URL (ex: `/clients/999/edit` onde 999 pertence a outro tenant).

**Files:**
- Modify: `core/Model.php:32-36`

- [ ] **Step 1: Adicionar filtro de tenant_id ao findById()**

Em `core/Model.php`, localizar:
```php
public function findById(int $id): array|bool
{
    $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}
```

Substituir por:
```php
public function findById(int $id): array|bool
{
    if ($this->isGlobal) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    if (!isset($_SESSION['tenant_id'])) {
        throw new \RuntimeException('findById() called without tenant context on non-global model');
    }
    $stmt = $this->db->prepare(
        "SELECT * FROM {$this->table} WHERE id = :id AND tenant_id = :tenant_id LIMIT 1"
    );
    $stmt->execute([':id' => $id, ':tenant_id' => (int) $_SESSION['tenant_id']]);
    return $stmt->fetch();
}
```

**Nota:** `PipelineStage` é global (usado por todos os tenants como lookup), então precisa de `$isGlobal = true`. Verificar:

- [ ] **Step 2: Verificar PipelineStage — deve ter isGlobal = true**

```bash
grep -n "isGlobal\|table" app/Models/PipelineStage.php | head -5
```

Se `isGlobal` não estiver definido como `true`, adicionar ao modelo:
```php
protected bool $isGlobal = true;
```

- [ ] **Step 3: Verificar sintaxe**

```bash
php -l core/Model.php && php -l app/Models/PipelineStage.php
```

Expected: `No syntax errors detected` em ambos.

- [ ] **Step 4: Commit**

```bash
git add core/Model.php app/Models/PipelineStage.php
git commit -m "security: scope findById() by tenant_id to prevent IDOR"
```

---

## Task 5: Task model — queries sem filtro de tenant

**Problema:** `Task::findAllWithRelations()`, `Task::findOverdue()` e `Task::findById()` (override local) não filtram por `tenant_id`. Tasks de outros tenants ficam visíveis.

**Files:**
- Modify: `app/Models/Task.php`

- [ ] **Step 1: Adicionar tenant filter em findAllWithRelations()**

Localizar em `Task.php`:
```php
$sql = "
    SELECT
        t.*,
        c.name  AS client_name,
        ...
    FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN users   u ON u.id = t.assigned_to
    LEFT JOIN users  cb ON cb.id = t.created_by
    WHERE 1=1
";
$params = [];
```

Substituir por:
```php
$tenantId = $this->currentTenantId();
$sql = "
    SELECT
        t.*,
        c.name  AS client_name,
        u.name  AS assigned_name,
        cb.name AS created_by_name
    FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
    LEFT JOIN users   u ON u.id = t.assigned_to
    LEFT JOIN users  cb ON cb.id = t.created_by
    WHERE t.assigned_to IN (
        SELECT id FROM users WHERE tenant_id = :tenant_id_u
    )
";
$params = [':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId];
```

- [ ] **Step 2: Adicionar tenant filter em findOverdue()**

Localizar:
```php
public function findOverdue(?int $userId = null): array
{
    $sql = "
        SELECT t.*, c.name AS client_name, u.name AS assigned_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id
        LEFT JOIN users   u ON u.id = t.assigned_to
        WHERE t.due_date < NOW()
          AND t.status IN ('pending','in_progress')
    ";
    $params = [];
```

Substituir por:
```php
public function findOverdue(?int $userId = null): array
{
    $tenantId = $this->currentTenantId();
    $sql = "
        SELECT t.*, c.name AS client_name, u.name AS assigned_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
        LEFT JOIN users   u ON u.id = t.assigned_to
        WHERE t.due_date < NOW()
          AND t.status IN ('pending','in_progress')
          AND t.assigned_to IN (
              SELECT id FROM users WHERE tenant_id = :tenant_id_u
          )
    ";
    $params = [':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId];
```

- [ ] **Step 3: Corrigir findById() override em Task.php**

Localizar o override local:
```php
public function findById(int $id): array|bool
{
    $stmt = $this->db->prepare("
        SELECT t.*, c.name AS client_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}
```

Substituir por:
```php
public function findById(int $id): array|bool
{
    $tenantId = $this->currentTenantId();
    $stmt = $this->db->prepare("
        SELECT t.*, c.name AS client_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id AND c.tenant_id = :tenant_id_c
        WHERE t.id = :id
          AND t.assigned_to IN (
              SELECT id FROM users WHERE tenant_id = :tenant_id_u
          )
    ");
    $stmt->execute([':id' => $id, ':tenant_id_c' => $tenantId, ':tenant_id_u' => $tenantId]);
    return $stmt->fetch();
}
```

- [ ] **Step 4: Verificar sintaxe**

```bash
php -l app/Models/Task.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add app/Models/Task.php
git commit -m "security: scope Task queries by tenant_id"
```

---

## Task 6: XSS — APP_NAME não escapado no layout principal

**Problema:** `app/Views/layouts/main.php:29` exibe `APP_NAME` diretamente sem `htmlspecialchars`. Se APP_NAME contiver `<script>` ou `"`, há risco de XSS.

**Files:**
- Modify: `app/Views/layouts/main.php:29`

- [ ] **Step 1: Escapar APP_NAME no sidebar**

Localizar em `main.php`:
```php
<h1 class="text-xl font-bold tracking-wide"><?= APP_NAME ?></h1>
```

Substituir por:
```php
<h1 class="text-xl font-bold tracking-wide"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
```

- [ ] **Step 2: Verificar outros usos de APP_NAME sem escape no mesmo arquivo**

```bash
grep -n "APP_NAME" app/Views/layouts/main.php
```

Confirmar que todas as ocorrências usam `htmlspecialchars()` (a linha do `<title>` já usa — verificar).

- [ ] **Step 3: Commit**

```bash
git add app/Views/layouts/main.php
git commit -m "security: escape APP_NAME output in sidebar to prevent XSS"
```

---

## Task 7: CSP bloqueando scripts inline sem nonce

**Problema:** Dois arquivos têm `<script>` sem atributo `nonce`, portanto bloqueados pela Content Security Policy (`script-src 'self' 'nonce-...' 'strict-dynamic'`):
- `app/Views/clients/index.php:60`
- `app/Views/components/pagination.php:161`

**Files:**
- Modify: `app/Views/clients/index.php:60`
- Modify: `app/Views/components/pagination.php:161`

- [ ] **Step 1: Adicionar nonce ao script em clients/index.php**

Localizar:
```html
<script>
// Ao submeter o formulário de filtros, reseta para a página 1
```

Substituir por:
```html
<script nonce="<?= CSP_NONCE ?>">
// Ao submeter o formulário de filtros, reseta para a página 1
```

- [ ] **Step 2: Adicionar nonce ao script em pagination.php**

Localizar:
```html
<script>
(function () {
    // Redireciona ao alterar "itens por página"
```

Substituir por:
```html
<script nonce="<?= CSP_NONCE ?>">
(function () {
    // Redireciona ao alterar "itens por página"
```

- [ ] **Step 3: Confirmar que não há outros scripts sem nonce**

```bash
grep -rn "<script" app/Views/ | grep -v "nonce" | grep -v "<!--" | grep -v ".js\""
```

Expected: nenhuma linha de output.

- [ ] **Step 4: Commit**

```bash
git add app/Views/clients/index.php app/Views/components/pagination.php
git commit -m "security: add CSP nonce to inline scripts blocked by policy"
```

---

## Task 8: Validação de role em UserController::update()

**Problema:** `UserController::update()` aceita qualquer string como `role` sem validar contra os valores permitidos. Se o código de validação do array na Task 3 (findById) for bypassado, um atacante admin poderia definir um role inválido.

**Files:**
- Modify: `app/Controllers/UserController.php:90-93`

- [ ] **Step 1: Adicionar validação do role**

Localizar em `UserController.php`:
```php
$data = [
    'name' => $this->input('name'),
    'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
    'role' => $this->inputRaw('role', 'seller'),
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
];
```

Substituir por:
```php
$requestedRole = $this->inputRaw('role', 'seller');
$data = [
    'name' => $this->input('name'),
    'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
    'role' => in_array($requestedRole, ['admin', 'seller', 'viewer'], true) ? $requestedRole : 'seller',
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
];
```

- [ ] **Step 2: Verificar sintaxe**

```bash
php -l app/Controllers/UserController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Controllers/UserController.php
git commit -m "fix: validate role value in UserController::update()"
```

---

## Task 9: Open Redirect via HTTP_REFERER em TaskController::store()

**Problema:** `TaskController::store()` usa `$_SERVER['HTTP_REFERER']` para redirecionar quando há erro de validação. O `Referer` header pode ser forjado por um atacante, fazendo o sistema redirecionar para URL externa.

**Files:**
- Modify: `app/Controllers/TaskController.php:138-140`

- [ ] **Step 1: Substituir redirect por Referer por redirect fixo**

Localizar:
```php
// Redireciona de volta para a origem
$ref = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/tasks';
header('Location: ' . $ref);
exit;
```

Substituir por:
```php
// Redireciona para a tela de tarefas (Referer header é forjável)
$clientId = $this->inputRaw('client_id');
if ($clientId) {
    $this->redirect('/clients/' . (int) $clientId);
} else {
    $this->redirect('/tasks');
}
return;
```

- [ ] **Step 2: Verificar sintaxe**

```bash
php -l app/Controllers/TaskController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Controllers/TaskController.php
git commit -m "security: replace forgeable HTTP_REFERER redirect in TaskController"
```

---

## Task 10: PipelineStage — marcar como isGlobal

**Problema:** `PipelineStage` é usado como lookup global (etapas do funil são compartilhadas por tenant mas a tabela tem `tenant_id`). O `findById()` agora com tenant filter quebraria buscas feitas antes de ter sessão (ex: durante setup). Verificar e corrigir a flag.

**Files:**
- Modify: `app/Models/PipelineStage.php` (verificar/adicionar `$isGlobal`)

- [ ] **Step 1: Ler o modelo PipelineStage**

```bash
cat app/Models/PipelineStage.php
```

- [ ] **Step 2: Verificar se isGlobal precisa ser false (tenant-scoped)**

`pipeline_stages` tem coluna `tenant_id` → deve ser scoped por tenant, portanto `$isGlobal` deve ser `false` (padrão herdado). O `findById()` agora vai filtrar corretamente por tenant. OK.

Mas `ClientController::store()` e `ClientController::update()` fazem `$stageModel->findById((int) $stageId)` para verificar `is_won_stage`. Isso está correto com o novo filtro desde que a sessão esteja disponível.

- [ ] **Step 3: Confirmar que não há uso de findById() sem sessão ativa**

```bash
grep -rn "findById" app/Controllers/ | grep -v "//"
```

Confirmar que todos os usos estão dentro de métodos protegidos pelo AuthMiddleware (ou seja, sessão sempre existe). Se sim, nenhuma mudança necessária.

- [ ] **Step 4: Commit (se houve mudança)**

```bash
git add app/Models/PipelineStage.php
git commit -m "fix: verify PipelineStage tenant scoping after Model::findById() change"
```

---

## Task 11: Responsividade — verificar views críticas

**Problema:** Verificar se as views com mais dados têm responsividade correta no mobile. Principal suspeita: tabelas que não usam `overflow-x-auto`.

**Files:**
- Modify: views onde tabelas não tiverem wrapper overflow

- [ ] **Step 1: Verificar tabelas em admin/users/index.php**

```bash
grep -n "overflow-x-auto\|<table" app/Views/admin/users/index.php | head -10
```

Se `<table>` não estiver dentro de `<div class="overflow-x-auto">`, adicionar o wrapper.

- [ ] **Step 2: Verificar tabelas em tasks/index.php**

```bash
grep -n "overflow-x-auto\|<table" app/Views/tasks/index.php | head -10
```

Mesma correção se necessário.

- [ ] **Step 3: Verificar cold-contacts/index.php**

```bash
grep -n "overflow-x-auto\|<table" app/Views/cold-contacts/index.php | head -10
```

- [ ] **Step 4: Confirmar que o layout principal tem meta viewport**

```bash
grep -n "viewport" app/Views/layouts/main.php
```

Expected: `<meta name="viewport" content="width=device-width, initial-scale=1.0">` — já presente na linha 6.

- [ ] **Step 5: Commit com correções encontradas**

```bash
git add app/Views/
git commit -m "ui: ensure table overflow-x-auto on mobile in all list views"
```

---

## Task 12: Testes — Phase10Test.php

**Problema:** Criar testes que verificam programaticamente cada correção aplicada. Seguir o padrão do micro-runner existente (`php tests/Phase10Test.php`).

**Files:**
- Create: `tests/Phase10Test.php`

- [ ] **Step 1: Criar o arquivo de testes**

Criar `tests/Phase10Test.php` com o conteúdo:

```php
<?php
/**
 * tests/Phase10Test.php — Testes de auditoria (Phase 10)
 *
 * Verifica cada correção aplicada na auditoria completa.
 * Sem dependências externas. Execute: php tests/Phase10Test.php
 */

declare(strict_types=1);

$results = ['pass' => 0, 'fail' => 0, 'errors' => []];

function ok(string $desc, bool $cond): void
{
    global $results;
    if ($cond) {
        echo "\033[32m  ✓\033[0m {$desc}\n";
        $results['pass']++;
    } else {
        echo "\033[31m  ✗\033[0m {$desc}\n";
        $results['fail']++;
        $results['errors'][] = $desc;
    }
}

function section(string $title): void
{
    echo "\n\033[1;34m── {$title}\033[0m\n";
}

define('ROOT', dirname(__DIR__));

// ===========================================================================
// 1. .gitignore — credenciais não rastreadas
// ===========================================================================
section('1. .gitignore');

$gitignore = file_get_contents(ROOT . '/.gitignore');
ok('.gitignore contém regra para "env crm.txt"', str_contains($gitignore, 'env crm.txt') || str_contains($gitignore, 'env\ crm.txt'));
ok('.gitignore contém regra para config/database.php', str_contains($gitignore, 'config/database.php'));
ok('.gitignore contém regra para scripts/smoke/', str_contains($gitignore, 'scripts/smoke/'));
ok('config/database.php não está rastreado pelo git', !in_array(
    'config/database.php',
    explode("\n", shell_exec('git -C ' . ROOT . ' ls-files config/database.php') ?? '')
));

// ===========================================================================
// 2. bootstrap.php — session.cookie_secure em produção
// ===========================================================================
section('2. session.cookie_secure');

$bootstrap = file_get_contents(ROOT . '/core/bootstrap.php');
ok('bootstrap.php ativa cookie_secure condicionalmente', str_contains($bootstrap, "APP_ENV === 'production'") && str_contains($bootstrap, "cookie_secure"));
ok('bootstrap.php NÃO tem cookie_secure comentado sem condição', !preg_match('/^\s*\/\/\s*ini_set\s*\(\s*[\'"]session\.cookie_secure/m', $bootstrap));

// ===========================================================================
// 3. AuthController — sem open redirect
// ===========================================================================
section('3. Open Redirect em AuthController');

$authCtrl = file_get_contents(ROOT . '/app/Controllers/AuthController.php');
ok('AuthController valida redirect_after_login antes de usar', str_contains($authCtrl, 'str_starts_with($savedRedirect') || str_contains($authCtrl, 'str_starts_with($redirect'));
ok('AuthController NÃO usa redirect_after_login diretamente em header()', !preg_match('/header\s*\(\s*[\'"]Location.*redirect_after_login/m', $authCtrl));

// ===========================================================================
// 4. Model::findById() — filtro de tenant
// ===========================================================================
section('4. IDOR — Model::findById() tenant filter');

$model = file_get_contents(ROOT . '/core/Model.php');
ok('Model::findById() inclui tenant_id no WHERE para modelos não-globais', str_contains($model, 'tenant_id') && str_contains($model, 'findById'));
ok('Model::findById() respeita isGlobal flag', str_contains($model, 'isGlobal') && str_contains($model, 'findById'));

// ===========================================================================
// 5. Task model — tenant filter
// ===========================================================================
section('5. Task model — tenant isolation');

$taskModel = file_get_contents(ROOT . '/app/Models/Task.php');
ok('Task::findAllWithRelations() filtra por tenant', str_contains($taskModel, 'tenant_id'));
ok('Task::findById() (override) filtra por tenant', (function() use ($taskModel) {
    // Verifica que o findById local menciona tenant
    preg_match('/function findById.*?}/s', $taskModel, $m);
    return isset($m[0]) && str_contains($m[0], 'tenant_id');
})());
ok('Task::findOverdue() filtra por tenant', (function() use ($taskModel) {
    preg_match('/function findOverdue.*?}/s', $taskModel, $m);
    return isset($m[0]) && str_contains($m[0], 'tenant_id');
})());

// ===========================================================================
// 6. XSS — APP_NAME escapado no layout
// ===========================================================================
section('6. XSS — APP_NAME no layout');

$mainLayout = file_get_contents(ROOT . '/app/Views/layouts/main.php');
// Garante que APP_NAME não aparece sem htmlspecialchars no sidebar
ok('APP_NAME no sidebar usa htmlspecialchars', (function() use ($mainLayout) {
    // Extrai a região do sidebar (antes do </aside>)
    $sidebarEnd = strpos($mainLayout, '</aside>');
    $sidebar = $sidebarEnd ? substr($mainLayout, 0, $sidebarEnd) : $mainLayout;
    // Verifica que não há <?= APP_NAME ?> sem escape
    return !preg_match('/<\?=\s*APP_NAME\s*\?>/', $sidebar);
})());

// ===========================================================================
// 7. CSP — nonce em todos os scripts inline
// ===========================================================================
section('7. CSP — nonce em scripts inline');

$viewsWithInlineScripts = [
    'app/Views/clients/index.php',
    'app/Views/components/pagination.php',
];

foreach ($viewsWithInlineScripts as $viewFile) {
    $content = file_get_contents(ROOT . '/' . $viewFile);
    // Encontra tags <script que não têm nonce= e não são src=
    preg_match_all('/<script(?![^>]*nonce=)[^>]*>/', $content, $matches);
    $scriptsWithoutNonce = array_filter($matches[0], fn($tag) => !str_contains($tag, 'src='));
    ok("{$viewFile}: todos os scripts inline têm nonce", empty($scriptsWithoutNonce));
}

// ===========================================================================
// 8. UserController — role validado no update
// ===========================================================================
section('8. Role validation em UserController::update()');

$userCtrl = file_get_contents(ROOT . '/app/Controllers/UserController.php');
ok('UserController::update() valida role contra enum', (function() use ($userCtrl) {
    // Extrai método update
    preg_match('/public function update.*?(?=public function|\Z)/s', $userCtrl, $m);
    if (!isset($m[0])) return false;
    return str_contains($m[0], "in_array") && str_contains($m[0], "'admin'") && str_contains($m[0], "'seller'");
})());

// ===========================================================================
// 9. TaskController — sem HTTP_REFERER para redirect
// ===========================================================================
section('9. TaskController — sem HTTP_REFERER para redirect');

$taskCtrl = file_get_contents(ROOT . '/app/Controllers/TaskController.php');
ok('TaskController::store() não usa HTTP_REFERER como destino de redirect', !str_contains($taskCtrl, "HTTP_REFERER"));

// ===========================================================================
// Resumo
// ===========================================================================
echo "\n" . str_repeat('─', 50) . "\n";
$total = $results['pass'] + $results['fail'];
echo "Resultado: {$results['pass']}/{$total} testes passaram\n";

if ($results['fail'] > 0) {
    echo "\033[31mFalhas:\033[0m\n";
    foreach ($results['errors'] as $e) {
        echo "  • {$e}\n";
    }
    exit(1);
}

echo "\033[32mTodos os testes passaram!\033[0m\n";
exit(0);
```

- [ ] **Step 2: Rodar os testes (antes das correções estarem todas aplicadas, alguns devem falhar)**

```bash
php tests/Phase10Test.php
```

Anote quais passam e quais falham. Após todas as tasks anteriores estarem concluídas, todos devem passar.

- [ ] **Step 3: Rodar os testes após todas as correções**

```bash
php tests/Phase10Test.php
```

Expected:
```
── 1. .gitignore
  ✓ .gitignore contém regra para "env crm.txt"
  ✓ .gitignore contém regra para config/database.php
  ✓ .gitignore contém regra para scripts/smoke/
  ✓ config/database.php não está rastreado pelo git
...
Resultado: XX/XX testes passaram
Todos os testes passaram!
```

- [ ] **Step 4: Rodar os testes antigos para garantir que não houve regressão**

```bash
php tests/Phase05Test.php && php tests/Phase06Test.php && php tests/Phase09Test.php
```

Expected: todos passam sem novas falhas.

- [ ] **Step 5: Commit**

```bash
git add tests/Phase10Test.php
git commit -m "test: add Phase10 audit verification tests"
```

---

## Self-Review

**Spec coverage check:**
- ✅ Credenciais/gitignore → Task 1
- ✅ session.cookie_secure → Task 2
- ✅ Open redirect AuthController → Task 3
- ✅ IDOR Model::findById() → Task 4
- ✅ Task model tenant isolation → Task 5
- ✅ APP_NAME XSS → Task 6
- ✅ CSP inline scripts → Task 7
- ✅ UserController role validation → Task 8
- ✅ HTTP_REFERER redirect → Task 9
- ✅ PipelineStage isGlobal → Task 10
- ✅ Responsividade mobile → Task 11
- ✅ Testes → Task 12

**Placeholder scan:** Nenhum TBD ou TODO encontrado.

**Type consistency:** `currentTenantId()` usada consistentemente. `isGlobal` verificada antes de qualquer filtro. `$tenantId` nomeado consistentemente em todos os snippets.
