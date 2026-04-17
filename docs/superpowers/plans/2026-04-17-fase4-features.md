# Fase 4 — Features Incompletas

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completar as 4 implementações meio-construídas: avatar upload, tenant onboarding UI, profile/change-password, e documentação do WhatsApp.

**Architecture:** Cada task é independente. A Task 2 (ProfileController + change-password) deve ser feita ANTES da Task 3 (TenantController), pois o AuthMiddleware da Fase 2 redireciona para `/profile/change-password` — a rota precisa existir antes de ativar as migrações. A Task 1 (avatar) é totalmente independente.

**Tech Stack:** PHP 8.0+, MySQL/PDO, HTML5 multipart forms, finfo.

**Pré-requisito:** Fases 1 e 2 concluídas (colunas `password_must_change` e `is_system_admin` devem existir no banco via migration 007).

---

## Mapa de Arquivos

| Arquivo | Ação | Responsabilidade |
|---------|------|-----------------|
| `app/Controllers/UserController.php` | Modificar | Adicionar uploadAvatar, destroyAvatar |
| `app/Controllers/ProfileController.php` | Criar | Editar perfil próprio e trocar senha |
| `app/Controllers/TenantController.php` | Criar | CRUD de tenants (system admin) |
| `app/Models/Tenant.php` | Criar | Queries na tabela tenants |
| `app/Views/admin/users/edit.php` | Modificar | Campo de upload de avatar |
| `app/Views/profile/index.php` | Criar | Formulário de dados pessoais |
| `app/Views/profile/change-password.php` | Criar | Formulário de troca de senha |
| `app/Views/admin/tenants/index.php` | Criar | Lista de tenants |
| `app/Views/admin/tenants/create.php` | Criar | Formulário criar tenant |
| `app/Views/admin/tenants/edit.php` | Criar | Formulário editar tenant |
| `core/Middleware/SystemAdminMiddleware.php` | Criar | Verificar is_system_admin = 1 |
| `config/routes.php` | Modificar | Novas rotas de avatar, profile, tenants |
| `public/uploads/.gitkeep` | Criar | Manter diretório de uploads no git |
| `tests/Phase14Test.php` | Criar | Testes estruturais da Fase 4 |

---

### Task 1: Avatar Upload

**Files:**
- Modify: `app/Controllers/UserController.php`
- Modify: `app/Views/admin/users/edit.php` (verificar existência)
- Modify: `config/routes.php`
- Create: `public/uploads/.gitkeep`

- [ ] **Step 1: Criar Phase14Test.php**

```php
<?php
/**
 * tests/Phase14Test.php — Fase 4: Features Incompletas
 * Execute: php tests/Phase14Test.php
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

// ── 1. Avatar Upload ─────────────────────────────────────────────────────────
section('1. Avatar Upload');
$src = file_get_contents(ROOT_PATH . '/app/Controllers/UserController.php');
ok('uploadAvatar existe',         strpos($src, 'uploadAvatar') !== false);
ok('destroyAvatar existe',        strpos($src, 'destroyAvatar') !== false);
ok('usa finfo_file()',            strpos($src, 'finfo_file') !== false);
ok('valida MIME (image/jpeg)',    strpos($src, 'image/jpeg') !== false);
ok('valida tamanho (2MB)',        strpos($src, '2') !== false && (strpos($src, '2097152') !== false || strpos($src, '2 * 1024') !== false));
ok('usa random_bytes para nome', strpos($src, 'random_bytes') !== false);
ok('salva em uploads/{tenant}',  strpos($src, 'uploads') !== false);

$routes = file_get_contents(ROOT_PATH . '/config/routes.php');
ok('rota POST avatar existe',    strpos($routes, '/avatar') !== false);

ok('public/uploads/.gitkeep existe', file_exists(ROOT_PATH . '/public/uploads/.gitkeep'));
```

- [ ] **Step 2: Criar public/uploads/.gitkeep**

```bash
touch public/uploads/.gitkeep
```

Verificar que `.gitignore` tem:
```
public/uploads/*
!public/uploads/.gitkeep
```

- [ ] **Step 3: Adicionar uploadAvatar e destroyAvatar ao UserController**

Abrir `app/Controllers/UserController.php`. Adicionar os dois métodos ao final da classe (antes do `}`):

```php
    public function uploadAvatar(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);

        $userModel = new User();
        $user = $userModel->findById($id);
        if (!$user) {
            $this->flash('error', 'Usuário não encontrado.');
            $this->redirect('/admin/users');
            return;
        }

        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Erro no upload. Tente novamente.');
            $this->redirect('/admin/users/' . $id . '/edit');
            return;
        }

        $file   = $_FILES['avatar'];
        $finfo  = new \finfo(FILEINFO_MIME_TYPE);
        $mime   = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            $this->flash('error', 'Tipo de arquivo não permitido. Use JPG, PNG ou WebP.');
            $this->redirect('/admin/users/' . $id . '/edit');
            return;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            $this->flash('error', 'Arquivo muito grande. Máximo: 2MB.');
            $this->redirect('/admin/users/' . $id . '/edit');
            return;
        }

        $tenantId  = $_SESSION['tenant_id'];
        $uploadDir = ROOT_PATH . '/public/uploads/' . $tenantId;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Quota: máximo 50 arquivos por tenant
        if (count(glob($uploadDir . '/*')) >= 50) {
            $this->flash('error', 'Limite de uploads atingido para este tenant.');
            $this->redirect('/admin/users/' . $id . '/edit');
            return;
        }

        $ext      = $allowed[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->flash('error', 'Falha ao salvar o arquivo.');
            $this->redirect('/admin/users/' . $id . '/edit');
            return;
        }

        // Deletar avatar anterior se existia
        if (!empty($user['avatar'])) {
            $oldPath = ROOT_PATH . '/public/' . ltrim($user['avatar'], '/');
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $relativePath = '/uploads/' . $tenantId . '/' . $filename;
        $userModel->update($id, ['avatar' => $relativePath]);

        $this->flash('success', 'Avatar atualizado com sucesso!');
        $this->redirect('/admin/users/' . $id . '/edit');
    }

    public function destroyAvatar(array $params = []): void
    {
        $this->requireRole('admin');
        $id = (int) ($params['id'] ?? 0);

        $userModel = new User();
        $user = $userModel->findById($id);
        if (!$user) {
            $this->flash('error', 'Usuário não encontrado.');
            $this->redirect('/admin/users');
            return;
        }

        if (!empty($user['avatar'])) {
            $path = ROOT_PATH . '/public/' . ltrim($user['avatar'], '/');
            if (file_exists($path)) {
                unlink($path);
            }
            $userModel->update($id, ['avatar' => null]);
        }

        $this->flash('success', 'Avatar removido.');
        $this->redirect('/admin/users/' . $id . '/edit');
    }
```

- [ ] **Step 4: Adicionar rotas**

Abrir `config/routes.php`. Após as rotas de usuários, adicionar:

```php
$router->post('/admin/users/{id}/avatar', 'UserController', 'uploadAvatar', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->post('/admin/users/{id}/avatar/delete', 'UserController', 'destroyAvatar', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
```

- [ ] **Step 5: Adicionar campo avatar na view de edição**

Abrir `app/Views/admin/users/edit.php`. Localizar o formulário. Adicionar:

1. No `<form>`, adicionar `enctype="multipart/form-data"`.
2. Antes do botão de submit, adicionar:

```html
<!-- Avatar -->
<div class="mt-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Avatar</label>

    <?php if (!empty($user['avatar'])): ?>
    <div class="flex items-center gap-4 mb-3">
        <img src="<?= htmlspecialchars(APP_URL . $user['avatar'], ENT_QUOTES, 'UTF-8') ?>"
             alt="Avatar"
             class="w-16 h-16 rounded-full object-cover border border-gray-200">
        <form method="POST"
              action="<?= APP_URL ?>/admin/users/<?= (int) $user['id'] ?>/avatar/delete"
              onsubmit="return confirm('Remover avatar?')">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="text-sm text-red-600 hover:underline">Remover avatar</button>
        </form>
    </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= APP_URL ?>/admin/users/<?= (int) $user['id'] ?>/avatar"
          enctype="multipart/form-data"
          class="flex items-center gap-3">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
               class="text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700">
        <button type="submit"
                class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
            Enviar
        </button>
    </form>
    <p class="mt-1 text-xs text-gray-500">JPG, PNG ou WebP — máximo 2MB.</p>
</div>
```

- [ ] **Step 6: Rodar testes**

```bash
php tests/Phase14Test.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/UserController.php app/Views/admin/users/edit.php config/routes.php public/uploads/.gitkeep tests/Phase14Test.php
git commit -m "feat: avatar upload com finfo MIME check, random filename e quota por tenant"
```

---

### Task 2: ProfileController — dados pessoais e troca de senha

**Files:**
- Create: `app/Controllers/ProfileController.php`
- Create: `app/Views/profile/index.php`
- Create: `app/Views/profile/change-password.php`
- Modify: `config/routes.php`
- Modify: `app/Models/User.php`

> Esta task deve ser concluída ANTES de ativar as migrações da Fase 2 em produção, porque o AuthMiddleware redireciona para `/profile/change-password` quando `password_must_change = 1`.

- [ ] **Step 1: Adicionar testes**

```php
// ── 2. ProfileController ─────────────────────────────────────────────────────
section('2. ProfileController');
ok('ProfileController existe',            file_exists(ROOT_PATH . '/app/Controllers/ProfileController.php'));
ok('view profile/index.php existe',       file_exists(ROOT_PATH . '/app/Views/profile/index.php'));
ok('view profile/change-password.php existe', file_exists(ROOT_PATH . '/app/Views/profile/change-password.php'));

$src = file_get_contents(ROOT_PATH . '/app/Controllers/ProfileController.php');
ok('método index existe',                 strpos($src, 'public function index') !== false);
ok('método update existe',                strpos($src, 'public function update') !== false);
ok('changePasswordForm existe',           strpos($src, 'changePasswordForm') !== false);
ok('changePassword existe',               strpos($src, 'public function changePassword') !== false);
ok('usa password_verify',                 strpos($src, 'password_verify') !== false);
ok('usa password_hash',                   strpos($src, 'password_hash') !== false);
ok('limpa password_must_change após troca', strpos($src, 'password_must_change') !== false);

$routes = file_get_contents(ROOT_PATH . '/config/routes.php');
ok('rota GET /profile existe',            strpos($routes, "get('/profile'") !== false);
ok('rota POST /profile/change-password',  strpos($routes, "'/profile/change-password'") !== false);
```

- [ ] **Step 2: Adicionar updatePassword em User model**

Abrir `app/Models/User.php`. Adicionar ao final da classe:

```php
    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = :hash, password_must_change = 0
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        return $stmt->execute([
            ':hash'      => $passwordHash,
            ':id'        => $id,
            ':tenant_id' => $this->currentTenantId(),
        ]);
    }
```

- [ ] **Step 3: Criar ProfileController**

Criar `app/Controllers/ProfileController.php`:

```php
<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\User;

class ProfileController extends Controller
{
    public function index(array $params = []): void
    {
        $userId    = (int) $_SESSION['user']['id'];
        $userModel = new User();
        $user      = $userModel->findById($userId);

        $this->render('profile/index', [
            'pageTitle'  => 'Meu Perfil',
            'title'      => 'Meu Perfil — ' . APP_NAME,
            'user'       => $user,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    public function update(array $params = []): void
    {
        $userId = (int) $_SESSION['user']['id'];
        $name   = trim($_POST['name'] ?? '');

        $raw   = trim($_POST['email'] ?? '');
        $email = filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : '';

        if (empty($name) || empty($email)) {
            $this->flash('error', 'Nome e e-mail são obrigatórios.');
            $this->redirect('/profile');
            return;
        }

        $userModel = new User();
        $userModel->update($userId, ['name' => $name, 'email' => $email]);

        // Atualizar sessão
        $_SESSION['user']['name']  = $name;
        $_SESSION['user']['email'] = $email;

        $this->flash('success', 'Perfil atualizado com sucesso!');
        $this->redirect('/profile');
    }

    public function changePasswordForm(array $params = []): void
    {
        $mustChange = !empty($_SESSION['user']['password_must_change']);

        $this->render('profile/change-password', [
            'pageTitle'  => 'Alterar Senha',
            'title'      => 'Alterar Senha — ' . APP_NAME,
            'mustChange' => $mustChange,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    public function changePassword(array $params = []): void
    {
        $userId      = (int) $_SESSION['user']['id'];
        $senhaAtual  = $_POST['senha_atual'] ?? '';
        $novaSenha   = $_POST['nova_senha'] ?? '';
        $confirmacao = $_POST['confirmacao'] ?? '';

        // Validações
        if (empty($senhaAtual) || empty($novaSenha) || empty($confirmacao)) {
            $this->flash('error', 'Preencha todos os campos.');
            $this->redirect('/profile/change-password');
            return;
        }

        if ($novaSenha !== $confirmacao) {
            $this->flash('error', 'A nova senha e a confirmação não conferem.');
            $this->redirect('/profile/change-password');
            return;
        }

        // Mínimo: 8 caracteres, 1 número, 1 maiúscula
        if (strlen($novaSenha) < 8
            || !preg_match('/[0-9]/', $novaSenha)
            || !preg_match('/[A-Z]/', $novaSenha)
        ) {
            $this->flash('error', 'A nova senha deve ter ao menos 8 caracteres, 1 número e 1 letra maiúscula.');
            $this->redirect('/profile/change-password');
            return;
        }

        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!password_verify($senhaAtual, $user['password_hash'])) {
            $this->flash('error', 'Senha atual incorreta.');
            $this->redirect('/profile/change-password');
            return;
        }

        $hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
        $userModel->updatePassword($userId, $hash);

        // Limpar flag na sessão
        $_SESSION['user']['password_must_change'] = 0;

        $this->flash('success', 'Senha alterada com sucesso!');
        $this->redirect('/profile');
    }
}
```

- [ ] **Step 4: Criar views de profile**

Criar `app/Views/profile/index.php`:

```php
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Meu Perfil</h1>

    <form method="POST" action="<?= APP_URL ?>/profile/update" class="bg-white rounded-xl shadow p-6 space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
            <input type="text" name="name"
                   value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required>
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                Salvar alterações
            </button>
            <a href="<?= APP_URL ?>/profile/change-password"
               class="ml-4 text-sm text-indigo-600 hover:underline">
                Alterar senha
            </a>
        </div>
    </form>
</div>
```

Criar `app/Views/profile/change-password.php`:

```php
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Alterar Senha</h1>

    <?php if (!empty($mustChange)): ?>
    <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
        Por segurança, você precisa definir uma nova senha antes de continuar.
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= APP_URL ?>/profile/change-password" class="bg-white rounded-xl shadow p-6 space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Senha atual</label>
            <input type="password" name="senha_atual"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required autocomplete="current-password">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nova senha</label>
            <input type="password" name="nova_senha"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required autocomplete="new-password"
                   minlength="8">
            <p class="text-xs text-gray-500 mt-1">Mínimo 8 caracteres, 1 número e 1 letra maiúscula.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar nova senha</label>
            <input type="password" name="confirmacao"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   required autocomplete="new-password">
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                Alterar senha
            </button>
            <?php if (empty($mustChange)): ?>
            <a href="<?= APP_URL ?>/profile"
               class="ml-4 text-sm text-gray-500 hover:underline">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>
```

- [ ] **Step 5: Adicionar rotas**

Abrir `config/routes.php`. Adicionar antes das rotas de settings:

```php
// ---- Perfil do usuário ----
$router->get('/profile', 'ProfileController', 'index', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/profile/update', 'ProfileController', 'update', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->get('/profile/change-password', 'ProfileController', 'changePasswordForm', ['AuthMiddleware', 'CspMiddleware']);
$router->post('/profile/change-password', 'ProfileController', 'changePassword', ['AuthMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
```

- [ ] **Step 6: Rodar testes**

```bash
php tests/Phase14Test.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/ProfileController.php app/Models/User.php app/Views/profile/ config/routes.php tests/Phase14Test.php
git commit -m "feat: ProfileController — editar perfil e trocar senha com validação de requisitos"
```

---

### Task 3: TenantController — onboarding UI

**Files:**
- Create: `app/Controllers/TenantController.php`
- Create: `app/Models/Tenant.php`
- Create: `app/Views/admin/tenants/index.php`
- Create: `app/Views/admin/tenants/create.php`
- Create: `app/Views/admin/tenants/edit.php`
- Create: `core/Middleware/SystemAdminMiddleware.php`
- Modify: `config/routes.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 3. TenantController ──────────────────────────────────────────────────────
section('3. TenantController');
ok('TenantController existe',              file_exists(ROOT_PATH . '/app/Controllers/TenantController.php'));
ok('Tenant model existe',                  file_exists(ROOT_PATH . '/app/Models/Tenant.php'));
ok('SystemAdminMiddleware existe',         file_exists(ROOT_PATH . '/core/Middleware/SystemAdminMiddleware.php'));
ok('view tenants/index.php existe',        file_exists(ROOT_PATH . '/app/Views/admin/tenants/index.php'));
ok('view tenants/create.php existe',       file_exists(ROOT_PATH . '/app/Views/admin/tenants/create.php'));

$src = file_get_contents(ROOT_PATH . '/app/Controllers/TenantController.php');
ok('método index existe',                  strpos($src, 'public function index') !== false);
ok('método store existe',                  strpos($src, 'public function store') !== false);
ok('store faz seed de pipeline stages',    strpos($src, 'seedDefaultPipelineStages') !== false);

$routes = file_get_contents(ROOT_PATH . '/config/routes.php');
ok('rota GET /admin/tenants existe',       strpos($routes, "'/admin/tenants'") !== false);

$mw = file_get_contents(ROOT_PATH . '/core/Middleware/SystemAdminMiddleware.php');
ok('SystemAdmin verifica is_system_admin', strpos($mw, 'is_system_admin') !== false);
```

- [ ] **Step 2: Criar SystemAdminMiddleware**

Criar `core/Middleware/SystemAdminMiddleware.php`:

```php
<?php

namespace Core\Middleware;

class SystemAdminMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user']['is_system_admin'])) {
            http_response_code(403);
            // Para JSON
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => ['code' => 'forbidden', 'message' => 'Acesso negado.']]);
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Acesso negado: requer permissão de administrador do sistema.'];
                header('Location: ' . APP_URL . '/dashboard');
            }
            exit;
        }
    }
}
```

- [ ] **Step 3: Criar Tenant model**

Criar `app/Models/Tenant.php`:

```php
<?php

namespace App\Models;

use Core\Model;

class Tenant extends Model
{
    protected string $table = 'tenants';
    protected bool $isGlobal = true;

    public function findAll(): array
    {
        return $this->db->query("SELECT * FROM tenants ORDER BY id ASC")->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tenants (name, slug, payment_cutoff_day)
            VALUES (:name, :slug, :cutoff)
        ");
        $stmt->execute([
            ':name'   => $data['name'],
            ':slug'   => $data['slug'],
            ':cutoff' => (int) ($data['payment_cutoff_day'] ?? 20),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tenants SET name = :name, payment_cutoff_day = :cutoff WHERE id = :id
        ");
        return $stmt->execute([
            ':name'   => $data['name'],
            ':cutoff' => (int) ($data['payment_cutoff_day'] ?? 20),
            ':id'     => $id,
        ]);
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tenants WHERE slug = :slug AND id != :id");
        $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
```

- [ ] **Step 4: Criar TenantController**

Criar `app/Controllers/TenantController.php`:

```php
<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Tenant;
use App\Models\User;

class TenantController extends Controller
{
    public function index(array $params = []): void
    {
        $tenantModel = new Tenant();
        $this->render('admin/tenants/index', [
            'pageTitle' => 'Tenants',
            'title'     => 'Tenants — ' . APP_NAME,
            'tenants'   => $tenantModel->findAll(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    public function create(array $params = []): void
    {
        $this->render('admin/tenants/create', [
            'pageTitle'  => 'Novo Tenant',
            'title'      => 'Novo Tenant — ' . APP_NAME,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    public function store(array $params = []): void
    {
        $name   = trim($_POST['name'] ?? '');
        $slug   = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? '')));
        $cutoff = (int) ($_POST['payment_cutoff_day'] ?? 20);

        $adminName  = trim($_POST['admin_name'] ?? '');
        $adminEmail = filter_var(trim($_POST['admin_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
        $adminPass  = $_POST['admin_password'] ?? '';

        if (!$name || !$slug || !$adminName || !$adminEmail || strlen($adminPass) < 8) {
            $this->flash('error', 'Preencha todos os campos. Senha mínima: 8 caracteres.');
            $this->redirect('/admin/tenants/create');
            return;
        }

        $tenantModel = new Tenant();

        if ($tenantModel->slugExists($slug)) {
            $this->flash('error', "O slug '{$slug}' já está em uso.");
            $this->redirect('/admin/tenants/create');
            return;
        }

        $pdo = \Core\Database::getInstance();
        $pdo->beginTransaction();
        try {
            // Criar tenant
            $tenantId = $tenantModel->create([
                'name'               => $name,
                'slug'               => $slug,
                'payment_cutoff_day' => $cutoff,
            ]);

            // Criar admin do novo tenant
            $userModel = new User();
            // Simular sessão para currentTenantId()
            $_SESSION['tenant_id'] = $tenantId;
            $userModel->create([
                'name'          => $adminName,
                'email'         => $adminEmail,
                'password_hash' => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]),
                'role'          => 'admin',
            ]);

            // Seed dos stages padrão
            require_once ROOT_PATH . '/database/seeders/pipeline_stages_default.php';
            seedDefaultPipelineStages($pdo, $tenantId);

            $pdo->commit();

            // Restaurar tenant_id da sessão
            $_SESSION['tenant_id'] = $_SESSION['user']['tenant_id'] ?? 1;

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['tenant_id'] = $_SESSION['user']['tenant_id'] ?? 1;
            $this->flash('error', 'Erro ao criar tenant: ' . $e->getMessage());
            $this->redirect('/admin/tenants/create');
            return;
        }

        $this->flash('success', "Tenant '{$name}' criado com sucesso!");
        $this->redirect('/admin/tenants');
    }

    public function edit(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantModel = new Tenant();
        $tenant = $tenantModel->findById($id);

        if (!$tenant) {
            $this->flash('error', 'Tenant não encontrado.');
            $this->redirect('/admin/tenants');
            return;
        }

        $this->render('admin/tenants/edit', [
            'pageTitle'  => 'Editar Tenant',
            'title'      => 'Editar Tenant — ' . APP_NAME,
            'tenant'     => $tenant,
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    public function update(array $params = []): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $cutoff = (int) ($_POST['payment_cutoff_day'] ?? 20);

        if (!$name) {
            $this->flash('error', 'Nome é obrigatório.');
            $this->redirect('/admin/tenants/' . $id . '/edit');
            return;
        }

        $tenantModel = new Tenant();
        $tenantModel->update($id, ['name' => $name, 'payment_cutoff_day' => $cutoff]);

        $this->flash('success', 'Tenant atualizado.');
        $this->redirect('/admin/tenants');
    }
}
```

- [ ] **Step 5: Criar views de tenants**

Criar `app/Views/admin/tenants/index.php`:

```php
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Tenants</h1>
    <a href="<?= APP_URL ?>/admin/tenants/create"
       class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
        + Novo Tenant
    </a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">ID</th>
                <th class="px-4 py-3 text-left">Nome</th>
                <th class="px-4 py-3 text-left">Slug</th>
                <th class="px-4 py-3 text-left">Corte</th>
                <th class="px-4 py-3 text-left">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($tenants as $tenant): ?>
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-500"><?= (int) $tenant['id'] ?></td>
            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="px-4 py-3 text-gray-500 font-mono"><?= htmlspecialchars($tenant['slug'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="px-4 py-3">Dia <?= (int) $tenant['payment_cutoff_day'] ?></td>
            <td class="px-4 py-3">
                <a href="<?= APP_URL ?>/admin/tenants/<?= (int) $tenant['id'] ?>/edit"
                   class="text-indigo-600 hover:underline text-xs">Editar</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

Criar `app/Views/admin/tenants/create.php`:

```php
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Novo Tenant</h1>

    <form method="POST" action="<?= APP_URL ?>/admin/tenants" class="bg-white rounded-xl shadow p-6 space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
            <legend class="text-sm font-semibold text-gray-700 px-1">Dados do Tenant</legend>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="name" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-xs text-gray-400">(letras, números, hífens)</span></label>
                <input type="text" name="slug" required pattern="[a-z0-9\-]+"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dia de corte (1–28)</label>
                <input type="number" name="payment_cutoff_day" value="20" min="1" max="28"
                       class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </fieldset>

        <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
            <legend class="text-sm font-semibold text-gray-700 px-1">Admin inicial do Tenant</legend>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="admin_name" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                <input type="email" name="admin_email" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Senha inicial</label>
                <input type="password" name="admin_password" required minlength="8"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-500 mt-1">O admin será forçado a trocar no primeiro login.</p>
            </div>
        </fieldset>

        <div class="pt-2 flex gap-3">
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                Criar Tenant
            </button>
            <a href="<?= APP_URL ?>/admin/tenants"
               class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </form>
</div>
```

Criar `app/Views/admin/tenants/edit.php`:

```php
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Editar Tenant</h1>

    <form method="POST" action="<?= APP_URL ?>/admin/tenants/<?= (int) $tenant['id'] ?>/update"
          class="bg-white rounded-xl shadow p-6 space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-xs text-gray-400">(read-only)</span></label>
            <input type="text" value="<?= htmlspecialchars($tenant['slug'], ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm font-mono text-gray-500" disabled>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dia de corte (1–28)</label>
            <input type="number" name="payment_cutoff_day" min="1" max="28"
                   value="<?= (int) $tenant['payment_cutoff_day'] ?>"
                   class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="pt-2 flex gap-3">
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                Salvar
            </button>
            <a href="<?= APP_URL ?>/admin/tenants"
               class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </form>
</div>
```

- [ ] **Step 6: Adicionar rotas de tenants**

Abrir `config/routes.php`. Adicionar antes das rotas de settings:

```php
// ---- Admin de Tenants (somente system admin) ----
$router->get('/admin/tenants', 'TenantController', 'index', ['AuthMiddleware', 'SystemAdminMiddleware', 'CspMiddleware']);
$router->get('/admin/tenants/create', 'TenantController', 'create', ['AuthMiddleware', 'SystemAdminMiddleware', 'CspMiddleware']);
$router->post('/admin/tenants', 'TenantController', 'store', ['AuthMiddleware', 'SystemAdminMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
$router->get('/admin/tenants/{id}/edit', 'TenantController', 'edit', ['AuthMiddleware', 'SystemAdminMiddleware', 'CspMiddleware']);
$router->post('/admin/tenants/{id}/update', 'TenantController', 'update', ['AuthMiddleware', 'SystemAdminMiddleware', 'CsrfMiddleware', 'CspMiddleware']);
```

- [ ] **Step 7: Rodar testes**

```bash
php tests/Phase14Test.php
```

- [ ] **Step 8: Commit**

```bash
git add app/Controllers/TenantController.php app/Models/Tenant.php core/Middleware/SystemAdminMiddleware.php app/Views/admin/tenants/ config/routes.php tests/Phase14Test.php
git commit -m "feat: TenantController — onboarding UI com seed de stages + SystemAdminMiddleware"
```

---

### Task 4: WhatsApp — documentação na UI

**Files:**
- Modify: `app/Views/interactions/` (form de criação/edição)
- Modify: `README.md`

- [ ] **Step 1: Localizar a view de interações**

```bash
ls app/Views/interactions/
```

Localizar o `<select>` de tipo de interação (contém `whatsapp`).

- [ ] **Step 2: Adicionar nota explicativa**

No arquivo da view que contém o select de tipo, após o `<select name="type">` ou próximo da opção `whatsapp`, adicionar:

```html
<p class="mt-1 text-xs text-gray-500">
    WhatsApp: registra manualmente que houve contato via WhatsApp — nenhuma mensagem é enviada pelo sistema.
</p>
```

- [ ] **Step 3: Atualizar README**

Abrir `README.md`. Adicionar (ou atualizar) seção "Integrações":

```markdown
## Integrações

| Integração | Status |
|------------|--------|
| WhatsApp   | Manual — o tipo "WhatsApp" em Interações registra que houve contato, mas não envia mensagens. |
| Webmail    | Link externo apenas (sem integração) |
| CRM Apollo | Link externo apenas (sem integração) |
```

- [ ] **Step 4: Adicionar testes**

```php
// ── 4. WhatsApp — documentação ───────────────────────────────────────────────
section('4. WhatsApp — nota na UI e README');
$readme = file_get_contents(ROOT_PATH . '/README.md');
ok('README menciona WhatsApp manual', strpos($readme, 'WhatsApp') !== false
    && (strpos($readme, 'manual') !== false || strpos($readme, 'Manual') !== false));
```

- [ ] **Step 5: Finalizar Phase14Test com resultado**

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

- [ ] **Step 6: Rodar suite completa**

```bash
php tests/Phase11Test.php && php tests/Phase12Test.php && php tests/Phase13Test.php && php tests/Phase14Test.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Views/ README.md tests/Phase14Test.php
git commit -m "feat: WhatsApp documentado como registro manual na UI e no README"
```

---

*Fase 4 concluída. Próximo: `docs/superpowers/plans/2026-04-17-fase5-qualidade.md`*
