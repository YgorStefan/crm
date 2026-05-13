# Design Spec — Reforma Visual Completa do CRM

**Data:** 2026-05-12
**Status:** Aprovado

---

## Objetivo

Reformar visualmente todo o projeto CRM para ter um visual responsivo, moderno e profissional, com suporte a tema claro/escuro, sidebar colapsável, ícones SVG consistentes e botões de ação padronizados.

---

## Decisões de Design Aprovadas

### 1. Tema Padrão: Clean Light com toggle para Dark Pro

- **Padrão:** tema claro — fundo `gray-50/100`, cards brancos, sombras sutis, estilo HubSpot/Pipedrive
- **Dark mode:** fundo `slate-900`, cards `slate-800`, bordas `white/8`, acentos índigo mantidos
- **Implementação:** `darkMode: 'class'` no `tailwind.config.js`, classe `dark` na tag `<html>`
- **Persistência:** `localStorage.getItem('theme')` — restaurado no `<head>` para evitar flash
- **Primeira visita:** usa `prefers-color-scheme: dark` para honrar a preferência do sistema operacional

### 2. Anti-FOUC (Flash of Unstyled Content)

- **Script inline** no `<head>` de **ambos** os layouts (`main.php` e `blank.php`), posicionado **antes** do link CSS
- Lê `localStorage.getItem('theme')` e adiciona classe `dark` ao `<html>` antes de qualquer renderização
- Fallback para `window.matchMedia('(prefers-color-scheme: dark)').matches` quando sem localStorage
- Todos os scripts inline usam `nonce="<?= CSP_NONCE ?>"` (middleware CSP ativo no projeto)

```html
<script nonce="<?= CSP_NONCE ?>">
  (function() {
    var t = localStorage.getItem('theme');
    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
  })();
</script>
```

### 3. Toggle de Tema — no Topbar

- Posição: topbar, ao lado do sino de notificações
- Visual: switch pill (36×20px), knob branco, fundo `#6366f1` (claro) / `#4f46e5` (escuro)
- Ícone sol (claro) / lua (escuro) em SVG ao lado do switch
- Ao clicar: alterna classe `dark` no `<html>` e salva preferência no `localStorage`
- **Atalho de teclado:** `Ctrl+Shift+L` — alterna tema sem precisar do mouse (acessibilidade e power users)

### 4. Tipografia — Inter via Google Fonts

- Substituir a fonte padrão do sistema por **Inter** (Google Fonts, variável `wght` 300–700)
- Carregamento otimizado: `rel="preconnect"` + `display=swap`
- Aplicada globalmente via `body { font-family: 'Inter', sans-serif; }`
- Coerente com o visual HubSpot/Pipedrive, escala bem em light e dark

### 5. Sidebar Colapsável em Dois Estados

| Estado | Largura | Conteúdo |
|--------|---------|----------|
| Expandida (padrão desktop) | `w-64` (256px) | Logo + labels + ícones |
| Mini (colapsada) | `w-16` (64px) | Só ícones centralizados |
| Mobile (< lg) | overlay 0→256px | Backdrop + slide, sem mini |

- **Gatilho:** botão hambúrguer no topo da sidebar (desktop) / no topbar (mobile)
- **Transição:** `transition-all duration-300` via CSS class `.sidebar-mini` no wrapper pai
- **Sem `marginLeft` inline:** o main content usa `lg:ml-16` ou `lg:ml-64` via classe CSS — corrige bug de dessincronia no resize
- **Transição de labels:** `opacity-0` aplicado **antes** da transição de largura (evita texto cortado durante animação)
- **Tooltips:** no modo mini, cada ícone de nav tem atributo `title` nativo (tooltip do browser)
- **Persistência:** `localStorage.getItem('sidebar')` — restaura estado ao recarregar
- **Tailwind safelist:** classes `lg:ml-16` e `lg:ml-64` adicionadas ao `safelist` em `tailwind.config.js` para não serem purgadas

### 6. Ícones SVG — substitui todos os emojis

Todos os emojis de navegação, ações e decorativos são substituídos por SVG inline (estilo Heroicons, `stroke="currentColor"`, `stroke-width="2"`).

| Contexto | Ícone SVG |
|----------|-----------|
| Dashboard | grid 2×2 |
| Clientes | pessoas (users) |
| Pipeline | gráfico de linha (activity) |
| Calendário | calendário |
| Contatos frios | telefone |
| Acompanhamento | linha de tendência |
| Usuários (admin) | pessoa única |
| Configurações | engrenagem/roda |
| AVA Pro / links externos | seta externa (external-link) |
| Webmail | envelope |
| Logout | seta de saída |
| Notificações | sino |
| Toggle tema | sol / lua |
| Hambúrguer | três traços |
| Empresa (login) | prédio/building |

Adapta ao tema automaticamente via `currentColor`.

### 7. Botões de Ação — Padrão Unificado

**Regra:**

- **Ações inline** (tabelas, cards kanban, listas): somente ícone SVG + `title` tooltip
  - Agrupados num "pill" com fundo `gray-50`, borda `gray-200`, `border-radius: 8px`
  - Hover: fundo colorido suave por tipo (ver=índigo, editar=âmbar, chat=verde, tarefa=violeta, excluir=vermelho)
- **Botões primários de criação** (Novo Cliente, Nova Tarefa, etc.): ícone `+` SVG + texto
- **Excluir:** sempre vermelho (`red-600`), sempre com `confirm()` nativo antes de agir
- **Escopo:** padronizar os botões de ação já existentes em todas as páginas (não adicionar novos botões de excluir onde não existem)
- **Dark mode:** hover backgrounds ajustados para opacidade (ex: `rgba(99,102,241,0.15)`)

**Mapeamento de ícones por ação:**

| Ação | Ícone | Cor |
|------|-------|-----|
| Ver detalhes | eye | índigo |
| Editar | pencil | âmbar |
| Nova interação | chat-bubble | verde |
| Nova tarefa | calendar-plus | violeta |
| Excluir | trash | vermelho |
| Mover/arrastar | grip | cinza |

---

## Arquivos a Modificar

### Sistema de Build

- `tailwind.config.js` — adicionar `darkMode: 'class'`, safelist `['lg:ml-16', 'lg:ml-64']`, expandir paleta, adicionar `fontFamily: { sans: ['Inter', ...] }`
- `resources/css/input.css` — adicionar utilitários de sidebar, transições, variantes dark para scrollbar kanban
- Rebuild de `public/assets/css/tailwind.css`

### Layout Principal

- `app/Views/layouts/main.php` — refatoração completa:
  - Script anti-FOUC inline com nonce no `<head>` antes do CSS
  - Google Fonts Inter no `<head>`
  - Sidebar com dois estados via CSS class + transição de opacity nos labels
  - Toggle de tema no topbar (switch pill + ícone SVG + atalho `Ctrl+Shift+L`)
  - JS para tema (localStorage + prefers-color-scheme + classe `dark`)
  - JS para sidebar (localStorage + classes CSS, sem `marginLeft` inline)
  - SVG icons em todos os links de navegação

- `app/Views/layouts/blank.php` — adicionar script anti-FOUC com nonce no `<head>`

### Chart.js — Dark Mode

- `public/assets/js/dashboard.js` — ao alternar o tema, re-renderizar o gráfico com cores adaptadas:
  - Modo claro: cores sólidas existentes
  - Modo escuro: cores com opacidade reduzida, bordas mais claras, grid lines `rgba(255,255,255,0.1)`, labels `#94a3b8`
- Observar evento de toggle via `document.addEventListener('themeChange', ...)` ou verificar classe `dark` no `<html>` no momento do render

### FullCalendar — Dark Mode

- `resources/css/input.css` — adicionar overrides CSS com seletor `.dark .fc-*`:
  - Background de células: `slate-800`
  - Bordas: `white/10`
  - Texto: `slate-200`
  - Evento: background índigo com opacidade

### Kanban Scrollbar — Dark Mode

- `resources/css/input.css` — substituir scrollbar hardcoded por variantes responsivas ao tema:
  - Claro: `#f1f5f9` (track), `#94a3b8` (thumb)
  - Escuro: `#1e293b` (track), `#475569` (thumb)

### Views de Páginas

- `app/Views/dashboard/index.php` — dark: classes nos KPI cards, gráfico
- `app/Views/clients/index.php` — botões de ação → ícone-only, dark: classes na tabela e modais
- `app/Views/clients/show.php` — dark: classes, botão editar → ícone
- `app/Views/clients/create.php` — dark: classes nos formulários
- `app/Views/clients/edit.php` — dark: classes nos formulários
- `app/Views/pipeline/index.php` — dark: classes no kanban, fix overflow tablet (`overflow-x-auto` + colunas largura fixa)
- `app/Views/pipeline/stages.php` — dark: classes
- `app/Views/tasks/index.php` — dark: classes, botões ação → ícones
- `app/Views/cold-contacts/index.php` — dark: classes, botões → ícones
- `app/Views/acompanhamento/index.php` — dark: classes
- `app/Views/auth/login.php` — SVG prédio no lugar do emoji 🏢, dark: classes
- `app/Views/admin/users/index.php` — dark: classes, botões → ícones
- `app/Views/admin/users/create.php` — dark: classes
- `app/Views/admin/users/edit.php` — dark: classes
- `app/Views/settings/index.php` — dark: classes
- `app/Views/components/pagination.php` — dark: classes
- `app/Views/errors/404.php` — dark: classes

---

## Bugs Visuais a Corrigir

| Bug | Correção |
|-----|----------|
| Sidebar usa `marginLeft` inline no JS, dessincroniza no resize | Substituir por classes CSS `lg:ml-0`/`lg:ml-16`/`lg:ml-64` + safelist |
| Emojis misturados com SVG (logout usa SVG, nav usa emoji) | Padronizar tudo SVG |
| Emoji `🏢` na tela de login depende de renderização do OS | SVG de prédio/empresa |
| Pipeline kanban com `flex flex-wrap` cria gaps em tablets | Trocar por `overflow-x-auto` com colunas de largura fixa |
| Scrollbar kanban com cores hardcoded (não responde ao dark mode) | Variantes dark no CSS |
| Chart.js ignora dark mode (cores fixas em JS) | Re-render com cores adaptadas ao tema ativo |
| Flash de tema errado no carregamento (FOUC) | Script anti-FOUC inline com nonce antes do CSS |
| Notificação badge com `hidden` sobrescreve `flex` | Manter como está (já funciona) — apenas adicionar dark: |

---

## Comportamento Responsivo

| Breakpoint | Sidebar | Conteúdo |
|------------|---------|----------|
| `< lg` (mobile/tablet) | overlay deslizante, fecha com backdrop | largura total |
| `≥ lg` (desktop) | mini (w-16) ou expandida (w-64), persistida | margem esquerda via CSS |

---

## Não está no escopo

- Mudança de funcionalidades ou rotas
- Alteração de lógica de backend
- Novo sistema de design tokens além de Tailwind dark:
- Animações complexas ou bibliotecas externas de UI
- Adicionar botões de excluir em páginas que não os possuem atualmente

---

## Critérios de Sucesso

- [ ] Tema claro/escuro alternável em todas as páginas, persistido entre sessões
- [ ] Primeira visita respeita `prefers-color-scheme` do sistema
- [ ] Sem flash de tema errado no carregamento (anti-FOUC)
- [ ] Sidebar colapsa para modo mini (só ícones) e expande, persistido entre sessões
- [ ] Zero emojis na navegação — todos SVG inline
- [ ] Botões de ação inline sem texto em todas as tabelas e listas
- [ ] Build do Tailwind reflete todas as classes dark: (sem classes purgadas)
- [ ] `lg:ml-16` e `lg:ml-64` no safelist — nunca purgadas
- [ ] Nenhum `marginLeft` inline no JS da sidebar
- [ ] Pipeline renderiza corretamente em tablet (sem gaps)
- [ ] Chart.js re-renderiza com cores corretas ao alternar tema
- [ ] FullCalendar legível no dark mode
- [ ] Scrollbar do kanban responsiva ao tema
- [ ] Tipografia Inter carregada e aplicada globalmente
- [ ] Atalho `Ctrl+Shift+L` alterna tema
