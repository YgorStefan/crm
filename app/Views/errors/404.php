<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Página não encontrada</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="text-8xl mb-4">🔍</div>
        <h1 class="text-6xl font-bold text-gray-800 mb-2">404</h1>
        <p class="text-xl text-gray-500 mb-6">Página não encontrada</p>
        <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/dashboard"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-3 rounded-lg transition-colors">
            ← Voltar ao Dashboard
        </a>
    </div>
</body>
</html>
