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
            <div class="sidebar-user-footer flex items-center gap-3 px-2 py-2 rounded-lg">
                <a href="<?= $safeAppUrl ?>/logout" title="Sair"
                    class="sidebar-avatar-logout w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-bold text-white flex-shrink-0 hover:ring-2 hover:ring-red-400 transition-all"
                    style="display:none">
                    <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
                </a>
                <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-bold text-white flex-shrink-0 sidebar-avatar-normal">
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
                    class="sidebar-logout flex-shrink-0 text-gray-400 hover:text-red-500 dark:text-slate-500 dark:hover:text-red-400 transition-colors">
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

            <!-- Hambúrguer (mobile apenas) -->
            <button id="sidebarToggle"
                class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <h2 class="text-base font-semibold text-gray-700 dark:text-slate-200 truncate flex-1">
                <?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <!-- Ações do topbar -->
            <div class="flex items-center gap-2 ml-auto">

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

                <!-- Toggle de tema com sol/lua dentro -->
                <button id="themeToggle" role="switch"
                    class="relative inline-flex w-11 h-6 rounded-full cursor-pointer
                           bg-gray-200 dark:bg-indigo-600
                           transition-colors duration-300
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-slate-800"
                    title="Alternar tema (Ctrl+Shift+L)">
                    <!-- Sol: lado esquerdo, visível no dark mode (círculo move p/ direita) -->
                    <svg class="absolute left-1 top-1/2 -translate-y-1/2 w-3 h-3 text-yellow-300 opacity-0 transition-opacity duration-300 dark:opacity-100" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                    <!-- Lua: lado direito, visível no light mode (círculo fica na esquerda) -->
                    <svg class="absolute right-1 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-500 transition-opacity duration-300 dark:opacity-0" fill="currentColor" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <!-- Círculo deslizante -->
                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-300 dark:translate-x-5"></span>
                </button>

                <!-- Data/Relógio -->
                <span class="text-sm text-gray-400 dark:text-slate-500 hidden sm:block" id="clock"></span>
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
