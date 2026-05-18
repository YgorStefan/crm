# Fase 3 — Polimento: Extração JS + Docblocks PHP

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extrair os 3 blocos de JS inline restantes para arquivos externos e adicionar docblocks PHP nos controllers, services e models.

**Architecture:** IIFE + ES6 Class com campos privados (`#`), auto-instanciação via `data-crm-widget`, CSRF lido de `<meta name="csrf-token">` com renovação via `#refreshCsrf(data)` a cada resposta POST. Views modificadas apenas para remover `<script>` e `onclick` inline, adicionando `data-*` attributes e definindo `$pageScripts`.

**Tech Stack:** PHP 8.1, JavaScript ES2022, FullCalendar v6 (CDN, sem defer), `window.CRM` namespace global.

---

## Mapa de arquivos

**Criar:**
- `public/assets/js/tasks.js` — `OverdueBanner` + `TaskCalendarManager`
- `public/assets/js/pipeline-stages.js` — `PipelineStagesManager`
- `public/assets/js/client-index.js` — `ClientIndexManager`

**Modificar:**
- `app/Views/layouts/main.php` — adicionar 2 meta tags
- `app/Views/tasks/index.php` — remover 2 scripts inline, remover 2 onclick, remover 1 hidden input, adicionar data-*, $pageScripts
- `app/Views/pipeline/stages.php` — remover 1 script inline, adicionar data-*, $pageScripts
- `app/Views/clients/index.php` — remover 2 scripts inline, remover 6 onclick, converter para data-*, $pageScripts
- `app/Controllers/*.php` (11 arquivos) — gap-fill de docblocks
- `app/Services/ClientService.php` — gap-fill de docblocks
- `core/Http/ApiResponse.php` — adicionar docblocks
- `app/Models/*.php` (7 arquivos) — gap-fill de docblocks

---

## Task 1: Meta tags de usuário no layout

**Files:**
- Modify: `app/Views/layouts/main.php:34`

- [ ] **Passo 1: Adicionar as duas meta tags após `<meta name="app-url">`**

Abrir `app/Views/layouts/main.php`. Após a linha 34 (`<meta name="app-url" ...>`), inserir:

```php
    <meta name="user-role" content="<?= htmlspecialchars($_SESSION['user']['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="user-id" content="<?= (int)($_SESSION['user']['id'] ?? 0) ?>">
```

O bloco de meta tags (linhas 33-34 + novas) deve ficar:
```php
    <!-- Token CSRF e URL base — lidos pelo JS externo -->
    <meta name="csrf-token" content="<?= htmlspecialchars(\Core\Middleware\CsrfMiddleware::getToken(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="app-url" content="<?= htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="user-role" content="<?= htmlspecialchars($_SESSION['user']['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="user-id" content="<?= (int)($_SESSION['user']['id'] ?? 0) ?>">
```

- [ ] **Passo 2: Verificar sintaxe PHP**

```bash
php -l app/Views/layouts/main.php
```
Esperado: `No syntax errors detected`

- [ ] **Passo 3: Commit**

```bash
git add app/Views/layouts/main.php
git commit -m "feat: adiciona meta tags user-role e user-id no layout"
```

---

## Task 2: Criar `tasks.js`

**Files:**
- Create: `public/assets/js/tasks.js`

- [ ] **Passo 1: Criar o arquivo com `OverdueBanner` e `TaskCalendarManager`**

Criar `public/assets/js/tasks.js` com o conteúdo completo abaixo.

Notas importantes antes de escrever:
- `OverdueBanner` lê dismiss do localStorage com chave diária (`crm_overdue_dismissed_<YYYY-MM-DD>`) — persiste o dia inteiro, reseta no dia seguinte
- `TaskCalendarManager` lê `appUrl`, `csrfToken`, `userRole`, `userId` de `<meta>` tags no constructor
- `#refreshCsrf(data)` atualiza tanto o estado interno quanto a `<meta name="csrf-token">` para outros módulos
- FullCalendar já está carregado em `main.php` sem `defer`, portanto disponível quando este arquivo executa (carregado com `defer`)
- `window.__calendar` é removido; calendário acessível via `window.CRM.taskCalendar`

```js
(function () {
    'use strict';

    class OverdueBanner {
        static #KEY = 'crm_overdue_dismissed_' + new Date().toISOString().slice(0, 10);

        #banner;
        #toggle;
        #dismiss;
        #list;
        #chevron;
        #lblCollapsed;
        #lblExpanded;

        constructor(banner) {
            this.#banner       = banner;
            this.#toggle       = banner.querySelector('#overdueToggle');
            this.#dismiss      = banner.querySelector('#overdueDismiss');
            this.#list         = banner.querySelector('#overdueList');
            this.#chevron      = banner.querySelector('#overdueChevron');
            this.#lblCollapsed = this.#toggle.querySelector('[data-label-collapsed]');
            this.#lblExpanded  = this.#toggle.querySelector('[data-label-expanded]');

            this.#toggle.addEventListener('click', () => this.#onToggle());
            this.#dismiss.addEventListener('click', () => this.#onDismiss());
        }

        #onToggle() {
            const isHidden = this.#list.classList.toggle('hidden');
            this.#chevron.style.transform = isHidden ? '' : 'rotate(180deg)';
            this.#lblCollapsed.classList.toggle('hidden', !isHidden);
            this.#lblExpanded.classList.toggle('hidden', isHidden);
        }

        #onDismiss() {
            try { localStorage.setItem(OverdueBanner.#KEY, '1'); } catch (e) {}
            this.#banner.style.display = 'none';
        }

        static isDismissed() {
            try { return localStorage.getItem(OverdueBanner.#KEY) === '1'; } catch (e) { return false; }
        }

        static init() {
            const banner = document.querySelector('[data-crm-widget="overdue-banner"]');
            if (!banner) return;
            if (OverdueBanner.isDismissed()) { banner.style.display = 'none'; return; }
            window.CRM = window.CRM || {};
            window.CRM.overdueBanner = new OverdueBanner(banner);
        }
    }

    class TaskCalendarManager {
        #calendarEl;
        #appUrl;
        #csrfToken;
        #userRole;
        #userId;
        #calendar;
        #filterStatus  = 'all';
        #filterPriority = 'all';
        #_applyingFilters = false;
        #selectedDate  = null;

        constructor(el) {
            this.#calendarEl = el;
            this.#appUrl     = document.querySelector('meta[name="app-url"]').content;
            this.#csrfToken  = document.querySelector('meta[name="csrf-token"]').content;
            this.#userRole   = document.querySelector('meta[name="user-role"]').content;
            this.#userId     = parseInt(document.querySelector('meta[name="user-id"]').content, 10);

            this.#initCalendar();
            this.#wireFilters();
            this.#wireModalButtons();
        }

        #refreshCsrf(data) {
            if (data.csrf_token) {
                this.#csrfToken = data.csrf_token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = data.csrf_token;
            }
        }

        passesFilter(ev) {
            const status   = ev.extendedProps.status;
            const priority = ev.extendedProps.priority;
            if (this.#filterStatus === 'pending' && status !== 'pending' && status !== 'in_progress') return false;
            if (this.#filterStatus === 'done'    && status !== 'done') return false;
            if (this.#filterStatus === 'overdue') {
                if (status === 'done') return false;
                if (ev.start && ev.start > new Date()) return false;
            }
            if (this.#filterPriority !== 'all' && priority !== this.#filterPriority) return false;
            return true;
        }

        applyFilters() {
            if (this.#_applyingFilters) return;
            this.#_applyingFilters = true;
            try {
                this.#calendar.getEvents().forEach(ev => {
                    const wanted = this.passesFilter(ev) ? 'auto' : 'none';
                    if (ev.display !== wanted) ev.setProp('display', wanted);
                });
            } finally {
                this.#_applyingFilters = false;
            }
        }

        buildTooltip(ev) {
            const lines = [ev.title];
            const props = ev.extendedProps || {};
            if (props.client_name) lines.push('Cliente: ' + props.client_name);
            const prioLabel = { high: 'Alta', medium: 'Média', low: 'Baixa' }[props.priority] || props.priority;
            if (prioLabel) lines.push('Prioridade: ' + prioLabel);
            if (props.status === 'done') lines.push('(concluída)');
            return lines.join('\n');
        }

        #initCalendar() {
            this.#calendar = new FullCalendar.Calendar(this.#calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                height: 'auto',
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'dayGridMonth,timeGridWeek',
                },
                buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana' },
                events:          this.#appUrl + '/api/tasks/calendar',
                dayMaxEventRows: 3,
                moreLinkText:    n => '+' + n + ' mais',
                dateClick:       info => this.handleDateClick(info.dateStr),
                eventClick:      info => { info.jsEvent.preventDefault(); this.openEditTaskModal(info.event.id); },
                eventDidMount:   info => {
                    info.el.style.cursor = 'pointer';
                    info.el.setAttribute('title', this.buildTooltip(info.event));
                    if (info.event.extendedProps.status === 'done') {
                        info.el.style.textDecoration = 'line-through';
                        info.el.style.opacity = '0.6';
                    }
                },
                eventsSet: () => this.applyFilters(),
            });
            this.#calendar.render();
        }

        #wireFilters() {
            document.querySelectorAll('.fc-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.fc-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    this.#filterStatus = btn.dataset.filter;
                    this.applyFilters();
                });
            });
            document.getElementById('fcPriorityFilter').addEventListener('change', e => {
                this.#filterPriority = e.target.value;
                this.applyFilters();
            });
        }

        #wireModalButtons() {
            document.getElementById('btnConflictView').addEventListener('click', () => {
                document.getElementById('modalDayConflict').style.display = 'none';
                this.#calendar.changeView('timeGridWeek', this.#selectedDate);
            });
            document.getElementById('btnConflictCreate').addEventListener('click', () => {
                document.getElementById('modalDayConflict').style.display = 'none';
                this.openNewTaskModal(this.#selectedDate);
            });
            document.getElementById('btnSaveTask').addEventListener('click', e => { e.preventDefault(); this.#saveTask(); });
            document.getElementById('btnDeleteTask').addEventListener('click', () => this.#deleteTask());
            document.getElementById('btnToggleDone').addEventListener('click', () => this.#toggleDone());

            ['modalTask', 'modalDayConflict'].forEach(id => {
                const el = document.getElementById(id);
                el.addEventListener('click', e => { if (e.target === el) el.style.display = 'none'; });
            });
            document.addEventListener('click', e => {
                if (e.target.dataset.action === 'close-modal') {
                    const target = document.getElementById(e.target.dataset.target);
                    if (target) target.style.display = 'none';
                }
            });
        }

        handleDateClick(dateStr) {
            this.#selectedDate = dateStr;
            const eventsOnDate = this.#calendar.getEvents().filter(e =>
                e.start && e.start.toISOString().slice(0, 10) === dateStr
            );
            if (eventsOnDate.length > 0) {
                const list = document.getElementById('conflictEventsList');
                list.innerHTML = '';
                eventsOnDate.forEach(ev => {
                    const div = document.createElement('div');
                    div.className = 'text-sm text-gray-700 dark:text-zinc-200 py-1 border-b border-gray-100 dark:border-zinc-800 last:border-0';
                    div.textContent = ev.title;
                    list.appendChild(div);
                });
                document.getElementById('modalDayConflict').style.display = 'flex';
            } else {
                this.openNewTaskModal(dateStr);
            }
        }

        openNewTaskModal(dateStr) {
            document.getElementById('modalTaskTitle').textContent = 'Nova Tarefa';
            document.getElementById('task_id').value = '';
            document.getElementById('task_title').value = '';
            document.getElementById('task_due_date').value = dateStr + 'T08:00';
            document.getElementById('task_priority').value = 'medium';
            document.getElementById('task_description').value = '';
            document.getElementById('taskActionBtns').style.display = 'none';
            document.getElementById('btnSaveTask').style.display = this.#userRole === 'viewer' ? 'none' : '';
            document.getElementById('modalTask').style.display = 'flex';
        }

        async openEditTaskModal(taskId) {
            try {
                const resp = await fetch(this.#appUrl + '/api/tasks/' + taskId);
                if (!resp.ok) return;
                const task = await resp.json();

                document.getElementById('modalTaskTitle').textContent = 'Editar Tarefa';
                document.getElementById('task_id').value = task.id;
                document.getElementById('task_title').value = task.title;
                document.getElementById('task_due_date').value = task.due_date.replace(' ', 'T').slice(0, 16);
                document.getElementById('task_priority').value = task.priority;
                document.getElementById('task_description').value = task.description || '';
                const assignedEl = document.getElementById('task_assigned_to');
                if (assignedEl && task.assigned_to) assignedEl.value = task.assigned_to;

                const canEdit = this.#userRole === 'admin' ||
                    (this.#userRole === 'seller' && (task.assigned_to == this.#userId || task.created_by == this.#userId));
                document.getElementById('taskActionBtns').style.display = canEdit ? 'flex' : 'none';
                document.getElementById('btnSaveTask').style.display = canEdit ? '' : 'none';

                const btnToggle = document.getElementById('btnToggleDone');
                if (task.status === 'done') {
                    btnToggle.textContent = 'Reabrir';
                    btnToggle.dataset.nextStatus = 'pending';
                } else {
                    btnToggle.textContent = 'Concluída';
                    btnToggle.dataset.nextStatus = 'done';
                }

                const clientLinkEl = document.getElementById('taskClientLink');
                const clientNameEl = document.getElementById('taskClientName');
                const clientUrlEl  = document.getElementById('taskClientUrl');
                if (task.client_id) {
                    clientNameEl.textContent = task.client_name || 'Cliente #' + task.client_id;
                    clientUrlEl.href = this.#appUrl + '/clients/' + task.client_id;
                    clientLinkEl.style.display = 'flex';
                } else {
                    clientLinkEl.style.display = 'none';
                }
                document.getElementById('modalTask').style.display = 'flex';
            } catch (e) {
                console.error('Erro ao carregar tarefa:', e);
            }
        }

        async #saveTask() {
            const taskId = document.getElementById('task_id').value;
            const url = taskId
                ? this.#appUrl + '/tasks/' + taskId + '/update'
                : this.#appUrl + '/tasks/store';

            const body = new URLSearchParams({
                _csrf_token: this.#csrfToken,
                title:       document.getElementById('task_title').value,
                due_date:    document.getElementById('task_due_date').value,
                priority:    document.getElementById('task_priority').value,
                description: document.getElementById('task_description').value,
            });
            const assignedEl = document.getElementById('task_assigned_to');
            if (assignedEl) body.append('assigned_to', assignedEl.value);

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString(),
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.#refreshCsrf(data);
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert('Erro ao salvar tarefa. Verifique os campos e tente novamente.');
                }
            } catch (e) {
                alert('Erro ao salvar tarefa.');
            }
        }

        async #deleteTask() {
            const taskId = document.getElementById('task_id').value;
            if (!taskId) return;
            if (!window.confirm('Excluir esta tarefa permanentemente?')) return;
            try {
                const body = new URLSearchParams({ _csrf_token: this.#csrfToken });
                const resp = await fetch(this.#appUrl + '/tasks/' + taskId + '/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString(),
                });
                const data = await resp.json();
                this.#refreshCsrf(data);
                if (data.success) {
                    const ev = this.#calendar.getEventById(taskId);
                    if (ev) ev.remove();
                    document.getElementById('modalTask').style.display = 'none';
                } else {
                    alert('Erro ao excluir tarefa.');
                }
            } catch (e) {
                alert('Erro de rede ao excluir tarefa.');
            }
        }

        async #toggleDone() {
            const taskId = document.getElementById('task_id').value;
            if (!taskId) return;
            const btn = document.getElementById('btnToggleDone');
            const nextStatus = btn.dataset.nextStatus || 'done';
            try {
                const body = new URLSearchParams({ _csrf_token: this.#csrfToken, status: nextStatus });
                const resp = await fetch(this.#appUrl + '/tasks/' + taskId + '/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString(),
                });
                const data = await resp.json();
                this.#refreshCsrf(data);
                if (data.success) {
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert('Erro ao atualizar tarefa.');
                }
            } catch (e) {
                alert('Erro de rede ao atualizar tarefa.');
            }
        }

        static init() {
            const el = document.querySelector('[data-crm-widget="task-calendar"]');
            if (!el) return;
            window.CRM = window.CRM || {};
            window.CRM.taskCalendar = new TaskCalendarManager(el);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        OverdueBanner.init();
        TaskCalendarManager.init();
    });
})();
```

- [ ] **Passo 2: Verificar sintaxe JS**

```bash
node --check public/assets/js/tasks.js
```
Esperado: sem saída (sem erros)

---

## Task 3: Adaptar `tasks/index.php`

**Files:**
- Modify: `app/Views/tasks/index.php`

> Não há commit no Task 2 isolado — o JS sem o wire da view não funciona. Commitar junto após Task 3.

- [ ] **Passo 1: Adicionar `data-crm-widget="overdue-banner"` no div do banner (linha 6)**

Alterar a linha de abertura do div `#overdueBanner`:

```php
// ANTES (linha 6):
    <div id="overdueBanner"
         class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 rounded-lg mb-4">

// DEPOIS:
    <div id="overdueBanner" data-crm-widget="overdue-banner"
         class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 rounded-lg mb-4">
```

- [ ] **Passo 2: Remover o bloco `<script>` do banner (linhas 39-60)**

Remover completamente:
```php
    <script nonce="<?= CSP_NONCE ?>">
    (function () {
        const banner   = document.getElementById('overdueBanner');
        // ... (todo o bloco até o fechamento })
    })();
    </script>
```

- [ ] **Passo 3: Adicionar `data-crm-widget="task-calendar"` no div do calendário (linha 64 original)**

Alterar:
```php
// ANTES:
<div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">

// DEPOIS:
<div data-crm-widget="task-calendar"
     class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
```

- [ ] **Passo 4: Remover `<input type="hidden" id="task_csrf">` (linha 113 original)**

Remover a linha:
```php
            <input type="hidden" id="task_csrf" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
```

- [ ] **Passo 5: Converter os dois `onclick` do modalTask para `data-action`**

**Botão ×** (linha ~108 original):
```php
// ANTES:
            <button onclick="document.getElementById('modalTask').style.display='none'"
                class="text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-2xl">&times;</button>

// DEPOIS:
            <button data-action="close-modal" data-target="modalTask"
                class="text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-2xl">&times;</button>
```

**Botão Cancelar** (linha ~178 original):
```php
// ANTES:
                    <button type="button" onclick="document.getElementById('modalTask').style.display='none'"
                        class="px-4 py-2 border border-gray-300 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 rounded-lg text-sm hover:bg-gray-100 transition-colors">
                        Cancelar
                    </button>

// DEPOIS:
                    <button type="button" data-action="close-modal" data-target="modalTask"
                        class="px-4 py-2 border border-gray-300 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 rounded-lg text-sm hover:bg-gray-100 transition-colors">
                        Cancelar
                    </button>
```

- [ ] **Passo 6: Remover o bloco `<script>` do FullCalendar (linhas 215-532 original)**

Remover completamente o bloco:
```php
<script nonce="<?= CSP_NONCE ?>">
    document.addEventListener('DOMContentLoaded', function () {
        // ... todo o bloco até o fechamento ...
        window.__calendar = calendar;
    });
</script>
```

- [ ] **Passo 7: Adicionar `$pageScripts` no início do arquivo**

Após a linha `<?php ?>` (linha 1-2), adicionar antes do primeiro comentário HTML:

```php
<?php
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts = '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/tasks.js?v=' . $_jsV('tasks.js') . '"></script>';
unset($_jsV);
?>
```

- [ ] **Passo 8: Verificar sintaxe PHP**

```bash
php -l app/Views/tasks/index.php
```
Esperado: `No syntax errors detected`

- [ ] **Passo 9: Commit**

```bash
git add public/assets/js/tasks.js app/Views/tasks/index.php
git commit -m "feat: extrai JS inline de tasks/index.php para tasks.js"
```

---

## Task 4: Criar `pipeline-stages.js`

**Files:**
- Create: `public/assets/js/pipeline-stages.js`

- [ ] **Passo 1: Criar o arquivo**

Criar `public/assets/js/pipeline-stages.js`:

```js
(function () {
    'use strict';

    class PipelineStagesManager {
        #appUrl;
        #csrfToken;

        constructor(container) {
            this.#appUrl    = document.querySelector('meta[name="app-url"]').content;
            this.#csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            this.#wireEdit(container);
            this.#wireMove(container);
            this.#wireWonToggle(container);
            this.#wireDelete(container);
        }

        #refreshCsrf(data) {
            if (data.csrf_token) {
                this.#csrfToken = data.csrf_token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = data.csrf_token;
            }
        }

        #wireEdit(container) {
            container.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', function () {
                    const row = this.closest('[data-stage-id]');
                    row.querySelector('.view-mode').classList.add('hidden');
                    row.querySelector('.edit-mode').classList.remove('hidden');
                    row.querySelector('.btn-edit').classList.add('hidden');
                    row.querySelector('.btn-save').classList.remove('hidden');
                    row.querySelector('.btn-cancel').classList.remove('hidden');
                });
            });

            container.querySelectorAll('.btn-cancel').forEach(btn => {
                btn.addEventListener('click', function () {
                    const row = this.closest('[data-stage-id]');
                    row.querySelector('.edit-name').value  = row.dataset.stageName;
                    row.querySelector('.edit-color').value = row.dataset.stageColor;
                    exitEditMode(row);
                });
            });

            container.querySelectorAll('.btn-save').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row   = btn.closest('[data-stage-id]');
                    const id    = row.dataset.stageId;
                    const name  = row.querySelector('.edit-name').value.trim();
                    const color = row.querySelector('.edit-color').value;

                    if (!name) { alert('O nome da etapa não pode ficar vazio.'); return; }

                    const body = new URLSearchParams({ _csrf_token: this.#csrfToken, name, color });
                    fetch(this.#appUrl + '/pipeline/stages/' + id + '/update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.#refreshCsrf(data);
                            if (!data.success) { alert('Erro ao salvar. Tente novamente.'); return; }
                            row.querySelector('.stage-name-text').textContent        = name;
                            row.querySelector('.color-preview').style.backgroundColor = color;
                            row.dataset.stageName  = name;
                            row.dataset.stageColor = color;
                            exitEditMode(row);
                        })
                        .catch(() => alert('Erro de comunicação. Tente novamente.'));
                });
            });

            function exitEditMode(row) {
                row.querySelector('.view-mode').classList.remove('hidden');
                row.querySelector('.edit-mode').classList.add('hidden');
                row.querySelector('.btn-edit').classList.remove('hidden');
                row.querySelector('.btn-save').classList.add('hidden');
                row.querySelector('.btn-cancel').classList.add('hidden');
            }
        }

        #wireMove(container) {
            container.querySelectorAll('.btn-move').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row       = btn.closest('[data-stage-id]');
                    const id        = row.dataset.stageId;
                    const direction = btn.dataset.direction;

                    const body = new URLSearchParams({ _csrf_token: this.#csrfToken, direction });
                    fetch(this.#appUrl + '/pipeline/stages/' + id + '/move', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.#refreshCsrf(data);
                            if (!data.success) return;
                            location.reload();
                        })
                        .catch(() => alert('Erro de comunicação. Tente novamente.'));
                });
            });
        }

        #wireWonToggle(container) {
            container.querySelectorAll('.btn-won-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.stageId;
                    btn.classList.add('opacity-50', 'cursor-wait');
                    btn.setAttribute('aria-label', 'Salvando...');
                    btn.disabled = true;

                    const body = new URLSearchParams({ _csrf_token: this.#csrfToken });
                    fetch(this.#appUrl + '/pipeline/stages/' + id + '/toggle-won', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.#refreshCsrf(data);
                            if (!data.success) { alert('Erro ao salvar. Tente novamente.'); return; }

                            const newState = data.is_won_stage === 1;
                            if (newState) {
                                container.querySelectorAll('.btn-won-toggle').forEach(b => {
                                    b.dataset.isWon = '0';
                                    b.setAttribute('aria-pressed', 'false');
                                    b.setAttribute('title', 'Definir como etapa de ganho');
                                    b.className = b.className.replace('text-indigo-600 font-medium', 'text-gray-400 hover:text-indigo-500');
                                    b.textContent = '☆';
                                });
                            }
                            btn.dataset.isWon = newState ? '1' : '0';
                            btn.setAttribute('aria-pressed', newState ? 'true' : 'false');
                            btn.setAttribute('title', newState ? 'Etapa de ganho ativa' : 'Definir como etapa de ganho');
                            btn.textContent = newState ? '★' : '☆';
                            btn.className = btn.className.replace(
                                newState ? 'text-gray-400 hover:text-indigo-500' : 'text-indigo-600 font-medium',
                                newState ? 'text-indigo-600 font-medium' : 'text-gray-400 hover:text-indigo-500'
                            );
                        })
                        .catch(() => alert('Erro de comunicação. Tente novamente.'))
                        .finally(() => {
                            btn.classList.remove('opacity-50', 'cursor-wait');
                            btn.removeAttribute('aria-label');
                            btn.disabled = false;
                        });
                });
            });
        }

        #wireDelete(container) {
            container.querySelectorAll('.form-delete-stage').forEach(form => {
                form.addEventListener('submit', function (e) {
                    const row   = this.closest('[data-stage-id]');
                    const name  = row ? row.dataset.stageName : '';
                    const count = row ? parseInt(row.dataset.clientCount || '0', 10) : 0;
                    const msg   = count > 0
                        ? count + ' cliente(s) nesta etapa perderão a etapa.'
                        : 'Esta etapa está vazia.';
                    if (!confirm('Remover a etapa "' + name + '"?\n' + msg)) e.preventDefault();
                });
            });
        }

        static init() {
            const container = document.querySelector('[data-crm-widget="pipeline-stages"]');
            if (!container) return;
            window.CRM = window.CRM || {};
            window.CRM.pipelineStages = new PipelineStagesManager(container);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        PipelineStagesManager.init();
    });
})();
```

- [ ] **Passo 2: Verificar sintaxe JS**

```bash
node --check public/assets/js/pipeline-stages.js
```
Esperado: sem saída (sem erros)

---

## Task 5: Adaptar `pipeline/stages.php`

**Files:**
- Modify: `app/Views/pipeline/stages.php`

- [ ] **Passo 1: Adicionar `data-crm-widget="pipeline-stages"` no div container**

O container principal começa na linha 22 (`<div class="max-w-2xl mx-auto">`). Alterar:

```php
// ANTES:
<div class="max-w-2xl mx-auto">

// DEPOIS:
<div data-crm-widget="pipeline-stages" class="max-w-2xl mx-auto">
```

- [ ] **Passo 2: Remover o bloco `<script>` inteiro (linhas 137-290)**

Remover o bloco completo desde `<script nonce="<?= CSP_NONCE ?>">` até o `</script>` final da view.

- [ ] **Passo 3: Adicionar `$pageScripts` no início do arquivo**

Após a linha `<?php ?>` inicial (linhas 1-2), adicionar:

```php
<?php
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts = '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/pipeline-stages.js?v=' . $_jsV('pipeline-stages.js') . '"></script>';
unset($_jsV);
?>
```

- [ ] **Passo 4: Verificar sintaxe PHP**

```bash
php -l app/Views/pipeline/stages.php
```
Esperado: `No syntax errors detected`

- [ ] **Passo 5: Commit**

```bash
git add public/assets/js/pipeline-stages.js app/Views/pipeline/stages.php
git commit -m "feat: extrai JS inline de pipeline/stages.php para pipeline-stages.js"
```

---

## Task 6: Criar `client-index.js`

**Files:**
- Create: `public/assets/js/client-index.js`

- [ ] **Passo 1: Criar o arquivo**

Criar `public/assets/js/client-index.js`:

```js
(function () {
    'use strict';

    class ClientIndexManager {
        constructor() {
            this.#wireFilterReset();
            this.#wireModals();
        }

        #wireFilterReset() {
            const form = document.getElementById('filterForm');
            if (!form) return;
            form.addEventListener('submit', function () {
                let pageInput = this.querySelector('input[name="page"]');
                if (!pageInput) {
                    pageInput = document.createElement('input');
                    pageInput.type = 'hidden';
                    pageInput.name = 'page';
                    this.appendChild(pageInput);
                }
                pageInput.value = '1';
            });
        }

        // Escuta no document porque os modais ficam fora do container data-crm-widget
        #wireModals() {
            document.addEventListener('click', e => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                if (btn.dataset.action === 'open-quick-interaction') {
                    document.getElementById('qiClientId').value = btn.dataset.clientId;
                    document.getElementById('qiClientName').textContent = btn.dataset.clientName;
                    const now = new Date();
                    now.setSeconds(0, 0);
                    document.getElementById('qiOccurredAt').value = now.toISOString().slice(0, 16);
                    document.getElementById('modalQuickInteraction').classList.remove('hidden');
                }

                if (btn.dataset.action === 'open-quick-task') {
                    document.getElementById('qtClientId').value = btn.dataset.clientId;
                    document.getElementById('qtClientName').textContent = btn.dataset.clientName;
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    tomorrow.setHours(12, 0, 0, 0);
                    document.getElementById('qtDueDate').value = tomorrow.toISOString().slice(0, 16);
                    document.getElementById('modalQuickTask').classList.remove('hidden');
                }

                if (btn.dataset.action === 'close-modal') {
                    const target = document.getElementById(btn.dataset.target);
                    if (target) target.classList.add('hidden');
                }
            });
        }

        static init() {
            if (!document.querySelector('[data-crm-widget="client-index"]')) return;
            window.CRM = window.CRM || {};
            window.CRM.clientIndex = new ClientIndexManager();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        ClientIndexManager.init();
    });
})();
```

- [ ] **Passo 2: Verificar sintaxe JS**

```bash
node --check public/assets/js/client-index.js
```
Esperado: sem saída (sem erros)

---

## Task 7: Adaptar `clients/index.php`

**Files:**
- Modify: `app/Views/clients/index.php`

- [ ] **Passo 1: Remover o bloco `<script>` do filter reset (linhas 61-78)**

Remover completamente:
```php
<script nonce="<?= CSP_NONCE ?>">
// Ao submeter o formulário de filtros, reseta para a página 1
(function () {
    var filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function () {
            // ...
        });
    }
})();
</script>
```

- [ ] **Passo 2: Adicionar `data-crm-widget="client-index"` no div da toolbar (linha 4)**

O atributo serve como sentinela de página — o `ClientIndexManager` verifica sua presença antes de instanciar. A event delegation usa `document` internamente (modais ficam fora do div).

```php
// ANTES:
<div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-3 mb-4">
    <form id="filterForm" ...>

// DEPOIS:
<div data-crm-widget="client-index"
     class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-3 mb-4">
    <form id="filterForm" ...>
```

- [ ] **Passo 3: Converter os 2 `onclick` da tabela para `data-action`**

**Botão Nova interação** (linha ~193-197):
```php
// ANTES:
            <button
                onclick="openQuickInteraction(<?= (int)$client['id'] ?>, <?= htmlspecialchars(json_encode($client['name']), ENT_QUOTES, 'UTF-8') ?>)"
                data-tooltip="Nova interação"
                class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/40 transition-colors">
                💬
            </button>

// DEPOIS:
            <button
                data-action="open-quick-interaction"
                data-client-id="<?= (int)$client['id'] ?>"
                data-client-name="<?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>"
                data-tooltip="Nova interação"
                class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/40 transition-colors">
                💬
            </button>
```

**Botão Nova tarefa** (linha ~200-204):
```php
// ANTES:
            <button
                onclick="openQuickTask(<?= (int)$client['id'] ?>, <?= htmlspecialchars(json_encode($client['name']), ENT_QUOTES, 'UTF-8') ?>)"
                data-tooltip="Nova tarefa"
                class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/40 transition-colors">
                📅
            </button>

// DEPOIS:
            <button
                data-action="open-quick-task"
                data-client-id="<?= (int)$client['id'] ?>"
                data-client-name="<?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>"
                data-tooltip="Nova tarefa"
                class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/40 transition-colors">
                📅
            </button>
```

- [ ] **Passo 4: Converter os 4 `onclick` de fechar modais para `data-action`**

**Modal Quick Interaction — botão ×** (linha ~225):
```php
// ANTES:
            <button onclick="document.getElementById('modalQuickInteraction').classList.add('hidden')" class="...">

// DEPOIS:
            <button data-action="close-modal" data-target="modalQuickInteraction" class="...">
```

**Modal Quick Interaction — botão Cancelar** (linha ~252):
```php
// ANTES:
                <button type="button" onclick="document.getElementById('modalQuickInteraction').classList.add('hidden')"
                    class="...">Cancelar</button>

// DEPOIS:
                <button type="button" data-action="close-modal" data-target="modalQuickInteraction"
                    class="...">Cancelar</button>
```

**Modal Quick Task — botão ×** (linha ~264):
```php
// ANTES:
            <button onclick="document.getElementById('modalQuickTask').classList.add('hidden')" class="...">

// DEPOIS:
            <button data-action="close-modal" data-target="modalQuickTask" class="...">
```

**Modal Quick Task — botão Cancelar** (linha ~291):
```php
// ANTES:
                <button type="button" onclick="document.getElementById('modalQuickTask').classList.add('hidden')"
                    class="...">Cancelar</button>

// DEPOIS:
                <button type="button" data-action="close-modal" data-target="modalQuickTask"
                    class="...">Cancelar</button>
```

- [ ] **Passo 5: Remover o bloco `<script>` das funções globais (linhas 298-316)**

Remover completamente:
```php
<script nonce="<?= CSP_NONCE ?>">
function openQuickInteraction(clientId, clientName) { ... }
function openQuickTask(clientId, clientName) { ... }
</script>
```

- [ ] **Passo 6: Adicionar `$pageScripts` no início do arquivo**

Após a linha `<?php ?>` inicial (linhas 1-2), adicionar:

```php
<?php
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts = '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/client-index.js?v=' . $_jsV('client-index.js') . '"></script>';
unset($_jsV);
?>
```

- [ ] **Passo 7: Verificar sintaxe PHP**

```bash
php -l app/Views/clients/index.php
```
Esperado: `No syntax errors detected`

- [ ] **Passo 8: Commit**

```bash
git add public/assets/js/client-index.js app/Views/clients/index.php
git commit -m "feat: extrai JS inline de clients/index.php para client-index.js"
```

---

## Task 8: Docblocks em `core/Http/ApiResponse.php`

**Files:**
- Modify: `core/Http/ApiResponse.php`

- [ ] **Passo 1: Adicionar docblocks nos dois métodos públicos**

O arquivo atual não tem docblocks. Adicionar:

```php
<?php

namespace Core\Http;

use Core\Middleware\CsrfMiddleware;

class ApiResponse
{
    /**
     * Monta payload JSON de sucesso, opcionalmente com CSRF token renovado.
     *
     * @param  array  $data   Dados adicionais a mesclar no payload
     * @param  bool   $token  Se true, inclui 'csrf_token' com token renovado
     * @return array
     */
    public static function success(array $data = [], bool $token = false): array
    {
        $payload = array_merge(['success' => true], $data);
        if ($token) {
            $payload['csrf_token'] = CsrfMiddleware::getToken();
        }
        return $payload;
    }

    /**
     * Monta payload JSON de erro com shape consistente para o frontend.
     *
     * @param  string  $message  Mensagem de erro legível pelo usuário
     * @return array
     */
    public static function error(string $message): array
    {
        return ['success' => false, 'error' => $message];
    }
}
```

- [ ] **Passo 2: Verificar sintaxe PHP**

```bash
php -l core/Http/ApiResponse.php
```
Esperado: `No syntax errors detected`

- [ ] **Passo 3: Commit**

```bash
git add core/Http/ApiResponse.php
git commit -m "docs: adiciona docblocks em ApiResponse"
```

---

## Task 9: Docblocks nos controllers

**Files:**
- Modify: `app/Controllers/TaskController.php` (método `destroy` sem docblock)
- Modify: `app/Controllers/AuthController.php`
- Modify: `app/Controllers/AdminController.php`
- Modify: `app/Controllers/UserController.php`
- Modify: `app/Controllers/ClientController.php`
- Modify: `app/Controllers/ColdContactController.php`
- Modify: `app/Controllers/DashboardController.php`
- Modify: `app/Controllers/InteractionController.php`
- Modify: `app/Controllers/PipelineController.php`
- Modify: `app/Controllers/SettingsController.php`
- Modify: `app/Controllers/AcompanhamentoController.php`

**Nota:** `core/Controller.php` já possui docblocks completos em todos os métodos — nenhuma alteração necessária.

**Regra:** Ler cada controller. Para cada método público sem docblock `@param`/`@return`, adicionar. Se já tem docblock completo, não reescrever.

- [ ] **Passo 1: Gap-fill em cada controller**

Para cada controller, abrir o arquivo e percorrer os métodos públicos. A assinatura-padrão dos action methods é `public function nome(array $params = []): void`. Adicionar docblocks faltantes conforme o padrão:

```php
/**
 * [Descrição do que o método faz, uma linha.]
 *
 * @param  array  $params  Parâmetros da rota (ex.: ['id' => '42'])
 * @return void
 */
public function nome(array $params = []): void
```

Para métodos que retornam outros tipos (ex.: `bool`, `array`), ajustar `@return` conforme a assinatura real.

**`TaskController`** — único método faltante identificado:
```php
// Adicionar antes de `public function destroy`:
/**
 * Remove uma tarefa permanentemente.
 *
 * @param  array  $params  Parâmetros da rota (requer 'id')
 * @return void
 */
```

Para os demais controllers: ler o arquivo, identificar métodos sem docblock e adicionar seguindo o mesmo padrão. Não inventar descrições genéricas — ler o corpo do método para entender o que ele faz.

- [ ] **Passo 2: Verificar sintaxe PHP de cada controller modificado**

```bash
php -l app/Controllers/TaskController.php
php -l app/Controllers/AuthController.php
php -l app/Controllers/AdminController.php
php -l app/Controllers/UserController.php
php -l app/Controllers/ClientController.php
php -l app/Controllers/ColdContactController.php
php -l app/Controllers/DashboardController.php
php -l app/Controllers/InteractionController.php
php -l app/Controllers/PipelineController.php
php -l app/Controllers/SettingsController.php
php -l app/Controllers/AcompanhamentoController.php
```
Esperado: `No syntax errors detected` em todos.

- [ ] **Passo 3: Commit**

```bash
git add app/Controllers/
git commit -m "docs: gap-fill de docblocks nos controllers"
```

---

## Task 10: Docblocks em models e `ClientService`

**Files:**
- Modify: `app/Models/Client.php`
- Modify: `app/Models/ClientSale.php`
- Modify: `app/Models/ColdContact.php`
- Modify: `app/Models/Task.php`
- Modify: `app/Models/Interaction.php`
- Modify: `app/Models/PipelineStage.php`
- Modify: `app/Models/User.php`
- Modify: `app/Services/ClientService.php`

**Regra:** Gap-fill — apenas métodos públicos sem docblock. Não reescrever os que já têm.

- [ ] **Passo 1: Gap-fill em cada model e no service**

Padrão para methods de model:

```php
/**
 * [Descrição do que busca/cria/atualiza/remove.]
 *
 * @param  int    $id      ID do registro
 * @param  array  $data    Dados a persistir
 * @return array|null      Dados do registro, ou null se não encontrado
 */
public function findById(int $id): ?array
```

Para `ClientService`, os métodos públicos são `getSalesWithPaymentStatus(int $clientId): array` e `getOverdueClientIds(): array`. Adicionar docblocks descrevendo o que calculam.

- [ ] **Passo 2: Verificar sintaxe PHP**

```bash
php -l app/Models/Client.php
php -l app/Models/ClientSale.php
php -l app/Models/ColdContact.php
php -l app/Models/Task.php
php -l app/Models/Interaction.php
php -l app/Models/PipelineStage.php
php -l app/Models/User.php
php -l app/Services/ClientService.php
```
Esperado: `No syntax errors detected` em todos.

- [ ] **Passo 3: Commit**

```bash
git add app/Models/ app/Services/ClientService.php
git commit -m "docs: gap-fill de docblocks nos models e ClientService"
```

---

## Checklist de verificação final

Antes de considerar a Fase 3 concluída, verificar manualmente no browser:

- [ ] `/tasks` — calendário renderiza, filtros funcionam, clicar em data abre modal, criar/editar/excluir tarefa funciona, banner de overdue togla e dismiss persiste no F5
- [ ] `/pipeline/stages` — editar nome e cor inline funciona, salvar e cancelar, reordenar (↑/↓), toggle de won stage, deleção com confirmação
- [ ] `/clients` — filtros com submit reseta para page 1, botão 💬 abre modal de interação com nome correto, botão 📅 abre modal de tarefa, fechar modais funciona
- [ ] DevTools → Elements: `<meta name="user-role">` e `<meta name="user-id">` presentes no `<head>`
- [ ] DevTools → Console: nenhum erro JS na carga das 3 páginas
- [ ] DevTools → Network: arquivos `tasks.js`, `pipeline-stages.js`, `client-index.js` carregados com status 200
