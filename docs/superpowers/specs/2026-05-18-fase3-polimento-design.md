# Refatoração — Fase 3: Polimento

**Data:** 2026-05-18
**Escopo:** Extração de JS inline restante + docblocks PHP
**Fases anteriores:** Fase 1 (backend), Fase 2 (extração JS principal)

---

## Contexto

Fases 1 e 2 concluídas. Fase 3 fecha o que ficou pendente:

- `tasks/index.php` foi explicitamente adiado na Fase 2 por ter FullCalendar
- `pipeline/stages.php` tem CSRF inconsistente (variável PHP inline em vez de `<meta name="csrf-token">`)
- `clients/index.php` tem funções globais `openQuickInteraction`/`openQuickTask` com `onclick` inline
- Docblocks PHP ausentes em controllers, services e models

---

## Arquitetura

### O que não muda

- Router, bootstrap, autoloader, `config/`, `database/`, `public/`
- Scripts de injeção de dados em `dashboard/index.php` e `acompanhamento/index.php` — padrão intencional
- Anti-FOUC de `errors/404.php` — requisito funcional, não pode ser externalizado
- FullCalendar carregado via CDN em `main.php` `<head>` sem `defer` — disponível antes de `tasks.js` executar

### Padrões JS (herdados da Fase 2)

- IIFE + ES6 Class com campos privados (`#`)
- `data-crm-widget` para auto-instanciação
- CSRF lido de `<meta name="csrf-token">` — única fonte de verdade
- Event delegation sobre `addEventListener` por elemento
- `textContent` para dados de usuário (nunca `innerHTML` com variável)
- `window.CRM` como único namespace global

### Novas meta tags no `<head>` de `main.php`

O calendário de tarefas precisa de `USER_ROLE` e `USER_ID` (controle de permissão de edição/exclusão). Na Fase 2, esses valores eram injetados via PHP inline no `<script>`. Para manter o padrão de zero dados PHP inline, adicionamos duas `<meta>` tags no `<head>` de `main.php`, junto às já existentes `csrf-token` e `app-url`:

```html
<meta name="user-role" content="<?= htmlspecialchars($_SESSION['user']['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
<meta name="user-id" content="<?= (int)($_SESSION['user']['id'] ?? 0) ?>">
```

Qualquer módulo que precise dessas informações lê com:
```js
document.querySelector('meta[name="user-role"]').content
document.querySelector('meta[name="user-id"]').content
```

### Estratégia de renovação de CSRF para múltiplos POSTs

Os módulos `tasks.js` e `pipeline-stages.js` fazem múltiplos POSTs na mesma sessão sem reload de página. A leitura única de `<meta name="csrf-token">` ficaria stale após o primeiro POST.

**Padrão adotado:** cada módulo lê o token da `<meta>` no constructor e, a cada resposta que contenha `csrf_token`, **atualiza a `<meta>` tag** além do estado interno:

```js
#refreshCsrf(data) {
    if (data.csrf_token) {
        this.#csrfToken = data.csrf_token;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.content = data.csrf_token;
    }
}
```

Isso garante que outros módulos que leiam a meta posteriormente recebam o token atualizado.

---

## Novos arquivos JS

### `public/assets/js/tasks.js`

Duas classes extraídas de `tasks/index.php`:

**`OverdueBanner`** — controla o banner de tarefas vencidas:
- Toggle expand/collapse via botão (labels "Ver lista" / "Ocultar" + chevron animado)
- Dismiss **persistente** com `localStorage` (`crm_overdue_dismissed`) — **melhoria funcional**: o código atual faz dismiss temporário via `banner.style.display = 'none'` (perde no F5); o novo comportamento persiste a dismissão até o próximo login ou limpeza manual

Auto-instanciação via `data-crm-widget="overdue-banner"`.

**`TaskCalendarManager`** — encapsula a inicialização do FullCalendar (~317 linhas de lógica):
- Recebe configuração via `data-events-url`, `data-view-url`, `data-create-url`
- Lê `USER_ROLE` e `USER_ID` de `<meta name="user-role">` e `<meta name="user-id">` — usados para decidir se mostra botões de edição/exclusão
- `#csrfToken` — lido de `<meta name="csrf-token">` no constructor, atualizado via `#refreshCsrf(data)` a cada resposta
- `passesFilter(event)` — filtra por status (all/pending/done/overdue) e prioridade via checkboxes
- `applyFilters()` — re-renderiza eventos após mudança de filtro, com guard `#_applyingFilters` contra reentrância
- `buildTooltip(event)` — monta texto do tooltip (usa concatenação de strings, não `innerHTML`)
- `handleDateClick(dateStr)` — verifica conflitos no dia, abre modal de conflito ou criação direta
- `openNewTaskModal(dateStr)` — modal de criação com data pré-preenchida
- `openEditTaskModal(taskId)` — carrega dados via GET `/api/tasks/{id}`, preenche modal, mostra link do cliente vinculado
- `#saveTask()` — POST para store/update, refetch de eventos
- `#deleteTask()` — POST para delete com confirmação
- `#toggleDone()` — alterna status done/pending
- Fecha modais via event delegation no backdrop (substitui `onclick` inline nos botões `×` e "Cancelar")

Auto-instanciação via `data-crm-widget="task-calendar"`.

> **`window.__calendar` descontinuado:** o código atual expõe `window.__calendar = calendar` (debug global). Na extração, essa referência será removida. O calendário fica acessível via `window.CRM.taskCalendar` se necessário.

### `public/assets/js/pipeline-stages.js`

**`PipelineStagesManager`** — CRUD completo de estágios do pipeline via AJAX:
- Edit inline (clique no nome do estágio — toggle view-mode/edit-mode com inputs de nome e cor)
- Save/cancel via botões (restaura valores originais dos `data-stage-*` attributes no cancel)
- Delete com confirmação segura (mostra nome da etapa + contagem de clientes vinculados)
- **Reordenação** — botões ↑/↓ fazem POST para `/pipeline/stages/{id}/move` com `direction=up|down`, seguido de `location.reload()`
- **Won Stage Toggle (FRAG-03)** — toggle de estrela (☆/★) com exclusividade mútua visual: ao ativar uma etapa como "ganho", desliga todas as outras via DOM. POST para `/pipeline/stages/{id}/toggle-won`. Inclui estados de loading (`opacity-50`, `cursor-wait`, `disabled`)
- CSRF lido de `<meta name="csrf-token">` no constructor, atualizado via `#refreshCsrf(data)` a cada resposta (corrige inconsistência atual de `let csrfToken = '<?= ... ?>'`)

Auto-instanciação via `data-crm-widget="pipeline-stages"`.

### `public/assets/js/client-index.js`

**`ClientIndexManager`** — comportamentos da listagem de clientes:
- Reset do formulário de filtros no submit (força `page=1`)
- Quick modals via event delegation em `data-action="open-quick-interaction"` e `data-action="open-quick-task"`
- Lê `data-client-id` e `data-client-name` dos elementos
- Fecha modais via event delegation em `data-action="close-modal"` com `data-target` (substitui `onclick` inline nos botões `×` e "Cancelar" dos dois modais)

Substitui funções globais `openQuickInteraction`/`openQuickTask`, 2 `onclick` inline na tabela e 4 `onclick` inline nos modais (fechar/cancelar).
Auto-instanciação via `data-crm-widget="client-index"`.

---

## Namespace `window.CRM` (atualizado)

| Propriedade | Arquivo | Tipo | Fase |
|---|---|---|---|
| `window.CRM.toast` | `toast.js` | `ToastManager` | 2 |
| `window.CRM.notifications` | `notifications.js` | `NotificationManager` | 2 |
| `window.CRM.layout` | `layout.js` | `LayoutManager` | 2 |
| `window.CRM.clientForm` | `client-form.js` | `ClientForm` | 2 |
| `window.CRM.clientShow` | `client-show.js` | `ClientShow` | 2 |
| `window.CRM.coldContacts` | `cold-contacts.js` | `ColdContactManager` | 2 |
| `window.CRM.taskCalendar` | `tasks.js` | `TaskCalendarManager` | **3** |
| `window.CRM.pipelineStages` | `pipeline-stages.js` | `PipelineStagesManager` | **3** |
| `window.CRM.clientIndex` | `client-index.js` | `ClientIndexManager` | **3** |

---

## Views modificadas

### `app/Views/layouts/main.php`

- Adiciona `<meta name="user-role">` e `<meta name="user-id">` no `<head>` (após `<meta name="app-url">`)

### `app/Views/tasks/index.php`

- Remove bloco `<script nonce>` do banner (linhas 39-60, 21 linhas — IIFE com toggle + dismiss)
- Remove bloco `<script nonce>` do FullCalendar (linhas 215-532, ~317 linhas — inicialização, filtros, modais, CRUD AJAX)
- Remove `onclick` inline dos botões de fechar/cancelar modal (linhas 108, 178)
- Remove `<input type="hidden" id="task_csrf">` (linha 113) — CSRF agora via `<meta>`
- Adiciona `data-crm-widget="task-calendar"` com `data-events-url`, `data-view-url`, `data-create-url` no container do calendário
- Adiciona `data-crm-widget="overdue-banner"` no container do banner
- Substitui `onclick` dos modais por `data-action="close-modal"` com `data-target`
- Define `$pageScripts = ['/public/assets/js/tasks.js']`

### `app/Views/pipeline/stages.php`

- Remove bloco `<script>` inline (linhas 137-290, 153 linhas — edição, reordenação, won toggle, deleção)
- Remove `let csrfToken = '<?= htmlspecialchars(...) ?>'` (violação do padrão Fase 2)
- Adiciona `data-crm-widget="pipeline-stages"` no container
- Define `$pageScripts = ['/public/assets/js/pipeline-stages.js']`

### `app/Views/clients/index.php`

- Remove bloco `<script>` inline do filter reset (linhas 61-78, IIFE)
- Remove bloco `<script>` inline das funções globais (linhas 298-316, `openQuickInteraction`/`openQuickTask`)
- Remove 2 `onclick="openQuickInteraction(...)"` e 2 `onclick="openQuickTask(...)"` dos botões da tabela (linhas 194, 201)
- Remove 4 `onclick` inline dos botões fechar/cancelar nos modais (linhas 225, 252, 264, 291)
- Adiciona `data-action="open-quick-interaction"`, `data-client-id`, `data-client-name` nos botões da tabela
- Adiciona `data-action="close-modal"` + `data-target` nos botões fechar/cancelar dos modais
- Adiciona `data-crm-widget="client-index"` no container
- Define `$pageScripts = ['/public/assets/js/client-index.js']`

---

## Docblocks PHP

Alvo: `@param` e `@return` nos métodos **públicos** de:

| Arquivo | Escopo |
|---|---|
| `app/Controllers/*.php` (11 controllers) | Todos os métodos públicos |
| `app/Services/ClientService.php` | Todos os métodos públicos |
| `core/Http/ApiResponse.php` | Todos os métodos públicos |
| `core/Controller.php` | Todos os métodos públicos |
| `app/Models/*.php` (7 models) | Gap-fill: métodos públicos sem docblock |

Regra: se um método já tem docblock completo, não reescrever — apenas gap-fill.

---

## Critérios de sucesso

- `tasks/index.php` não contém `<script>` com lógica (apenas eventualmente injeção de dados se necessário)
- `tasks/index.php` não contém `onclick` inline
- `pipeline/stages.php` não contém `<script>` inline nem `csrfToken` via PHP inline
- `clients/index.php` não contém `<script>` inline, `onclick` inline nem funções JS globais
- CSRF em todos os novos módulos lido de `<meta name="csrf-token">` com renovação via `#refreshCsrf()`
- `USER_ROLE` e `USER_ID` lidos de `<meta>` tags (não PHP inline)
- `window.__calendar` removido — calendário acessível via `window.CRM.taskCalendar`
- Nenhuma função global nova introduzida (apenas `window.CRM` existente)
- Todos os métodos públicos dos alvos listados têm `@param`/`@return`
- Nenhum comportamento observável pelo usuário muda (exceto dismiss persistente do banner de overdue)

---

## Fora de escopo

- Scripts de injeção de dados (`dashboard/index.php`, `acompanhamento/index.php`)
- Anti-FOUC em `errors/404.php`
- Bundler, TypeScript, testes automatizados
- Criação de `Tenant` model
- Mudanças de schema SQL
- Novos endpoints ou rotas
- `onclick` inline em views não listadas acima (`pipeline/index.php`, `auth/login.php`, `layouts/main.php` no flash message) — dívida técnica para fase futura
