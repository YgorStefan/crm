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

### 2. Toggle de Tema — no Topbar

- Posição: topbar, ao lado do sino de notificações
- Visual: switch pill (36×20px), knob branco, fundo `#6366f1` (claro) / `#4f46e5` (escuro)
- Ícone ☀️ (claro) / 🌙 (escuro) em SVG ao lado do switch
- Ao clicar: alterna classe `dark` no `<html>` e salva preferência no `localStorage`

### 3. Sidebar Colapsável em Dois Estados

| Estado | Largura | Conteúdo |
|--------|---------|----------|
| Expandida (padrão desktop) | `w-64` (256px) | Logo + labels + ícones |
| Mini (colapsada) | `w-16` (64px) | Só ícones centralizados |
| Mobile (< lg) | overlay 0→256px | Backdrop + slide, sem mini |

- **Gatilho:** botão hambúrguer no topo da sidebar (desktop) / no topbar (mobile)
- **Transição:** `transition-all duration-300` via CSS class `.sidebar-mini` no wrapper pai
- **Sem `marginLeft` inline:** o main content usa `ml-16` ou `ml-64` via classe CSS, não JS inline style — corrige bug de dessincronia no resize
- **Tooltips:** no modo mini, cada ícone de nav tem atributo `title` nativo (tooltip do browser)
- **Persistência:** `localStorage.getItem('sidebar')` — restaura estado ao recarregar

### 4. Ícones SVG — substitui todos os emojis

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

Adapta ao tema automaticamente via `currentColor`.

### 5. Botões de Ação — Padrão Unificado

**Regra:**

- **Ações inline** (tabelas, cards kanban, listas): somente ícone SVG + `title` tooltip
  - Agrupados num "pill" com fundo `gray-50`, borda `gray-200`, `border-radius: 8px`
  - Hover: fundo colorido suave por tipo (ver=índigo, editar=âmbar, chat=verde, tarefa=violeta, excluir=vermelho)
- **Botões primários de criação** (Novo Cliente, Nova Tarefa, etc.): ícone `+` SVG + texto
- **Excluir:** sempre vermelho (`red-600`), sempre com `confirm()` nativo antes de agir
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

- `tailwind.config.js` — adicionar `darkMode: 'class'`, expandir paleta
- `resources/css/input.css` — adicionar utilitários de sidebar, tooltip, transições
- Rebuild de `public/assets/css/tailwind.css`

### Layout Principal

- `app/Views/layouts/main.php` — refatoração completa:
  - Sidebar com dois estados via CSS class
  - Toggle de tema no topbar
  - JS para tema (localStorage + classe `dark`)
  - JS para sidebar (localStorage + classes CSS, sem `marginLeft` inline)
  - SVG icons em todos os links de navegação

### Views de Páginas

- `app/Views/dashboard/index.php` — dark: classes nos KPI cards, gráfico
- `app/Views/clients/index.php` — botões de ação → ícone-only, dark: classes na tabela e modais
- `app/Views/clients/show.php` — dark: classes, botão editar → ícone
- `app/Views/clients/create.php` — dark: classes nos formulários
- `app/Views/clients/edit.php` — dark: classes nos formulários
- `app/Views/pipeline/index.php` — dark: classes no kanban, fix overflow tablet
- `app/Views/pipeline/stages.php` — dark: classes
- `app/Views/tasks/index.php` — dark: classes, botões ação → ícones
- `app/Views/cold-contacts/index.php` — dark: classes, botões → ícones
- `app/Views/acompanhamento/index.php` — dark: classes
- `app/Views/auth/login.php` — SVG no lugar do emoji 🏢, dark: classes
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
| Sidebar usa `marginLeft` inline no JS, dessincroniza no resize | Substituir por classes CSS `ml-0`/`ml-16`/`ml-64` |
| Emojis misturados com SVG (logout usa SVG, nav usa emoji) | Padronizar tudo SVG |
| Emoji `🏢` na tela de login depende de renderização do OS | SVG de prédio/empresa |
| Pipeline kanban com `flex flex-wrap` cria gaps em tablets | Trocar por `overflow-x-auto` com colunas de largura fixa |
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

---

## Critérios de Sucesso

- [ ] Tema claro/escuro alternável em todas as páginas, persistido entre sessões
- [ ] Sidebar colapsa para modo mini (só ícones) e expande, persistido entre sessões
- [ ] Zero emojis na navegação — todos SVG inline
- [ ] Botões de ação inline sem texto em todas as tabelas e listas
- [ ] Build do Tailwind reflete todas as classes dark: (sem classes purgadas)
- [ ] Nenhum `marginLeft` inline no JS da sidebar
- [ ] Pipeline renderiza corretamente em tablet (sem gaps)
