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
