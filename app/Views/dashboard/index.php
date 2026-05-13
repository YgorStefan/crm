<?php
$totalValue = array_sum(array_column($stageData, 'total_value'));
$wonStage = array_filter($stageData, fn($s) => !empty($s['is_won_stage']));
$wonRevenue = array_sum(array_column($wonStage, 'total_value'));
?>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">

    <a href="<?= APP_URL ?>/clients"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 flex items-center gap-4 hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-700 transition-all">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($totalClients) ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Clientes ativos</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/tasks"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 flex items-center gap-4 hover:shadow-md hover:border-amber-200 dark:hover:border-amber-700 transition-all">
        <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400 flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M9 14l2 2 4-4"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= $pendingTasks ?></p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Minhas tarefas abertas</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/tasks"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border <?= count($overdueTasks) > 0 ? 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20' : 'border-gray-100 dark:border-slate-700' ?> p-5 flex items-center gap-4 hover:shadow-md transition-all">
        <div class="w-12 h-12 rounded-xl <?= count($overdueTasks) > 0 ? 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400' : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400' ?> flex items-center justify-center flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold <?= count($overdueTasks) > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-800 dark:text-white' ?>"><?= count($overdueTasks) ?></p>
            <p class="text-sm <?= count($overdueTasks) > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-slate-400' ?>">Tarefas atrasadas</p>
        </div>
    </a>

    <a href="<?= APP_URL ?>/pipeline"
       class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 flex items-center gap-4 hover:shadow-md hover:border-green-200 dark:hover:border-green-700 transition-all">
        <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center text-green-600 dark:text-green-400 flex-shrink-0">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-green-700 dark:text-green-400">R$ <?= number_format($wonRevenue, 2, ',', '.') ?></p>
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
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-indigo-500 flex-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span class="font-semibold text-gray-700 dark:text-slate-200">Próximas Tarefas (7 dias)</span>
            </div>
            <a href="<?= APP_URL ?>/tasks" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Ver todas</a>
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
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-indigo-500 flex-shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="font-semibold text-gray-700 dark:text-slate-200">Atividade Recente</span>
            </div>
            <a href="<?= APP_URL ?>/clients" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Ver clientes</a>
        </div>
        <?php
        $typeIcons = [
            'call'      => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-blue-500"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.93 12 19.79 19.79 0 0 1 1.86 3.38 2 2 0 0 1 3.84 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
            'email'     => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-green-500"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'meeting'   => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-purple-500"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'whatsapp'  => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-teal-500"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            'note'      => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-yellow-500"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
            'other'     => '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-gray-400"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
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