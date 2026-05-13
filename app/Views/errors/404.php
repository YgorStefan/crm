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
