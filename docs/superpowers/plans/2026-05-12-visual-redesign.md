# Visual Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reformar visualmente o CRM Apollo com tema claro/escuro persistido, sidebar colapsável, SVG icons em toda a navegação e botões de ação padronizados.

**Architecture:** Tailwind `darkMode: 'class'` com classe `dark` no `<html>` gerenciada por JS; sidebar usa CSS class `.sidebar-mini` para alternar entre `w-64` e `w-16` sem `marginLeft` inline; `navLink()` em `helpers.php` é refatorada para aceitar SVG HTML diretamente.

**Tech Stack:** PHP views, Tailwind CSS (build local via `.bin/tailwindcss.exe`), Chart.js, FullCalendar, localStorage para persistência.

---

## SVG Icons Reference

Ícones reutilizáveis ao longo do plano. Todos: `fill="none" stroke="currentColor" stroke-width="2"`.

```html
<!-- Dashboard -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>

<!-- Clientes (users) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>

<!-- Pipeline (activity) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>

<!-- Calendário / Tarefas -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>

<!-- Contatos Frios (phone) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.93 12 19.79 19.79 0 0 1 1.86 3.38 2 2 0 0 1 3.84 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>

<!-- Acompanhamento (trending) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>

<!-- Usuário único (admin) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>

<!-- Configurações (settings) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>

<!-- External link (AVA Pro / CRM Apollo) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>

<!-- Envelope (Webmail) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>

<!-- Hambúrguer -->
<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>

<!-- X (fechar) -->
<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>

<!-- Sol (light mode) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>

<!-- Lua (dark mode) -->
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>

<!-- Plus (+ novo) -->
<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>

<!-- Eye (ver detalhes) -->
<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>

<!-- Pencil (editar) -->
<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>

<!-- Chat bubble (nova interação) -->
<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>

<!-- Calendar+ (nova tarefa) -->
<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>

<!-- Trash (excluir) -->
<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>

<!-- Building (login) -->
<svg width="40" height="40" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="1"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
```

---

## Task 1: Build System — tailwind.config.js + input.css

**Files:**
- Modify: `tailwind.config.js`
- Modify: `resources/css/input.css`

- [ ] **Step 1: Update tailwind.config.js**

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        "./app/Views/**/*.php",
        "./public/assets/js/**/*.js",
    ],
    safelist: [
        'lg:ml-16',
        'lg:ml-64',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
            colors: {
                primary: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                },
            },
        },
    },
    plugins: [],
};
```

- [ ] **Step 2: Update resources/css/input.css**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

/* ── Sidebar mini state ─────────────────────────────────── */
#sidebar {
    transition: width 0.3s ease-in-out;
}
#sidebar .nav-label,
#sidebar .sidebar-brand,
#sidebar .sidebar-section-label,
#sidebar .user-info {
    overflow: hidden;
    white-space: nowrap;
    transition: opacity 0.15s ease-in-out, max-width 0.3s ease-in-out;
    max-width: 200px;
    opacity: 1;
}
#sidebar.sidebar-mini {
    width: 4rem;
}
#sidebar.sidebar-mini .nav-label,
#sidebar.sidebar-mini .sidebar-brand,
#sidebar.sidebar-mini .sidebar-section-label,
#sidebar.sidebar-mini .user-info {
    max-width: 0;
    opacity: 0;
}
#sidebar.sidebar-mini nav a {
    justify-content: center;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
    gap: 0;
}

/* ── Kanban scrollbar ───────────────────────────────────── */
.kanban-scroll::-webkit-scrollbar {
    height: 6px;
}
.kanban-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.dark .kanban-scroll::-webkit-scrollbar-track {
    background: #1e293b;
}
.kanban-scroll::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 3px;
}
.dark .kanban-scroll::-webkit-scrollbar-thumb {
    background: #475569;
}

/* ── Kanban drag states ─────────────────────────────────── */
.dragging {
    opacity: 0.4;
    transform: rotate(2deg);
}
.drag-over {
    outline: 2px dashed #4f46e5;
    outline-offset: 2px;
}

/* ── FullCalendar dark mode overrides ───────────────────── */
.dark .fc {
    color: #cbd5e1;
}
.dark .fc-theme-standard .fc-scrollgrid,
.dark .fc-theme-standard td,
.dark .fc-theme-standard th {
    border-color: rgba(255,255,255,0.08);
}
.dark .fc-col-header-cell-cushion,
.dark .fc-daygrid-day-number,
.dark .fc-list-day-text,
.dark .fc-list-day-side-text {
    color: #94a3b8;
}
.dark .fc-daygrid-day.fc-day-today,
.dark .fc-timegrid-col.fc-day-today {
    background-color: rgba(99, 102, 241, 0.08);
}
.dark .fc-button-primary {
    background-color: #4f46e5;
    border-color: #4338ca;
}
.dark .fc-button-primary:hover {
    background-color: #4338ca;
}
.dark .fc-button-primary:disabled {
    background-color: #334155;
    border-color: #334155;
}
.dark .fc-event {
    background-color: rgba(99, 102, 241, 0.75);
    border-color: #6366f1;
}
.dark .fc-list-event:hover td {
    background-color: rgba(255,255,255,0.05);
}
.dark .fc-popover {
    background-color: #1e293b;
    border-color: rgba(255,255,255,0.08);
}
.dark .fc-popover-header {
    background-color: #0f172a;
    color: #cbd5e1;
}
```

- [ ] **Step 3: Commit**

```bash
git add tailwind.config.js resources/css/input.css
git commit -m "build: darkMode class, safelist, Inter font, dark scrollbar e FullCalendar"
```

---

## Task 2: core/helpers.php — Refatorar navLink() para SVG

**Files:**
- Modify: `core/helpers.php`

- [ ] **Step 1: Atualizar navLink() para aceitar SVG HTML raw e classes do sidebar branco**

Substituir a função `navLink()` inteira (linhas 14–27):

```php
function navLink(string $href, string $svgIcon, string $label, string $currentPath): string
{
    $active = ($currentPath === $href || str_starts_with($currentPath, $href . '/'));
    $base   = 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors';
    $cls    = $active
        ? "$base bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
        : "$base text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200";

    $safeHref  = htmlspecialchars(APP_URL . $href, ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    // $svgIcon is trusted developer-authored inline SVG, never user input
    return "<a href=\"{$safeHref}\" class=\"{$cls}\">{$svgIcon}<span class=\"nav-label ml-1\">{$safeLabel}</span></a>";
}
```

- [ ] **Step 2: Commit**

```bash
git add core/helpers.php
git commit -m "refactor: navLink() aceita SVG HTML raw e suporta dark mode"
```

---

## Task 3: layouts/blank.php + auth/login.php

**Files:**
- Modify: `app/Views/layouts/blank.php`
- Modify: `app/Views/auth/login.php`

- [ ] **Step 1: Reescrever blank.php com anti-FOUC, Inter e dark classes**

```php
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <?php $safeAppUrl = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8'); ?>
    <!-- Anti-FOUC: aplica dark antes do CSS carregar -->
    <script nonce="<?= CSP_NONCE ?>">
        (function() {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $safeAppUrl ?>/assets/css/tailwind.css">
</head>

<body class="bg-gray-50 dark:bg-slate-900 min-h-screen flex items-center justify-center transition-colors duration-300">
    <?= $content ?>
</body>

</html>
```

- [ ] **Step 2: Reescrever login.php — substituir emoji 🏢, adicionar dark classes, SVG eye toggle**

```php
<?php ?>
<div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden">

    <!-- Cabeçalho colorido -->
    <div class="bg-indigo-600 dark:bg-indigo-700 px-8 py-8 text-center">
        <div class="flex justify-center mb-3">
            <svg width="40" height="40" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">
                <rect x="2" y="7" width="20" height="14" rx="1"/>
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                <line x1="12" y1="12" x2="12" y2="16"/>
                <line x1="10" y1="14" x2="14" y2="14"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white"><?= APP_NAME ?></h1>
        <p class="text-indigo-200 text-sm mt-1">Acesse sua conta para continuar</p>
    </div>

    <div class="px-8 py-8">

        <?php if (!empty($timeout)): ?>
        <div class="mb-5 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300 rounded-lg text-sm">
            Sua sessão expirou por inatividade. Por favor, faça login novamente.
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="mb-5 p-3 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg text-sm flex items-center gap-2">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/login" novalidate>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-5">
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">E-mail</label>
                <input
                    type="email" id="email" name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required autocomplete="username" placeholder="seu@email.com"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm
                           bg-white dark:bg-slate-700 text-gray-800 dark:text-white
                           placeholder-gray-400 dark:placeholder-slate-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Senha</label>
                <div class="relative">
                    <input
                        type="password" id="password" name="password"
                        required autocomplete="current-password" placeholder="••••••••"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm
                               bg-white dark:bg-slate-700 text-gray-800 dark:text-white
                               placeholder-gray-400 dark:placeholder-slate-500
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors pr-10">
                    <button type="button" onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition-colors"
                        title="Mostrar/ocultar senha">
                        <span id="eyeIconSvg">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
                       py-2.5 px-4 rounded-lg transition-colors duration-200 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800">
                Entrar no Sistema
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-gray-400 dark:text-slate-600">
            <?= APP_NAME ?> &copy; <?= date('Y') ?>
        </p>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    function togglePassword() {
        const input = document.getElementById('password');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        document.getElementById('eyeIconSvg').innerHTML = isHidden
            ? '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
            : '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
</script>
```

- [ ] **Step 3: Commit**

```bash
git add app/Views/layouts/blank.php app/Views/auth/login.php
git commit -m "feat: anti-FOUC e dark mode no login, SVG substitui emoji 🏢"
```

---

## Task 4: layouts/main.php — Refatoração Completa

**Files:**
- Modify: `app/Views/layouts/main.php`

Este é o arquivo mais crítico. Substituir o conteúdo completo.

- [ ] **Step 1: Reescrever main.php**

```php
<?php $safeAppUrl = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8'); ?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:,">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Anti-FOUC: aplica dark class antes do CSS para evitar flash -->
    <script nonce="<?= CSP_NONCE ?>">
        (function() {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $safeAppUrl ?>/assets/css/tailwind.css">
    <script nonce="<?= CSP_NONCE ?>" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script nonce="<?= CSP_NONCE ?>" src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
    <script nonce="<?= CSP_NONCE ?>" src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.20/locales-all.global.min.js"></script>
</head>

<body class="bg-gray-100 dark:bg-slate-900 font-sans text-gray-800 dark:text-slate-200 transition-colors duration-300">

<div class="flex h-screen overflow-hidden">

    <!-- Backdrop mobile -->
    <div id="sidebarBackdrop"
        class="fixed inset-0 bg-gray-900/50 z-40 hidden lg:hidden transition-opacity"></div>

    <!-- ── SIDEBAR ─────────────────────────────────────────────── -->
    <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-50 flex flex-col
               bg-white dark:bg-slate-800
               border-r border-gray-200 dark:border-slate-700
               w-64 -translate-x-full lg:translate-x-0">

        <!-- Header: brand + hambúrguer -->
        <div class="flex items-center justify-between px-4 py-4 border-b border-gray-200 dark:border-slate-700 flex-shrink-0">
            <div class="sidebar-brand">
                <p class="text-sm font-bold text-gray-800 dark:text-white">
                    <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-xs text-gray-400 dark:text-slate-500">Gestão de Relacionamento</p>
            </div>
            <!-- Desktop: colapsa para mini -->
            <button id="sidebarCollapseBtn"
                class="hidden lg:flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0
                       text-gray-400 hover:text-gray-600 hover:bg-gray-100
                       dark:text-slate-500 dark:hover:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                title="Colapsar menu">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <!-- Mobile: fecha overlay -->
            <button id="closeSidebarBtn"
                class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg
                       text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300 transition-colors">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Navegação -->
        <nav class="flex-1 py-3 overflow-y-auto overflow-x-hidden">
            <?php
            $basePath = parse_url(APP_URL, PHP_URL_PATH) ?? '';
            $currentPath = substr($_SERVER['REQUEST_URI'], strlen($basePath));
            $currentPath = strtok($currentPath, '?') ?: '/';
            ?>
            <div class="space-y-0.5 px-3">
                <?= navLink('/dashboard',
                    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
                    'Dashboard', $currentPath) ?>
                <?= navLink('/clients',
                    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                    'Clientes', $currentPath) ?>
                <?= navLink('/pipeline',
                    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
                    'Pipeline', $currentPath) ?>
                <?= navLink('/tasks',
                    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
                    'Calendário', $currentPath) ?>
                <?= navLink('/cold-contacts',
                    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.93 12 19.79 19.79 0 0 1 1.86 3.38 2 2 0 0 1 3.84 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
                    'Contatos frios', $currentPath) ?>
                <?= navLink('/acompanhamento',
                    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
                    'Acompanhamento', $currentPath) ?>
            </div>

            <!-- Acesso Rápido -->
            <div class="mt-4 px-3">
                <p class="sidebar-section-label text-xs uppercase font-semibold text-gray-400 dark:text-slate-600 px-2 mb-1">Acesso Rápido</p>
                <div class="space-y-0.5">
                    <a href="https://avapro.ademicon.com.br/" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        <span class="nav-label">AVA Pro</span>
                    </a>
                    <a href="https://webmail.autorizadoademicon.com.br/?_task=mail&_mbox=INBOX" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span class="nav-label">Webmail</span>
                    </a>
                    <a href="https://crmapollo.com.br/app/views/index.php" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span class="nav-label">CRM Apollo</span>
                    </a>
                </div>
            </div>

            <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700 px-3">
                <p class="sidebar-section-label text-xs uppercase font-semibold text-gray-400 dark:text-slate-600 px-2 mb-1">Administração</p>
                <div class="space-y-0.5">
                    <?= navLink('/admin/users',
                        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
                        'Usuários', $currentPath) ?>
                    <?= navLink('/settings',
                        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
                        'Configurações', $currentPath) ?>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Perfil do usuário -->
        <div class="px-3 py-3 border-t border-gray-200 dark:border-slate-700 flex-shrink-0">
            <div class="flex items-center gap-3 px-2 py-2 rounded-lg">
                <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-bold text-white flex-shrink-0">
                    <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-info flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-700 dark:text-slate-200 truncate">
                        <?= htmlspecialchars($_SESSION['user']['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="text-xs text-gray-400 dark:text-slate-500 capitalize">
                        <?= htmlspecialchars($_SESSION['user']['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <a href="<?= $safeAppUrl ?>/logout" title="Sair"
                    class="flex-shrink-0 text-gray-400 hover:text-red-500 dark:text-slate-500 dark:hover:text-red-400 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                </a>
            </div>
        </div>
    </aside>
    <!-- ── / SIDEBAR ───────────────────────────────────────────── -->

    <!-- ── MAIN CONTENT ──────────────────────────────────────── -->
    <div id="mainContent" class="flex-1 flex flex-col w-full overflow-hidden">

        <!-- Topbar -->
        <header class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-4 sm:px-6 py-3 flex items-center gap-3 flex-shrink-0 z-10 relative">

            <!-- Hambúrguer (mobile: abre overlay | desktop: também usado como fallback) -->
            <button id="sidebarToggle"
                class="text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <h2 class="text-base font-semibold text-gray-700 dark:text-slate-200 truncate flex-1">
                <?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <!-- Ações do topbar -->
            <div class="flex items-center gap-2 ml-auto">

                <!-- Relógio -->
                <span class="text-sm text-gray-400 dark:text-slate-500 hidden sm:block" id="clock"></span>

                <!-- Toggle de tema -->
                <div class="flex items-center gap-1.5">
                    <!-- Sol: visível no dark mode -->
                    <svg class="hidden dark:block w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <!-- Lua: visível no light mode -->
                    <svg class="block dark:hidden w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <button id="themeToggle" role="switch"
                        class="relative inline-flex w-9 h-5 rounded-full cursor-pointer
                               bg-gray-200 dark:bg-indigo-600
                               transition-colors duration-300
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-slate-800"
                        title="Alternar tema (Ctrl+Shift+L)">
                        <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-300 dark:translate-x-4"></span>
                    </button>
                </div>

                <!-- Notificações -->
                <div class="relative" id="notification-bell">
                    <button id="btnNotifications"
                        class="relative text-gray-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700"
                        title="Notificações">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span id="notifBadge"
                            class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </button>
                    <div id="notifDropdown"
                        class="hidden absolute right-0 top-full mt-2 w-80
                               bg-white dark:bg-slate-800 rounded-xl shadow-xl
                               border border-gray-200 dark:border-slate-700
                               z-50 max-h-64 overflow-y-auto">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700 dark:text-slate-200">Notificações</span>
                            <button id="btnClearNotifs" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Limpar</button>
                        </div>
                        <div id="notifList" class="divide-y divide-gray-50 dark:divide-slate-700">
                            <div class="px-4 py-3 text-sm text-gray-400 dark:text-slate-500 text-center">Nenhuma notificação</div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Flash message -->
        <?php if (!empty($_SESSION['flash'])): ?>
            <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
            <?php
            $flashColors = [
                'success' => 'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-700 text-green-800 dark:text-green-300',
                'error'   => 'bg-red-50 dark:bg-red-900/30 border-red-400 dark:border-red-700 text-red-800 dark:text-red-300',
                'warning' => 'bg-yellow-50 dark:bg-yellow-900/30 border-yellow-400 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300',
                'info'    => 'bg-blue-50 dark:bg-blue-900/30 border-blue-400 dark:border-blue-700 text-blue-800 dark:text-blue-300',
            ];
            $flashColor = $flashColors[$flash['type']] ?? $flashColors['info'];
            ?>
            <div id="flashMsg"
                class="mx-4 sm:mx-6 mt-4 px-4 py-3 rounded border-l-4 <?= $flashColor ?> flex items-center justify-between">
                <span class="text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></span>
                <button onclick="document.getElementById('flashMsg').remove()"
                    class="ml-4 text-lg font-bold opacity-60 hover:opacity-100">&times;</button>
            </div>
        <?php endif; ?>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 w-full">
            <?= $content ?>
        </main>
    </div>
    <!-- ── / MAIN CONTENT ────────────────────────────────────── -->

</div>

<!-- ── SCRIPTS ───────────────────────────────────────────────── -->
<script nonce="<?= CSP_NONCE ?>">
    // Relógio
    function updateClock() {
        const el = document.getElementById('clock');
        if (el) {
            const now = new Date();
            el.textContent = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
    }
    updateClock();
    setInterval(updateClock, 60000);

    // ── TEMA ──────────────────────────────────────────────────
    const htmlEl = document.documentElement;

    function applyTheme(isDark) {
        isDark ? htmlEl.classList.add('dark') : htmlEl.classList.remove('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        document.dispatchEvent(new CustomEvent('themeChange', { detail: { dark: isDark } }));
    }

    document.getElementById('themeToggle').addEventListener('click', function() {
        applyTheme(!htmlEl.classList.contains('dark'));
    });

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'L') {
            e.preventDefault();
            applyTheme(!htmlEl.classList.contains('dark'));
        }
    });

    // ── SIDEBAR ───────────────────────────────────────────────
    const sidebar       = document.getElementById('sidebar');
    const backdrop      = document.getElementById('sidebarBackdrop');
    const mainContent   = document.getElementById('mainContent');
    const collapseBtn   = document.getElementById('sidebarCollapseBtn');
    const toggleBtn     = document.getElementById('sidebarToggle');
    const closeBtn      = document.getElementById('closeSidebarBtn');

    let isMini = localStorage.getItem('sidebar') === 'mini';

    function setSidebarMini(mini) {
        isMini = mini;
        if (mini) {
            sidebar.classList.add('sidebar-mini');
            mainContent.classList.remove('lg:ml-64');
            mainContent.classList.add('lg:ml-16');
        } else {
            sidebar.classList.remove('sidebar-mini');
            mainContent.classList.remove('lg:ml-16');
            mainContent.classList.add('lg:ml-64');
        }
        localStorage.setItem('sidebar', mini ? 'mini' : 'expanded');
        setTimeout(() => window.dispatchEvent(new Event('resize')), 320);
    }

    function openMobileSidebar() {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        backdrop.classList.remove('hidden');
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }

    // Inicialização
    if (window.innerWidth >= 1024) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        setSidebarMini(isMini);
    }
    // Mobile fica fechado (já tem -translate-x-full na classe padrão)

    collapseBtn?.addEventListener('click', () => setSidebarMini(!isMini));

    toggleBtn?.addEventListener('click', function() {
        if (window.innerWidth >= 1024) {
            setSidebarMini(!isMini);
        } else {
            const isOpen = !sidebar.classList.contains('-translate-x-full');
            isOpen ? closeMobileSidebar() : openMobileSidebar();
        }
    });

    closeBtn?.addEventListener('click', closeMobileSidebar);
    backdrop?.addEventListener('click', closeMobileSidebar);

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            backdrop.classList.add('hidden');
            // Reaplica as classes de margem (podem ter sido removidas no resize para mobile)
            if (isMini) {
                mainContent.classList.remove('lg:ml-64');
                mainContent.classList.add('lg:ml-16');
            } else {
                mainContent.classList.remove('lg:ml-16');
                mainContent.classList.add('lg:ml-64');
            }
        } else {
            mainContent.classList.remove('lg:ml-64', 'lg:ml-16');
        }
    });

    // Flash
    setTimeout(() => document.getElementById('flashMsg')?.remove(), 5000);
</script>

<script nonce="<?= CSP_NONCE ?>">
    // Notificações (polling a cada 60s)
    (function () {
        const appUrl = <?= json_encode(APP_URL) ?>;
        const NOTIFIED = new Set();
        const notifAlerts = [];

        const badge    = document.getElementById('notifBadge');
        const list     = document.getElementById('notifList');
        const dropdown = document.getElementById('notifDropdown');
        const btnBell  = document.getElementById('btnNotifications');
        const btnClear = document.getElementById('btnClearNotifs');

        btnBell.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', function() { dropdown.classList.add('hidden'); });
        dropdown.addEventListener('click', function(e) { e.stopPropagation(); });

        btnClear.addEventListener('click', function() {
            notifAlerts.length = 0;
            updateBadge();
            list.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400 dark:text-slate-500 text-center">Nenhuma notificação</div>';
        });

        function updateBadge() {
            if (notifAlerts.length > 0) {
                badge.textContent = notifAlerts.length > 9 ? '9+' : notifAlerts.length;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        function showToast(message, type) {
            const colors = { task: 'bg-indigo-700', birthday: 'bg-pink-600' };
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 z-50 ' + (colors[type] || 'bg-gray-700') + ' text-white px-4 py-3 rounded-xl shadow-lg text-sm max-w-xs';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 8000);
        }

        function addToDropdown(item) {
            const empty = list.querySelector('.text-gray-400, .text-slate-500');
            if (empty) empty.remove();
            const div = document.createElement('div');
            div.className = 'px-4 py-3 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/50';
            div.textContent = (item.type === 'birthday' ? '🎂 ' : '⏰ ') + item.message;
            list.prepend(div);
        }

        async function checkNotifications() {
            try {
                const resp = await fetch(appUrl + '/api/tasks/upcoming');
                if (!resp.ok) return;
                const data = await resp.json();
                data.forEach(function(item) {
                    if (NOTIFIED.has(item.key)) return;
                    NOTIFIED.add(item.key);
                    notifAlerts.push(item);
                    showToast(item.message, item.type);
                    addToDropdown(item);
                });
                updateBadge();
            } catch (e) { /* silencia erros de rede */ }
        }

        checkNotifications();
        setInterval(checkNotifications, 60000);
    })();
</script>

</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add app/Views/layouts/main.php
git commit -m "feat: refatora main.php — sidebar mini/full, dark mode, SVG nav, anti-FOUC, Inter"
```

---

## Task 5: dashboard/index.php + dashboard.js — Dark Mode

**Files:**
- Modify: `app/Views/dashboard/index.php`
- Modify: `public/assets/js/dashboard.js`

- [ ] **Step 1: Adicionar dark classes nos KPI cards e substituir emojis por SVG**

No arquivo `app/Views/dashboard/index.php`, substituir os 4 KPI cards (linhas 8–45):

```php
<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

    <a href="<?= APP_URL ?>/clients"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 flex items-center gap-4 hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-700 transition-all">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($totalClients) ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Clientes ativos</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/tasks"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 flex items-center gap-4 hover:shadow-md hover:border-amber-200 dark:hover:border-amber-700 transition-all">
        <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400 flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M9 14l2 2 4-4"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $pendingTasks ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Minhas tarefas abertas</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/tasks"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border <?= count($overdueTasks) > 0 ? 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20' : 'border-gray-100 dark:border-slate-700' ?> p-5 flex items-center gap-4 hover:shadow-md transition-all">
        <div class="w-12 h-12 rounded-xl <?= count($overdueTasks) > 0 ? 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400' : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400' ?> flex items-center justify-center flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold <?= count($overdueTasks) > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-800 dark:text-white' ?>"><?= count($overdueTasks) ?></p>
            <p class="text-sm <?= count($overdueTasks) > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-slate-400' ?>">Tarefas atrasadas</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/pipeline"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 flex items-center gap-4 hover:shadow-md hover:border-green-200 dark:hover:border-green-700 transition-all">
        <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center text-green-600 dark:text-green-400 flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-green-700 dark:text-green-400">R$ <?= number_format($wonRevenue, 2, ',', '.') ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Negócios ganhos</p>
        </div>
    </a>
</div>
```

- [ ] **Step 2: Adicionar dark classes nos cards de Tarefas e Atividade Recente**

Substituir o bloco do grid (linhas 48–113) mantendo lógica idêntica, apenas adicionando dark classes:

Na div `<!-- Tarefas dos próximos 7 dias -->`:
- `bg-white` → `bg-white dark:bg-slate-800`
- `border-gray-100` → `border-gray-100 dark:border-slate-700`
- `border-b border-gray-100` → `border-b border-gray-100 dark:border-slate-700`
- `font-semibold text-gray-700` (h4) → `font-semibold text-gray-700 dark:text-slate-200`
- `text-indigo-600` (link) → `text-indigo-600 dark:text-indigo-400`
- `divide-y divide-gray-50` → `divide-y divide-gray-50 dark:divide-slate-700`
- `text-gray-700` (task title) → `text-gray-700 dark:text-slate-200`
- `text-gray-400` (client, date) → `text-gray-400 dark:text-slate-500`

Na div `<!-- Atividade Recente -->`: mesmas trocas.

Substituir o cabeçalho `📅 Próximas Tarefas (7 dias)` por SVG:
```php
<div class="flex items-center gap-2">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-indigo-500 flex-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    <span class="font-semibold text-gray-700 dark:text-slate-200">Próximas Tarefas (7 dias)</span>
</div>
```

E `🕐 Atividade Recente`:
```php
<div class="flex items-center gap-2">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-indigo-500 flex-shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <span class="font-semibold text-gray-700 dark:text-slate-200">Atividade Recente</span>
</div>
```

Na atividade recente, substituir os emojis de tipo de interação por SVG inline no `$typeIcons`:
```php
$typeIcons = [
    'call'      => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-blue-500"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.93 12 19.79 19.79 0 0 1 1.86 3.38 2 2 0 0 1 3.84 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    'email'     => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-green-500"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'meeting'   => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-purple-500"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'whatsapp'  => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-teal-500"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'note'      => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-yellow-500"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    'other'     => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-gray-400"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
];
```

E na linha que usa o ícone, trocar `<span class="text-lg flex-shrink-0 mt-0.5">` por `<span class="flex-shrink-0 mt-0.5">` (sem text-lg pois SVG tem tamanho próprio), e usar `<?= $typeIcons[$inter['type']] ?? $typeIcons['other'] ?>` (note: não usar htmlspecialchars, pois é SVG HTML).

Também dark no card do gráfico (linha 116–121):
```php
<div class="mb-8">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
        <h4 class="text-sm font-semibold text-gray-600 dark:text-slate-400 mb-4">Distribuição no Pipeline</h4>
        <canvas id="chartPipeline" height="100"></canvas>
    </div>
</div>
```

- [ ] **Step 3: Atualizar dashboard.js para dark mode re-render**

Substituir o conteúdo de `public/assets/js/dashboard.js`:

```js
(function () {
    'use strict';

    if (typeof pipelineData === 'undefined' || typeof Chart === 'undefined') return;

    Chart.defaults.font.family = "'Inter', sans-serif";

    let chartInstance = null;

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function getChartColors() {
        if (isDark()) {
            return {
                gridColor: 'rgba(255,255,255,0.06)',
                tickColor: '#94a3b8',
                tooltipBg: '#1e293b',
                tooltipBorder: 'rgba(255,255,255,0.1)',
            };
        }
        return {
            gridColor: '#f1f5f9',
            tickColor: '#6b7280',
            tooltipBg: '#fff',
            tooltipBorder: '#e2e8f0',
        };
    }

    function renderChart() {
        const ctxBar = document.getElementById('chartPipeline');
        if (!ctxBar) return;

        if (chartInstance) {
            chartInstance.destroy();
        }

        const c = getChartColors();

        chartInstance = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: pipelineData.labels,
                datasets: [{
                    label: 'Clientes',
                    data: pipelineData.counts,
                    backgroundColor: pipelineData.colors.map(c => c + 'cc'),
                    borderColor: pipelineData.colors,
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: c.tooltipBg,
                        borderColor: c.tooltipBorder,
                        borderWidth: 1,
                        titleColor: isDark() ? '#e2e8f0' : '#374151',
                        bodyColor: isDark() ? '#94a3b8' : '#6b7280',
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} cliente${ctx.parsed.y !== 1 ? 's' : ''}`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: c.tickColor,
                            callback: val => Number.isInteger(val) ? val : null,
                        },
                        grid: { color: c.gridColor }
                    },
                    x: {
                        ticks: { color: c.tickColor },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    renderChart();

    // Re-render quando o tema muda
    document.addEventListener('themeChange', renderChart);

})();
```

- [ ] **Step 4: Commit**

```bash
git add app/Views/dashboard/index.php public/assets/js/dashboard.js
git commit -m "feat: dark mode no dashboard — KPI SVG, cards, Chart.js re-render"
```

---

## Task 6: clients/index.php — Botões Icon-Only + Dark Mode

**Files:**
- Modify: `app/Views/clients/index.php`

- [ ] **Step 1: Substituir botão "Novo Cliente" e emojis no header**

Linha 9–14 (link "Novo Cliente"), substituir `➕ Novo Cliente` por SVG:
```php
<a href="<?= APP_URL ?>/clients/create"
   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
          font-medium px-4 py-2 rounded-lg text-sm transition-colors">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Novo Cliente
</a>
```

- [ ] **Step 2: Adicionar dark classes no filtro de busca**

Na div do filtro (linha 17–59):
- `bg-white` → `bg-white dark:bg-slate-800`
- `border-gray-100` → `border-gray-100 dark:border-slate-700`
- Inputs `border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none` → adicionar `dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-400`
- Selects: mesmo padrão
- Botão "Filtrar": `bg-indigo-600 hover:bg-indigo-700` — mantém (já funciona no dark)
- Link "Limpar": `bg-gray-100 hover:bg-gray-200 text-gray-700` → adicionar `dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200`

- [ ] **Step 3: Adicionar dark classes na tabela**

Na tabela (linha 80–176):
- Container `bg-white` → `bg-white dark:bg-slate-800`
- `border-gray-100` → `border-gray-100 dark:border-slate-700`
- Estado vazio `text-gray-400` → `text-gray-400 dark:text-slate-500`
- `thead bg-gray-50 border-b border-gray-200` → `dark:bg-slate-700/50 dark:border-slate-600`
- `th text-gray-600` → `dark:text-slate-400`
- `tbody divide-y divide-gray-100` → `dark:divide-slate-700`
- Rows `hover:bg-gray-50` → `dark:hover:bg-slate-700/30`
- Links de nome `text-indigo-700 hover:text-indigo-900` → `dark:text-indigo-400 dark:hover:text-indigo-300`
- Badge "Em atraso": `bg-red-100 text-red-700` → `dark:bg-red-900/40 dark:text-red-400`
- `text-gray-600` (empresa, etc) → `dark:text-slate-400`

- [ ] **Step 4: Substituir botões de ação por pill icon-only**

Linha 154–169 (coluna Ações), substituir por:

```php
<td class="px-4 py-3 text-center">
    <div class="inline-flex items-center gap-0.5 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg p-1">
        <!-- Ver -->
        <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
           title="Ver detalhes"
           class="w-7 h-7 flex items-center justify-center rounded-md text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 transition-colors">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </a>
        <!-- Editar -->
        <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>/edit"
           title="Editar"
           class="w-7 h-7 flex items-center justify-center rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/40 transition-colors">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </a>
        <!-- Nova interação -->
        <button
            onclick="openQuickInteraction(<?= (int)$client['id'] ?>, <?= htmlspecialchars(json_encode($client['name']), ENT_QUOTES, 'UTF-8') ?>)"
            title="Nova interação"
            class="w-7 h-7 flex items-center justify-center rounded-md text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/40 transition-colors">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </button>
        <!-- Nova tarefa -->
        <button
            onclick="openQuickTask(<?= (int)$client['id'] ?>, <?= htmlspecialchars(json_encode($client['name']), ENT_QUOTES, 'UTF-8') ?>)"
            title="Nova tarefa"
            class="w-7 h-7 flex items-center justify-center rounded-md text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/40 transition-colors">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
        </button>
    </div>
</td>
```

- [ ] **Step 5: Adicionar dark classes nos dois modais**

Modal `modalQuickInteraction` e `modalQuickTask`:
- `bg-white` → `bg-white dark:bg-slate-800`
- `border-b border-gray-100` → `border-b border-gray-100 dark:border-slate-700`
- `font-bold text-gray-800` (h4) → `dark:text-white`
- `text-gray-400 hover:text-gray-600` (fechar) → `dark:text-slate-500 dark:hover:text-slate-300`
- `text-sm font-medium text-gray-700 mb-1` (labels) → `dark:text-slate-300`
- Inputs e selects: `border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500` → adicionar `dark:border-slate-600 dark:bg-slate-700 dark:text-white`
- Textarea: mesmo padrão
- Botão "Cancelar": `bg-gray-100 hover:bg-gray-200 text-gray-700` → `dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200`

- [ ] **Step 6: Commit**

```bash
git add app/Views/clients/index.php
git commit -m "feat: clients index — botões icon-only pill, dark mode completo"
```

---

## Task 7: clients/show.php + create.php + edit.php — Dark Mode

**Files:**
- Modify: `app/Views/clients/show.php`
- Modify: `app/Views/clients/create.php`
- Modify: `app/Views/clients/edit.php`

Padrão de dark classes a aplicar em todos:

| Elemento | Classes a adicionar |
|---|---|
| Cards `bg-white` | `dark:bg-slate-800` |
| `border-gray-100` | `dark:border-slate-700` |
| Títulos `text-gray-700/800` | `dark:text-slate-200` |
| Texto secundário `text-gray-500/600` | `dark:text-slate-400` |
| Texto pequeno `text-gray-400` | `dark:text-slate-500` |
| Labels de formulário | `dark:text-slate-300` |
| Inputs/selects/textarea | `dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-400` |
| `divide-y divide-gray-100` | `dark:divide-slate-700` |
| Links de voltar `text-gray-400 hover:text-gray-600` | `dark:text-slate-500 dark:hover:text-slate-300` |
| `bg-gray-50` (seções) | `dark:bg-slate-700/30` |

- [ ] **Step 1: Aplicar dark classes em show.php**

Em `show.php`:
- Cabeçalho: `text-gray-800` → `dark:text-white`, `text-gray-500` → `dark:text-slate-400`
- Botão Editar (já tem SVG): adicionar `dark:bg-indigo-700` no hover ou manter
- Todos os cards `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- Seções `border-b border-gray-100` → `dark:border-slate-700`
- `text-gray-700` (labels de campo) → `dark:text-slate-300`
- `text-gray-600` (valores) → `dark:text-slate-400`
- Timeline de interações: `bg-gray-50` → `dark:bg-slate-700/30`, ícones mantidos
- `interactionTypes` array: os valores `'icon'` são emojis — substituir pelos mesmos SVGs definidos em Task 5 Step 2

- [ ] **Step 2: Aplicar dark classes em create.php e edit.php**

Ambos os arquivos têm a mesma estrutura de formulário:
- Seções `px-6 py-5 border-b border-gray-100` → `dark:border-slate-700`
- `bg-white ... border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- `text-gray-500 uppercase` (subtítulo de seção) → `dark:text-slate-500`
- Todos os inputs, selects, textareas: adicionar `dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-400`
- Labels `text-gray-700` → `dark:text-slate-300`
- `text-gray-500` (textos de ajuda) → `dark:text-slate-500`
- Botões de submit: `bg-indigo-600 hover:bg-indigo-700` mantém
- Botões secundários `bg-gray-100 hover:bg-gray-200 text-gray-700` → `dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200`
- Link "Voltar" `text-gray-400 hover:text-gray-600` → `dark:text-slate-500 dark:hover:text-slate-300`
- `px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between` (rodapé/footer de formulário) → `dark:bg-slate-700/30 dark:border-slate-700`

- [ ] **Step 3: Commit**

```bash
git add app/Views/clients/show.php app/Views/clients/create.php app/Views/clients/edit.php
git commit -m "feat: dark mode em show, create e edit de clientes"
```

---

## Task 8: pipeline/index.php + stages.php — Kanban Fix + Dark Mode

**Files:**
- Modify: `app/Views/pipeline/index.php`
- Modify: `app/Views/pipeline/stages.php`

- [ ] **Step 1: Corrigir kanban — trocar flex flex-wrap por overflow-x-auto**

No `pipeline/index.php`, linha 11–14 (botões de header):
- `➕ Novo Cliente` → SVG `+`
- `⚙️ Etapas` → SVG settings (apenas ícone, sem texto, ou ícone + texto para botão secundário)

Linha 25–28 (kanban board container), substituir:
```php
<!-- Board Kanban — scroll horizontal, colunas fixas -->
<div class="kanban-scroll overflow-x-auto pb-4" id="kanbanBoard"
     data-move-url="<?= APP_URL ?>/pipeline/move"
     data-stats-url="<?= APP_URL ?>/api/dashboard/stats"
     data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
    <div class="flex gap-4 items-start min-w-max">
```

Linha 36–38 (coluna kanban):
```php
<div class="kanban-column w-72 flex-shrink-0 flex flex-col"
     data-stage-id="<?= $stage['id'] ?>">
```

Fechar o `<div class="flex gap-4 items-start min-w-max">` antes do fechamento de `#kanbanBoard`.

Linha 55–56 (zona de drop):
```php
<div class="kanban-drop-zone flex-1 min-h-24 bg-gray-100 dark:bg-slate-700/50 rounded-b-xl p-2 space-y-2"
     data-stage-id="<?= $stage['id'] ?>">
```

Linha 60–63 (kanban card):
```php
<div class="kanban-card bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-600 p-3 cursor-grab hover:shadow-md transition-shadow"
```

Linha 75 (`🏢` empresa no card): substituir por SVG inline:
```php
<p class="text-xs text-gray-400 dark:text-slate-500 truncate mt-0.5 flex items-center gap-1">
    <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="1"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
    <?= htmlspecialchars($client['company'], ENT_QUOTES, 'UTF-8') ?>
</p>
```

- [ ] **Step 2: Adicionar dark classes nos cards e cabeçalhos de coluna**

- `border-t border-gray-100` → `dark:border-slate-600`
- `text-gray-800` (nome do cliente) → `dark:text-white`
- `text-gray-400` (textos secundários) → `dark:text-slate-500`
- Rodapé do card: quaisquer textos `text-gray-500` → `dark:text-slate-500`

- [ ] **Step 3: Dark mode em stages.php**

- `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- `text-gray-800` / `text-gray-700` → `dark:text-white` / `dark:text-slate-200`
- `border-b border-gray-100` → `dark:border-slate-700`
- Input e color picker: `border-gray-300` → `dark:border-slate-600 dark:bg-slate-700 dark:text-white`
- `divide-y divide-gray-100` → `dark:divide-slate-700`
- Link "Excluir etapa" `text-red-500` → mantém (funciona em dark)

- [ ] **Step 4: Commit**

```bash
git add app/Views/pipeline/index.php app/Views/pipeline/stages.php
git commit -m "fix: kanban overflow-x-auto (tablet), dark mode no pipeline"
```

---

## Task 9: tasks/index.php — Dark Mode

**Files:**
- Modify: `app/Views/tasks/index.php`

- [ ] **Step 1: Alerta de tarefas atrasadas — dark classes e SVG**

Linha 5–25 (alerta vermelho):
- `bg-red-50` → `dark:bg-red-900/20`
- `border-red-500` → mantém
- `text-red-700` → `dark:text-red-400`
- `bg-white border-red-100` → `dark:bg-slate-800 dark:border-red-800/50`
- `text-red-800` → `dark:text-red-300`
- `text-red-400` → `dark:text-red-500`
- `text-red-500` → `dark:text-red-400`
- Substituir `🚨` por SVG:
```php
<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-red-500 flex-shrink-0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
```

- [ ] **Step 2: Card do calendário e modal — dark classes**

Linha 28–30 (card FullCalendar):
```php
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4">
    <div id="fc-calendar"></div>
</div>
```

No modal `modalTask` (linhas 33–…):
- `bg-white` → `dark:bg-slate-800`
- `border-b border-gray-100` → `dark:border-slate-700`
- Título `text-gray-800` → `dark:text-white`
- Botão fechar `text-gray-400 hover:text-gray-600` → `dark:text-slate-500 dark:hover:text-slate-300`
- Labels `text-gray-700` → `dark:text-slate-300`
- Inputs: `dark:border-slate-600 dark:bg-slate-700 dark:text-white`
- `bg-indigo-50 rounded-lg` (link cliente): `dark:bg-indigo-900/30`
- `text-indigo-700` → `dark:text-indigo-300`
- Selects (prioridade, status, tipo): mesmas dark classes nos inputs
- Botão Cancelar: `dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200`

- [ ] **Step 3: Commit**

```bash
git add app/Views/tasks/index.php
git commit -m "feat: dark mode em tasks — alerta SVG, modal e FullCalendar"
```

---

## Task 10: cold-contacts/index.php + acompanhamento/index.php — Dark Mode

**Files:**
- Modify: `app/Views/cold-contacts/index.php`
- Modify: `app/Views/acompanhamento/index.php`

- [ ] **Step 1: Dark mode em cold-contacts/index.php**

Padrão idêntico ao já estabelecido:
- Cards `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- `text-gray-700` → `dark:text-slate-200`
- `text-gray-500` → `dark:text-slate-400`
- `text-gray-400` → `dark:text-slate-500`
- Labels `text-gray-700 mb-1` → `dark:text-slate-300`
- Inputs `border-gray-300` → `dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-400`
- Input file: `dark:text-slate-400 dark:file:bg-indigo-900/30 dark:file:text-indigo-300`
- Botão importar: `bg-indigo-600 hover:bg-indigo-700` — mantém
- Cards de importação mensais: aplicar dark classes nas bordas, fundos e textos
- Badges de status (enviado/falhou/etc): verificar as classes usadas e adicionar dark equivalentes

- [ ] **Step 2: Dark mode em acompanhamento/index.php**

- Navegação de meses: botões `border-gray-300 text-gray-600 hover:bg-gray-100` → `dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700`
- Span do mês atual: `bg-gray-100 text-gray-800` → `dark:bg-slate-700 dark:text-white`
- Card vazio `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- `text-gray-500` / `text-gray-400` → `dark:text-slate-400` / `dark:text-slate-500`
- Emoji `📭`: substituir por SVG:
```php
<svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="text-gray-300 dark:text-slate-600 mb-4"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
```
- Cards de métricas `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- Valores grandes `text-gray-800` → `dark:text-white`
- Textos descrição `text-gray-500` → `dark:text-slate-400`
- Barras da tabela de etapas: bordas e textos com dark classes

- [ ] **Step 3: Commit**

```bash
git add app/Views/cold-contacts/index.php app/Views/acompanhamento/index.php
git commit -m "feat: dark mode em cold-contacts e acompanhamento"
```

---

## Task 11: Páginas restantes — Admin, Settings, Pagination, 404

**Files:**
- Modify: `app/Views/admin/users/index.php`
- Modify: `app/Views/admin/users/create.php`
- Modify: `app/Views/admin/users/edit.php`
- Modify: `app/Views/settings/index.php`
- Modify: `app/Views/components/pagination.php`
- Modify: `app/Views/errors/404.php`

- [ ] **Step 1: admin/users/index.php — remover emojis de role, icon-only edit, dark mode**

Role labels (linha 6–7): remover emojis:
```php
$roleLabels = ['admin' => 'Admin', 'seller' => 'Vendedor', 'viewer' => 'Leitor'];
```

Botão "Novo Usuário" (linha 11–14): `➕ Novo Usuário` → SVG +:
```php
<a href="<?= APP_URL ?>/admin/users/create"
   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Novo Usuário
</a>
```

Dark classes na tabela:
- `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- `thead bg-gray-50 border-b border-gray-200` → `dark:bg-slate-700/50 dark:border-slate-600`
- `th text-gray-600` → `dark:text-slate-400`
- `tbody divide-y divide-gray-100` → `dark:divide-slate-700`
- `hover:bg-gray-50` → `dark:hover:bg-slate-700/30`
- Avatar `bg-indigo-100 text-indigo-700` → `dark:bg-indigo-900/40 dark:text-indigo-300`
- `text-gray-700` (nome) → `dark:text-slate-200`
- `text-gray-500` (email) → `dark:text-slate-400`
- Badge admin `bg-purple-100 text-purple-700` → `dark:bg-purple-900/40 dark:text-purple-300`
- Badge seller `bg-blue-100 text-blue-700` → `dark:bg-blue-900/40 dark:text-blue-300`
- Badge viewer `bg-gray-100 text-gray-600` → `dark:bg-slate-700 dark:text-slate-400`
- Badge ativo `text-green-700 bg-green-100` → `dark:bg-green-900/40 dark:text-green-400`
- Badge inativo `text-red-600 bg-red-100` → `dark:bg-red-900/40 dark:text-red-400`

- [ ] **Step 2: admin/users/create.php e edit.php — remover emojis, dark mode**

Em ambos, selects de role — remover emojis:
```php
<option value="seller">Vendedor</option>
<option value="admin">Administrador</option>
<option value="viewer">Leitor</option>
```

Dark classes (mesma tabela de Task 7 Step 2):
- `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- Labels `text-gray-700` → `dark:text-slate-300`
- Inputs/selects: `dark:border-slate-600 dark:bg-slate-700 dark:text-white`
- Botões cancelar/voltar: `dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200`
- Links de voltar: `dark:text-slate-500 dark:hover:text-slate-300`

- [ ] **Step 3: settings/index.php — dark mode**

- `bg-white border-gray-100` → `dark:bg-slate-800 dark:border-slate-700`
- `text-gray-700` / `text-gray-500` (títulos e subtítulos) → `dark:text-slate-200` / `dark:text-slate-400`
- `border-b border-gray-100` (separadores) → `dark:border-slate-700`
- Labels `text-gray-700` → `dark:text-slate-300`
- Inputs: `dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-400`
- Inputs desabilitados: `dark:border-slate-700 dark:bg-slate-700/50 dark:text-slate-500`
- Texto de ajuda `text-gray-400` → `dark:text-slate-600`
- Botão salvar: mantém `bg-indigo-600`
- Footer do card `px-5 py-4 bg-gray-50` → `dark:bg-slate-700/30`

- [ ] **Step 4: pagination.php — dark mode**

- `text-gray-500` → `dark:text-slate-400`
- `text-gray-700` (strong) → `dark:text-slate-200`
- Select `border-gray-300` → `dark:border-slate-600 dark:bg-slate-700 dark:text-white`
- Botão/link Anterior/Próximo: `border-gray-300 bg-white text-gray-600 hover:bg-gray-50` → `dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700`
- Botão desabilitado: `border-gray-200 bg-gray-50 text-gray-400` → `dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-600`
- Página atual: `bg-indigo-600 text-white` → mantém
- Outros números: mesmos que Anterior/Próximo

- [ ] **Step 5: errors/404.php — anti-FOUC, Inter, dark, SVG**

Substituir `🔍` por SVG e adicionar dark mode + anti-FOUC:

```php
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Página não encontrada</title>
    <?php $safeAppUrl = defined('APP_URL') ? htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') : ''; ?>
    <script>
        (function() {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $safeAppUrl ?>/assets/css/tailwind.css">
</head>
<body class="bg-gray-50 dark:bg-slate-900 min-h-screen flex items-center justify-center font-sans transition-colors">
    <div class="text-center">
        <div class="flex justify-center mb-6">
            <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="text-gray-300 dark:text-slate-700">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </div>
        <h1 class="text-6xl font-bold text-gray-800 dark:text-white mb-2">404</h1>
        <p class="text-xl text-gray-500 dark:text-slate-400 mb-6">Página não encontrada</p>
        <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/dashboard"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-3 rounded-lg transition-colors">
            ← Voltar ao Dashboard
        </a>
    </div>
</body>
</html>
```

Nota: este arquivo tem CSP_NONCE potencialmente não definido, então o script inline não usa nonce (é uma página de erro standalone). Se o projeto injetar o nonce aqui, adicionar `nonce="<?= CSP_NONCE ?>"`.

- [ ] **Step 6: Commit**

```bash
git add app/Views/admin/users/index.php app/Views/admin/users/create.php app/Views/admin/users/edit.php app/Views/settings/index.php app/Views/components/pagination.php app/Views/errors/404.php
git commit -m "feat: dark mode e limpeza de emojis em admin, settings, pagination e 404"
```

---

## Task 12: Tailwind CSS Rebuild

**Files:**
- Run: `scripts/build_css.php`

- [ ] **Step 1: Verificar o script de build e executar**

O projeto usa um script PHP que chama o binário local do Tailwind. Rodar:

```bash
php scripts/build_css.php
```

Saída esperada: arquivo `public/assets/css/tailwind.css` atualizado sem erros.

- [ ] **Step 2: Verificar que classes dark: estão no CSS gerado**

```bash
grep -c "dark:" public/assets/css/tailwind.css
```

Resultado esperado: número > 0 (centenas de ocorrências).

- [ ] **Step 3: Verificar que as classes do safelist estão presentes**

```bash
grep "lg:ml-16\|lg:ml-64" public/assets/css/tailwind.css
```

Resultado esperado: ambas as classes aparecem no arquivo.

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/tailwind.css
git commit -m "build: rebuild Tailwind CSS com dark mode e safelist"
```

---

## Critérios de Verificação Final

Após completar todas as tasks, verificar manualmente no navegador:

1. **Tema**: toggle no topbar alterna light/dark; `Ctrl+Shift+L` funciona; tema persiste ao recarregar
2. **Primeira visita** (limpar localStorage): tema segue `prefers-color-scheme` do sistema
3. **Sidebar desktop**: hambúrguer dentro da sidebar colapsa para modo mini (só ícones); expande de volta; persiste
4. **Sidebar mobile**: hamburger no topbar abre overlay; backdrop fecha ao clicar fora
5. **Sem emojis**: navegar por todas as páginas; verificar que nenhum emoji de navegação aparece
6. **Botões icon-only**: na lista de clientes e admin/users, botões de ação exibem apenas ícone com tooltip ao hover
7. **Pipeline tablet**: em viewport ~768px, kanban tem scroll horizontal sem gaps
8. **Chart.js dark**: acessar Dashboard em dark mode; gráfico usa cores adaptadas
9. **FullCalendar dark**: acessar Calendário em dark mode; visualização legível
10. **Flash messages**: aparecem corretamente em light e dark
11. **Login**: tela de login sem emoji `🏢`, responsiva em dark mode
