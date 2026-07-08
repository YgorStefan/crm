<?php
// Variáveis injetadas pelo Controller::render() via extract($data)
$tenant        = $tenant ?? [];
$has_gmaps_key = $has_gmaps_key ?? false;
$csrf_token    = $csrf_token ?? '';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Configurações da Organização</h3>
    </div>

    <form method="POST" action="<?= APP_URL ?>/settings/update">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Seção: Dados da Organização -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                <h4 class="font-semibold text-gray-700 dark:text-zinc-200">Dados da Organização</h4>
                <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">Informações básicas do seu tenant.</p>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div>
                    <label for="settings_tenant_name" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                        Nome da Organização
                    </label>
                    <input type="text" id="settings_tenant_name" name="tenant_name" required
                        value="<?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label for="settings_tenant_slug" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                        Identificador (slug)
                    </label>
                    <input type="text" id="settings_tenant_slug" disabled
                        value="<?= htmlspecialchars($tenant['slug'], ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full px-3 py-2 border border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/50 rounded-lg text-sm text-gray-400 dark:text-zinc-500 cursor-not-allowed">
                    <p class="text-xs text-gray-400 dark:text-zinc-600 mt-1">
                        O identificador é definido na criação do tenant e não pode ser alterado.
                    </p>
                </div>
            </div>
        </div>

        <!-- Seção: Ciclo de Pagamento -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                <h4 class="font-semibold text-gray-700 dark:text-zinc-200">Ciclo de Pagamento</h4>
                <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">
                    Configura quando o ciclo mensal de cotas começa.
                </p>
            </div>
            <div class="px-5 py-4">
                <label for="settings_payment_cutoff_day" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Dia de corte do ciclo (1–28)
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" id="settings_payment_cutoff_day" name="payment_cutoff_day" min="1" max="28" required
                        value="<?= (int) $tenant['payment_cutoff_day'] ?>"
                        class="w-24 px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <span class="text-sm text-gray-500 dark:text-zinc-400">dia do mês</span>
                </div>
                <p class="text-xs text-gray-400 dark:text-zinc-600 mt-2">
                    Cotas com <code>paid_at</code> antes deste dia no mês atual são consideradas em atraso.
                    Valor padrão: <strong>20</strong>.
                    Limite máximo: 28 (compatível com todos os meses).
                </p>
            </div>
        </div>

        <!-- Seção: Integrações -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                <h4 class="font-semibold text-gray-700 dark:text-zinc-200">Integrações</h4>
                <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">
                    Chaves de API para serviços externos.
                </p>
            </div>
            <div class="px-5 py-4">
                <label for="gmapsKeyInput" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Chave API Google Maps
                </label>
                <?php if ($has_gmaps_key): ?>
                    <p class="text-xs text-green-600 dark:text-green-400 mb-2 flex items-center gap-1">
                        ✅ Chave configurada. Preencha abaixo apenas para substituir.
                    </p>
                <?php else: ?>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1">
                        ⚠️ Nenhuma chave configurada. O módulo de Prospecção não funcionará sem ela.
                    </p>
                <?php endif; ?>
                <div class="relative">
                    <input type="password" name="google_maps_api_key" id="gmapsKeyInput"
                        autocomplete="off" placeholder="<?= $has_gmaps_key ? 'Nova chave (deixe vazio para manter a atual)' : 'Cole sua chave API aqui' ?>"
                        class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <button type="button" id="toggleGmapsKey"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-zinc-300 text-xs px-1"
                        title="Mostrar/ocultar chave">
                        👁
                    </button>
                </div>
                <p class="text-xs text-gray-400 dark:text-zinc-600 mt-1">
                    Ative as APIs <strong>Places API (New)</strong> e <strong>Places API</strong> no Google Cloud Console.
                    Restrinja a chave por IP do servidor para segurança.
                </p>
            </div>
        </div>
        <?php
        $pageScripts = ($pageScripts ?? '') . '
<script nonce="' . CSP_NONCE . '">
document.addEventListener("DOMContentLoaded", function () {
    var btn   = document.getElementById("toggleGmapsKey");
    var input = document.getElementById("gmapsKeyInput");
    if (!btn || !input) return;
    btn.addEventListener("click", function () {
        input.type = input.type === "password" ? "text" : "password";
        btn.textContent = input.type === "password" ? "👁" : "🙈";
    });
});
</script>';
        ?>

        <!-- Ações -->
        <div class="flex justify-end">
            <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition-colors">
                Salvar configurações
            </button>
        </div>
    </form>
</div>
