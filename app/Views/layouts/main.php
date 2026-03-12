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
                        primary:  { 50:'#eef2ff', 100:'#e0e7ff', 500:'#6366f1', 600:'#4f46e5', 700:'#4338ca' },
                        sidebar:  '#1e1b4b',
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Scrollbar personalizada para o Kanban */
        .kanban-scroll::-webkit-scrollbar { height: 6px; }
        .kanban-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .kanban-scroll::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 3px; }
        /* Cartão Kanban sendo arrastado */
        .dragging { opacity: 0.4; transform: rotate(2deg); }
        .drag-over { outline: 2px dashed #4f46e5; outline-offset: 2px; }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-800">

<div class="flex h-screen overflow-hidden bg-gray-100">

    <div id="sidebarBackdrop" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden lg:hidden transition-opacity"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-sidebar text-white flex flex-col transform -translate-x-full transition-transform duration-300 lg:relative lg:translate-x-0">
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
            function navLink(string $href, string $icon, string $label, string $currentPath): string {
                $active = ($currentPath === $href || str_starts_with($currentPath, $href . '/'));
                $base   = 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors';
                $cls    = $active ? "$base bg-indigo-600 text-white" : "$base text-indigo-200 hover:bg-indigo-800 hover:text-white";
                return "<a href=\"" . APP_URL . $href . "\" class=\"$cls\">$icon <span>$label</span></a>";
            }
            // Caminho atual para destacar o item ativo
            $basePath = parse_url(APP_URL, PHP_URL_PATH);
            $currentPath = substr($_SERVER['REQUEST_URI'], strlen($basePath));
            $currentPath = strtok($currentPath, '?') ?: '/';
            ?>

            <?= navLink('/dashboard', '📊', 'Dashboard', $currentPath) ?>
            <?= navLink('/clients',   '👥', 'Clientes',  $currentPath) ?>
            <?= navLink('/pipeline',  '🏷️', 'Pipeline',  $currentPath) ?>
            <?= navLink('/tasks',     '✅', 'Tarefas',   $currentPath) ?>

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
                    <p class="text-sm font-medium truncate"><?= htmlspecialchars($_SESSION['user']['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-indigo-300 capitalize"><?= $_SESSION['user']['role'] ?? '' ?></p>
                </div>
                <a href="<?= APP_URL ?>/logout" title="Sair" class="text-indigo-300 hover:text-white text-lg flex-shrink-0">⏻</a>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col w-full overflow-hidden">

        <header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex items-center justify-between flex-shrink-0 z-10 relative">
            <div class="flex items-center gap-3 w-full">
                <button id="sidebarToggle" class="text-gray-500 hover:text-gray-700 lg:hidden p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h2 class="text-lg font-semibold text-gray-700 truncate">
                    <?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?>
                </h2>
                
                <span class="text-sm text-gray-400 ml-auto hidden sm:block" id="clock"></span>
            </div>
        </header>

        <?php if (!empty($_SESSION['flash'])): ?>
        <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <?php
            $flashColors = [
                'success' => 'bg-green-50 border-green-400 text-green-800',
                'error'   => 'bg-red-50 border-red-400 text-red-800',
                'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
                'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
            ];
            $flashColor = $flashColors[$flash['type']] ?? $flashColors['info'];
        ?>
        <div id="flashMsg" class="mx-4 sm:mx-6 mt-4 px-4 py-3 rounded border-l-4 <?= $flashColor ?> flex items-center justify-between">
            <span class="text-sm sm:text-base"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></span>
            <button onclick="document.getElementById('flashMsg').remove()" class="ml-4 text-lg font-bold opacity-60 hover:opacity-100">&times;</button>
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
            clockEl.textContent = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
        }
    }
    updateClock();
    setInterval(updateClock, 60000);

    // ==========================================
    // LÓGICA DO MENU RESPONSIVO (SIDEBAR)
    // ==========================================
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

</body>
</html>