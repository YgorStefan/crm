<?php
?>
<div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">

    <!-- Cabeçalho colorido -->
    <div class="bg-indigo-600 px-8 py-8 text-center">
        <div class="text-4xl mb-2">🏢</div>
        <h1 class="text-2xl font-bold text-white"><?= APP_NAME ?></h1>
        <p class="text-indigo-200 text-sm mt-1">Acesse sua conta para continuar</p>
    </div>

    <div class="px-8 py-8">

        <!-- Alerta de sessão expirada -->
        <?php if (!empty($timeout)): ?>
        <div class="mb-5 p-3 bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg text-sm">
            ⚠️ Sua sessão expirou por inatividade. Por favor, faça login novamente.
        </div>
        <?php endif; ?>

        <!-- Mensagem de erro de login -->
        <?php if (!empty($error)): ?>
        <div class="mb-5 p-3 bg-red-50 border border-red-300 text-red-700 rounded-lg text-sm flex items-center gap-2">
            <span>⛔</span>
            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>

        <!--
            Formulário de login.
            O action aponta para a rota POST /login.
            O campo hidden _csrf_token é validado pelo CsrfMiddleware antes
            de o AuthController::login() ser executado.
        -->
        <form method="POST" action="<?= APP_URL ?>/login" novalidate>
            <!-- Token CSRF oculto — obrigatório em todos os formulários POST -->
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <!-- Campo E-mail -->
            <div class="mb-5">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    E-mail
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autocomplete="username"
                    placeholder="seu@email.com"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                           transition-colors"
                >
            </div>

            <!-- Campo Senha -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-1">
                    <label for="password" class="block text-sm font-medium text-gray-700">Senha</label>
                </div>
                <div class="relative">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                               transition-colors pr-10"
                    >
                    <!-- Botão para mostrar/ocultar senha -->
                    <button
                        type="button"
                        onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        title="Mostrar/ocultar senha"
                    >
                        <span id="eyeIcon">👁️</span>
                    </button>
                </div>
            </div>

            <!-- Botão de submit -->
            <button
                type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
                       py-2.5 px-4 rounded-lg transition-colors duration-200 text-sm
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                Entrar no Sistema
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-gray-400">
            <?= APP_NAME ?> &copy; <?= date('Y') ?>
        </p>
    </div>
</div>

<script>
    // Alterna a visibilidade do campo de senha
    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = '🙈';
        } else {
            input.type = 'password';
            icon.textContent = '👁️';
        }
    }
</script>
