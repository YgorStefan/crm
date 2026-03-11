<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Tailwind CSS via CDN (ideal para hospedagem compartilhada) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Configuração personalizada do Tailwind
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#4f46e5', hover: '#4338ca' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <?= $content ?>
</body>
</html>
