# Fase 2 — Segurança

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fechar 10 vetores de ataque exploráveis e aplicar hardening de autenticação.

**Architecture:** Cada task é independente. Começar pelas que afetam o fluxo crítico (login, roles) antes das de CSP e headers. Testes estruturais com runner PHP puro.

**Tech Stack:** PHP 8.0+, MySQL/PDO, Apache .htaccess.

---

## Mapa de Arquivos

| Arquivo | Ação | Responsabilidade |
|---------|------|-----------------|
| `core/Middleware/RateLimitMiddleware.php` | Criar | Limitar tentativas de login por IP |
| `core/Middleware/AuthMiddleware.php` | Modificar | Verificar password_must_change |
| `core/Middleware/CspMiddleware.php` | Modificar | Remover unsafe-inline e jsdelivr do connect-src |
| `core/Controller.php` | Modificar | requireRole() retorna 403 para JSON; redirect() valida path |
| `core/bootstrap.php` | Modificar | session.gc_maxlifetime + Logger .htaccess proativo |
| `app/Controllers/AuthController.php` | Modificar | filter_var FILTER_VALIDATE_EMAIL |
| `app/Controllers/UserController.php` | Modificar | filter_var FILTER_VALIDATE_EMAIL |
| `app/Controllers/TaskController.php` | Modificar | Role auth em update/destroy |
| `app/Views/layouts/main.php` | Modificar | Remover style="" inline |
| `config/routes.php` | Modificar | RateLimitMiddleware no POST /login |
| `database/migrations/006_add_login_attempts_table.php` | Criar | Tabela login_attempts |
| `database/migrations/007_add_users_flags.php` | Criar | Colunas password_must_change e is_system_admin |
| `app/.htaccess` | Criar | Deny all |
| `core/.htaccess` | Criar | Deny all |
| `config/.htaccess` | Criar | Deny all |
| `database/.htaccess` | Criar | Deny all |
| `tests/.htaccess` | Criar | Deny all |
| `tests/Phase12Test.php` | Criar | Testes estruturais da Fase 2 |

---

### Task 1: Migrations — tabela login_attempts e flags de usuário

**Files:**
- Create: `database/migrations/006_add_login_attempts_table.php`
- Create: `database/migrations/007_add_users_flags.php`

- [ ] **Step 1: Criar migration 006**

Criar `database/migrations/006_add_login_attempts_table.php`:

```php
<?php
/**
 * Migration 006 — Criar tabela login_attempts para rate limiting.
 * Execute: php database/migrations/006_add_login_attempts_table.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip           VARCHAR(45)  NOT NULL,
        attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_login_attempts_ip_time (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Tabela login_attempts criada.\n";
```

- [ ] **Step 2: Criar migration 007**

Criar `database/migrations/007_add_users_flags.php`:

```php
<?php
/**
 * Migration 007 — Adicionar colunas password_must_change e is_system_admin em users.
 * Execute: php database/migrations/007_add_users_flags.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// Adicionar password_must_change se não existir
$col = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_must_change'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE users ADD COLUMN password_must_change TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = forçar troca na sessão seguinte'");
    echo "Coluna password_must_change adicionada.\n";
} else {
    echo "Coluna password_must_change já existe.\n";
}

// Adicionar is_system_admin se não existir
$col2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_system_admin'")->fetch();
if (!$col2) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_system_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = acesso a /admin/tenants'");
    echo "Coluna is_system_admin adicionada.\n";
} else {
    echo "Coluna is_system_admin já existe.\n";
}

// Marcar o admin seed (id=1) com password_must_change = 1
$pdo->exec("UPDATE users SET password_must_change = 1 WHERE id = 1 AND role = 'admin'");
echo "Admin seed marcado para troca de senha.\n";
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/006_add_login_attempts_table.php database/migrations/007_add_users_flags.php
git commit -m "feat: migrations 006-007 — login_attempts e flags de usuário"
```

---

### Task 2: RateLimitMiddleware

**Files:**
- Create: `core/Middleware/RateLimitMiddleware.php`
- Modify: `config/routes.php`

- [ ] **Step 1: Criar testes**

Criar `tests/Phase12Test.php`:

```php
<?php
/**
 * tests/Phase12Test.php — Fase 2: Segurança
 * Execute: php tests/Phase12Test.php
 */
declare(strict_types=1);

$results = ['pass' => 0, 'fail' => 0, 'errors' => []];
function ok(string $desc, bool $cond): void {
    global $results;
    if ($cond) { echo "\033[32m  ✓\033[0m {$desc}\n"; $results['pass']++; }
    else       { echo "\033[31m  ✗\033[0m {$desc}\n"; $results['fail']++; $results['errors'][] = $desc; }
}
function section(string $title): void { echo "\n\033[1;34m── {$title}\033[0m\n"; }

define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . DS . 'app');
define('CORE_PATH', ROOT_PATH . DS . 'core');
define('APP_URL',   'http://localhost');
define('APP_ENV',   'testing');
define('SESSION_LIFETIME', 7200);

// ── 1. RateLimitMiddleware existe ────────────────────────────────────────────
section('1. RateLimitMiddleware');
ok('arquivo existe', file_exists(ROOT_PATH . '/core/Middleware/RateLimitMiddleware.php'));
$src = file_get_contents(ROOT_PATH . '/core/Middleware/RateLimitMiddleware.php');
ok('classe RateLimitMiddleware definida', strpos($src, 'class RateLimitMiddleware') !== false);
ok('método handle() existe',              strpos($src, 'public function handle()') !== false);
ok('consulta login_attempts',             strpos($src, 'login_attempts') !== false);
ok('usa threshold de 5 tentativas',       strpos($src, '5') !== false);

// ── 2. Rotas — RateLimitMiddleware no POST /login ────────────────────────────
section('2. Rotas — RateLimitMiddleware no POST /login');
$routes = file_get_contents(ROOT_PATH . '/config/routes.php');
ok('POST /login tem RateLimitMiddleware', strpos($routes, "post('/login'") !== false
    && preg_match("/post\('\/login'[^;]*RateLimitMiddleware/s", $routes) === 1);
```

- [ ] **Step 2: Rodar e confirmar falhas**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 3: Criar RateLimitMiddleware**

Criar `core/Middleware/RateLimitMiddleware.php`:

```php
<?php

namespace Core\Middleware;

use Core\Database;
use Core\Logger;

class RateLimitMiddleware
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 60;

    public function handle(): void
    {
        $ip = $this->clientIp();
        $pdo = Database::getInstance();

        // Limpar registros antigos (janela de 10 minutos)
        $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")
            ->execute();

        // Contar tentativas recentes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip = :ip
              AND attempted_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
        ");
        $stmt->execute([':ip' => $ip, ':seconds' => self::WINDOW_SECONDS]);
        $count = (int) $stmt->fetchColumn();

        // Registrar esta tentativa
        $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)")
            ->execute([':ip' => $ip]);

        if ($count >= self::MAX_ATTEMPTS) {
            (new Logger())->warning("Rate limit atingido para IP {$ip}");
            $_SESSION['flash'] = [
                'type'    => 'error',
                'message' => 'Muitas tentativas. Aguarde 1 minuto antes de tentar novamente.',
            ];
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }

    private function clientIp(): string
    {
        // Verificar X-Forwarded-For apenas se confiável (detrás de proxy conhecido)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
```

- [ ] **Step 4: Adicionar RateLimitMiddleware na rota POST /login**

Abrir `config/routes.php`. Localizar:
```php
$router->post('/login', 'AuthController', 'login', ['CsrfMiddleware', 'CspMiddleware']);
```

Substituir por:
```php
$router->post('/login', 'AuthController', 'login', ['RateLimitMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
```

- [ ] **Step 5: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 6: Commit**

```bash
git add core/Middleware/RateLimitMiddleware.php config/routes.php tests/Phase12Test.php
git commit -m "feat: RateLimitMiddleware — máximo 5 tentativas de login por IP/60s"
```

---

### Task 3: AuthMiddleware — password_must_change redirect

**Files:**
- Modify: `core/Middleware/AuthMiddleware.php`

- [ ] **Step 1: Adicionar testes**

Adicionar ao `tests/Phase12Test.php` (antes do bloco de resultado final):

```php
// ── 3. AuthMiddleware — password_must_change ─────────────────────────────────
section('3. AuthMiddleware — password_must_change');
$src = file_get_contents(ROOT_PATH . '/core/Middleware/AuthMiddleware.php');
ok('verifica password_must_change',       strpos($src, 'password_must_change') !== false);
ok('redireciona para /profile/change-password', strpos($src, '/profile/change-password') !== false);
```

- [ ] **Step 2: Implementar**

Abrir `core/Middleware/AuthMiddleware.php`. Após o bloco de timeout (linha ~50), antes do fechamento do método `handle()`, adicionar:

```php
        // Forçar troca de senha se flag ativa
        // Exceção: o próprio /profile/change-password e /logout não são bloqueados
        if (!empty($_SESSION['user']['password_must_change'])) {
            $uri = strtok($_SERVER['REQUEST_URI'], '?');
            $basePath = parse_url(APP_URL, PHP_URL_PATH) ?? '';
            $path = ($basePath !== '' && str_starts_with($uri, $basePath))
                ? substr($uri, strlen($basePath))
                : $uri;
            $allowed = ['/profile/change-password', '/logout'];
            if (!in_array('/' . ltrim($path, '/'), $allowed, true)) {
                header('Location: ' . APP_URL . '/profile/change-password');
                exit;
            }
        }
```

- [ ] **Step 3: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 4: Commit**

```bash
git add core/Middleware/AuthMiddleware.php tests/Phase12Test.php
git commit -m "feat: AuthMiddleware — redireciona para troca de senha se password_must_change=1"
```

---

### Task 4: TaskController — autorização por role em update/destroy

**Files:**
- Modify: `app/Controllers/TaskController.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 4. TaskController — role auth ────────────────────────────────────────────
section('4. TaskController — role auth em update/destroy');
$src = file_get_contents(ROOT_PATH . '/app/Controllers/TaskController.php');
ok('update() verifica role viewer',   preg_match("/update[^}]*viewer/s", $src) === 1);
ok('update() verifica role seller',   preg_match("/update[^}]*seller/s", $src) === 1);
ok('destroy() verifica role viewer',  preg_match("/destroy[^}]*viewer/s", $src) === 1);
ok('destroy() verifica role seller',  preg_match("/destroy[^}]*seller/s", $src) === 1);
```

- [ ] **Step 2: Implementar role auth**

Abrir `app/Controllers/TaskController.php`. Localizar `update()`. Após a busca da tarefa (`$task = $taskModel->findById($id)`), inserir:

```php
        // Autorização por role
        $role   = $_SESSION['user']['role'] ?? '';
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($role === 'viewer') {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
            } else {
                $this->flash('error', 'Acesso negado: leitores não podem editar tarefas.');
                $this->redirect('/tasks');
            }
            return;
        }

        if ($role === 'seller'
            && (int) $task['assigned_to'] !== $userId
            && (int) $task['created_by']  !== $userId
        ) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
            } else {
                $this->flash('error', 'Acesso negado: você só pode editar suas próprias tarefas.');
                $this->redirect('/tasks');
            }
            return;
        }
```

Repetir o mesmo bloco no início de `destroy()` (após buscar `$task`).

- [ ] **Step 3: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Controllers/TaskController.php tests/Phase12Test.php
git commit -m "fix: TaskController — viewer não pode editar/deletar; seller só as suas"
```

---

### Task 5: Controller::requireRole() retorna 403 para JSON

**Files:**
- Modify: `core/Controller.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 5. Controller::requireRole() — 403 para JSON ─────────────────────────────
section('5. Controller::requireRole() — 403 para JSON');

require_once ROOT_PATH . '/core/Controller.php';

$src = file_get_contents(ROOT_PATH . '/core/Controller.php');
ok('requireRole detecta Accept JSON',          strpos($src, 'application/json') !== false);
ok('requireRole retorna 403 para JSON',         strpos($src, '403') !== false);
ok('redirect() rejeita paths com ://',          strpos($src, '://') !== false && strpos($src, "str_contains(\$path, '://')") !== false);
ok('redirect() rejeita paths sem /inicial',    strpos($src, "str_starts_with(\$path, '/')") !== false);
```

- [ ] **Step 2: Implementar**

Abrir `core/Controller.php`. Substituir o método `requireRole()`:

```php
    protected function requireRole(string|array $roles): void
    {
        $roles    = (array) $roles;
        $userRole = $_SESSION['user']['role'] ?? '';

        if (!in_array($userRole, $roles, true)) {
            $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                   || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

            if ($isJson) {
                $this->json([
                    'success' => false,
                    'error'   => ['code' => 'forbidden', 'message' => 'Acesso negado.'],
                ], 403);
            } else {
                $this->flash('error', 'Acesso negado: você não tem permissão para esta ação.');
                $this->redirect('/dashboard');
            }
        }
    }
```

Substituir o método `redirect()`:

```php
    protected function redirect(string $path): void
    {
        if (str_contains($path, '://') || !str_starts_with($path, '/')) {
            $path = '/dashboard';
        }
        header('Location: ' . APP_URL . $path);
        exit;
    }
```

- [ ] **Step 3: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 4: Commit**

```bash
git add core/Controller.php tests/Phase12Test.php
git commit -m "fix: requireRole() retorna 403 para JSON; redirect() valida paths"
```

---

### Task 6: .htaccess por diretório

**Files:**
- Create: `app/.htaccess`, `core/.htaccess`, `config/.htaccess`, `database/.htaccess`, `tests/.htaccess`

- [ ] **Step 1: Criar arquivos**

Conteúdo idêntico em todos:

```apache
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
```

Criar todos de uma vez:

```bash
htaccess_content='<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>'

echo "$htaccess_content" > app/.htaccess
echo "$htaccess_content" > core/.htaccess
echo "$htaccess_content" > config/.htaccess
echo "$htaccess_content" > database/.htaccess
echo "$htaccess_content" > tests/.htaccess
```

- [ ] **Step 2: Adicionar testes**

```php
// ── 6. .htaccess por diretório ───────────────────────────────────────────────
section('6. .htaccess por diretório');
foreach (['app', 'core', 'config', 'database', 'tests'] as $dir) {
    $path = ROOT_PATH . "/{$dir}/.htaccess";
    ok("{$dir}/.htaccess existe",            file_exists($path));
    ok("{$dir}/.htaccess tem Require denied", strpos(file_get_contents($path), 'Require all denied') !== false);
}
```

- [ ] **Step 3: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 4: Commit**

```bash
git add app/.htaccess core/.htaccess config/.htaccess database/.htaccess tests/.htaccess tests/Phase12Test.php
git commit -m "feat: .htaccess em app/, core/, config/, database/, tests/ — Deny all"
```

---

### Task 7: CSP — remover unsafe-inline e jsdelivr do connect-src

**Files:**
- Modify: `core/Middleware/CspMiddleware.php`
- Modify: `app/Views/layouts/main.php`

- [ ] **Step 1: Auditar style= inline no layout**

```bash
grep -n 'style="' app/Views/layouts/main.php
```

Anotar as linhas com `style=""` inline.

- [ ] **Step 2: Substituir style= inline por classes Tailwind**

Abrir `app/Views/layouts/main.php`. Para cada `style=""` encontrado:

- `style="background-color: #cor"` → usar `bg-[#cor]` ou classe Tailwind equivalente
- `style="color: #cor"` → usar `text-[#cor]`
- `style="width: Xpx"` → usar `w-[Xpx]`

Exemplo de padrão para cores de stages dinâmicas (geradas por PHP):
```php
// antes:
<div style="background-color: <?= htmlspecialchars($stage['color']) ?>">

// depois (usando Tailwind JIT arbitrary):
<div class="pipeline-stage-header" data-color="<?= htmlspecialchars($stage['color']) ?>">
```

Se a cor for dinâmica (variável PHP) e não mapeável para Tailwind, usar CSS variable inline no elemento root da view em vez de `style=""` em cada elemento — ou adicionar nonce:
```php
<style nonce="<?= CSP_NONCE ?>">
  .stage-header-<?= $stage['id'] ?> { background-color: <?= htmlspecialchars($stage['color']) ?>; }
</style>
```

- [ ] **Step 3: Atualizar CspMiddleware**

Abrir `core/Middleware/CspMiddleware.php`. Substituir o conteúdo do método `handle()`:

```php
    public function handle(): void
    {
        $nonce = bin2hex(random_bytes(16));
        define('CSP_NONCE', $nonce);

        $csp = "default-src 'self'; " .
               "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'; " .
               "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com; " .
               "img-src 'self' data:; " .
               "font-src 'self' data: https://fonts.gstatic.com; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none'; " .
               "base-uri 'none'; " .
               "form-action 'self'";

        header("Content-Security-Policy: " . $csp);
    }
```

- [ ] **Step 4: Adicionar testes**

```php
// ── 7. CSP — sem unsafe-inline, sem jsdelivr em connect-src ──────────────────
section('7. CspMiddleware');
$src = file_get_contents(ROOT_PATH . '/core/Middleware/CspMiddleware.php');
ok("sem 'unsafe-inline' no style-src",     strpos($src, "'unsafe-inline'") === false);
ok("sem cdn.jsdelivr.net em connect-src",   strpos($src, 'cdn.jsdelivr.net') === false);
ok("style-src usa nonce",                   strpos($src, "nonce-{") !== false || preg_match('/style-src[^;]*nonce/', $src) === 1);
```

- [ ] **Step 5: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 6: Commit**

```bash
git add core/Middleware/CspMiddleware.php app/Views/layouts/main.php tests/Phase12Test.php
git commit -m "fix: CSP remove unsafe-inline (style-src usa nonce) e jsdelivr do connect-src"
```

---

### Task 8: bootstrap.php — session gc_maxlifetime e Logger .htaccess proativo

**Files:**
- Modify: `core/bootstrap.php`
- Modify: `core/Logger.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 8. bootstrap.php — session gc e Logger .htaccess ─────────────────────────
section('8. bootstrap.php e Logger .htaccess');
$bsrc = file_get_contents(ROOT_PATH . '/core/bootstrap.php');
ok('bootstrap define session.gc_maxlifetime', strpos($bsrc, 'session.gc_maxlifetime') !== false);
ok('bootstrap cria .htaccess de storage/logs', strpos($bsrc, 'storage/logs') !== false);

$lsrc = file_get_contents(ROOT_PATH . '/core/Logger.php');
ok('Logger não escreve .htaccess no handle()',
    preg_match('/function log[^}]*htaccess/s', $lsrc) === 0
    && preg_match('/function log[^}]*\.htaccess/s', $lsrc) === 0);
```

- [ ] **Step 2: Atualizar bootstrap.php**

Abrir `core/bootstrap.php`. Após o bloco de configurações de sessão e antes de `session_name(SESSION_NAME)`, adicionar:

```php
ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
```

Após o `require_once CORE_PATH . DS . 'helpers.php';`, adicionar:

```php
// Garantir proteção do diretório de logs desde o bootstrap (não lazy)
$_logDir     = ROOT_PATH . DS . 'storage' . DS . 'logs';
$_htaccessLog = $_logDir . DS . '.htaccess';
if (!is_dir($_logDir)) {
    mkdir($_logDir, 0755, true);
}
if (!file_exists($_htaccessLog)) {
    file_put_contents($_htaccessLog, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
}
unset($_logDir, $_htaccessLog);
```

- [ ] **Step 3: Remover escrita lazy de .htaccess do Logger**

Abrir `core/Logger.php`. Localizar o trecho que escreve o `.htaccess` dentro do método `log()` (ou método inicialização). Remover esse trecho — o bootstrap agora garante a existência do arquivo.

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 5: Commit**

```bash
git add core/bootstrap.php core/Logger.php tests/Phase12Test.php
git commit -m "fix: bootstrap define gc_maxlifetime e cria .htaccess de logs — não mais lazy"
```

---

### Task 9: filter_var FILTER_SANITIZE_EMAIL → FILTER_VALIDATE_EMAIL

**Files:**
- Modify: `app/Controllers/AuthController.php`
- Modify: `app/Controllers/UserController.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 9. filter_var — FILTER_SANITIZE_EMAIL removido ───────────────────────────
section('9. filter_var — sem FILTER_SANITIZE_EMAIL');
foreach (['AuthController', 'UserController'] as $ctrl) {
    $src = file_get_contents(ROOT_PATH . "/app/Controllers/{$ctrl}.php");
    ok("{$ctrl} não usa FILTER_SANITIZE_EMAIL", strpos($src, 'FILTER_SANITIZE_EMAIL') === false);
    ok("{$ctrl} usa FILTER_VALIDATE_EMAIL",      strpos($src, 'FILTER_VALIDATE_EMAIL') !== false);
}
```

- [ ] **Step 2: Corrigir AuthController**

Abrir `app/Controllers/AuthController.php`. Localizar:
```php
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
```

Substituir por:
```php
$raw   = trim($_POST['email'] ?? '');
$email = filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : '';
```

- [ ] **Step 3: Corrigir UserController**

Abrir `app/Controllers/UserController.php`. Localizar todas as ocorrências de `FILTER_SANITIZE_EMAIL` nos métodos `store()` e `update()`. Para cada uma:

```php
// antes:
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

// depois:
$raw   = trim($_POST['email'] ?? '');
$email = filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : '';
if (!$email) {
    $this->flash('error', 'E-mail inválido.');
    $this->redirect(/* rota de volta */);
    return;
}
```

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase12Test.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/AuthController.php app/Controllers/UserController.php tests/Phase12Test.php
git commit -m "fix: substituir FILTER_SANITIZE_EMAIL por FILTER_VALIDATE_EMAIL (PHP 8.1+ compat)"
```

---

### Task 10: Deletar env crm.txt

**Files:**
- Delete: `env crm.txt`

- [ ] **Step 1: Deletar o arquivo**

```bash
rm "env crm.txt"
```

- [ ] **Step 2: Verificar que já está no .gitignore**

```bash
grep "env" .gitignore
```

Confirmar que a linha `env\ crm.txt` existe.

- [ ] **Step 3: Adicionar aviso no README**

Abrir `README.md`. Localizar a seção de instalação. Adicionar após as instruções de `.env`:

```markdown
> **Atenção:** Nunca mantenha arquivos de credenciais (`.env`, `env crm.txt`, etc.) no
> diretório do projeto. Use variáveis de ambiente do painel Hostinger ou coloque o `.env`
> **fora** da pasta pública. Se você rotacionou credenciais após um vazamento, documente
> isso em seus registros internos.
```

- [ ] **Step 4: Rodar suite completa**

```bash
php tests/Phase12Test.php
```

Resultado esperado: `Todos os testes passaram.`

```bash
php tests/Phase11Test.php
```

Resultado esperado: `Todos os testes passaram.`

- [ ] **Step 5: Commit**

```bash
git add README.md
git commit -m "security: remove env crm.txt e documenta política de credenciais no README"
```

---

### Task 11: Finalizar Phase12Test

**Files:**
- Modify: `tests/Phase12Test.php`

- [ ] **Step 1: Adicionar bloco de resultado final**

Garantir que o final de `tests/Phase12Test.php` contém:

```php
// ── Resultado final ───────────────────────────────────────────────────────────
$total = $results['pass'] + $results['fail'];
echo "\n\033[1m────────────────────────────────────\033[0m\n";
echo "\033[1mResultado: {$results['pass']}/{$total} testes passaram\033[0m\n";

if ($results['fail'] > 0) {
    echo "\n\033[31mFalharam:\033[0m\n";
    foreach ($results['errors'] as $e) { echo "  • {$e}\n"; }
    exit(1);
}
echo "\033[32mTodos os testes passaram.\033[0m\n";
exit(0);
```

- [ ] **Step 2: Rodar suite completa de regressão**

```bash
php tests/Phase11Test.php && php tests/Phase12Test.php
```

Esperado: ambos passam.

- [ ] **Step 3: Commit**

```bash
git add tests/Phase12Test.php
git commit -m "test: Phase12 — cobertura completa da Fase 2 (segurança)"
```

---

*Fase 2 concluída. Próximo: `docs/superpowers/plans/2026-04-17-fase3-performance.md`*
