<?php
/**
 * View: admin/users/index.php — Lista de Usuários
 * Variáveis: $users
 */
$roleLabels = ['admin' => '🔑 Admin', 'seller' => '💼 Vendedor', 'viewer' => '👁️ Leitor'];
$roleBadges = ['admin' => 'bg-purple-100 text-purple-700', 'seller' => 'bg-blue-100 text-blue-700', 'viewer' => 'bg-gray-100 text-gray-600'];
?>
<div class="flex items-center justify-between mb-6">
    <h3 class="text-2xl font-bold text-gray-800">Usuários</h3>
    <a href="<?= APP_URL ?>/admin/users/create"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
        ➕ Novo Usuário
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Usuário</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">E-mail</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Perfil</th>
                <th class="text-left px-5 py-3 font-semibold text-gray-600">Status</th>
                <th class="text-center px-5 py-3 font-semibold text-gray-600">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($users as $user): ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm flex-shrink-0">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="font-medium text-gray-700"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </td>
                <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="px-5 py-3">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $roleBadges[$user['role']] ?? $roleBadges['viewer'] ?>">
                        <?= $roleLabels[$user['role']] ?? $user['role'] ?>
                    </span>
                </td>
                <td class="px-5 py-3">
                    <?php if ($user['is_active']): ?>
                    <span class="text-xs text-green-700 bg-green-100 px-2 py-0.5 rounded-full">Ativo</span>
                    <?php else: ?>
                    <span class="text-xs text-red-600 bg-red-100 px-2 py-0.5 rounded-full">Inativo</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3 text-center">
                    <a href="<?= APP_URL ?>/admin/users/<?= $user['id'] ?>/edit"
                       class="text-amber-600 hover:text-amber-800 text-sm" title="Editar">✏️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
