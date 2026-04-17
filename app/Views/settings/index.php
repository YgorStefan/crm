<?php ?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Configurações da Organização</h3>
    </div>

    <form method="POST" action="<?= APP_URL ?>/settings/update">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Seção: Dados da Organização -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="font-semibold text-gray-700">Dados da Organização</h4>
                <p class="text-sm text-gray-500 mt-0.5">Informações básicas do seu tenant.</p>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nome da Organização
                    </label>
                    <input type="text" name="tenant_name" required
                        value="<?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Identificador (slug)
                    </label>
                    <input type="text" disabled
                        value="<?= htmlspecialchars($tenant['slug'], ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-sm text-gray-400 cursor-not-allowed">
                    <p class="text-xs text-gray-400 mt-1">
                        O identificador é definido na criação do tenant e não pode ser alterado.
                    </p>
                </div>
            </div>
        </div>

        <!-- Seção: Ciclo de Pagamento -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="font-semibold text-gray-700">Ciclo de Pagamento</h4>
                <p class="text-sm text-gray-500 mt-0.5">
                    Configura quando o ciclo mensal de cotas começa.
                </p>
            </div>
            <div class="px-5 py-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Dia de corte do ciclo (1–28)
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" name="payment_cutoff_day" min="1" max="28" required
                        value="<?= (int) $tenant['payment_cutoff_day'] ?>"
                        class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <span class="text-sm text-gray-500">dia do mês</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Cotas com <code>paid_at</code> antes deste dia no mês atual são consideradas em atraso.
                    Valor padrão: <strong>20</strong>.
                    Limite máximo: 28 (compatível com todos os meses).
                </p>
            </div>
        </div>

        <!-- Ações -->
        <div class="flex justify-end">
            <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition-colors">
                Salvar configurações
            </button>
        </div>
    </form>
</div>
