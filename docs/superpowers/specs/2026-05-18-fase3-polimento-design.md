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

---

## Novos arquivos JS

### `public/assets/js/tasks.js`

Duas classes extraídas de `tasks/index.php`:

**`OverdueBanner`** — controla o banner de tarefas vencidas:
- Toggle expand/collapse via botão
- Dismiss com `localStorage` (`crm_overdue_dismissed`)

**`TaskCalendarManager`** — encapsula a inicialização do FullCalendar:
- Recebe configuração via `data-events-url`, `data-view-url`, `data-create-url`
- `passesFilter(event)` — filtra por tipo e usuário via checkboxes
- `applyFilters()` — re-renderiza eventos após mudança de filtro
- `buildTooltip(event)` — monta HTML do tooltip (usa `textContent`, não `innerHTML` com variável)
- `handleDateClick(info)` — abre modal de criação com data pré-preenchida

Auto-instanciação via `data-crm-widget="task-calendar"`.

### `public/assets/js/pipeline-stages.js`

**`PipelineStagesManager`** — CRUD de estágios do pipeline via AJAX:
- Edit inline (clique no nome do estágio)
- Save/cancel via botões
- Delete com confirmação
- CSRF lido de `<meta name="csrf-token">` (corrige inconsistência atual)

Auto-instanciação via `data-crm-widget="pipeline-stages"`.

### `public/assets/js/client-index.js`

**`ClientIndexManager`** — comportamentos da listagem de clientes:
- Reset do formulário de filtros no submit (preserva `page=1`)
- Quick modals via event delegation em `data-action="open-quick-interaction"` e `data-action="open-quick-task"`
- Lê `data-client-id` e `data-client-name` dos elementos

Substitui funções globais `openQuickInteraction`/`openQuickTask` e `onclick` inline.
Auto-instanciação via `data-crm-widget="client-index"`.

---

## Views modificadas

### `app/Views/tasks/index.php`

- Remove bloco `<script nonce>` do banner (linhas 39-57)
- Remove bloco `<script nonce>` do FullCalendar (linhas 215+)
- Adiciona `data-crm-widget="task-calendar"` com `data-events-url`, `data-view-url`, `data-create-url` no container
- Adiciona `data-crm-widget="overdue-banner"` no container do banner
- Define `$pageScripts = ['/public/assets/js/tasks.js']`

### `app/Views/pipeline/stages.php`

- Remove bloco `<script>` inline (~150 linhas)
- Remove `let csrfToken = '<?= htmlspecialchars(...) ?>'` (violação do padrão Fase 2)
- Adiciona `data-crm-widget="pipeline-stages"` no container
- Define `$pageScripts = ['/public/assets/js/pipeline-stages.js']`

### `app/Views/clients/index.php`

- Remove dois blocos `<script>` inline (filter reset e funções globais)
- Remove `onclick="openQuickInteraction(...)"` e `onclick="openQuickTask(...)"` dos botões
- Adiciona `data-action="open-quick-interaction"`, `data-client-id`, `data-client-name` nos botões
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
| `app/Models/*.php` | Gap-fill: métodos públicos sem docblock |

Regra: se um método já tem docblock completo, não reescrever — apenas gap-fill.

---

## Critérios de sucesso

- `tasks/index.php` não contém `<script>` com lógica (apenas eventualmente injeção de dados se necessário)
- `pipeline/stages.php` não contém `<script>` inline nem `csrfToken` via PHP inline
- `clients/index.php` não contém `<script>` inline nem `onclick` com funções JS
- CSRF em `pipeline-stages.js` lido de `<meta name="csrf-token">`
- Nenhuma função global nova introduzida (apenas `window.CRM` existente)
- Todos os métodos públicos dos alvos listados têm `@param`/`@return`
- Nenhum comportamento observável pelo usuário muda

---

## Fora de escopo

- Scripts de injeção de dados (`dashboard/index.php`, `acompanhamento/index.php`)
- Anti-FOUC em `errors/404.php`
- Bundler, TypeScript, testes automatizados
- Criação de `Tenant` model
- Mudanças de schema SQL
- Novos endpoints ou rotas
