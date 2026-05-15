<?php
/**
 * View: admin/index.php — Painel administrativo unificado com 3 abas.
 * Variaveis: $activeTab, $csrf_token, $users (na aba users), $tenant (nas abas org/payment)
 */
$tabs = [
    'users'   => 'Usuários',
    'org'     => 'Organização',
    'payment' => 'Ciclo de Pagamento',
];
$roleLabels = ['admin' => 'Admin', 'seller' => 'Vendedor', 'viewer' => 'Leitor'];
$roleBadges = [
    'admin'  => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    'seller' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'viewer' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-400',
];
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Administração</h3>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1"><?= htmlspecialchars($tabs[$activeTab], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php if ($activeTab === 'users'): ?>
    <a href="<?= APP_URL ?>/admin/users/create"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
        ➕ Novo Usuário
    </a>
    <?php endif; ?>
</div>

<!-- Tabs -->
<div class="flex flex-wrap gap-1 mb-4 border-b border-gray-200 dark:border-slate-700" role="tablist">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= APP_URL ?>/admin?tab=<?= $key ?>"
           role="tab"
           aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>"
           class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors <?= $activeTab === $key
                ? 'border-indigo-600 text-indigo-700 dark:text-indigo-400 dark:border-indigo-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200 hover:border-gray-300 dark:hover:border-slate-600' ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($activeTab === 'users'): ?>
    <!-- ====================== TAB: USUÁRIOS ====================== -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-600">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 dark:text-slate-400">Usuário</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 dark:text-slate-400">E-mail</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 dark:text-slate-400">Perfil</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 dark:text-slate-400">Status</th>
                        <th class="text-center px-5 py-3 font-semibold text-gray-600 dark:text-slate-400">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 flex items-center justify-center font-bold text-sm flex-shrink-0">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <span class="font-medium text-gray-700 dark:text-slate-200"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-500 dark:text-slate-400"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-5 py-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $roleBadges[$user['role']] ?? $roleBadges['viewer'] ?>">
                                <?= $roleLabels[$user['role']] ?? $user['role'] ?>
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <?php if ($user['is_active']): ?>
                            <span class="text-xs text-green-700 dark:text-green-400 bg-green-100 dark:bg-green-900/40 px-2 py-0.5 rounded-full">Ativo</span>
                            <?php else: ?>
                            <span class="text-xs text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/40 px-2 py-0.5 rounded-full">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <a href="<?= APP_URL ?>/admin/users/<?= $user['id'] ?>/edit"
                               data-tooltip="Editar"
                               class="has-tooltip inline-flex items-center justify-center w-7 h-7 rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/40 transition-colors">
                                ✏️
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'org'): ?>
    <!-- ====================== TAB: ORGANIZAÇÃO ====================== -->
    <div class="max-w-2xl">
        <form method="POST" action="<?= APP_URL ?>/settings/update">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_redirect_to" value="/admin?tab=org">
            <input type="hidden" name="payment_cutoff_day" value="<?= (int) $tenant['payment_cutoff_day'] ?>">

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h4 class="font-semibold text-gray-700 dark:text-slate-200">Dados da Organização</h4>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Informações básicas do seu tenant.</p>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nome da Organização</label>
                        <input type="text" name="tenant_name" required
                            value="<?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Identificador (slug)</label>
                        <input type="text" disabled
                            value="<?= htmlspecialchars($tenant['slug'], ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full px-3 py-2 border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 rounded-lg text-sm text-gray-400 dark:text-slate-500 cursor-not-allowed">
                        <p class="text-xs text-gray-400 dark:text-slate-600 mt-1">O identificador é definido na criação do tenant e não pode ser alterado.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition-colors">
                    Salvar
                </button>
            </div>
        </form>
    </div>

<?php elseif ($activeTab === 'payment'): ?>
    <!-- ====================== TAB: CICLO DE PAGAMENTO ====================== -->
    <div class="max-w-2xl">
        <form method="POST" action="<?= APP_URL ?>/settings/update">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_redirect_to" value="/admin?tab=payment">
            <input type="hidden" name="tenant_name" value="<?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h4 class="font-semibold text-gray-700 dark:text-slate-200">Ciclo de Pagamento</h4>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Configura quando o ciclo mensal de cotas começa.</p>
                </div>
                <div class="px-5 py-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Dia de corte do ciclo (1–28)</label>
                    <div class="flex items-center gap-3">
                        <input type="number" name="payment_cutoff_day" min="1" max="28" required
                            value="<?= (int) $tenant['payment_cutoff_day'] ?>"
                            class="w-24 px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span class="text-sm text-gray-500 dark:text-slate-400">dia do mês</span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-slate-600 mt-2">
                        Cotas com <code>paid_at</code> antes deste dia no mês atual são consideradas em atraso.
                        Valor padrão: <strong>20</strong>. Limite máximo: 28 (compatível com todos os meses).
                    </p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition-colors">
                    Salvar
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>
