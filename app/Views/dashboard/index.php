<?php
$totalValue = array_sum(array_column($stageData, 'total_value'));
$wonStage = array_filter($stageData, fn($s) => !empty($s['is_won_stage']));
$wonRevenue = array_sum(array_column($wonStage, 'total_value'));
?>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-8">

    <a href="<?= APP_URL ?>/clients"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 flex items-center gap-3 hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-700 transition-all">
        <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0 text-xl">
            👥
        </div>
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white"><?= number_format($totalClients) ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Clientes ativos</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/tasks"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 flex items-center gap-3 hover:shadow-md hover:border-amber-200 dark:hover:border-amber-700 transition-all">
        <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400 flex-shrink-0 text-xl">
            ✅
        </div>
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white"><?= $pendingTasks ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Minhas tarefas abertas</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/tasks"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border <?= count($overdueTasks) > 0 ? 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20' : 'border-gray-100 dark:border-slate-700' ?> p-4 flex items-center gap-3 hover:shadow-md transition-all">
        <div class="w-10 h-10 rounded-xl <?= count($overdueTasks) > 0 ? 'bg-red-100 dark:bg-red-900/40' : 'bg-gray-100 dark:bg-slate-700' ?> flex items-center justify-center flex-shrink-0 text-xl">
            ⚠️
        </div>
        <div>
            <p class="text-xl font-bold <?= count($overdueTasks) > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-800 dark:text-white' ?>"><?= count($overdueTasks) ?></p>
            <p class="text-sm <?= count($overdueTasks) > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-slate-400' ?>">Tarefas atrasadas</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/pipeline"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 flex items-center gap-3 hover:shadow-md hover:border-green-200 dark:hover:border-green-700 transition-all">
        <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center flex-shrink-0 text-xl">
            💰
        </div>
        <div>
            <p class="text-xl font-bold text-green-700 dark:text-green-400 whitespace-nowrap">R$ <?= number_format($wonRevenue, 2, ',', '.') ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Negócios ganhos</p>
        </div>
    </a>
</div>

<!-- Próximas Tarefas + Atividade Recente -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    <!-- Tarefas dos próximos 7 dias -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="text-indigo-500 flex-shrink-0">📅</span>
                <span class="font-semibold text-gray-700 dark:text-slate-200">Próximas Tarefas (7 dias)</span>
            </div>
            <a href="<?= APP_URL ?>/tasks" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 transition-colors">
                Ver todas ➜
            </a>
        </div>
        <?php if (empty($upcomingTasks)): ?>
            <div class="px-5 py-8 text-center text-gray-400 dark:text-slate-500 text-sm">Nenhuma tarefa nos próximos 7 dias 🎉</div>
        <?php else: ?>
            <div class="divide-y divide-gray-50 dark:divide-slate-700 overflow-y-auto" style="max-height: 416px;">
                <?php
                $priorityColors = ['low' => 'bg-green-400', 'medium' => 'bg-yellow-400', 'high' => 'bg-red-500'];
                foreach ($upcomingTasks as $task):
                    ?>
                    <div class="px-5 py-3 flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full flex-shrink-0 <?= $priorityColors[$task['priority']] ?? 'bg-gray-400' ?>"></div>
                        <div class="flex-1 min-w-0">
                            <a href="<?= APP_URL ?>/tasks" class="text-sm font-medium text-gray-700 dark:text-slate-200 hover:text-indigo-600 truncate block">
                                <?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php if ($task['client_name']): ?>
                                <p class="text-xs text-gray-400 dark:text-slate-500 truncate">👥 <?= htmlspecialchars($task['client_name'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-gray-400 dark:text-slate-500 flex-shrink-0"><?= date('d/m', strtotime($task['due_date'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Atividade Recente -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="text-indigo-500 flex-shrink-0">🕐</span>
                <span class="font-semibold text-gray-700 dark:text-slate-200">Atividade Recente</span>
            </div>
            <a href="<?= APP_URL ?>/clients" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 transition-colors">
                Ver clientes ➜
            </a>
        </div>
        <?php
        $typeIcons = [
            'call'     => '📞',
            'email'    => '📧',
            'meeting'  => '🤝',
            'whatsapp' => '💬',
            'note'     => '📝',
            'other'    => 'ℹ️',
        ];
        if (empty($recentInteractions)):
            ?>
            <div class="px-5 py-8 text-center text-gray-400 dark:text-slate-500 text-sm">Nenhuma interação registrada.</div>
        <?php else: ?>
            <div class="divide-y divide-gray-50 dark:divide-slate-700 overflow-y-auto" style="max-height: 416px;">
                <?php foreach ($recentInteractions as $inter): ?>
                    <div class="px-5 py-3 flex items-start gap-3">
                        <span class="flex-shrink-0 mt-0.5"><?= $typeIcons[$inter['type']] ?? $typeIcons['other'] ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700 dark:text-slate-200">
                                <a href="<?= APP_URL ?>/clients/<?= $inter['client_id'] ?>" class="hover:text-indigo-600">
                                    <?= htmlspecialchars($inter['client_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-slate-400 truncate">
                                <?= htmlspecialchars($inter['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="text-xs text-gray-400 dark:text-slate-500 flex-shrink-0">
                            <?= date('d/m H:i', strtotime($inter['occurred_at'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Distribuição no Pipeline -->
<div class="mb-8">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
        <h4 class="text-sm font-semibold text-gray-600 dark:text-slate-400 mb-4">Distribuição no Pipeline</h4>
        <canvas id="chartPipeline" height="100"></canvas>
    </div>
</div>

<!-- Dados para o Chart.js (injetados como JSON no HTML) -->
<script nonce="<?= CSP_NONCE ?>">
    // Dados do pipeline injetados pelo PHP como JSON seguro
    const pipelineData = <?= json_encode([
        'labels' => array_column($stageData, 'name'),
        'counts' => array_map('intval', array_column($stageData, 'total')),
        'colors' => array_column($stageData, 'color'),
    ], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script nonce="<?= CSP_NONCE ?>" src="<?= APP_URL ?>/assets/js/dashboard.js"></script>