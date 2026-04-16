<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <?php $safeAppUrl = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8'); ?>
    <link rel="stylesheet" href="<?= $safeAppUrl ?>/assets/css/tailwind.css">
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <?= $content ?>
</body>

</html>