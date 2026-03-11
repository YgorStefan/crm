<?php
/**
 * View: clients/index.php
 * Variáveis: $clients, $stages, $users, $filters
 */
?>
<!-- Cabeçalho da página -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Clientes</h3>
        <p class="text-sm text-gray-500 mt-1"><?= count($clients) ?> cliente(s) encontrado(s)</p>
    </div>
    <a href="<?= APP_URL ?>/clients/create"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
              font-medium px-4 py-2 rounded-lg text-sm transition-colors">
        ➕ Novo Cliente
    </a>
</div>

<!-- Filtros de busca -->
<form method="GET" action="<?= APP_URL ?>/clients"
      class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

    <input type="text" name="search" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>"
           placeholder="Buscar por nome, empresa..."
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">

    <select name="stage_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todas as etapas</option>
        <?php foreach ($stages as $stage): ?>
        <option value="<?= $stage['id'] ?>" <?= $filters['stage_id'] == $stage['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="assigned_to" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todos os responsáveis</option>
        <?php foreach ($users as $user): ?>
        <option value="<?= $user['id'] ?>" <?= $filters['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
    </select>

    <div class="flex gap-2">
        <button type="submit"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            Filtrar
        </button>
        <a href="<?= APP_URL ?>/clients"
           class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            Limpar
        </a>
    </div>
</form>

<!-- Tabela de clientes -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($clients)): ?>
    <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-4">👥</div>
        <p class="text-lg font-medium">Nenhum cliente encontrado</p>
        <p class="text-sm mt-1">
            <a href="<?= APP_URL ?>/clients/create" class="text-indigo-600 hover:underline">Cadastre o primeiro cliente</a>
        </p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Nome</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden md:table-cell">Empresa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden lg:table-cell">Contato</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Etapa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden lg:table-cell">Valor</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden xl:table-cell">Responsável</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($clients as $client): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <!-- Nome + Empresa (mobile) -->
                    <td class="px-4 py-3">
                        <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                           class="font-medium text-indigo-700 hover:text-indigo-900">
                            <?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <p class="text-xs text-gray-400 md:hidden">
                            <?= htmlspecialchars($client['company'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </td>
                    <!-- Empresa -->
                    <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                        <?= htmlspecialchars($client['company'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Contato -->
                    <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">
                        <?php if ($client['email']): ?>
                        <div><?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($client['phone']): ?>
                        <div class="text-xs"><?= htmlspecialchars($client['phone'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </td>
                    <!-- Etapa com badge colorido -->
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium text-white"
                              style="background-color: <?= htmlspecialchars($client['stage_color'] ?? '#6366f1', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($client['stage_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <!-- Valor do negócio -->
                    <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">
                        <?php if ($client['deal_value'] > 0): ?>
                        R$ <?= number_format($client['deal_value'], 2, ',', '.') ?>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- Responsável -->
                    <td class="px-4 py-3 text-gray-500 hidden xl:table-cell">
                        <?= htmlspecialchars($client['assigned_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Ações -->
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                               class="text-indigo-600 hover:text-indigo-800 text-xs font-medium" title="Ver detalhes">👁️</a>
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>/edit"
                               class="text-amber-600 hover:text-amber-800 text-xs font-medium" title="Editar">✏️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
