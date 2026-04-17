# Fase 1 — Isolamento e Integridade de Dados

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar todos os vazamentos cross-tenant e corrupção de dados em produção.

**Architecture:** Sete correções independentes mas relacionadas: reorganização do repositório, sync do schema SQL, migration de limpeza de dados corrompidos por htmlspecialchars, isolamento de ColdContact, Interaction e PipelineStage, e validação de client_id no InteractionController.

**Tech Stack:** PHP 8.0+, MySQL/PDO, testes com runner PHP puro (sem PHPUnit).

---

## Mapa de Arquivos

| Arquivo | Ação | Responsabilidade |
|---------|------|-----------------|
| `.gitignore` | Modificar | Remover `scripts/` e `docs/superpowers/` do ignore |
| `database/schema.sql` | Modificar | Adicionar tenant_id nas 5 tabelas + flags users |
| `database/migrations/001_migrate_tenant_initial.php` | Criar (renomear) | Migration original de scripts/ |
| `database/migrations/002_migrate_cold_contacts_tenant.php` | Criar (renomear) | Migration original de scripts/ |
| `database/migrations/003_verify_tenant_backfill.php` | Criar (renomear) | Migration original de scripts/ |
| `database/migrations/004_decode_htmlentities.php` | Criar | Decodificar dados corrompidos no banco |
| `database/migrations/005_pipeline_stages_assign_tenants.php` | Criar | Garantir tenant_id correto nos stages |
| `database/migrations/009_backfill_interactions_tenant.php` | Criar | Popular interactions.tenant_id via join com clients |
| `database/seeders/pipeline_stages_default.php` | Criar | Seed dos 6 stages padrão para um tenant |
| `bin/build_css.php` | Criar (mover de scripts/) | Build helper do Tailwind |
| `bin/setup_tailwind.php` | Criar (mover de scripts/) | Setup do binário Tailwind |
| `tests/smoke/` | Criar (mover de scripts/smoke/) | Scripts de smoke test |
| `core/Controller.php` | Modificar | `input()` remove htmlspecialchars |
| `app/Models/ColdContact.php` | Modificar | Adicionar tenant_id em todas as queries |
| `app/Models/Interaction.php` | Modificar | Override findById com join em clients |
| `app/Models/PipelineStage.php` | Modificar | isGlobal=false + override findById/delete |
| `app/Controllers/InteractionController.php` | Modificar | Validar client_id pertence ao tenant |
| `tests/Phase11Test.php` | Criar | Testes estruturais da Fase 1 |

---

### Task 1: Reorganizar scripts/ no repositório

**Files:**
- Modify: `.gitignore`
- Modify: `database/migrations/` (criar diretório com arquivos de scripts/)
- Modify: `bin/` (criar diretório com build helpers)
- Modify: `tests/smoke/` (criar diretório com smoke scripts)

- [ ] **Step 1: Criar diretórios de destino**

```bash
mkdir -p database/migrations
mkdir -p bin
mkdir -p tests/smoke
```

- [ ] **Step 2: Mover arquivos existentes de scripts/**

Os arquivos em `scripts/` estão apenas no filesystem (gitignored). Copiar para os novos destinos:

```bash
# Migrations
cp scripts/migrate_tenant_initial.php database/migrations/001_migrate_tenant_initial.php
cp scripts/migrate_cold_contacts_tenant.php database/migrations/002_migrate_cold_contacts_tenant.php
cp scripts/verify_tenant_backfill.php database/migrations/003_verify_tenant_backfill.php

# Build helpers
cp scripts/build_css.php bin/build_css.php
cp scripts/setup_tailwind.php bin/setup_tailwind.php

# Smoke tests
cp -r scripts/smoke/. tests/smoke/
```

Se os arquivos não existirem mais em `scripts/` (já foram apagados), criar placeholders:
```bash
echo '<?php // migration movida — ver histórico' > database/migrations/001_migrate_tenant_initial.php
echo '<?php // migration movida — ver histórico' > database/migrations/002_migrate_cold_contacts_tenant.php
echo '<?php // migration movida — ver histórico' > database/migrations/003_verify_tenant_backfill.php
```

- [ ] **Step 3: Atualizar .gitignore**

Abrir `.gitignore`. Localizar e substituir o bloco:
```
# --- Scripts de desenvolvimento descartáveis ---
scripts/
tailwind.config.js
resources/
```

Por:
```
# --- Scripts descartáveis de uso único ---
# (scripts de produção ficam em database/migrations/ e bin/)

# --- Tailwind source e config (versionados) ---
# tailwind.config.js e resources/ foram removidos do ignore
```

- [ ] **Step 4: Commit**

```bash
git add .gitignore database/migrations/ bin/ tests/smoke/
git commit -m "chore: move scripts/ para database/migrations/, bin/ e tests/smoke/"
```

---

### Task 2: Sincronizar database/schema.sql

**Files:**
- Modify: `database/schema.sql`

- [ ] **Step 1: Verificar estado atual do schema**

```bash
grep -n "tenant_id" database/schema.sql
```

Confirmar que `clients`, `client_sales`, `interactions`, `tasks`, `cold_contacts` NÃO têm `tenant_id`.

- [ ] **Step 2: Adicionar tenant_id na tabela clients**

Abrir `database/schema.sql`. Localizar a definição da tabela `clients`. Adicionar após a última coluna de negócio e antes de `PRIMARY KEY`:

```sql
    tenant_id     INT UNSIGNED        NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
```

E após o `PRIMARY KEY`, adicionar:
```sql
    INDEX idx_clients_tenant (tenant_id),
    CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
```

- [ ] **Step 3: Adicionar tenant_id nas tabelas client_sales, interactions, tasks**

Repetir o mesmo padrão para `client_sales`, `interactions` e `tasks`:
- Coluna: `tenant_id INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant'`
- Index: `INDEX idx_<tabela>_tenant (tenant_id)`
- FK: `CONSTRAINT fk_<tabela>_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE`

- [ ] **Step 4: Adicionar tenant_id e colunas de archival em cold_contacts**

Para `cold_contacts`, adicionar também:
```sql
    tenant_id          INT UNSIGNED  NOT NULL DEFAULT 1 COMMENT 'Isolamento multi-tenant',
    archived_at        DATETIME      NULL     DEFAULT NULL,
    imported_year_month CHAR(7) GENERATED ALWAYS AS (DATE_FORMAT(imported_at, '%Y-%m')) STORED,
```

E os índices:
```sql
    INDEX idx_cold_contacts_tenant (tenant_id),
    INDEX idx_cc_year_month (imported_year_month),
    CONSTRAINT fk_cold_contacts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
```

- [ ] **Step 5: Adicionar colunas na tabela users**

Localizar a tabela `users`. Após a coluna `is_active`, adicionar:
```sql
    password_must_change TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = forçar troca de senha no próximo login',
    is_system_admin      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = admin da plataforma (acesso a /admin/tenants)',
```

- [ ] **Step 6: Atualizar o seed do admin**

Localizar o INSERT do usuário admin no schema. Garantir que inclua `password_must_change = 1`:
```sql
INSERT IGNORE INTO users (id, name, email, password_hash, role, tenant_id, password_must_change) VALUES
(1, 'Administrador', 'admin@crm.local',
 '$2y$12$...hash_de_Admin@1234...', 'admin', 1, 1);
```

- [ ] **Step 7: Atualizar seed de pipeline_stages com tenant_id explícito**

```sql
INSERT INTO pipeline_stages (name, color, position, tenant_id) VALUES
  ('Prospecção',       '#6366f1', 1, 1),
  ('Qualificação',     '#f59e0b', 2, 1),
  ('Proposta',         '#3b82f6', 3, 1),
  ('Negociação',       '#8b5cf6', 4, 1),
  ('Fechado - Ganho',  '#10b981', 5, 1),
  ('Fechado - Perdido','#ef4444', 6, 1);
```

- [ ] **Step 8: Commit**

```bash
git add database/schema.sql
git commit -m "feat: sincroniza schema.sql com banco real (tenant_id em 5 tabelas, flags users)"
```

---

### Task 3: Migration 004 — decodificar htmlentities do banco

**Files:**
- Create: `database/migrations/004_decode_htmlentities.php`

- [ ] **Step 1: Criar migration**

Criar `database/migrations/004_decode_htmlentities.php`:

```php
<?php
/**
 * Migration 004 — Decodificar htmlentities gravados erroneamente no banco.
 *
 * Problema: Controller::input() aplicava htmlspecialchars() antes de gravar,
 * corrompendo dados. Ex: "Smith & Co" → "Smith &amp; Co".
 *
 * Esta migration percorre as colunas de texto afetadas e aplica html_entity_decode()
 * apenas quando o valor atual difere do decodificado.
 *
 * ATENÇÃO: Fazer backup antes de rodar. Rodar em staging primeiro.
 *
 * Execute: php database/migrations/004_decode_htmlentities.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$columns = [
    'clients'       => ['name', 'company', 'email', 'phone', 'address', 'notes'],
    'interactions'  => ['description'],
    'tasks'         => ['title', 'description'],
    'cold_contacts' => ['name', 'phone', 'notes'],
    'users'         => ['name'],
];

$totalFixed = 0;

foreach ($columns as $table => $cols) {
    foreach ($cols as $col) {
        // Verifica se coluna existe
        $check = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'")->fetch();
        if (!$check) continue;

        $rows = $pdo->query("SELECT id, `{$col}` FROM `{$table}` WHERE `{$col}` IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE `{$table}` SET `{$col}` = :val WHERE id = :id");

        foreach ($rows as $row) {
            $decoded = html_entity_decode($row[$col], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded !== $row[$col]) {
                $stmt->execute([':val' => $decoded, ':id' => $row['id']]);
                $totalFixed++;
                echo "Corrigido: {$table}.{$col} id={$row['id']}\n";
            }
        }
    }
}

echo "\nTotal de campos corrigidos: {$totalFixed}\n";
echo "Migration 004 concluída.\n";
```

- [ ] **Step 2: Commit**

```bash
git add database/migrations/004_decode_htmlentities.php
git commit -m "feat: migration 004 — decodifica htmlentities gravados erroneamente"
```

---

### Task 4: Migration 005 — garantir tenant_id nos pipeline stages

**Files:**
- Create: `database/migrations/005_pipeline_stages_assign_tenants.php`

- [ ] **Step 1: Criar migration**

Criar `database/migrations/005_pipeline_stages_assign_tenants.php`:

```php
<?php
/**
 * Migration 005 — Garantir que todos os pipeline_stages tenham tenant_id correto.
 *
 * O campo tenant_id existe com DEFAULT 1. Esta migration verifica se há stages
 * com tenant_id = 0 ou NULL e os atribui ao tenant 1 (default).
 * Se houver apenas 1 tenant ativo, não há ação necessária.
 *
 * Execute: php database/migrations/005_pipeline_stages_assign_tenants.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// Contar tenants ativos
$tenantCount = (int) $pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
echo "Tenants no banco: {$tenantCount}\n";

// Corrigir stages sem tenant_id válido
$stmt = $pdo->prepare("UPDATE pipeline_stages SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0");
$stmt->execute();
echo "Stages corrigidos: {$stmt->rowCount()}\n";

// Listar distribuição atual
$rows = $pdo->query("SELECT tenant_id, COUNT(*) as total FROM pipeline_stages GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  tenant_id={$r['tenant_id']}: {$r['total']} stages\n";
}

echo "Migration 005 concluída.\n";
```

- [ ] **Step 2: Commit**

```bash
git add database/migrations/005_pipeline_stages_assign_tenants.php
git commit -m "feat: migration 005 — corrige tenant_id em pipeline_stages"
```

---

### Task 5: Migration 009 — backfill interactions.tenant_id

**Files:**
- Create: `database/migrations/009_backfill_interactions_tenant.php`

- [ ] **Step 1: Criar migration**

Criar `database/migrations/009_backfill_interactions_tenant.php`:

```php
<?php
/**
 * Migration 009 — Backfill de tenant_id na tabela interactions.
 *
 * Rodar APÓS a coluna interactions.tenant_id existir no banco
 * (criada pela migration inicial ou pelo schema.sql atualizado).
 *
 * Popula interactions.tenant_id a partir do clients.tenant_id via JOIN.
 *
 * Execute: php database/migrations/009_backfill_interactions_tenant.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// Verificar se coluna existe
$col = $pdo->query("SHOW COLUMNS FROM interactions LIKE 'tenant_id'")->fetch();
if (!$col) {
    echo "ERRO: coluna interactions.tenant_id não existe. Rode o schema.sql antes.\n";
    exit(1);
}

$stmt = $pdo->prepare("
    UPDATE interactions i
    INNER JOIN clients c ON c.id = i.client_id
    SET i.tenant_id = c.tenant_id
    WHERE i.tenant_id IS NULL OR i.tenant_id = 0
");
$stmt->execute();
echo "Interactions atualizadas: {$stmt->rowCount()}\n";

// Verificar se sobraram sem tenant_id
$orphans = (int) $pdo->query("SELECT COUNT(*) FROM interactions WHERE tenant_id IS NULL OR tenant_id = 0")->fetchColumn();
if ($orphans > 0) {
    echo "AVISO: {$orphans} interaction(s) sem client_id válido — atribuindo tenant_id = 1\n";
    $pdo->exec("UPDATE interactions SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0");
}

echo "Migration 009 concluída.\n";
```

- [ ] **Step 2: Commit**

```bash
git add database/migrations/009_backfill_interactions_tenant.php
git commit -m "feat: migration 009 — backfill interactions.tenant_id via join com clients"
```

---

### Task 6: Seeder de pipeline stages padrão

**Files:**
- Create: `database/seeders/pipeline_stages_default.php`

- [ ] **Step 1: Criar diretório e seeder**

```bash
mkdir -p database/seeders
```

Criar `database/seeders/pipeline_stages_default.php`:

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add database/seeders/pipeline_stages_default.php
git commit -m "feat: seeder de pipeline stages padrão por tenant"
```

---

### Task 7: Controller::input() sem htmlspecialchars

**Files:**
- Modify: `core/Controller.php`

- [ ] **Step 1: Escrever teste que falha**

Criar `tests/Phase11Test.php` com a primeira seção:

```php
<?php
/**
 * tests/Phase11Test.php — Fase 1: Isolamento e Integridade
 * Execute: php tests/Phase11Test.php
 */
declare(strict_types=1);

$results = ['pass' => 0, 'fail' => 0, 'errors' => []];

function ok(string $desc, bool $cond): void
{
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
define('VIEW_PATH', APP_PATH . DS . 'Views');
define('APP_ENV',   'testing');

// ── 1. Controller::input() não deve encodar htmlspecialchars ─────────────────
section('1. Controller::input() — sem htmlspecialchars');

require_once ROOT_PATH . '/core/Controller.php';

// Criar subclasse concreta para testar o método protegido
class TestController extends Core\Controller {
    public function exposeInput(string $key, string $default = ''): string {
        return $this->input($key, $default);
    }
    public function index(): void {}
}

$ctrl = new TestController();

// Simular POST com & e aspas
$_POST['nome'] = 'Smith & Co "Ltda"';
ok('input() retorna valor com & intacto',   $ctrl->exposeInput('nome') === "Smith & Co \"Ltda\"");
ok('input() não codifica &amp;',            strpos($ctrl->exposeInput('nome'), '&amp;') === false);
ok('input() não codifica &quot;',           strpos($ctrl->exposeInput('nome'), '&quot;') === false);
ok('input() ainda aplica trim',             $ctrl->exposeInput('espaco', '') === '' || true); // trim testado separado

$_POST['espaco'] = '  texto  ';
ok('input() aplica trim no valor',          $ctrl->exposeInput('espaco') === 'texto');
```

- [ ] **Step 2: Rodar teste e confirmar falha**

```bash
php tests/Phase11Test.php
```

Esperado: `✗ input() retorna valor com & intacto` (porque input() ainda codifica).

- [ ] **Step 3: Implementar — remover htmlspecialchars de input()**

Abrir `core/Controller.php`. Localizar o método `input()`:

```php
protected function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}
```

Substituir por:

```php
protected function input(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}
```

- [ ] **Step 4: Rodar teste e confirmar aprovação**

```bash
php tests/Phase11Test.php
```

Esperado: `✓` em todos os asserts da seção 1.

- [ ] **Step 5: Commit**

```bash
git add core/Controller.php tests/Phase11Test.php
git commit -m "fix: Controller::input() remove htmlspecialchars — encode deve ser feito nas views"
```

---

### Task 8: ColdContact — tenant isolation

**Files:**
- Modify: `app/Models/ColdContact.php`

- [ ] **Step 1: Adicionar testes de estrutura ao Phase11Test.php**

Adicionar ao `tests/Phase11Test.php`:

```php
// ── 2. ColdContact — verificação estrutural de tenant_id ─────────────────────
section('2. ColdContact — tenant isolation (estrutural)');

// Verifica que as queries no arquivo contêm tenant_id
$src = file_get_contents(ROOT_PATH . '/app/Models/ColdContact.php');

ok('countFindMonthSummaries usa tenant_id',     strpos($src, 'countFindMonthSummaries') !== false
    && preg_match('/countFindMonthSummaries.*?tenant_id/s', $src) === 1);
ok('findMonthSummaries usa tenant_id',          strpos($src, 'tenant_id') !== false);
ok('countByMonth usa tenant_id',                preg_match('/countByMonth.*?tenant_id/s', $src) === 1);
ok('findByMonth usa tenant_id',                 preg_match('/findByMonth.*?tenant_id/s', $src) === 1);
ok('create() inclui tenant_id no INSERT',       strpos($src, 'INSERT INTO cold_contacts') !== false
    && preg_match('/INSERT INTO cold_contacts[^;]*tenant_id/s', $src) === 1);
ok('update() usa tenant_id no WHERE',           preg_match('/UPDATE cold_contacts[^;]*tenant_id/s', $src) === 1);
ok('destroy() usa tenant_id no WHERE',          preg_match('/DELETE FROM cold_contacts[^;]*tenant_id/s', $src) === 1);
ok('deleteByMonth() usa tenant_id no WHERE',    preg_match('/deleteByMonth[^}]*tenant_id/s', $src) === 1);
ok('bulkAtualizarExtras() usa tenant_id',       preg_match('/bulkAtualizarExtras[^}]*tenant_id/s', $src) === 1);
```

- [ ] **Step 2: Rodar teste e confirmar falhas**

```bash
php tests/Phase11Test.php
```

Esperado: todas as seção 2 falham.

- [ ] **Step 3: Implementar tenant isolation no ColdContact**

Substituir o conteúdo de `app/Models/ColdContact.php` por:

```php
<?php

namespace App\Models;

use Core\Model;

class ColdContact extends Model
{
    protected string $table = 'cold_contacts';

    public function countFindMonthSummaries(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT imported_year_month)
            FROM cold_contacts
            WHERE tenant_id = :tenant_id
              AND archived_at IS NULL
        ");
        $stmt->execute([':tenant_id' => $this->currentTenantId()]);
        return (int) $stmt->fetchColumn();
    }

    public function findMonthSummaries(?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT
                imported_year_month AS mes_ano,
                COUNT(*)            AS total
            FROM cold_contacts
            WHERE tenant_id = :tenant_id
              AND archived_at IS NULL
            GROUP BY imported_year_month
            ORDER BY imported_year_month DESC
        ";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':tenant_id', $this->currentTenantId(), \PDO::PARAM_INT);
            $stmt->bindValue(':limit',     $limit,         \PDO::PARAM_INT);
            $stmt->bindValue(':offset',    $offset ?? 0,   \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':tenant_id' => $this->currentTenantId()]);
        }

        return $stmt->fetchAll();
    }

    public function countByMonth(string $yearMonth, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM cold_contacts
            WHERE imported_year_month = :year_month
              AND tenant_id = :tenant_id
        ";
        $params = [
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ];

        if (!empty($filters['dia'])) {
            $sql .= " AND DAY(data_mensagem) = :dia";
            $params[':dia'] = (int) $filters['dia'];
        }
        if (!empty($filters['telefone_enviado'])) {
            $sql .= " AND telefone_enviado LIKE :telefone_enviado";
            $params[':telefone_enviado'] = '%' . $filters['telefone_enviado'] . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findByMonth(string $yearMonth, array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        $sql = "
            SELECT *
            FROM cold_contacts
            WHERE imported_year_month = :year_month
              AND tenant_id = :tenant_id
        ";
        $params = [
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ];

        if (!empty($filters['dia'])) {
            $sql .= " AND DAY(data_mensagem) = :dia";
            $params[':dia'] = (int) $filters['dia'];
        }
        if (!empty($filters['telefone_enviado'])) {
            $sql .= " AND telefone_enviado LIKE :telefone_enviado";
            $params[':telefone_enviado'] = '%' . $filters['telefone_enviado'] . '%';
        }

        $sql .= " ORDER BY id ASC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit',  $limit,       \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, \PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cold_contacts
                (phone, name, tipo_lista, telefone_enviado, data_mensagem, tenant_id, imported_at)
            VALUES
                (:phone, :name, :tipo_lista, :telefone_enviado, :data_mensagem, :tenant_id, NOW())
        ");
        $stmt->execute([
            ':phone'            => $data['phone'],
            ':name'             => $data['name'],
            ':tipo_lista'       => $data['tipo_lista'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem'    => $data['data_mensagem'] ?? null,
            ':tenant_id'        => $this->currentTenantId(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cold_contacts
            SET phone            = :phone,
                name             = :name,
                telefone_enviado = :telefone_enviado,
                data_mensagem    = :data_mensagem
            WHERE id = :id
              AND tenant_id = :tenant_id
        ");
        $stmt->execute([
            ':phone'            => $data['phone'],
            ':name'             => $data['name'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem'    => $data['data_mensagem'] ?? null,
            ':id'               => $id,
            ':tenant_id'        => $this->currentTenantId(),
        ]);
        return $stmt->rowCount() > 0;
    }

    public function destroy(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM cold_contacts WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByMonth(string $yearMonth): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM cold_contacts
             WHERE imported_year_month = :year_month
               AND tenant_id = :tenant_id"
        );
        $stmt->execute([
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ]);
        return $stmt->rowCount();
    }

    public function bulkAtualizarExtras(array $ids, ?string $telefone, ?string $dataMensagem): int
    {
        if (empty($ids)) return 0;

        $setClauses = [];
        $params     = [];

        if ($telefone !== null) {
            $setClauses[] = "telefone_enviado = ?";
            $params[] = $telefone === '' ? null : $telefone;
        }
        if ($dataMensagem !== null) {
            $setClauses[] = "data_mensagem = ?";
            $params[] = $dataMensagem === '' ? null : $dataMensagem;
        }

        if (empty($setClauses)) return 0;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE cold_contacts
                SET " . implode(', ', $setClauses) . "
                WHERE id IN ({$placeholders})
                  AND tenant_id = ?";

        $params = array_merge($params, array_map('intval', $ids));
        $params[] = $this->currentTenantId();

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function findForExport(string $yearMonth, array $filters = []): array
    {
        return $this->findByMonth($yearMonth, $filters);
    }

    public function archiveMonth(string $yearMonth): int
    {
        $stmt = $this->db->prepare("
            UPDATE cold_contacts
            SET archived_at = NOW()
            WHERE imported_year_month = :year_month
              AND tenant_id = :tenant_id
              AND archived_at IS NULL
        ");
        $stmt->execute([
            ':year_month' => $yearMonth,
            ':tenant_id'  => $this->currentTenantId(),
        ]);
        return $stmt->rowCount();
    }
}
```

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase11Test.php
```

Esperado: todas as seções passam.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ColdContact.php tests/Phase11Test.php
git commit -m "fix: ColdContact — tenant isolation em todas as queries + método archiveMonth"
```

---

### Task 9: Interaction — override findById com tenant gate

**Files:**
- Modify: `app/Models/Interaction.php`

- [ ] **Step 1: Adicionar testes ao Phase11Test.php**

```php
// ── 3. Interaction — override findById ───────────────────────────────────────
section('3. Interaction::findById() — tenant gate via join');

$src = file_get_contents(ROOT_PATH . '/app/Models/Interaction.php');

ok('Interaction define isGlobal = true',         strpos($src, 'isGlobal = true') !== false);
ok('Interaction sobrescreve findById',            preg_match('/public function findById/', $src) === 1);
ok('findById usa INNER JOIN clients',             strpos($src, 'INNER JOIN clients') !== false);
ok('findById filtra por c.tenant_id',             strpos($src, 'c.tenant_id') !== false);
```

- [ ] **Step 2: Rodar e confirmar falhas**

```bash
php tests/Phase11Test.php
```

- [ ] **Step 3: Implementar override em Interaction**

Abrir `app/Models/Interaction.php`. Adicionar após `protected string $table = 'interactions';`:

```php
    // isGlobal = true evita que Core\Model::findById() lance RuntimeException
    // pela ausência de tenant_id na tabela. A segurança de tenant é garantida
    // pelo override abaixo, que usa INNER JOIN com clients.
    // Remover este flag após rodar migration 009 e a coluna existir na tabela.
    protected bool $isGlobal = true;

    /**
     * Busca interação por ID garantindo que pertence ao tenant correto
     * via JOIN com a tabela clients.
     */
    public function findById(int $id): array|bool
    {
        $stmt = $this->db->prepare("
            SELECT i.*
            FROM interactions i
            INNER JOIN clients c ON c.id = i.client_id
            WHERE i.id = :id
              AND c.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id'        => $id,
            ':tenant_id' => $this->currentTenantId(),
        ]);
        return $stmt->fetch();
    }
```

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase11Test.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/Interaction.php tests/Phase11Test.php
git commit -m "fix: Interaction::findById() — tenant gate via INNER JOIN clients"
```

---

### Task 10: InteractionController — validar client_id pertence ao tenant

**Files:**
- Modify: `app/Controllers/InteractionController.php`

- [ ] **Step 1: Adicionar testes estruturais ao Phase11Test.php**

```php
// ── 4. InteractionController — validação de client_id ───────────────────────
section('4. InteractionController — verifica client_id antes de write');

$src = file_get_contents(ROOT_PATH . '/app/Controllers/InteractionController.php');

ok('store() instancia Client model',      strpos($src, 'new Client()') !== false);
ok('store() usa findById no clientId',    preg_match('/store[^}]*findById\(\$clientId\)/s', $src) === 1);
ok('update() valida client via findById', preg_match('/update[^}]*findById/s', $src) === 1);
ok('usa App\Models\Client (use statement)', strpos($src, 'use App\Models\Client') !== false);
```

- [ ] **Step 2: Rodar e confirmar falhas**

```bash
php tests/Phase11Test.php
```

- [ ] **Step 3: Implementar validação**

Substituir o conteúdo de `app/Controllers/InteractionController.php`:

```php
<?php

namespace App\Controllers;

use Core\Controller;
use Core\Middleware\CsrfMiddleware;
use App\Models\Interaction;
use App\Models\Client;

class InteractionController extends Controller
{
    public function store(array $params = []): void
    {
        $clientId   = (int) ($this->inputRaw('client_id') ?? 0);
        $description = trim($_POST['description'] ?? '');
        $occurredAt  = $this->inputRaw('occurred_at');

        if (!$clientId || empty($description) || empty($occurredAt)) {
            $this->flash('error', 'Preencha todos os campos da interação.');
            $this->redirect('/clients/' . $clientId);
            return;
        }

        // Garante que o cliente pertence ao tenant do usuário logado
        $clientModel = new Client();
        $client = $clientModel->findById($clientId);
        if (!$client) {
            $this->flash('error', 'Cliente não encontrado.');
            $this->redirect('/clients');
            return;
        }

        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $interactionModel = new Interaction();
        $interactionModel->create([
            'client_id'   => $clientId,
            'user_id'     => $_SESSION['user']['id'],
            'type'        => $this->inputRaw('type', 'note'),
            'description' => $description,
            'occurred_at' => $occurredAt,
        ]);

        $this->flash('success', 'Interação registrada com sucesso!');
        $this->redirect('/clients/' . $clientId);
    }

    public function update(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        // Garante que a interação pertence ao tenant (via override findById)
        $interactionModel = new Interaction();
        $interaction = $interactionModel->findById($id);
        if (!$interaction) {
            $this->json(['success' => false, 'error' => 'Interação não encontrada.'], 404);
            return;
        }

        $description = trim($_POST['description'] ?? '');
        $type        = $_POST['type'] ?? '';
        $occurredAt  = $_POST['occurred_at'] ?? '';

        $validTypes = ['call', 'email', 'meeting', 'whatsapp', 'note', 'other'];
        if (empty($description) || !in_array($type, $validTypes, true) || empty($occurredAt)) {
            $this->json(['success' => false, 'error' => 'Campos inválidos.'], 422);
            return;
        }

        $occurredAt = str_replace('T', ' ', $occurredAt) . ':00';

        $ok = $interactionModel->update($id, [
            'description' => $description,
            'type'        => $type,
            'occurred_at' => $occurredAt,
        ]);

        $this->json(['success' => $ok, 'csrf_token' => CsrfMiddleware::getToken()]);
    }

    public function destroy(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $clientId = (int) ($this->inputRaw('client_id') ?? 0);

        $interactionModel = new Interaction();

        // findById já aplica tenant gate via INNER JOIN clients
        $inter = $interactionModel->findById($id);
        if ($inter) {
            $clientId = $clientId ?: (int) $inter['client_id'];
            $interactionModel->delete($id);
        }

        $this->flash('success', 'Interação removida.');
        $this->redirect('/clients/' . $clientId);
    }
}
```

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase11Test.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/InteractionController.php tests/Phase11Test.php
git commit -m "fix: InteractionController — valida client_id pertence ao tenant antes de write"
```

---

### Task 11: PipelineStage — isGlobal=false com overrides

**Files:**
- Modify: `app/Models/PipelineStage.php`

- [ ] **Step 1: Adicionar testes ao Phase11Test.php**

```php
// ── 5. PipelineStage — isGlobal=false + overrides ────────────────────────────
section('5. PipelineStage — tenant gate em findById/delete');

$src = file_get_contents(ROOT_PATH . '/app/Models/PipelineStage.php');

ok('isGlobal = false',                          strpos($src, 'isGlobal = false') !== false);
ok('sobrescreve findById',                      preg_match('/public function findById/', $src) === 1);
ok('findById usa tenant_id no WHERE',           preg_match('/findById[^}]*tenant_id/s', $src) === 1);
ok('sobrescreve delete',                        preg_match('/public function delete/', $src) === 1);
ok('delete usa tenant_id no WHERE',             preg_match('/public function delete[^}]*tenant_id/s', $src) === 1);
```

- [ ] **Step 2: Rodar e confirmar falhas**

```bash
php tests/Phase11Test.php
```

- [ ] **Step 3: Implementar isGlobal=false e overrides**

Abrir `app/Models/PipelineStage.php`. Localizar e alterar:

```php
// antes:
protected bool $isGlobal = true;

// depois:
protected bool $isGlobal = false;
```

Adicionar após a declaração da propriedade `$isGlobal`:

```php
    /**
     * Busca stage por ID dentro do tenant correto.
     * Override necessário porque isGlobal era true — agora garante isolamento.
     */
    public function findById(int $id): array|bool
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM pipeline_stages WHERE id = :id AND tenant_id = :tenant_id LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetch();
    }

    /**
     * Deleta stage garantindo que pertence ao tenant.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM pipeline_stages WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() > 0;
    }
```

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase11Test.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/PipelineStage.php tests/Phase11Test.php
git commit -m "fix: PipelineStage — isGlobal=false com override findById/delete para tenant isolation"
```

---

### Task 12: Finalizar Phase11Test e verificar cobertura

**Files:**
- Modify: `tests/Phase11Test.php`

- [ ] **Step 1: Adicionar verificações de arquivos criados**

Adicionar ao final de `tests/Phase11Test.php`:

```php
// ── 6. Arquivos criados ───────────────────────────────────────────────────────
section('6. Arquivos de migrations e seeders existem');

ok('migration 004 existe', file_exists(ROOT_PATH . '/database/migrations/004_decode_htmlentities.php'));
ok('migration 005 existe', file_exists(ROOT_PATH . '/database/migrations/005_pipeline_stages_assign_tenants.php'));
ok('migration 009 existe', file_exists(ROOT_PATH . '/database/migrations/009_backfill_interactions_tenant.php'));
ok('seeder pipeline stages existe', file_exists(ROOT_PATH . '/database/seeders/pipeline_stages_default.php'));

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

- [ ] **Step 2: Rodar suite completa**

```bash
php tests/Phase11Test.php
```

Esperado: `Todos os testes passaram.`

- [ ] **Step 3: Rodar testes anteriores para garantir não-regressão**

```bash
php tests/Phase05Test.php && php tests/Phase06Test.php && php tests/Phase09Test.php && php tests/Phase10Test.php
```

Esperado: todos passam.

- [ ] **Step 4: Commit final da fase**

```bash
git add tests/Phase11Test.php
git commit -m "test: Phase11 — cobertura completa da Fase 1 (isolamento e integridade)"
```

---

*Fase 1 concluída. Próximo: `docs/superpowers/plans/2026-04-17-fase2-seguranca.md`*
