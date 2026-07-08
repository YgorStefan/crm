<?php
// Variáveis injetadas pelo Controller::render() via extract($data)
$csrf_token = $csrf_token ?? '';
$forced     = $forced ?? false;
$error      = $error ?? '';
?>
<div class="w-full max-w-xs bg-white dark:bg-zinc-900 rounded-2xl shadow-xl overflow-hidden">

    <!-- Cabeçalho colorido -->
    <div class="bg-indigo-600 dark:bg-indigo-700 px-6 py-5 text-center">
        <div class="flex justify-center mb-2 text-3xl">
            🔒
        </div>
        <h1 class="text-xl font-bold text-white">Trocar Senha</h1>
        <p class="text-indigo-200 text-xs mt-1">
            <?= $forced
                ? 'Por segurança, defina uma nova senha antes de continuar'
                : 'Defina uma nova senha de acesso' ?>
        </p>
    </div>

    <div class="px-6 py-6">

        <?php if (!empty($error)): ?>
        <div class="mb-5 p-3 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg text-sm flex items-center gap-2">
            ⚠️ <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/profile/change-password" novalidate>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-4">
                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Senha atual</label>
                <input
                    type="password" id="current_password" name="current_password"
                    required autocomplete="current-password" placeholder="••••••••"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-lg text-sm
                           bg-white dark:bg-zinc-800 text-gray-800 dark:text-white
                           placeholder-gray-400 dark:placeholder-zinc-600
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            </div>

            <div class="mb-4">
                <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Nova senha</label>
                <input
                    type="password" id="new_password" name="new_password"
                    required autocomplete="new-password" minlength="8" placeholder="Mínimo 8 caracteres"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-lg text-sm
                           bg-white dark:bg-zinc-800 text-gray-800 dark:text-white
                           placeholder-gray-400 dark:placeholder-zinc-600
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            </div>

            <div class="mb-5">
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Confirmar nova senha</label>
                <input
                    type="password" id="confirm_password" name="confirm_password"
                    required autocomplete="new-password" minlength="8" placeholder="Repita a nova senha"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-lg text-sm
                           bg-white dark:bg-zinc-800 text-gray-800 dark:text-white
                           placeholder-gray-400 dark:placeholder-zinc-600
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
                       py-2 rounded-lg transition-colors duration-200 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                Salvar nova senha
            </button>

            <?php if (!$forced): ?>
            <a href="<?= APP_URL ?>/dashboard"
               class="block text-center mt-3 text-sm text-gray-500 dark:text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                Cancelar
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>
