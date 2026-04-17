# Fase 3 — Performance

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar queries desnecessariamente pesadas no path crítico do dashboard e melhorar o polling de notificações.

**Architecture:** Cinco fixes cirúrgicos e independentes. Nenhum muda a interface pública dos models — apenas substitui implementações ineficientes. Tasks 1 e 2 são prioridade (afetam cada carregamento do dashboard).

**Tech Stack:** PHP 8.0+, MySQL/PDO, Vanilla JS.

---

## Mapa de Arquivos

| Arquivo | Ação | Responsabilidade |
|---------|------|-----------------|
| `app/Controllers/DashboardController.php` | Modificar | Usar countAllWithRelations e findUpcomingForUser |
| `app/Models/Task.php` | Modificar | Adicionar findUpcomingForUser |
| `app/Models/Client.php` | Modificar | findAllOverdueSalesByClient — SQL puro + cache |
| `app/Views/layouts/main.php` | Modificar | Notifications polling com visibilityState + backoff |
| `tests/Phase13Test.php` | Criar | Testes estruturais da Fase 3 |

---

### Task 1: Dashboard — trocar count() por countAllWithRelations()

**Files:**
- Modify: `app/Controllers/DashboardController.php`

- [ ] **Step 1: Criar Phase13Test.php**

```php
<?php
/**
 * tests/Phase13Test.php — Fase 3: Performance
 * Execute: php tests/Phase13Test.php
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

// ── 1. DashboardController — count fix ───────────────────────────────────────
section('1. DashboardController — count via countAllWithRelations');
$src = file_get_contents(ROOT_PATH . '/app/Controllers/DashboardController.php');
ok('não usa count($clientModel->findAllWithRelations())',
    strpos($src, 'count($clientModel->findAllWithRelations())') === false);
ok('usa countAllWithRelations',
    strpos($src, 'countAllWithRelations') !== false);
```

- [ ] **Step 2: Rodar e confirmar falha**

```bash
php tests/Phase13Test.php
```

- [ ] **Step 3: Corrigir DashboardController**

Abrir `app/Controllers/DashboardController.php`. Localizar:
```php
$totalClients = count($clientModel->findAllWithRelations());
```

Substituir por:
```php
$totalClients = $clientModel->countAllWithRelations([]);
```

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase13Test.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/DashboardController.php tests/Phase13Test.php
git commit -m "perf: dashboard usa countAllWithRelations() em vez de count(findAll())"
```

---

### Task 2: Dashboard — upcoming tasks filtrada no banco

**Files:**
- Modify: `app/Models/Task.php`
- Modify: `app/Controllers/DashboardController.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 2. Task::findUpcomingForUser() ───────────────────────────────────────────
section('2. Task::findUpcomingForUser()');
$src = file_get_contents(ROOT_PATH . '/app/Models/Task.php');
ok('método findUpcomingForUser existe',          strpos($src, 'findUpcomingForUser') !== false);
ok('usa BETWEEN NOW() AND DATE_ADD',             strpos($src, 'BETWEEN NOW()') !== false
                                               || strpos($src, 'DATE_ADD(NOW()') !== false);
ok('tem LIMIT 20',                               strpos($src, 'LIMIT 20') !== false || strpos($src, ':limit') !== false);

$dsrc = file_get_contents(ROOT_PATH . '/app/Controllers/DashboardController.php');
ok('dashboard não usa array_filter para upcoming', strpos($dsrc, 'array_filter') === false
    || strpos($dsrc, 'strtotime') === false);
ok('dashboard usa findUpcomingForUser',           strpos($dsrc, 'findUpcomingForUser') !== false);
```

- [ ] **Step 2: Implementar Task::findUpcomingForUser()**

Abrir `app/Models/Task.php`. Adicionar após o método `findAllWithRelations()`:

```php
    /**
     * Retorna tarefas pendentes com vencimento nos próximos N dias para um usuário.
     * Busca feita no banco — sem filtragem em PHP.
     *
     * @param  int  $userId
     * @param  int  $days   Janela em dias a partir de hoje (default 7)
     * @return array
     */
    public function findUpcomingForUser(int $userId, int $days = 7): array
    {
        $tenantId = $this->currentTenantId();
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                c.name  AS client_name,
                u.name  AS assigned_name,
                cb.name AS created_by_name
            FROM tasks t
            LEFT JOIN clients c  ON c.id  = t.client_id  AND c.tenant_id = :tenant_id_c
            LEFT JOIN users   u  ON u.id  = t.assigned_to
            LEFT JOIN users  cb  ON cb.id = t.created_by
            WHERE t.assigned_to = :user_id
              AND t.status = 'pending'
              AND t.tenant_id = :tenant_id
              AND t.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
            ORDER BY t.due_date ASC
            LIMIT 20
        ");
        $stmt->bindValue(':user_id',    $userId,   \PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id',  $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id_c', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue(':days',        $days,     \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
```

- [ ] **Step 3: Atualizar DashboardController**

Abrir `app/Controllers/DashboardController.php`. Localizar o bloco:
```php
$upcomingTasks = $taskModel->findAllWithRelations([
    'status' => 'pending',
    'assigned_to' => $_SESSION['user']['id'],
]);
$nextWeek = time() + (7 * 24 * 3600);
$upcomingTasks = array_filter($upcomingTasks, fn($t) => strtotime($t['due_date']) <= $nextWeek);
```

Substituir por:
```php
$upcomingTasks = $taskModel->findUpcomingForUser((int) $_SESSION['user']['id']);
```

Atualizar o `render()` para passar `array_values($upcomingTasks)` → já é um array indexado, sem necessidade de `array_values`:
```php
'upcomingTasks' => $upcomingTasks,
```

- [ ] **Step 4: Rodar testes**

```bash
php tests/Phase13Test.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/Task.php app/Controllers/DashboardController.php tests/Phase13Test.php
git commit -m "perf: Task::findUpcomingForUser() filtra upcoming no banco (sem array_filter PHP)"
```

---

### Task 3: Client::findAllOverdueSalesByClient() — SQL puro + cache

**Files:**
- Modify: `app/Models/Client.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 3. Client::findAllOverdueSalesByClient() — SQL puro ──────────────────────
section('3. Client::findAllOverdueSalesByClient() — SQL puro');
$src = file_get_contents(ROOT_PATH . '/app/Models/Client.php');
ok('findAllOverdueSalesByClient usa SELECT DISTINCT',
    preg_match('/findAllOverdueSalesByClient[^}]*SELECT DISTINCT/s', $src) === 1);
ok('não usa foreach para calcular atraso',
    preg_match('/findAllOverdueSalesByClient[^}]*foreach[^}]*isPaid/s', $src) === 0);
ok('usa cache estático',
    preg_match('/findAllOverdueSalesByClient[^}]*static \$cache/s', $src) === 1);
```

- [ ] **Step 2: Substituir implementação**

Abrir `app/Models/Client.php`. Substituir o método `findAllOverdueSalesByClient()` por:

```php
    public function findAllOverdueSalesByClient(): array
    {
        static $cache = [];
        $tenantId = $this->currentTenantId();

        if (isset($cache[$tenantId])) {
            return $cache[$tenantId];
        }

        // Só verifica atraso a partir do dia de corte configurado
        $diaHoje = (int) (new \DateTimeImmutable('now'))->format('j');
        if ($diaHoje < $this->getTenantCutoffDay()) {
            return $cache[$tenantId] = [];
        }

        $ref      = $this->computeRefMonth();
        $refStart = sprintf('%04d-%02d-01', $ref['ano'], $ref['mes']);

        $stmt = $this->db->prepare("
            SELECT DISTINCT cs.client_id
            FROM client_sales cs
            INNER JOIN clients c ON c.id = cs.client_id
            WHERE c.tenant_id = :tenant_id
              AND c.is_active  = 1
              AND (cs.paid_at IS NULL OR cs.paid_at < :ref_start)
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':ref_start' => $refStart]);

        $cache[$tenantId] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $cache[$tenantId];
    }
```

- [ ] **Step 3: Rodar testes**

```bash
php tests/Phase13Test.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/Client.php tests/Phase13Test.php
git commit -m "perf: findAllOverdueSalesByClient() usa SQL puro com cache estático por request"
```

---

### Task 4: Notifications polling — visibilityState, backoff, BroadcastChannel

**Files:**
- Modify: `app/Views/layouts/main.php`

- [ ] **Step 1: Adicionar testes**

```php
// ── 4. Notifications polling ─────────────────────────────────────────────────
section('4. Notifications — visibilityState + backoff');
$src = file_get_contents(ROOT_PATH . '/app/Views/layouts/main.php');
ok('usa visibilityState',         strpos($src, 'visibilityState') !== false);
ok('usa visibilitychange event',  strpos($src, 'visibilitychange') !== false);
ok('tem backoff exponencial',     strpos($src, 'backoff') !== false || strpos($src, 'Backoff') !== false);
ok('usa BroadcastChannel',        strpos($src, 'BroadcastChannel') !== false);
```

- [ ] **Step 2: Localizar bloco de notifications**

Abrir `app/Views/layouts/main.php`. Localizar o bloco JavaScript de `checkNotifications` / `setInterval`. Normalmente começa com:
```javascript
function checkNotifications() {
```
ou
```javascript
setInterval(checkNotifications, 60000);
```

- [ ] **Step 3: Substituir bloco de polling**

Substituir o bloco existente de notificações por:

```javascript
(function () {
    const bc = new BroadcastChannel('crm_notif');
    let backoffFactor = 1;
    let intervalId = null;

    function renderNotifications(data) {
        // lógica original de renderização — manter como estava
        // ex: atualizar badge de contador, lista de alertas, etc.
        if (typeof window.handleNotificationData === 'function') {
            window.handleNotificationData(data);
        }
    }

    async function fetchNotifications() {
        if (document.visibilityState === 'hidden') return;
        try {
            const res = await fetch('<?= APP_URL ?>/api/tasks/upcoming', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
                }
            });
            if (!res.ok) {
                backoffFactor = Math.min(backoffFactor * 2, 8);
                return;
            }
            backoffFactor = 1;
            const data = await res.json();
            renderNotifications(data);
            bc.postMessage(data); // compartilha com outras abas
        } catch {
            backoffFactor = Math.min(backoffFactor * 2, 8);
        }
        // Reagendar com backoff
        clearInterval(intervalId);
        intervalId = setInterval(fetchNotifications, 60000 * backoffFactor);
    }

    // Retomar polling quando aba ficar visível
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            backoffFactor = 1;
            fetchNotifications();
        }
    });

    // Receber dados de outra aba (evitar fetch duplicado)
    bc.onmessage = (e) => renderNotifications(e.data);

    // Iniciar
    intervalId = setInterval(fetchNotifications, 60000);
    fetchNotifications(); // chamada inicial
})();
```

> **Importante:** verificar se o código original de `renderNotifications` (atualizar o badge, preencher lista de alertas) está preservado dentro de `window.handleNotificationData`. Se não, mover a lógica de render original para dentro de `renderNotifications()` acima.

- [ ] **Step 4: Garantir meta tag CSRF no layout**

Verificar se `app/Views/layouts/main.php` já tem:
```html
<meta name="csrf-token" content="<?= htmlspecialchars(Core\Middleware\CsrfMiddleware::getToken(), ENT_QUOTES, 'UTF-8') ?>">
```

Se não existir, adicionar no `<head>`.

- [ ] **Step 5: Rodar testes**

```bash
php tests/Phase13Test.php
```

- [ ] **Step 6: Commit**

```bash
git add app/Views/layouts/main.php tests/Phase13Test.php
git commit -m "perf: notifications polling com visibilityState, backoff exponencial e BroadcastChannel"
```

---

### Task 5: SRI hashes para CDN — Chart.js e FullCalendar

**Files:**
- Modify: `app/Views/layouts/main.php`

- [ ] **Step 1: Gerar SRI hash para Chart.js 4.4.7**

```bash
curl -s https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js | openssl dgst -sha384 -binary | openssl base64 -A
```

Anotar o hash. Formato final: `sha384-<hash>`.

- [ ] **Step 2: Gerar SRI hash para FullCalendar 6.1.20**

```bash
curl -s https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js | openssl dgst -sha384 -binary | openssl base64 -A
```

- [ ] **Step 3: Atualizar tags script no layout**

Abrir `app/Views/layouts/main.php`. Localizar as tags `<script>` do CDN. Substituir pelo padrão:

```html
<script
  src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
  integrity="sha384-HASH_AQUI"
  crossorigin="anonymous"
  nonce="<?= CSP_NONCE ?>"></script>

<script
  src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"
  integrity="sha384-HASH_AQUI"
  crossorigin="anonymous"
  nonce="<?= CSP_NONCE ?>"></script>
```

Substituir `HASH_AQUI` pelos valores gerados nos steps 1 e 2.

- [ ] **Step 4: Adicionar testes**

```php
// ── 5. SRI hashes no CDN ─────────────────────────────────────────────────────
section('5. SRI hashes nas tags CDN');
$src = file_get_contents(ROOT_PATH . '/app/Views/layouts/main.php');
ok('chart.js 4.4.7 com integrity',    strpos($src, 'chart.js@4.4.7') !== false
                                    && strpos($src, 'integrity="sha384-') !== false);
ok('fullcalendar com integrity',       strpos($src, 'fullcalendar') !== false
                                    && strpos($src, 'integrity="sha384-') !== false);
ok('crossorigin="anonymous"',          strpos($src, 'crossorigin="anonymous"') !== false);
```

- [ ] **Step 5: Rodar testes**

```bash
php tests/Phase13Test.php
```

- [ ] **Step 6: Commit**

```bash
git add app/Views/layouts/main.php tests/Phase13Test.php
git commit -m "perf: atualiza Chart.js 4.4.7 e adiciona SRI hashes nos CDN scripts"
```

---

### Task 6: Finalizar Phase13Test

**Files:**
- Modify: `tests/Phase13Test.php`

- [ ] **Step 1: Adicionar resultado final**

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

- [ ] **Step 2: Rodar suite completa**

```bash
php tests/Phase11Test.php && php tests/Phase12Test.php && php tests/Phase13Test.php
```

- [ ] **Step 3: Commit**

```bash
git add tests/Phase13Test.php
git commit -m "test: Phase13 — cobertura da Fase 3 (performance)"
```

---

*Fase 3 concluída. Próximo: `docs/superpowers/plans/2026-04-17-fase4-features.md`*
