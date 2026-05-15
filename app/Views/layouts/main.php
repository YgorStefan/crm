<?php
$safeAppUrl = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8');
$assetV = static function (string $rel): string {
    $abs = __DIR__ . '/../../../public/' . ltrim($rel, '/');
    return is_file($abs) ? (string) filemtime($abs) : '0';
};
?>
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
    <link rel="stylesheet" href="<?= $safeAppUrl ?>/assets/css/tailwind.css?v=<?= $assetV('assets/css/tailwind.css') ?>">
    <script nonce="<?= CSP_NONCE ?>" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script nonce="<?= CSP_NONCE ?>" src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
    <script nonce="<?= CSP_NONCE ?>" src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.20/locales-all.global.min.js"></script>
</head>

<body class="bg-gray-100 dark:bg-zinc-950 font-sans text-gray-800 dark:text-zinc-200 transition-colors duration-300">

<div class="flex h-screen overflow-hidden">

    <!-- Backdrop mobile -->
    <div id="sidebarBackdrop"
        class="fixed inset-0 bg-gray-900/50 z-40 hidden lg:hidden transition-opacity"></div>

    <!-- ── SIDEBAR ─────────────────────────────────────────────── -->
    <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-50 flex flex-col
               bg-white dark:bg-zinc-900
               border-r border-gray-200 dark:border-zinc-800
               w-56 -translate-x-full lg:translate-x-0">

        <!-- Header: brand + hambúrguer (mesma altura do topbar) -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-zinc-800 flex-shrink-0">
            <div class="sidebar-brand">
                <p class="text-sm font-bold text-gray-800 dark:text-white leading-tight">
                    <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <!-- Desktop: colapsa para mini -->
            <button id="sidebarCollapseBtn"
                class="hidden lg:flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0
                       text-gray-400 hover:text-gray-600 hover:bg-gray-100
                       dark:text-zinc-500 dark:hover:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
                title="Colapsar menu">
                ☰
            </button>
            <!-- Mobile: fecha overlay -->
            <button id="closeSidebarBtn"
                class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg
                       text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition-colors">
                ✕
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
                <?= navLink('/dashboard', '📊', 'Dashboard', $currentPath) ?>
                <?= navLink('/clients', '👥', 'Clientes', $currentPath) ?>
                <?= navLink('/pipeline', '📈', 'Pipeline', $currentPath) ?>
                <?= navLink('/tasks', '📅', 'Calendário', $currentPath) ?>
                <?= navLink('/cold-contacts', '🧊', 'Contatos frios', $currentPath) ?>
                <?= navLink('/acompanhamento', '📉', 'Acompanhamento', $currentPath) ?>
            </div>

            <!-- Acesso Rápido -->
            <div class="px-3">
                <div class="sidebar-section-divider">
                    <div class="section-line section-line-left"></div>
                    <span class="section-title">Acesso Rápido</span>
                    <div class="section-line section-line-right"></div>
                </div>
                <div class="space-y-0.5">
                    <a href="https://avapro.ademicon.com.br/" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 transition-colors">
                        🔗
                        <span class="nav-label">AVA Pro</span>
                    </a>
                    <a href="https://webmail.autorizadoademicon.com.br/?_task=mail&_mbox=INBOX" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 transition-colors">
                        📧
                        <span class="nav-label">Webmail</span>
                    </a>
                    <a href="https://crmapollo.com.br/app/views/index.php" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 transition-colors">
                        🏠
                        <span class="nav-label">CRM Apollo</span>
                    </a>
                </div>
            </div>

            <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
            <div class="px-3">
                <div class="sidebar-section-divider">
                    <div class="section-line section-line-left"></div>
                    <span class="section-title">Administração</span>
                    <div class="section-line section-line-right"></div>
                </div>
                <div class="space-y-0.5">
                    <?= navLink('/admin', '⚙️', 'Configurações', $currentPath) ?>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Perfil do usuário -->
        <div class="px-3 pb-3 flex-shrink-0">
            <div class="sidebar-section-divider">
                <div class="section-line section-line-left"></div>
                <span class="section-title">Conta</span>
                <div class="section-line section-line-right"></div>
            </div>
            <div class="sidebar-user-footer flex items-center gap-3 px-2 py-2 rounded-lg">
                <?php $__avatar = $_SESSION['user']['avatar'] ?? ''; ?>
                <a href="<?= $safeAppUrl ?>/logout" title="Sair"
                    class="sidebar-avatar-logout w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-bold text-white flex-shrink-0 hover:ring-2 hover:ring-red-400 transition-all overflow-hidden"
                    style="display:none">
                    <?php if ($__avatar): ?>
                        <img src="<?= htmlspecialchars($__avatar, ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </a>
                <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-bold text-white flex-shrink-0 sidebar-avatar-normal overflow-hidden">
                    <?php if ($__avatar): ?>
                        <img src="<?= htmlspecialchars($__avatar, ENT_QUOTES, 'UTF-8') ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="user-info flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-700 dark:text-zinc-200 truncate">
                        <?= htmlspecialchars($_SESSION['user']['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="text-xs text-gray-400 dark:text-zinc-500 capitalize">
                        <?= htmlspecialchars($_SESSION['user']['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <a href="<?= $safeAppUrl ?>/logout" title="Sair"
                    class="sidebar-logout flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 dark:text-zinc-500 dark:hover:text-red-400 dark:hover:bg-red-900/40 transition-colors">
                    🏃
                </a>
            </div>
        </div>
    </aside>
    <!-- ── / SIDEBAR ───────────────────────────────────────────── -->

    <!-- ── MAIN CONTENT ──────────────────────────────────────── -->
    <div id="mainContent" class="flex-1 flex flex-col w-full overflow-hidden">

        <!-- Topbar -->
        <header class="bg-white dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-800 px-4 sm:px-6 py-3 flex items-center gap-3 flex-shrink-0 z-10 relative">

            <!-- Hambúrguer (mobile apenas) -->
            <button id="sidebarToggle"
                class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors">
                ☰
            </button>

            <h2 class="text-base font-semibold text-gray-700 dark:text-zinc-200 truncate flex-1">
                <?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <!-- Ações do topbar -->
            <div class="flex items-center gap-2 ml-auto">

                <!-- Notificações -->
                <div class="relative" id="notification-bell">
                    <button id="btnNotifications"
                        class="relative text-gray-500 hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-zinc-800"
                        title="Notificações">
                        🔔
                        <span id="notifBadge"
                            class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </button>
                    <div id="notifDropdown"
                        class="hidden absolute right-0 top-full mt-2 w-80
                               bg-white dark:bg-zinc-900 rounded-xl shadow-xl
                               border border-gray-200 dark:border-zinc-800
                               z-50 max-h-64 overflow-y-auto overflow-x-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700 dark:text-zinc-200">Notificações</span>
                            <button id="btnClearNotifs"
                                class="text-gray-500 hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400 transition-colors p-1 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-800">
                                🗑️
                            </button>
                        </div>
                        <div id="notifList" class="divide-y divide-gray-50 dark:divide-zinc-800">
                            <div class="px-4 py-3 text-sm text-gray-400 dark:text-zinc-500 text-center">Nenhuma notificação</div>
                        </div>
                    </div>
                </div>

                <!-- Toggle de tema com sol/lua dentro -->
                <button id="themeToggle" role="switch"
                    class="relative inline-flex w-11 h-6 rounded-full cursor-pointer
                           bg-gray-200 dark:bg-indigo-600
                           transition-colors duration-300
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-zinc-900"
                    title="Alternar tema (Ctrl+Shift+L)">
                    <!-- Lua: lado esquerdo, visível no dark mode -->
                    <span class="absolute left-1 top-1/2 -translate-y-1/2 text-xs leading-none opacity-0 transition-opacity duration-300 dark:opacity-100">🌙</span>
                    <!-- Sol: lado direito, visível no light mode -->
                    <span class="absolute right-1 top-1/2 -translate-y-1/2 text-xs leading-none transition-opacity duration-300 dark:opacity-0">☀️</span>
                    <!-- Círculo deslizante -->
                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-300 dark:translate-x-5"></span>
                </button>

                <!-- Data/Relógio -->
                <span class="text-sm text-gray-400 dark:text-zinc-500 hidden sm:block" id="clock"></span>
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
            mainContent.classList.remove('lg:ml-56');
            mainContent.classList.add('lg:ml-16');
        } else {
            sidebar.classList.remove('sidebar-mini');
            mainContent.classList.remove('lg:ml-16');
            mainContent.classList.add('lg:ml-56');
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
                mainContent.classList.remove('lg:ml-56');
                mainContent.classList.add('lg:ml-16');
            } else {
                mainContent.classList.remove('lg:ml-16');
                mainContent.classList.add('lg:ml-56');
            }
        } else {
            mainContent.classList.remove('lg:ml-56', 'lg:ml-16');
        }
    });

    // Flash
    setTimeout(() => document.getElementById('flashMsg')?.remove(), 5000);
</script>

<script nonce="<?= CSP_NONCE ?>">
    // Notificações (polling a cada 60s)
    (function () {
        const appUrl = <?= json_encode(APP_URL) ?>;
        const DISMISS_KEY = 'crm.notif_dismissed';
        const TOASTED = new Set();
        let notifAlerts = [];

        const badge    = document.getElementById('notifBadge');
        const list     = document.getElementById('notifList');
        const dropdown = document.getElementById('notifDropdown');
        const btnBell  = document.getElementById('btnNotifications');
        const btnClear = document.getElementById('btnClearNotifs');

        function getDismissed() {
            try {
                const raw = localStorage.getItem(DISMISS_KEY);
                return new Set(raw ? JSON.parse(raw) : []);
            } catch (e) { return new Set(); }
        }
        function saveDismissed(set) {
            try { localStorage.setItem(DISMISS_KEY, JSON.stringify(Array.from(set))); } catch (e) {}
        }
        function isDismissed(key) { return getDismissed().has(key); }
        function dismissKey(key) {
            const set = getDismissed();
            set.add(key);
            saveDismissed(set);
        }
        function dismissAll(keys) {
            const set = getDismissed();
            keys.forEach(function (k) { set.add(k); });
            saveDismissed(set);
        }
        function cleanupDismissed(activeKeys) {
            // Remove do localStorage chaves que nao estao mais no payload do servidor
            const set = getDismissed();
            const active = new Set(activeKeys);
            const next = new Set();
            set.forEach(function (k) { if (active.has(k)) next.add(k); });
            if (next.size !== set.size) saveDismissed(next);
        }

        btnBell.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', function() { dropdown.classList.add('hidden'); });
        dropdown.addEventListener('click', function(e) { e.stopPropagation(); });

        btnClear.addEventListener('click', function() {
            dismissAll(notifAlerts.map(function (a) { return a.key; }));
            notifAlerts = [];
            render();
        });

        function updateBadge() {
            if (notifAlerts.length > 0) {
                badge.textContent = notifAlerts.length > 9 ? '9+' : notifAlerts.length;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        function ensureToastContainer() {
            let c = document.getElementById('crm-toast-container');
            if (!c) {
                c = document.createElement('div');
                c.id = 'crm-toast-container';
                // Inline styles: independem do build do Tailwind. flex-col-reverse empilha
                // novos toasts ACIMA dos antigos. pointer-events:auto garante clique no X.
                c.style.cssText =
                    'position:fixed;bottom:1rem;right:1rem;z-index:9999;' +
                    'display:flex;flex-direction:column-reverse;gap:0.5rem;' +
                    'pointer-events:auto;max-width:calc(100vw - 2rem);';
                document.body.appendChild(c);
            }
            return c;
        }

        function showToast(message, type, opts) {
            opts = opts || {};
            const bgColors = {
                task:     '#4338ca',
                birthday: '#db2777',
                success:  '#16a34a',
                error:    '#dc2626',
                info:     '#374151',
            };
            const container = ensureToastContainer();
            const toast = document.createElement('div');
            toast.style.cssText =
                'background-color:' + (bgColors[type] || '#374151') + ';' +
                'color:#fff;padding:0.75rem 1rem;border-radius:0.75rem;' +
                'box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05);' +
                'font-size:0.875rem;line-height:1.25rem;max-width:20rem;' +
                'display:flex;align-items:flex-start;gap:0.5rem;pointer-events:auto;';

            const msg = document.createElement('span');
            msg.style.cssText = 'flex:1 1 0%;min-width:0;word-break:break-word;';
            msg.textContent = message;

            const btnX = document.createElement('button');
            btnX.type = 'button';
            btnX.setAttribute('aria-label', 'Dispensar');
            btnX.style.cssText =
                'flex-shrink:0;background:transparent;border:0;cursor:pointer;' +
                'color:rgba(255,255,255,0.75);font-size:1.125rem;line-height:1;' +
                'font-weight:700;padding:0 0.25rem;margin:-0.125rem -0.25rem 0 0;';
            btnX.textContent = '\u00D7';
            btnX.addEventListener('mouseenter', function () { btnX.style.color = '#fff'; });
            btnX.addEventListener('mouseleave', function () { btnX.style.color = 'rgba(255,255,255,0.75)'; });

            let removed = false;
            function removeToast() {
                if (removed) return;
                removed = true;
                toast.remove();
            }
            btnX.addEventListener('click', function (e) {
                e.stopPropagation();
                removeToast();
            });

            toast.appendChild(msg);
            toast.appendChild(btnX);
            container.appendChild(toast);

            const ttl = typeof opts.duration === 'number' ? opts.duration : 8000;
            if (ttl > 0) setTimeout(removeToast, ttl);
            return removeToast;
        }

        // Exp\u00f5e helper global para outras telas (ex.: clients/show.php)
        window.crmToast = showToast;

        function render() {
            list.innerHTML = '';
            if (notifAlerts.length === 0) {
                list.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400 dark:text-zinc-500 text-center">Nenhuma notificação</div>';
                updateBadge();
                return;
            }
            notifAlerts.forEach(function (item) {
                const row = document.createElement('div');
                row.className = 'px-4 py-3 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-800/50 flex items-start gap-2';
                const icon = item.type === 'birthday' ? '🎂' : '⏰';
                const msgSpan = document.createElement('span');
                msgSpan.className = 'flex-1 min-w-0 break-words';
                msgSpan.textContent = icon + ' ' + item.message;
                const btnX = document.createElement('button');
                btnX.type = 'button';
                btnX.className = 'flex-shrink-0 text-gray-400 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400 transition-colors p-0.5 rounded';
                btnX.setAttribute('aria-label', 'Dispensar');
                btnX.dataset.key = item.key;
                btnX.textContent = '✕';
                btnX.addEventListener('click', function () {
                    dismissKey(item.key);
                    notifAlerts = notifAlerts.filter(function (a) { return a.key !== item.key; });
                    render();
                });
                row.appendChild(msgSpan);
                row.appendChild(btnX);
                list.appendChild(row);
            });
            updateBadge();
        }

        async function checkNotifications() {
            try {
                const resp = await fetch(appUrl + '/api/tasks/upcoming');
                if (!resp.ok) return;
                const data = await resp.json();
                cleanupDismissed(data.map(function (i) { return i.key; }));
                notifAlerts = [];
                data.forEach(function(item) {
                    if (isDismissed(item.key)) return;
                    notifAlerts.push(item);
                    if (!TOASTED.has(item.key)) {
                        TOASTED.add(item.key);
                        showToast(item.message, item.type);
                    }
                });
                render();
            } catch (e) { /* silencia erros de rede */ }
        }

        render();
        checkNotifications();
        setInterval(checkNotifications, 60000);
    })();
</script>

<!-- Mascaras globais (data-mask="currency" / data-mask="digits") -->
<script nonce="<?= CSP_NONCE ?>" src="<?= $safeAppUrl ?>/assets/js/masks.js?v=<?= $assetV('assets/js/masks.js') ?>"></script>

<!-- Custom Select global (substitui <select> nativos) -->
<script nonce="<?= CSP_NONCE ?>" src="<?= $safeAppUrl ?>/assets/js/custom-select.js?v=<?= $assetV('assets/js/custom-select.js') ?>"></script>

</body>
</html>
