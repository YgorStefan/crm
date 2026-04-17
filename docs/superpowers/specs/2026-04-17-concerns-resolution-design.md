# Resolução Completa do CONCERNS.md

**Data:** 2026-04-17  
**Base:** commit `f2fba75` (branch `master`)  
**Escopo:** todos os 40+ itens inventariados em `.planning/codebase/CONCERNS.md`

---

## Visão Geral

O projeto CRM possui dívida técnica acumulada em cinco categorias: isolamento de dados multi-tenant, segurança, performance, features incompletas e qualidade de código. Este documento especifica a resolução completa em **5 fases ordenadas por risco**, cada uma commitável e testável independentemente.

### Decisões de Design Tomadas

| Decisão | Escolha | Razão |
|---------|---------|-------|
| `PipelineStage` — global ou por tenant? | **Por tenant** (`isGlobal = false`) | CRMs diferentes têm funis diferentes; compartilhar stages entre tenants é um vazamento de configuração |
| Avatar upload | **Implementar** | Coluna existe no schema, layout já tem fallback — feature meio-construída deve ser concluída |
| Tenant onboarding | **UI `/admin/tenants`** | Multi-tenancy sem onboarding automatizado é inoperável em escala |
| WhatsApp | **Registro manual** | Nenhuma integração — adicionar nota explicativa na UI |

---

## Fase 1 — Isolamento e Integridade de Dados

**Objetivo:** eliminar vazamentos cross-tenant e corrupção de dados em produção.

### 1.1 `ColdContact` — tenant isolation

`app/Models/ColdContact.php` não referencia `tenant_id` em nenhuma query, apesar da coluna existir após a migration. Todos os métodos de leitura e escrita expõem dados de todos os tenants.

**Mudanças:**
- `countFindMonthSummaries()`: adicionar `WHERE tenant_id = :tenant_id`
- `findMonthSummaries()`: adicionar `WHERE cc.tenant_id = :tenant_id`
- `countByMonth()` e `findByMonth()`: adicionar `AND cc.tenant_id = :tenant_id`
- `update()`, `destroy()`, `deleteByMonth()`, `bulkAtualizarExtras()`: adicionar `AND tenant_id = :tenant_id` em todos os WHERE
- `create()`: adicionar `tenant_id` no INSERT e no array de binding
- Todos os métodos leem `$this->currentTenantId()` — padrão já existente em `Client.php`

### 1.2 `htmlspecialchars()` no input — remover, não gravar no banco

`Controller::input()` aplica `htmlspecialchars()` antes de gravar no banco, corrompendo dados (`Smith & Co` → `Smith &amp; Co`). Views já aplicam `htmlspecialchars()` na renderização.

**Mudanças:**
- `core/Controller.php`: `input()` passa a fazer apenas `trim()`, sem encode
- `inputRaw()` mantido por ora (será deprecado na Fase 5)
- **Migration de limpeza** `database/migrations/004_decode_htmlentities.php`: percorre colunas de texto afetadas (`clients.name`, `clients.company`, `clients.email`, `clients.phone`, `clients.address`, `interactions.notes`, `tasks.title`, `tasks.description`, `cold_contacts.name`, `cold_contacts.phone`, `cold_contacts.notes`, `users.name`) e aplica `html_entity_decode($value, ENT_QUOTES, 'UTF-8')` em cada row

### 1.3 `Interaction` — validar client_id pertence ao tenant

`InteractionController::store()`, `update()`, `destroy()` aceitam `client_id` sem verificar se pertence ao tenant do usuário logado.

**Mudanças:**
- `store()`: antes de INSERT, chamar `$clientModel->findById($clientId)` — se null → `$this->json(['success' => false, 'error' => 'not_found'], 404)` (ou redirect com flash para rotas HTML)
- `update()`: mesma verificação de `client_id` se presente no payload
- `destroy()`: verificação de que `$interaction['client_id']` pertence ao tenant via join (ver 1.4)
- Instanciar `Client` no controller onde ainda não existe

### 1.4 `Interaction` — leitura direta sem tenant gate

`Core\Model::findById()` lança `RuntimeException` se `interactions` não tiver `tenant_id`. Por ora a tabela não tem a coluna — reads de interação são feitos via join em clients. Solução sem adicionar coluna:

**Mudanças em `app/Models/Interaction.php`:**
- Sobrescrever `findById(int $id): array|false` com query que faz `INNER JOIN clients c ON c.id = i.client_id AND c.tenant_id = :tenant_id WHERE i.id = :id`
- Adicionar `protected bool $isGlobal = true` **temporariamente** — necessário enquanto a coluna `interactions.tenant_id` não existir nos bancos em produção. A segurança é garantida pelo override acima.
- Após rodar a migration `009_backfill_interactions_tenant.php` (ver abaixo): remover `isGlobal = true` e o override, deixando o `Core\Model::findById()` padrão funcionar com o tenant gate automático.

**Nova migration `database/migrations/009_backfill_interactions_tenant.php`:**
```php
UPDATE interactions i
INNER JOIN clients c ON c.id = i.client_id
SET i.tenant_id = c.tenant_id
WHERE i.tenant_id IS NULL OR i.tenant_id = 0;
```
Esta migration deve rodar após `001_migrate_tenant_initial.php` e antes de qualquer deploy com a nova versão do model.

### 1.5 `PipelineStage` — por tenant

`$isGlobal = true` mas todos os métodos da classe já filtram por `tenant_id` manualmente. `Core\Model::findById()` com `isGlobal = true` não aplica filtro de tenant, permitindo que um tenant acesse stages de outro.

**Mudanças:**
- `app/Models/PipelineStage.php`: `protected bool $isGlobal = false`
- Sobrescrever `findById(int $id): array|false` adicionando `AND tenant_id = :tenant_id`
- Sobrescrever `delete(int $id): bool` adicionando `AND tenant_id = :tenant_id`
- **Migration** `database/migrations/005_pipeline_stages_assign_tenants.php`: verificar stages com `tenant_id = 1` que pertencem a outros tenants e corrigir conforme o tenant do usuário associado (ou manter DEFAULT 1 se houver apenas um tenant ativo)
- Seed dos 6 stages padrão ficará em `database/seeders/pipeline_stages_default.php`, chamado pelo onboarding de novo tenant (Fase 4)

### 1.6 `database/schema.sql` — sincronizar

O schema documentado não reflete o banco real — `tenant_id` ausente em 5 tabelas.

**Mudanças em `database/schema.sql`:**
- `clients`: adicionar `tenant_id INT UNSIGNED NOT NULL DEFAULT 1`, FK para `tenants(id)`, índice `idx_clients_tenant`
- `client_sales`: mesmos três itens
- `interactions`: mesmos três itens
- `tasks`: mesmos três itens  
- `cold_contacts`: mesmos três itens, além de `imported_year_month CHAR(7) GENERATED ALWAYS AS (DATE_FORMAT(imported_at, '%Y-%m')) STORED` + índice (antecipa Fase 5)
- Coluna `password_must_change TINYINT(1) NOT NULL DEFAULT 0` em `users` (antecipa Fase 2)
- Coluna `is_system_admin TINYINT(1) NOT NULL DEFAULT 0` em `users` (antecipa Fase 4)
- Seed admin atualizado: `password_must_change = 1`

### 1.7 `scripts/` — fora do `.gitignore`

Migrations de produção e build helpers não são descartáveis.

**Mudanças:**
- Criar `database/migrations/` e mover `migrate_tenant_initial.php`, `migrate_cold_contacts_tenant.php`, `verify_tenant_backfill.php` para lá, renomeando com prefixo numérico (`001_`, `002_`, `003_`)
- Criar `bin/` e mover `build_css.php`, `setup_tailwind.php`
- `scripts/smoke/` mover para `tests/smoke/`
- Remover `scripts/` do `.gitignore`; adicionar `scripts/` de volta apenas se o diretório ainda existir com arquivos temporários

---

## Fase 2 — Segurança

**Objetivo:** fechar vetores de ataque exploráveis e hardening de autenticação.

### 2.1 Rate limit no login

**Nova tabela** `login_attempts`:
```sql
CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip_time (ip, attempted_at)
);
```

**Novo `core/Middleware/RateLimitMiddleware.php`:**
- Contar tentativas do IP nos últimos 60 segundos
- Se >= 5: flash de erro, redirect de volta ao login, registrar no Logger
- Aplicar na rota `POST /login` em `config/routes.php`
- Limpeza de registros antigos: DELETE onde `attempted_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)` a cada chamada (amortizado)

### 2.2 Força troca de senha no primeiro login

- `users.password_must_change` (já adicionada no schema da Fase 1)
- `core/Middleware/AuthMiddleware.php`: após validar sessão, se `$_SESSION['user']['password_must_change'] == 1` e a rota atual não for `/profile/change-password` nem `/logout` → redirect para `/profile/change-password`
- `ProfileController::changePassword()`: ao salvar nova senha com sucesso → `UPDATE users SET password_must_change = 0` + atualizar `$_SESSION['user']`

### 2.3 `TaskController` — autorização por role

`update()` e `destroy()`:
```php
$role = $_SESSION['user']['role'];
$userId = $_SESSION['user']['id'];

if ($role === 'viewer') {
    // JSON ou redirect com 403
}
if ($role === 'seller' && $task['assigned_to'] != $userId && $task['created_by'] != $userId) {
    // 403
}
// admin: irrestrito
```

### 2.4 `requireRole()` — 403 para JSON

`core/Controller.php::requireRole()`:
- Detectar se a requisição é JSON: `str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')` ou header `X-Requested-With: XMLHttpRequest`
- Se JSON: `$this->json(['success' => false, 'error' => ['code' => 'forbidden', 'message' => 'Acesso negado']], 403); exit;`
- Se HTML: manter redirect atual para `/dashboard` com flash

### 2.5 `.htaccess` por diretório

Criar arquivo em cada diretório sensível com conteúdo:
```apache
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
```
Diretórios: `app/`, `core/`, `config/`, `database/`, `tests/`

Documentar equivalente nginx no README:
```nginx
location ~* ^/(app|core|config|database|tests)/ {
    deny all;
}
```

### 2.6 CSP — remover `unsafe-inline` e `cdn.jsdelivr.net` do connect-src

- `core/Middleware/CspMiddleware.php`: remover `'unsafe-inline'` de `style-src`
- Auditar `app/Views/layouts/main.php` e demais views para substituir atributos `style="..."` inline por classes Tailwind equivalentes
- Remover `https://cdn.jsdelivr.net` de `connect-src` (CDN é `script-src`, não `connect-src`)

### 2.7 `session.gc_maxlifetime`

`core/bootstrap.php` — adicionar antes de `session_start()`:
```php
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
```

### 2.8 `filter_var` deprecado

Substituir em `AuthController::login()`, `UserController::store()`, `UserController::update()`:
```php
// antes
$email = filter_var($raw, FILTER_SANITIZE_EMAIL);
// depois
$email = filter_var($raw, FILTER_VALIDATE_EMAIL) ? trim($raw) : null;
if (!$email) { /* erro de validação */ }
```

### 2.9 `env crm.txt` — remover

- Deletar `env crm.txt` do diretório do projeto
- Adicionar instrução no README: credenciais de produção devem ser configuradas via painel do Hostinger (variáveis de ambiente) ou em `.env` fora da pasta do projeto
- Rotacionar credenciais expostas (senha do banco, qualquer chave de API) — ação manual do operador, documentada no spec

### 2.10 Senha padrão no seed — já coberta por `password_must_change = 1` (Fase 1 + 2.2)

### 2.11 `Controller::redirect()` — validação de path

```php
public function redirect(string $path): void {
    if (str_contains($path, '://') || !str_starts_with($path, '/')) {
        $path = '/dashboard';
    }
    header('Location: ' . APP_URL . $path);
    exit;
}
```

---

## Fase 3 — Performance

**Objetivo:** eliminar queries desnecessariamente pesadas no path crítico.

### 3.1 Dashboard — count de clientes

`app/Controllers/DashboardController.php:22`:
```php
// antes
$totalClients = count($clientModel->findAllWithRelations());
// depois
$totalClients = $clientModel->countAllWithRelations([]);
```

### 3.2 Dashboard — upcoming tasks no banco

Novo método em `app/Models/Task.php`:
```php
public function findUpcomingForUser(int $userId, int $days = 7): array {
    $sql = "SELECT t.*, c.name as client_name
            FROM tasks t
            LEFT JOIN clients c ON c.id = t.client_id
            WHERE t.assigned_to = :user_id
              AND t.status = 'pending'
              AND t.tenant_id = :tenant_id
              AND t.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
            ORDER BY t.due_date ASC
            LIMIT 20";
    // ...
}
```
`DashboardController`: substituir `array_filter` pelo novo método.

### 3.3 `findAllOverdueSalesByClient()` — SQL puro

Substituir o walk em PHP por:
```sql
SELECT DISTINCT cs.client_id
FROM client_sales cs
INNER JOIN clients c ON c.id = cs.client_id
WHERE c.tenant_id = :tenant_id
  AND c.is_active = 1
  AND (cs.paid_at IS NULL OR cs.paid_at < :ref_start)
```
Cache de 60 segundos em propriedade estática: `static $cache = []; $key = $tenantId; if (isset($cache[$key])) return $cache[$key];`

### 3.4 Notifications polling — visibilityState + backoff

Em `app/Views/layouts/main.php` (bloco `checkNotifications`):
```javascript
let notifBackoff = 1;
let notifInterval = null;

function startNotifPolling() {
    notifInterval = setInterval(async () => {
        if (document.visibilityState === 'hidden') return;
        try {
            const res = await fetch('/api/tasks/upcoming', {...});
            if (!res.ok) { notifBackoff = Math.min(notifBackoff * 2, 8); }
            else { notifBackoff = 1; }
            // processar res
        } catch (e) { notifBackoff = Math.min(notifBackoff * 2, 8); }
    }, 60000 * notifBackoff);
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') { notifBackoff = 1; }
});

// BroadcastChannel: tab principal faz fetch, outras recebem
const bc = new BroadcastChannel('crm_notif');
bc.onmessage = (e) => renderNotifications(e.data);
```

### 3.5 SRI hashes para CDN

Calcular hashes SHA-384 para Chart.js 4.4.7 e FullCalendar 6.1.20. Atualizar Chart.js de 4.4.0 → 4.4.7. Adicionar `integrity="sha384-..."` e `crossorigin="anonymous"` em cada `<script>`.

---

## Fase 4 — Features Incompletas

**Objetivo:** completar implementações meio-construídas e eliminar inconsistências de UX.

### 4.1 Avatar upload

**Rotas** (`config/routes.php`):
```
POST   /admin/users/{id}/avatar   → UserController::uploadAvatar
DELETE /admin/users/{id}/avatar   → UserController::destroyAvatar
```

**`UserController::uploadAvatar()`:**
1. Verificar `$_FILES['avatar']` existe e `error === UPLOAD_ERR_OK`
2. `finfo_file()` → aceitar apenas `image/jpeg`, `image/png`, `image/webp`
3. Verificar tamanho ≤ 2MB
4. Verificar quota: `count(glob("public/uploads/{$tenantId}/*")) < 50`
5. Gerar nome: `bin2hex(random_bytes(16)) . '.' . $ext`
6. Salvar em `public/uploads/{tenant_id}/`
7. `User::update(['avatar' => $relativePath])`
8. Deletar avatar anterior se existia

**View** `app/Views/admin/users/edit.php`: adicionar `<input type="file" name="avatar" accept="image/*">` e preview do avatar atual.

**`public/uploads/{tenant_id}/`**: criar na Fase 1 se não existir; `.htaccess` permitindo acesso (único diretório público além de `public/assets/`).

### 4.2 Tenant onboarding UI

**Nova tabela** (já tem `tenants`): adicionar `is_active TINYINT(1) DEFAULT 1` se não existir.

**Nova flag em `users`**: `is_system_admin TINYINT(1) NOT NULL DEFAULT 0` (já adicionada na Fase 1).

**Novas rotas:**
```
GET    /admin/tenants           → TenantController::index    [is_system_admin]
GET    /admin/tenants/create    → TenantController::create   [is_system_admin]
POST   /admin/tenants           → TenantController::store    [is_system_admin]
GET    /admin/tenants/{id}/edit → TenantController::edit     [is_system_admin]
POST   /admin/tenants/{id}      → TenantController::update   [is_system_admin]
```

**`TenantController::store()`:**
1. INSERT em `tenants`
2. INSERT em `users` (admin do novo tenant, `password_must_change = 1`)
3. Seed dos 6 stages padrão para o novo `tenant_id` (extrair em `database/seeders/pipeline_stages_default.php`)
4. Redirect para lista com flash de sucesso

**Middleware de guarda:** criar `SystemAdminMiddleware` que verifica `$_SESSION['user']['is_system_admin'] == 1`.

### 4.3 Settings/profile — troca de senha e dados pessoais

**Nova rota:**
```
GET  /profile                   → ProfileController::index
POST /profile                   → ProfileController::update       (nome, email)
GET  /profile/change-password   → ProfileController::changePasswordForm
POST /profile/change-password   → ProfileController::changePassword
```

**`ProfileController::changePassword()`:**
- Validar `senha_atual` com `password_verify()`
- Validar `nova_senha` — mínimo 8 caracteres, ao menos 1 número e 1 maiúscula
- `password_hash($novaSenha, PASSWORD_BCRYPT)`
- `UPDATE users SET password = :hash, password_must_change = 0`
- Regenerar sessão CSRF

**View:** `app/Views/profile/index.php` e `app/Views/profile/change-password.php`

Sidebar: adicionar link "Meu Perfil" no menu do usuário (dropdown no header).

### 4.4 WhatsApp — documentação

- View `app/Views/interactions/create.php` (ou `_form.php`): adicionar `<p class="text-xs text-gray-500">Tipo WhatsApp registra o contato manualmente, sem enviar mensagem.</p>` ao lado do `<select>` de tipo
- README: seção "Integrações" com nota explícita que WhatsApp é manual

---

## Fase 5 — Qualidade e Arquitetura

**Objetivo:** eliminar dívida técnica acumulada, uniformizar padrões e adicionar proteções de regressão.

### 5.1 JSON helper unificado

Todos os endpoints que fazem `header('Content-Type: ...') + echo json_encode + exit` migram para `$this->json($payload, $status)`.

Arquivos afetados: `ClientController` (storeSale, destroySale, markSalePaid, updateNotes), `InteractionController` (update), `ColdContactController` (update, destroy, deleteMonth, bulkUpdate, listJson), `PipelineController` (move, getStages), `TaskController` (toggle, getTask).

### 5.2 Error shape padronizado

Shape único para todos os endpoints JSON:
```json
{ "success": true|false, "data": {}, "error": { "code": "snake_case", "message": "Texto PT-BR" } }
```
HTTP status sempre semântico. `Controller::json()` não muda assinatura — chamadores passam o status correto.

### 5.3 Views — extrair partials

**`clients/show.php`** (819 linhas) → extrair:
- `app/Views/components/client-sales-table.php`
- `app/Views/components/client-interactions-timeline.php`
- `app/Views/components/client-tasks-list.php`
- `app/Views/components/client-pipeline-badge.php`

**`cold-contacts/index.php`** (672 linhas) → extrair:
- `app/Views/components/cold-contacts-table.php`
- `app/Views/components/cold-contacts-filters.php`

Construção de arrays de view-model (ex.: `$grouped = array_reduce(...)`) mover para o controller.

### 5.4 Tailwind no git

Remover `tailwind.config.js` e `resources/css/input.css` do `.gitignore`. Commitar ambos. Manter `public/assets/css/tailwind.css` no git (build artifact comprometido é aceitável para deploy em shared hosting sem pipeline de build).

### 5.5 PHP 8.2

- README: atualizar requisito para PHP 8.2+
- `.htaccess` (raiz): adicionar `php_value engine on` + comentário sobre versão
- Varrer codebase por deprecations do 8.1→8.2 (`${var}` string interpolation, etc.)

### 5.6 `config/database.php` lendo `.env`

`config/database.php.example`:
```php
return [
    'host'    => env('DB_HOST', 'localhost'),
    'dbname'  => env('DB_NAME', 'crm'),
    'user'    => env('DB_USER', 'root'),
    'pass'    => env('DB_PASS', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];
```
README: atualizar passo de instalação para copiar `.env.example` → `.env` antes de `config/database.php.example` → `config/database.php`.

### 5.7 Testes de isolamento de tenant

Novo `tests/TenantIsolationTest.php`:
- Setup: criar 2 tenants no banco de teste, 1 usuário cada, registros de clients/tasks/interactions/cold_contacts para cada tenant
- Para cada Model e cada método público: verificar que chamadas com `tenant_id = 1` nunca retornam rows com `tenant_id = 2` e vice-versa
- Usar o runner manual existente (sem PHPUnit — consistente com o projeto)

### 5.8 CI básico

`.github/workflows/test.yml`:
```yaml
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env: { MYSQL_ROOT_PASSWORD: root, MYSQL_DATABASE: crm_test }
        options: --health-cmd="mysqladmin ping" --health-interval=10s
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }
      - run: mysql -uroot -proot crm_test < database/schema.sql
      - run: for f in tests/Phase*.php tests/TenantIsolationTest.php; do php $f; done
```

### 5.9 `cold_contacts` — archival policy

- Coluna `archived_at DATETIME NULL DEFAULT NULL` em `cold_contacts`
- Coluna `imported_year_month CHAR(7) GENERATED ALWAYS AS (DATE_FORMAT(imported_at, '%Y-%m')) STORED` + índice `idx_cc_year_month` (schema.sql já atualizado na Fase 1)
- UI: botão "Arquivar mês" na listagem de cards mensais — POST `/cold-contacts/archive/{year-month}` que faz `UPDATE cold_contacts SET archived_at = NOW() WHERE imported_year_month = :ym AND tenant_id = :tenant_id`
- `findMonthSummaries()`: adicionar `WHERE archived_at IS NULL` por padrão; parâmetro `$includeArchived = false`

### 5.10 Router — error handling

`core/Router.php::dispatch()`:
```php
if (!class_exists($controllerClass)) {
    $this->notFound();
    return;
}
try {
    (new $controllerClass())->{$action}($params);
} catch (\Throwable $e) {
    (new Logger())->error('Unhandled exception: ' . $e->getMessage());
    if (APP_ENV === 'production') {
        http_response_code(500);
        include ROOT_PATH . '/app/Views/errors/500.php';
    } else {
        throw $e;
    }
}
```
Criar `app/Views/errors/500.php` e `app/Views/errors/404.php` com layout simples.

### 5.11 Logger `.htaccess` — do bootstrap, não do runtime

`core/bootstrap.php`: após configurar sessão, verificar e criar `.htaccess` em `storage/logs/`:
```php
$logDir = ROOT_PATH . '/storage/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$htaccess = $logDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Require all denied\n");
}
```
Remover a escrita lazy de `core/Logger.php`.

### 5.12 `Controller::input()` — métodos tipados

Adicionar em `core/Controller.php`:
```php
protected function inputString(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $_GET[$key] ?? $default);
}
protected function inputInt(string $key, int $default = 0): int {
    return (int) ($this->inputString($key) ?: $default);
}
protected function inputEmail(string $key): ?string {
    $v = $this->inputString($key);
    return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
}
```
`input()` e `inputRaw()` marcados como `@deprecated` via docblock. Migrar **todos** os usos existentes nos controllers na Fase 5.

### 5.13 `Core\Session` facade

Novo `core/Session.php`:
```php
class Session {
    public static function get(string $key, mixed $default = null): mixed { ... }
    public static function set(string $key, mixed $value): void { ... }
    public static function user(): array { return $_SESSION['user'] ?? []; }
    public static function tenantId(): int { return (int)($_SESSION['tenant_id'] ?? 0); }
    public static function flash(string $key, string $value): void { ... }
    public static function getFlash(string $key): ?string { ... }
}
```
Migrar todos os `$_SESSION[...]` diretos dos middlewares e controllers para `Session::*`. Facilita testes futuros e troca de storage.

### 5.14 `created_by` exibido nas tarefas

`app/Views/tasks/index.php`: adicionar coluna "Criado por" (`$task['created_by_name']`) na tabela — o JOIN já existe em `Task::findAllWithRelations()`, o dado já chega mas não é exibido.

---

## Migrations — Ordem de Execução

```
database/migrations/
  001_migrate_tenant_initial.php          (existente, renomeado)
  002_migrate_cold_contacts_tenant.php    (existente, renomeado)
  003_verify_tenant_backfill.php          (existente, renomeado)
  004_decode_htmlentities.php             (novo — Fase 1)
  005_pipeline_stages_assign_tenants.php  (novo — Fase 1)
  006_add_login_attempts_table.php        (novo — Fase 2)
  007_add_users_flags.php                 (novo — password_must_change, is_system_admin)
  008_cold_contacts_archival.php          (novo — Fase 5)
  009_backfill_interactions_tenant.php    (novo — Fase 1, rodar após 001)
```

---

## Arquivos Criados / Modificados por Fase

### Fase 1
- `app/Models/ColdContact.php` — tenant isolation
- `app/Models/Interaction.php` — override findById
- `app/Models/PipelineStage.php` — isGlobal + override
- `core/Controller.php` — input() sem htmlspecialchars
- `database/schema.sql` — sync completo
- `database/migrations/004_decode_htmlentities.php` — novo
- `database/migrations/005_pipeline_stages_assign_tenants.php` — novo
- `database/seeders/pipeline_stages_default.php` — novo
- `database/migrations/001-003` — renomeados de `scripts/`
- `.gitignore` — remover `scripts/`, adicionar `database/migrations/*.log`

### Fase 2
- `core/Middleware/RateLimitMiddleware.php` — novo
- `core/Middleware/AuthMiddleware.php` — password_must_change check
- `core/Middleware/CspMiddleware.php` — remover unsafe-inline e jsdelivr de connect-src
- `core/Controller.php` — requireRole 403, redirect() validação
- `core/bootstrap.php` — session.gc_maxlifetime, Logger .htaccess
- `app/Controllers/TaskController.php` — role auth
- `app/Controllers/AuthController.php` — filter_var
- `app/Controllers/UserController.php` — filter_var
- `app/Views/layouts/main.php` — remover style="" inline
- `config/routes.php` — RateLimitMiddleware no POST /login
- `database/migrations/006_add_login_attempts_table.php` — novo
- `database/migrations/007_add_users_flags.php` — novo
- `.htaccess` em `app/`, `core/`, `config/`, `database/`, `tests/` — novos

### Fase 3
- `app/Controllers/DashboardController.php` — count fix, tasks fix
- `app/Models/Task.php` — findUpcomingForUser
- `app/Models/Client.php` — findAllOverdueSalesByClient SQL puro
- `app/Views/layouts/main.php` — notifications polling

### Fase 4
- `app/Controllers/UserController.php` — uploadAvatar, destroyAvatar
- `app/Controllers/TenantController.php` — novo
- `app/Controllers/ProfileController.php` — novo
- `app/Models/Tenant.php` — novo (ou expandir existente)
- `app/Views/admin/users/edit.php` — campo avatar
- `app/Views/admin/tenants/` — novo diretório
- `app/Views/profile/` — novo diretório
- `core/Middleware/SystemAdminMiddleware.php` — novo
- `config/routes.php` — novas rotas
- `public/uploads/.gitkeep` — novo

### Fase 5
- `app/Controllers/ColdContactController.php` — json helper, archive
- `app/Controllers/ClientController.php` — json helper
- `app/Controllers/InteractionController.php` — json helper
- `app/Controllers/PipelineController.php` — json helper
- `app/Controllers/TaskController.php` — json helper, created_by display
- `app/Views/clients/show.php` — extrair partials
- `app/Views/cold-contacts/index.php` — extrair partials
- `app/Views/components/client-sales-table.php` — novo
- `app/Views/components/client-interactions-timeline.php` — novo
- `app/Views/components/client-tasks-list.php` — novo
- `app/Views/components/cold-contacts-table.php` — novo
- `app/Views/components/cold-contacts-filters.php` — novo
- `app/Views/errors/404.php` — novo
- `app/Views/errors/500.php` — novo
- `core/Router.php` — class_exists + try/catch
- `core/Session.php` — novo
- `core/Controller.php` — inputString/inputInt/inputEmail, deprecar input()/inputRaw()
- `core/Logger.php` — remover escrita lazy de .htaccess
- `config/database.php.example` — usar env()
- `tailwind.config.js` — tirar do .gitignore e commitar
- `resources/css/input.css` — tirar do .gitignore e commitar
- `tests/TenantIsolationTest.php` — novo
- `tests/smoke/` — movido de `scripts/smoke/`
- `.github/workflows/test.yml` — novo
- `database/migrations/008_cold_contacts_archival.php` — novo
- `.gitignore` — tailwind.config.js e resources/css/input.css removidos
- `README.md` — nginx .htaccess, PHP 8.2, WhatsApp manual, credenciais

---

## Riscos e Mitigações

| Risco | Mitigação |
|-------|-----------|
| Migration `htmlentities_decode` corromper dados que legítimamente têm `&amp;` | Rodar em banco de staging primeiro; backup antes; a migration verifica `html_entity_decode($v) !== $v` antes de UPDATE |
| `PipelineStage isGlobal = false` quebrar stages existentes | Migration 005 verifica e corrige tenant_id antes de alterar a flag |
| Rate limit bloquear o próprio admin por engano | Whitelist de IP configurável em `.env` (`RATE_LIMIT_WHITELIST=127.0.0.1`) |
| CSP sem `unsafe-inline` quebrar Tailwind utilities inline | Tailwind utility classes são `class=`, não `style=` — não são afetadas por style-src |

---

*Design doc gerado em 2026-04-17. Cobre todos os 40+ itens de `.planning/codebase/CONCERNS.md`.*
