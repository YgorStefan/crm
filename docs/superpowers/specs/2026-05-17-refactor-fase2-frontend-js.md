# Refatoração Frontend — Fase 2: Extração de JS Inline

**Data:** 2026-05-17
**Escopo:** Extrair JavaScript inline de `main.php` e quatro views para arquivos externos com ES6 Classes + IIFE
**Fases anteriores:** Fase 1 (backend — concluída)
**Fases seguintes:** Fase 3 (polimento)

---

## Contexto

O projeto tem ~1.850 linhas de JavaScript inline distribuídas em:

| Origem | Linhas JS | Problema |
|---|---|---|
| `app/Views/layouts/main.php` | ~315 | Dois blocos globais sem modularização |
| `app/Views/clients/show.php` | ~430 | 6 blocos soltos, estado global via `window.crmCsrfToken` |
| `app/Views/clients/create.php` | ~114 | Duplicado em `edit.php` |
| `app/Views/clients/edit.php` | ~116 | Cópia de `create.php` |
| `app/Views/cold-contacts/index.php` | ~400 | 4 variáveis globais, responsabilidades misturadas |

Problemas comuns: globals soltas, `innerHTML` com dados de usuário (risco XSS), lógica duplicada entre `create.php`/`edit.php`, ausência de escopo.

---

## Arquitetura

### Padrão: ES6 Classes + IIFE

Cada arquivo externo usa IIFE para isolar escopo e registra no namespace global `window.CRM`:

```js
(function () {
    'use strict';

    class ExampleManager {
        constructor(options) { ... }
        // ...
    }

    window.CRM = window.CRM || {};
    window.CRM.example = new ExampleManager();
})();
```

Nenhum bundler, nenhum `type="module"` — compatível com a CSP nonce atual e com a infra de build existente (apenas Tailwind via npm).

### Namespace

`window.CRM` é o único global introduzido. Cada arquivo adiciona uma propriedade:

| Propriedade | Arquivo | Tipo |
|---|---|---|
| `window.CRM.toast` | `toast.js` | instância de `ToastManager` |
| `window.CRM.notifications` | `notifications.js` | instância de `NotificationManager` |
| `window.CRM.layout` | `layout.js` | instância de `LayoutManager` |
| `window.CRM.clientForm` | `client-form.js` | instância de `ClientForm` |
| `window.CRM.clientShow` | `client-show.js` | instância de `ClientShow` |
| `window.CRM.coldContacts` | `cold-contacts.js` | instância de `ColdContactManager` |

---

## Novos arquivos

### 1. `public/assets/js/toast.js`

**Classe:** `ToastManager`

**Responsabilidade:** criar, empilhar e remover toasts visuais.

**Métodos públicos:**
- `show(message, type, opts): Function` — cria um toast e retorna `removeToast`. `type` é `'task' | 'birthday' | 'success' | 'error' | 'info'`. `opts.duration` (ms, default 8000; 0 = persistente).

**Comportamento:**
- Cria `#crm-toast-container` no `document.body` se não existir (estilos inline, independe do Tailwind build)
- Empilha com `flex-direction: column-reverse` (novo toast aparece acima do anterior)
- Botão `×` remove imediatamente; TTL remove automaticamente

**Retrocompatibilidade:**
```js
window.crmToast = window.CRM.toast.show.bind(window.CRM.toast);
```
Views que chamam `window.crmToast(...)` continuam funcionando sem alteração.

---

### 2. `public/assets/js/notifications.js`

**Classe:** `NotificationManager`

**Responsabilidade:** polling de notificações, badge, dropdown, integração com `ToastManager`.

**Dependência:** `window.CRM.toast` — deve carregar após `toast.js`.

**Métodos internos:**
- `#fetchNotifications()` — `fetch('/api/tasks/upcoming')`, aplica dismissed set
- `#render()` — atualiza lista e badge
- `#updateBadge()` — mostra/oculta contador
- `#dismiss(key)` / `#dismissAll()` — persiste no `localStorage`
- `#cleanupDismissed(activeKeys)` — remove chaves obsoletas do `localStorage`

**Estado interno (propriedades privadas):**
- `#alerts` — array de notificações ativas
- `#toasted` — `Set` de chaves já exibidas como toast (evita repetição)
- `#DISMISS_KEY` — chave do localStorage

**Inicialização:** chama `#fetchNotifications()` imediatamente + `setInterval` a cada 60s.

---

### 3. `public/assets/js/layout.js`

**Classe:** `LayoutManager`

**Responsabilidade:** sidebar, tema, relógio, flash auto-dismiss.

**Métodos públicos:**
- `applyTheme(isDark: boolean)` — alterna classe `.dark` no `<html>`, persiste em `localStorage`, dispara `CustomEvent('themeChange')`

**Métodos privados:**
- `#initClock()` — atualiza `#clock` a cada 60s
- `#initTheme()` — listener no `#themeToggle` + atalho `Ctrl+Shift+L`
- `#initSidebar()` — `setSidebarMini`, open/close mobile, listeners de resize e backdrop
- `#initFlash()` — `setTimeout` de 5s para remover `#flashMsg`

**Nota:** o script anti-FOUC no `<head>` (lê `localStorage` e aplica `.dark` antes do CSS) permanece inline em `main.php` — é requisito funcional, não pode ser externalizado.

---

### 4. `public/assets/js/client-form.js`

**Classe:** `ClientForm`

**Responsabilidade:** máscaras de input, integração ViaCEP, campos condicionais, normalização de datas no submit.

**Construtor:** `constructor(formEl)` — recebe o elemento `<form>` com `data-mode="create|edit"`.

**Métodos privados:**
- `#applyMasks()` — phone (formato `(99) 9 9999-9999`), CPF/CNPJ (detecta 11 vs 14 dígitos), CEP, data de nascimento (`dd/mm/yyyy`)
- `#initViaCep()` — listener no campo `#zip_code`; ao digitar 8 dígitos, busca `https://viacep.com.br/ws/{cep}/json/` e preenche logradouro, bairro, cidade, estado
- `#initConditionals()` — show/hide de campos dependentes do `pipeline_stage_id` selecionado (ex: campo `closed_at` aparece só em etapa "Venda Fechada")
- `#fixDatesBeforeSubmit()` — converte campos `dd/mm/yyyy` → `yyyy-mm-dd` antes do `submit`

**Auto-instanciação:** o arquivo detecta o elemento via `data-crm-widget="client-form"` e instancia sem script adicional:
```js
// Bottom of client-form.js (inside IIFE)
document.addEventListener('DOMContentLoaded', () => {
    const el = document.querySelector('[data-crm-widget="client-form"]');
    if (el) {
        window.CRM = window.CRM || {};
        window.CRM.clientForm = new ClientForm(el);
    }
});
```

**Em `create.php` e `edit.php`:** o `<form>` recebe `data-crm-widget="client-form" data-mode="create"`. Nenhum script de inicialização separado necessário.

---

### 5. `public/assets/js/client-show.js`

**Classe:** `ClientShow`

**Responsabilidade:** toda a interatividade da página de detalhe do cliente.

**Construtor:** lê `data-csrf` do elemento raiz (ex: `<div data-csrf="<?= ... ?>">`) — elimina `window.crmCsrfToken`.

**Métodos privados:**
- `#initInteractions()` — edição inline de interações (toggle view/edit, AJAX save/delete)
- `#initNotes()` — autosave de notas com debounce
- `#initSales()` — criação e remoção de cotas via AJAX, inserção de linha na tabela
- `#initPayments()` — marcar cota como paga, atualizar data formatada na UI
- `#initTaskModal()` — abrir modal de nova tarefa, submit AJAX, atualizar lista

**Segurança:** qualquer dado variável (nome, email, texto livre) inserido no DOM usa `element.textContent` ou `element.setAttribute`. `innerHTML` apenas para estrutura HTML fixa sem variável do servidor.

---

### 6. `public/assets/js/cold-contacts.js`

**Classe:** `ColdContactManager`

**Responsabilidade:** gerenciamento completo da tela de contatos frios (modal com tabela, filtros, paginação, bulk update, export, import XLSX).

**Estado (propriedades privadas):**
- `#yearMonth` — mês/ano atualmente carregado
- `#page` — página atual
- `#filters` — objeto de filtros ativos
- `#totalPages` — para controle de paginação

Elimina as variáveis globais `currentYearMonth`, `currentPage`, `filterStatus`, `totalPages` do escopo do arquivo original.

**Métodos privados:**
- `#loadTable()` — fetch `/cold-contacts/list-json`, chama `#renderRows()`
- `#renderRows(contacts)` — cria elementos DOM via `createElement`/`textContent` (sem `innerHTML` com dado variável)
- `#setupFilters()` — listeners nos inputs de filtro com debounce
- `#handleBulkUpdate(ids, data)` — AJAX bulk update + reload
- `#exportCsv()` — gera e baixa CSV a partir dos dados carregados
- `#importXlsx(file)` — lê XLSX via SheetJS, exibe preview, dispara import

**Segurança:** `renderRows()` nunca usa `innerHTML` com campo de contato (nome, telefone, email). Elementos de texto sempre via `textContent`.

---

## Arquivos modificados

| Arquivo | Mudança |
|---|---|
| `app/Views/layouts/main.php` | Remove 2 blocos `<script>` inline (exceto anti-FOUC); adiciona 5 `<script defer src>` no `<head>` |
| `app/Views/clients/create.php` | Remove bloco `<script>` inline; adiciona `data-crm-widget` no `<form>`; adiciona `<script defer src="client-form.js">` no `<head>` via slot |
| `app/Views/clients/edit.php` | Idem `create.php` |
| `app/Views/clients/show.php` | Remove 6 blocos `<script>` inline; adiciona `data-crm-widget` + `data-csrf` no elemento raiz; adiciona `<script defer src="client-show.js">` via slot |
| `app/Views/cold-contacts/index.php` | Remove bloco `<script>` inline; adiciona `data-crm-widget` no elemento raiz; adiciona `<script defer src="cold-contacts.js">` via slot |

---

## Ordem de carregamento e estratégia `defer`

**Problema:** os scripts das views são renderizados dentro de `<main>` (via `$content`), portanto executariam antes dos scripts do layout colocados no rodapé do `<body>`. Isso quebraria dependências como `window.CRM.toast`.

**Solução: `defer` + `<head>`**

Todos os `<script src>` — do layout e das views — usam `defer` e são declarados no `<head>`. Scripts com `defer` executam em ordem de documento após o HTML ser totalmente parseado, garantindo que `toast.js` (declarado primeiro no `<head>`) sempre execute antes de `client-show.js` (declarado depois).

```html
<!-- <head> de main.php -->
<script nonce="<?= CSP_NONCE ?>" defer src=".../toast.js?v=..."></script>
<script nonce="<?= CSP_NONCE ?>" defer src=".../notifications.js?v=..."></script>
<script nonce="<?= CSP_NONCE ?>" defer src=".../layout.js?v=..."></script>
<script nonce="<?= CSP_NONCE ?>" defer src=".../masks.js?v=..."></script>
<script nonce="<?= CSP_NONCE ?>" defer src=".../custom-select.js?v=..."></script>
<?= $pageScripts ?? '' ?>  <!-- scripts de página também com defer, injetados aqui -->
```

`$pageScripts` é uma variável PHP setada pela view antes de ser capturada pelo layout — contém apenas tags `<script defer src nonce>`, sem lógica inline.

**Auto-instanciação via `DOMContentLoaded`:** como todos os scripts são `defer`, o DOM está pronto quando executam. Cada arquivo de página usa `DOMContentLoaded` internamente para localizar seu elemento raiz via `data-crm-widget` e instanciar a classe — sem nenhum script de inicialização separado nas views.

`toast.js` antes de `notifications.js` — dependência direta garantida pela ordem no `<head>`.

---

## Regras de segurança (aplicadas em todos os arquivos)

1. **Dado variável → `textContent`** — qualquer valor vindo do servidor ou do usuário é inserido via `element.textContent`, nunca via `innerHTML`
2. **HTML estrutural fixo → `innerHTML` permitido** — botões e ícones sem variável podem usar `innerHTML`
3. **CSRF token via `data-*`** — lido de atributo DOM no construtor; não injetado como literal JS inline
4. **Sem `eval()`**, sem `new Function()`
5. **Nonce obrigatório** em todo `<script>` — seja externo ou inline

---

## Critérios de sucesso

- Nenhum `<script>` inline nos arquivos modificados com mais de 2 linhas (exceto o anti-FOUC do `<head>` e linhas de inicialização mínima)
- `window.CRM` é o único global novo introduzido
- `window.crmToast` continua funcionando por retrocompatibilidade
- Nenhuma rota existente quebra
- Nenhum `innerHTML` recebe valor de variável do servidor ou input do usuário
- Sintaxe JS válida em todos os arquivos (verificável com `node --check` ou `php -l` equivalente)
- Dark mode, sidebar, notificações, toasts, máscaras, ViaCEP e cotas de consórcio funcionam após a extração

---

## Fora de escopo desta fase

- `tasks/index.php` — bem estruturado com FullCalendar, fica para Fase 3 se necessário
- Bundler (Vite/webpack)
- TypeScript
- Testes automatizados de frontend
- Mudanças de comportamento observável pelo usuário
