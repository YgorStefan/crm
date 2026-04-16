<?php
$totalValue = array_sum(array_column($stageData, 'total_value'));
$wonStage = array_filter($stageData, fn($s) => !empty($s['is_won_stage']));
$wonRevenue = array_sum(array_column($wonStage, 'total_value'));
?>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

    <!-- Total de Clientes -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-2xl flex-shrink-0">👥</div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= number_format($totalClients) ?></p>
            <p class="text-sm text-gray-500">Clientes ativos</p>
        </div>
    </div>

    <!-- Tarefas Pendentes -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-2xl flex-shrink-0">✅</div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= $pendingTasks ?></p>
            <p class="text-sm text-gray-500">Minhas tarefas abertas</p>
        </div>
    </div>

    <!-- Tarefas Atrasadas -->
    <div
        class="bg-white rounded-xl shadow-sm border <?= count($overdueTasks) > 0 ? 'border-red-200 bg-red-50' : 'border-gray-100' ?> p-5 flex items-center gap-4">
        <div
            class="w-12 h-12 rounded-xl <?= count($overdueTasks) > 0 ? 'bg-red-100' : 'bg-gray-100' ?> flex items-center justify-center text-2xl flex-shrink-0">
            ⚠️</div>
        <div>
            <p class="text-2xl font-bold <?= count($overdueTasks) > 0 ? 'text-red-700' : 'text-gray-800' ?>">
                <?= count($overdueTasks) ?></p>
            <p class="text-sm <?= count($overdueTasks) > 0 ? 'text-red-500' : 'text-gray-500' ?>">Tarefas atrasadas</p>
        </div>
    </div>

    <!-- Receita Fechada -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl flex-shrink-0">💰</div>
        <div>
            <p class="text-2xl font-bold text-green-700">R$ <?= number_format($wonRevenue, 2, ',', '.') ?></p>
            <p class="text-sm text-gray-500">Negócios ganhos</p>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    <!-- Gráfico: Clientes por Etapa (Barras) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h4 class="text-sm font-semibold text-gray-600 mb-4">Distribuição no Pipeline</h4>
        <canvas id="chartPipeline" height="220"></canvas>
    </div>

    <!-- Gráfico: Valor por Etapa (Pizza) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h4 class="text-sm font-semibold text-gray-600 mb-4">Valor Total por Etapa (R$)</h4>
        <canvas id="chartValues" height="220"></canvas>
    </div>
</div>

<!-- Linha inferior: Tarefas + Atividade Recente -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Tarefas dos próximos 7 dias -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
            <h4 class="font-semibold text-gray-700">📅 Próximas Tarefas (7 dias)</h4>
            <a href="<?= APP_URL ?>/tasks" class="text-xs text-indigo-600 hover:underline">Ver todas</a>
        </div>
        <?php if (empty($upcomingTasks)): ?>
            <div class="px-5 py-8 text-center text-gray-400 text-sm">Nenhuma tarefa nos próximos 7 dias 🎉</div>
        <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php
                $priorityColors = ['low' => 'bg-green-400', 'medium' => 'bg-yellow-400', 'high' => 'bg-red-500'];
                foreach (array_slice($upcomingTasks, 0, 6) as $task):
                    ?>
                    <div class="px-5 py-3 flex items-center gap-3">
                        <div
                            class="w-2 h-2 rounded-full flex-shrink-0 <?= $priorityColors[$task['priority']] ?? 'bg-gray-400' ?>">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700 truncate">
                                <?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($task['client_name']): ?>
                                <p class="text-xs text-gray-400 truncate">👥
                                    <?= htmlspecialchars($task['client_name'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <span
                            class="text-xs text-gray-400 flex-shrink-0"><?= date('d/m', strtotime($task['due_date'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Atividade Recente -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
            <h4 class="font-semibold text-gray-700">🕐 Atividade Recente</h4>
            <a href="<?= APP_URL ?>/clients" class="text-xs text-indigo-600 hover:underline">Ver clientes</a>
        </div>
        <?php
        $typeIcons = ['call' => '📞', 'email' => '📧', 'meeting' => '🤝', 'whatsapp' => '💬', 'note' => '📝', 'other' => '📌'];
        if (empty($recentInteractions)):
            ?>
            <div class="px-5 py-8 text-center text-gray-400 text-sm">Nenhuma interação registrada.</div>
        <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php foreach ($recentInteractions as $inter): ?>
                    <div class="px-5 py-3 flex items-start gap-3">
                        <span class="text-lg flex-shrink-0 mt-0.5"><?= $typeIcons[$inter['type']] ?? '📌' ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700">
                                <a href="<?= APP_URL ?>/clients/<?= $inter['client_id'] ?>" class="hover:text-indigo-600">
                                    <?= htmlspecialchars($inter['client_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                <?= htmlspecialchars($inter['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0">
                            <?= date('d/m H:i', strtotime($inter['occurred_at'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Dados para o Chart.js (injetados como JSON no HTML) -->
<script nonce="<?= CSP_NONCE ?>">
    // Dados do pipeline injetados pelo PHP como JSON seguro
    const pipelineData = <?= json_encode([
        'labels' => array_column($stageData, 'name'),
        'counts' => array_map('intval', array_column($stageData, 'total')),
        'values' => array_map('floatval', array_column($stageData, 'total_value')),
        'colors' => array_column($stageData, 'color'),
    ], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script nonce="<?= CSP_NONCE ?>" src="<?= APP_URL ?>/assets/js/dashboard.js"></script>