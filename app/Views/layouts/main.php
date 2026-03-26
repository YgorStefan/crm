<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
                        sidebar: '#1e1b4b',
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.20/locales-all.global.min.js"></script>
    <style>
        /* Scrollbar personalizada para o Kanban */
        .kanban-scroll::-webkit-scrollbar {
            height: 6px;
        }

        .kanban-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .kanban-scroll::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }

        /* Cartão Kanban sendo arrastado */
        .dragging {
            opacity: 0.4;
            transform: rotate(2deg);
        }

        .drag-over {
            outline: 2px dashed #4f46e5;
            outline-offset: 2px;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans text-gray-800">

    <div class="flex h-screen overflow-hidden bg-gray-100">

        <div id="sidebarBackdrop"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden lg:hidden transition-opacity"></div>

        <aside id="sidebar"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-sidebar text-white flex flex-col transform -translate-x-full transition-transform duration-300 lg:relative lg:translate-x-0">
            <div class="px-6 py-5 border-b border-indigo-800 flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold tracking-wide"><?= APP_NAME ?></h1>
                    <p class="text-xs text-indigo-300 mt-1">Gestão de Relacionamento</p>
                </div>
                <button id="closeSidebarBtn" class="lg:hidden text-indigo-200 hover:text-white">
                    ✕
                </button>
            </div>

            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <?php
                // Função auxiliar para destacar o item de menu ativo
                function navLink(string $href, string $icon, string $label, string $currentPath): string
                {
                    $active = ($currentPath === $href || str_starts_with($currentPath, $href . '/'));
                    $base = 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors';
                    $cls = $active ? "$base bg-indigo-600 text-white" : "$base text-indigo-200 hover:bg-indigo-800 hover:text-white";
                    return "<a href=\"" . APP_URL . $href . "\" class=\"$cls\">$icon <span>$label</span></a>";
                }
                // Caminho atual para destacar o item ativo
                $basePath = parse_url(APP_URL, PHP_URL_PATH) ?? '';
                $currentPath = substr($_SERVER['REQUEST_URI'], strlen($basePath));
                $currentPath = strtok($currentPath, '?') ?: '/';
                ?>

                <?= navLink('/dashboard', '📊', 'Dashboard', $currentPath) ?>
                <?= navLink('/clients', '👥', 'Clientes', $currentPath) ?>
                <?= navLink('/pipeline', '🏷️', 'Pipeline', $currentPath) ?>
                <?= navLink('/tasks', '📅', 'Calendário', $currentPath) ?>
                <?= navLink('/cold-contacts', '🧊', 'Contatos frios', $currentPath) ?>
                <?= navLink('/acompanhamento', '📈', 'Acompanhamento', $currentPath) ?>

                <div class="pt-3 mt-3 border-t border-indigo-800">
                    <p class="text-xs uppercase text-indigo-400 px-3 mb-2">Acesso Rápido</p>
                    <a href="https://avapro.ademicon.com.br/" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-indigo-200 hover:bg-indigo-800 hover:text-white">
                        🚀 <span>AVA Pro</span>
                    </a>
                    <a href="https://webmail.autorizadoademicon.com.br/?_task=mail&_mbox=INBOX" target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-indigo-200 hover:bg-indigo-800 hover:text-white">
                        📧 <span>Webmail</span>
                    </a>
                    <a href="https://crmapollo.com.br/app/views/index.php" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-indigo-200 hover:bg-indigo-800 hover:text-white">
                        🏠 <span>CRM Apollo</span>
                    </a>
                </div>

                <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <div class="pt-3 mt-3 border-t border-indigo-800">
                        <p class="text-xs uppercase text-indigo-400 px-3 mb-2">Administração</p>
                        <?= navLink('/admin/users', '👤', 'Usuários', $currentPath) ?>
                    </div>
                <?php endif; ?>
            </nav>

            <div class="px-4 py-4 border-t border-indigo-800">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-bold">
                        <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">
                            <?= htmlspecialchars($_SESSION['user']['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-indigo-300 capitalize"><?= $_SESSION['user']['role'] ?? '' ?></p>
                    </div>
                    <a href="<?= APP_URL ?>/logout" title="Sair"
                        class="text-indigo-300 hover:text-white flex-shrink-0 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col w-full overflow-hidden">

            <header
                class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex items-center justify-between flex-shrink-0 z-10 relative">
                <div class="flex items-center gap-3 w-full">
                    <button id="sidebarToggle" class="text-gray-500 hover:text-gray-700 lg:hidden p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-700 truncate">
                        <?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <!-- Notificacoes -->
                    <div class="relative ml-auto" id="notification-bell">
                        <button id="btnNotifications"
                            class="relative text-gray-500 hover:text-indigo-600 transition-colors p-1"
                            title="Notificacoes">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <span id="notifBadge"
                                class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">0</span>
                        </button>
                        <!-- Dropdown de notificacoes -->
                        <div id="notifDropdown"
                            class="hidden absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50 max-h-64 overflow-y-auto">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-700">Notificacoes</span>
                                <button id="btnClearNotifs"
                                    class="text-xs text-indigo-600 hover:underline">Limpar</button>
                            </div>
                            <div id="notifList" class="divide-y divide-gray-50">
                                <div class="px-4 py-3 text-sm text-gray-400 text-center">Nenhuma notificacao</div>
                            </div>
                        </div>
                    </div>
                    <span class="text-sm text-gray-400 hidden sm:block ml-2" id="clock"></span>
                </div>
            </header>

            <?php if (!empty($_SESSION['flash'])): ?>
                <?php $flash = $_SESSION['flash'];
                unset($_SESSION['flash']); ?>
                <?php
                $flashColors = [
                    'success' => 'bg-green-50 border-green-400 text-green-800',
                    'error' => 'bg-red-50 border-red-400 text-red-800',
                    'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
                    'info' => 'bg-blue-50 border-blue-400 text-blue-800',
                ];
                $flashColor = $flashColors[$flash['type']] ?? $flashColors['info'];
                ?>
                <div id="flashMsg"
                    class="mx-4 sm:mx-6 mt-4 px-4 py-3 rounded border-l-4 <?= $flashColor ?> flex items-center justify-between">
                    <span
                        class="text-sm sm:text-base"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></span>
                    <button onclick="document.getElementById('flashMsg').remove()"
                        class="ml-4 text-lg font-bold opacity-60 hover:opacity-100">&times;</button>
                </div>
            <?php endif; ?>

            <main class="flex-1 overflow-y-auto p-4 sm:p-6 w-full">
                <?= $content ?>
            </main>
        </div>
    </div>

    <script>
        // Relógio em tempo real no topbar
        function updateClock() {
            const now = new Date();
            const clockEl = document.getElementById('clock');
            if (clockEl) {
                clockEl.textContent = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            }
        }
        updateClock();
        setInterval(updateClock, 60000);

        // LÓGICA DO MENU RESPONSIVO (SIDEBAR)
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggleBtn = document.getElementById('sidebarToggle');
        const closeBtn = document.getElementById('closeSidebarBtn');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        }

        // Abre ao clicar no botão hambúrguer
        toggleBtn?.addEventListener('click', openSidebar);

        // Fecha ao clicar no botão "X" de dentro do menu
        closeBtn?.addEventListener('click', closeSidebar);

        // Fecha ao clicar na área escura fora do menu
        backdrop?.addEventListener('click', closeSidebar);

        // Auto-remove flash message após 5 segundos
        setTimeout(() => document.getElementById('flashMsg')?.remove(), 5000);
    </script>

    <script>
        // Polling leve a cada 60s — sem WebSocket/ServiceWorker
        (function () {
            const appUrl = '<?= APP_URL ?>';
            const NOTIFIED = new Set();
            const notifAlerts = [];

            const badge = document.getElementById('notifBadge');
            const list = document.getElementById('notifList');
            const dropdown = document.getElementById('notifDropdown');
            const btnBell = document.getElementById('btnNotifications');
            const btnClear = document.getElementById('btnClearNotifs');

            // Toggle dropdown
            btnBell.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', function () {
                dropdown.classList.add('hidden');
            });
            dropdown.addEventListener('click', function (e) { e.stopPropagation(); });

            // Clear notifications
            btnClear.addEventListener('click', function () {
                notifAlerts.length = 0;
                updateBadge();
                list.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400 text-center">Nenhuma notificacao</div>';
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
                const colors = {
                    task: 'bg-indigo-700',
                    birthday: 'bg-pink-600',
                };
                const bgColor = colors[type] || 'bg-gray-700';
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-4 right-4 z-50 ' + bgColor + ' text-white px-4 py-3 rounded-xl shadow-lg text-sm max-w-xs animate-pulse';
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(function () { toast.remove(); }, 8000);
            }

            function addToDropdown(item) {
                // Remove empty state message
                const empty = list.querySelector('.text-gray-400');
                if (empty) empty.remove();

                const div = document.createElement('div');
                div.className = 'px-4 py-3 text-sm text-gray-700 hover:bg-gray-50';
                const icon = item.type === 'birthday' ? '🎂' : '⏰';
                div.textContent = icon + ' ' + item.message;
                list.prepend(div);
            }

            async function checkNotifications() {
                try {
                    const resp = await fetch(appUrl + '/api/tasks/upcoming');
                    if (!resp.ok) return;
                    const data = await resp.json();

                    data.forEach(function (item) {
                        if (NOTIFIED.has(item.key)) return;
                        NOTIFIED.add(item.key);
                        notifAlerts.push(item);
                        showToast(item.message, item.type);
                        addToDropdown(item);
                    });
                    updateBadge();
                } catch (e) { /* silencia erros de rede */ }
            }

            // Roda imediatamente e depois a cada 60 segundos
            checkNotifications();
            setInterval(checkNotifications, 60000);
        })();
    </script>

</body>

</html>