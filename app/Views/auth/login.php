<?php ?>
<div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden">

    <!-- Cabeçalho colorido -->
    <div class="bg-indigo-600 dark:bg-indigo-700 px-8 py-8 text-center">
        <div class="flex justify-center mb-3">
            <svg width="40" height="40" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">
                <rect x="2" y="7" width="20" height="14" rx="1"/>
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                <line x1="12" y1="12" x2="12" y2="16"/>
                <line x1="10" y1="14" x2="14" y2="14"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white"><?= APP_NAME ?></h1>
        <p class="text-indigo-200 text-sm mt-1">Acesse sua conta para continuar</p>
    </div>

    <div class="px-8 py-8">

        <?php if (!empty($timeout)): ?>
        <div class="mb-5 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300 rounded-lg text-sm">
            Sua sessão expirou por inatividade. Por favor, faça login novamente.
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="mb-5 p-3 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg text-sm flex items-center gap-2">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/login" novalidate>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-5">
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">E-mail</label>
                <input
                    type="email" id="email" name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required autocomplete="username" placeholder="seu@email.com"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm
                           bg-white dark:bg-slate-700 text-gray-800 dark:text-white
                           placeholder-gray-400 dark:placeholder-slate-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Senha</label>
                <div class="relative">
                    <input
                        type="password" id="password" name="password"
                        required autocomplete="current-password" placeholder="••••••••"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm
                               bg-white dark:bg-slate-700 text-gray-800 dark:text-white
                               placeholder-gray-400 dark:placeholder-slate-500
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors pr-10">
                    <button type="button" onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition-colors"
                        title="Mostrar/ocultar senha">
                        <span id="eyeIconSvg">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
                       py-2.5 px-4 rounded-lg transition-colors duration-200 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800">
                Entrar no Sistema
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-gray-400 dark:text-slate-600">
            <?= APP_NAME ?> &copy; <?= date('Y') ?>
        </p>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    function togglePassword() {
        const input = document.getElementById('password');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        document.getElementById('eyeIconSvg').innerHTML = isHidden
            ? '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
            : '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
</script>
