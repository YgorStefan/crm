<?php ?>
<div class="w-full max-w-xs bg-white dark:bg-zinc-900 rounded-2xl shadow-xl overflow-hidden">

    <!-- Cabeçalho colorido -->
    <div class="bg-indigo-600 dark:bg-indigo-700 px-6 py-5 text-center">
        <div class="flex justify-center mb-2 text-3xl">
            👤
        </div>
        <h1 class="text-xl font-bold text-white"><?= APP_NAME ?></h1>
        <p class="text-indigo-200 text-xs mt-1">Acesse sua conta para continuar</p>
    </div>

    <div class="px-6 py-6">

        <?php if (!empty($timeout)): ?>
        <div class="mb-5 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300 rounded-lg text-sm">
            Sua sessão expirou por inatividade. Por favor, faça login novamente.
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="mb-5 p-3 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg text-sm flex items-center gap-2">
            ⚠️ <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/login" novalidate>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">E-mail</label>
                <input
                    type="email" id="email" name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required autocomplete="username" placeholder="seu@email.com"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-lg text-sm
                           bg-white dark:bg-zinc-800 text-gray-800 dark:text-white
                           placeholder-gray-400 dark:placeholder-zinc-600
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            </div>

            <div class="mb-5">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Senha</label>
                <div class="relative">
                    <input
                        type="password" id="password" name="password"
                        required autocomplete="current-password" placeholder="••••••••"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-lg text-sm
                               bg-white dark:bg-zinc-800 text-gray-800 dark:text-white
                               placeholder-gray-400 dark:placeholder-zinc-600
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors pr-10">
                    <button type="button" onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-zinc-500 hover:text-gray-600 dark:hover:text-zinc-300 transition-colors"
                        title="Mostrar/ocultar senha">
                        <span id="eyeIconSvg">👁️</span>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="block mx-auto px-12 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
                       py-2 rounded-lg transition-colors duration-200 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                Entrar
            </button>
        </form>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    function togglePassword() {
        const input = document.getElementById('password');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        document.getElementById('eyeIconSvg').textContent = isHidden ? '🙈' : '👁️';
    }
</script>
