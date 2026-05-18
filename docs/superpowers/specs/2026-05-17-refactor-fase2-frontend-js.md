# Refatoração Frontend — Fase 2: Extração de JS Inline

**Data:** 2026-05-17
**Concluída:** 2026-05-18
**Status:** ✅ Concluída
**Escopo:** Extrair JavaScript inline de `main.php` e quatro views para arquivos externos com ES6 Classes + IIFE
**Fases anteriores:** Fase 1 (backend — concluída)
**Fases seguintes:** Fase 3 (polimento)

## Resultado

- 6 arquivos JS criados em `public/assets/js/`: `toast.js`, `notifications.js`, `layout.js`, `client-form.js`, `client-show.js`, `cold-contacts.js`
- ~1.850 linhas de JS inline removidas das views
- `window.CRM` como único global novo; `window.crmToast` mantém retrocompatibilidade
- Todos os scripts no `<head>` com `defer`; slot `$pageScripts` para scripts específicos de view
- Verificado em produção (localhost:8000): HTTP 200 em todas as rotas, meta tags e scripts defer presentes, zero JS inline nas views de escopo

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
        constructor(rootEl) { ... }
    }

    // Auto-instanciação: localiza todos os widgets desta classe e instancia
    window.CRM = window.CRM || {};
    document.querySelectorAll('[data-crm-widget="example"]').forEach(el => {
        window.CRM.example = new ExampleManager(el);
    });
})();
```

Com `defer`, o DOM já está completamente parseado quando o script executa — não é necessário `DOMContentLoaded`. Nenhum `type="module"`, nenhum bundler — compatível com a CSP nonce atual e com a infra de build existente (apenas Tailwind via npm).

### Namespace

`window.CRM` é o único global introduzido. Cada arquivo adiciona uma propriedade:

| Propriedade | Arquivo | Tipo |
|---|---|---|
| `window.CRM.toast` | `toast.js` | instância de `ToastManager` |
| `window.CRM.notifications` | `notifications.js` | instância de `NotificationManager` |
| `window.CRM.layout` | `layout.js` | instância de `LayoutManager` |
| `window.CRM.clientForm` | `client-form.js` | instância de `ClientForm` (ou array se múltiplos) |
| `window.CRM.clientShow` | `client-show.js` | instância de `ClientShow` |
| `window.CRM.coldContacts` | `cold-contacts.js` | instância de `ColdContactManager` |

### CSRF Token — padrão único

O token CSRF é exposto em uma única `<meta>` no `<head>` de `main.php`:

```html
<meta name="csrf-token" content="<?= htmlspecialchars(CsrfMiddleware::getToken(), ENT_QUOTES, 'UTF-8') ?>">
```

Qualquer módulo que faça POST lê com:
```js
document.querySelector('meta[name="csrf-token"]').content
```

Elimina o risco de esquecer o token em algum widget e centraliza a renovação em um único lugar.

---

## Novos arquivos

### 1. `public/assets/js/toast.js`

**Classe:** `ToastManager`

**Responsabilidade:** criar, empilhar e remover toasts visuais.

**Métodos públicos:**
- `show(message, type, opts): Function` — cria um toast e retorna `removeToast`. `type`: `'task' | 'birthday' | 'success' | 'error' | 'info'`. `opts.duration` (ms, default 8000; 0 = persistente).

**Comportamento:**
- Cria `#crm-toast-container` no `document.body` se não existir (estilos inline, independe do Tailwind build)
- Empilha com `flex-direction: column-reverse` (novo toast aparece acima do anterior)
- Botão `×` remove imediatamente; TTL remove automaticamente

**Auto-instanciação:** instancia diretamente (sem `data-crm-widget` — é utilitário global):
```js
window.CRM = window.CRM || {};
window.CRM.toast = new ToastManager();
```

**Retrocompatibilidade:**
```js
window.crmToast = window.CRM.toast.show.bind(window.CRM.toast);
```
Views que chamam `window.crmToast(...)` continuam funcionando sem alteração.

---

### 2. `public/assets/js/notifications.js`

**Classe:** `NotificationManager`

**Dependência:** `window.CRM.toast` — deve carregar após `toast.js` (garantido pela ordem no `<head>`).

**Responsabilidade:** polling de notificações, badge, dropdown, integração com `ToastManager`.

**Métodos privados:**
- `#fetchNotifications()` — `fetch('/api/tasks/upcoming')`, aplica dismissed set
- `#render()` — atualiza lista e badge com `createElement`/`textContent`
- `#updateBadge()` — mostra/oculta contador
- `#dismiss(key)` / `#dismissAll(keys)` — persiste no `localStorage` com `try/catch`
- `#cleanupDismissed(activeKeys)` — remove chaves obsoletas do `localStorage` com `try/catch`

**Estado interno (propriedades privadas):**
- `#alerts` — array de notificações ativas
- `#toasted` — `Set` de chaves já exibidas como toast (evita repetição)
- `#DISMISS_KEY = 'crm.notif_dismissed'`

**Segurança no `localStorage`:** toda interação com `localStorage` em bloco `try/catch` para suportar navegadores com modo restrito de privacidade ou cota esgotada — a falha é silenciosa, não quebra a execução.

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

**Responsabilidade:** coordenar máscaras de input, consulta de CEP, campos condicionais e normalização de datas no submit de formulários de cliente.

**Construtor:** `constructor(formEl)` — recebe o elemento `<form>` com `data-mode="create|edit"`.

**Métodos privados:**
- `#applyMasks()` — phone (`(99) 9 9999-9999`), CPF/CNPJ (detecta 11 vs 14 dígitos), CEP (`99999-999`), data de nascimento (`dd/mm/yyyy`). As máscaras são específicas de campos do formulário de cliente — distintas das máscaras globais de `masks.js` (que opera por `data-mask` em qualquer campo).
- `#fetchAddress(cep)` — método estático privado; chama `https://viacep.com.br/ws/{cep}/json/` e retorna o objeto de endereço. Separa a lógica de rede do preenchimento DOM.
- `#initViaCep()` — listener no campo CEP; ao digitar 8 dígitos, chama `#fetchAddress()` e preenche logradouro, bairro, cidade, estado via `element.value`
- `#initConditionals()` — show/hide de campos dependentes do `pipeline_stage_id` (ex: `closed_at` só aparece em etapa "Venda Fechada")
- `#fixDatesBeforeSubmit()` — no evento `submit`: valida campos de data com regex `^\d{2}\/\d{2}\/\d{4}$` antes de converter; se inválido, cancela o submit e exibe erro inline. Converte válidos para `yyyy-mm-dd`.

**Auto-instanciação:**
```js
document.querySelectorAll('[data-crm-widget="client-form"]').forEach(el => {
    const instance = new ClientForm(el);
    window.CRM = window.CRM || {};
    window.CRM.clientForm = instance; // último formulário vence (normalmente há apenas 1)
});
```

**Em `create.php` e `edit.php`:** o `<form>` recebe `data-crm-widget="client-form" data-mode="create"`. Nenhum script inline nas views.

> **`masks.js` vs `client-form.js`:** `masks.js` já existe e opera globalmente em qualquer elemento com `data-mask="currency|digits"`. `client-form.js` contém máscaras específicas dos campos de cliente (phone, CPF/CNPJ, CEP, data) que têm lógica própria de formatação e não são intercambiáveis com o sistema `data-mask`.

---

### 5. `public/assets/js/client-show.js`

**Classe:** `ClientShow`

**Responsabilidade:** toda a interatividade da página de detalhe do cliente.

**Construtor:** `constructor(rootEl)` — lê `data-csrf` do elemento raiz via `document.querySelector('meta[name="csrf-token"]').content`.

**Métodos privados (um por domínio — SRP):**
- `#initInteractions()` — edição inline de interações (toggle view/edit, AJAX save/delete). Usa **event delegation** no container pai, não addEventListener em cada linha.
- `#initNotes()` — autosave de notas com debounce (500ms)
- `#initSales()` — criação e remoção de cotas via AJAX; insere linhas na tabela via `createElement`. Usa **event delegation** na tabela de cotas.
- `#initPayments()` — marcar cota como paga, atualizar data formatada na UI via `textContent`
- `#initTaskModal()` — abrir modal de nova tarefa, submit AJAX, atualizar lista

**Segurança:** qualquer dado variável (nome, email, texto livre) inserido no DOM usa `element.textContent`. `innerHTML` apenas para estrutura HTML fixa sem variável do servidor.

**Auto-instanciação:**
```js
document.querySelectorAll('[data-crm-widget="client-show"]').forEach(el => {
    window.CRM = window.CRM || {};
    window.CRM.clientShow = new ClientShow(el);
});
```

---

### 6. `public/assets/js/cold-contacts.js`

**Classe:** `ColdContactManager`

**Responsabilidade:** orquestrar a tela de contatos frios — delega responsabilidades específicas a classes auxiliares internas.

**Classes auxiliares (definidas dentro do mesmo arquivo, dentro da IIFE):**

- `TableRenderer` — recebe array de contatos e retorna nós DOM via `createElement`/`textContent`. Nunca usa `innerHTML` com dado variável. Único lugar que sabe como renderizar uma linha de contato.
- `FilterState` — encapsula os filtros ativos (`nome`, `dia`, `telefone_enviado`) e a página atual. Expõe `toQueryParams()` para construir a querystring do fetch.
- `ExcelService` — encapsula leitura de XLSX via SheetJS (`#importXlsx`) e geração de CSV (`#exportCsv`). Isolado para facilitar troca futura de biblioteca.

**`ColdContactManager` (orquestrador):**
- `#yearMonth` — mês/ano atualmente carregado
- `#filterState` — instância de `FilterState`
- `#renderer` — instância de `TableRenderer`
- `#excelService` — instância de `ExcelService`
- `#loadTable()` — fetch `/cold-contacts/list-json` com params de `#filterState`, chama `#renderer`
- `#setupFilters()` — listeners nos inputs com debounce, atualiza `#filterState` e recarrega
- `#handleBulkUpdate(ids, data)` — AJAX bulk update + reload. Lê CSRF de `<meta name="csrf-token">`.
- Paginação por **event delegation** no container da tabela

Elimina as variáveis globais `currentYearMonth`, `currentPage`, `filterStatus`, `totalPages`.

**Segurança:** `TableRenderer` nunca usa `innerHTML` com campo de contato. Todo texto de usuário via `textContent`.

---

## Arquivos modificados

| Arquivo | Mudança |
|---|---|
| `app/Views/layouts/main.php` | Adiciona `<meta name="csrf-token">` no `<head>`; remove 2 blocos `<script>` inline (exceto anti-FOUC); adiciona 5 `<script defer src>` no `<head>`; adiciona slot `<?= $pageScripts ?? '' ?>` após os 5 scripts |
| `app/Views/clients/create.php` | Remove bloco `<script>` inline; adiciona `data-crm-widget="client-form" data-mode="create"` no `<form>`; define `$pageScripts` com `<script defer src="client-form.js">` |
| `app/Views/clients/edit.php` | Idem `create.php` com `data-mode="edit"` |
| `app/Views/clients/show.php` | Remove 6 blocos `<script>` inline; adiciona `data-crm-widget="client-show"` no elemento raiz; define `$pageScripts` com `<script defer src="client-show.js">` |
| `app/Views/cold-contacts/index.php` | Remove bloco `<script>` inline; adiciona `data-crm-widget="cold-contacts"` no elemento raiz; define `$pageScripts` com `<script defer src="cold-contacts.js">` |

---

## Ordem de carregamento e estratégia `defer`

**Problema:** os scripts das views são renderizados dentro de `<main>` (via `$content`), portanto executariam antes dos scripts do layout colocados no rodapé do `<body>`. Isso quebraria dependências como `window.CRM.toast`.

**Solução: `defer` + `<head>`**

Todos os `<script src>` — do layout e das views — usam `defer` e são declarados no `<head>`. Scripts com `defer` executam em ordem de documento após o HTML ser totalmente parseado, garantindo que `toast.js` (declarado primeiro) sempre execute antes de `client-show.js` (declarado depois via `$pageScripts`).

```html
<!-- <head> de main.php — scripts existentes + novos -->
<meta name="csrf-token" content="<?= htmlspecialchars(CsrfMiddleware::getToken(), ENT_QUOTES, 'UTF-8') ?>">

<!-- Novos: layout global -->
<script nonce="<?= CSP_NONCE ?>" defer src=".../toast.js?v=..."></script>
<script nonce="<?= CSP_NONCE ?>" defer src=".../notifications.js?v=..."></script>
<script nonce="<?= CSP_NONCE ?>" defer src=".../layout.js?v=..."></script>

<!-- Pré-existentes (movidos para <head> com defer) -->
<script nonce="<?= CSP_NONCE ?>" defer src=".../masks.js?v=..."></script>
<script nonce="<?= CSP_NONCE ?>" defer src=".../custom-select.js?v=..."></script>

<!-- Slot de página: scripts específicos da view (também com defer) -->
<?= $pageScripts ?? '' ?>
```

> **`masks.js` e `custom-select.js`** são arquivos **pré-existentes** em `public/assets/js/`. Eram carregados no rodapé do `<body>` — esta fase os move para o `<head>` com `defer` junto com os demais.

`$pageScripts` é setado pela view antes de ser capturado pelo layout e contém apenas tags `<script defer src nonce>` — sem lógica inline.

Como `defer` garante que o DOM está pronto na execução, não é necessário `DOMContentLoaded` dentro das IIFEs. A auto-instanciação usa `querySelectorAll` diretamente.

---

## Regras de segurança (aplicadas em todos os arquivos)

1. **Dado variável → `textContent`** — qualquer valor vindo do servidor ou do usuário é inserido via `element.textContent`, nunca via `innerHTML`
2. **HTML estrutural fixo → `innerHTML` permitido** — botões e ícones sem variável podem usar `innerHTML`
3. **CSRF via `<meta name="csrf-token">`** — único ponto de leitura do token; todos os módulos que fazem POST leem desta meta tag
4. **Sem `eval()`**, sem `new Function()`
5. **Nonce obrigatório** em todo `<script>` — externo ou inline
6. **`localStorage` sempre em `try/catch`** — falha silenciosa em modo restrito de privacidade
7. **Event delegation** em containers com filhos dinâmicos — nunca `addEventListener` em cada nó gerado

---

## Critérios de sucesso

- Nenhum `<script>` com lógica JS nas views modificadas (exceto o anti-FOUC de 3 linhas em `main.php`)
- `window.CRM` é o único global novo introduzido
- `window.crmToast` continua funcionando por retrocompatibilidade
- Nenhuma rota existente quebra
- Nenhum `innerHTML` recebe valor de variável do servidor ou input do usuário
- Sintaxe JS válida em todos os arquivos (verificável com `node --check arquivo.js`)
- Dark mode, sidebar, notificações, toasts, máscaras, ViaCEP e cotas de consórcio funcionam após a extração

---

## Fora de escopo desta fase

- `tasks/index.php` — bem estruturado com FullCalendar, fica para Fase 3 se necessário
- Bundler (Vite/webpack) e `type="module"`
- TypeScript
- Testes automatizados de frontend
- Mudanças de comportamento observável pelo usuário
- Troca de `<input type="text">` por `<input type="date">` nos formulários
