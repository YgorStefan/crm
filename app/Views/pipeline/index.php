<?php
?>

<!-- Cabeçalho -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Pipeline de Vendas</h3>
        <p class="text-sm text-gray-500 dark:text-zinc-500 mt-1">Arraste os cartões para mover clientes entre etapas</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= APP_URL ?>/clients/create"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
            ➕ Novo Cliente
        </a>
        <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
        <a href="<?= APP_URL ?>/pipeline/stages"
           class="inline-flex items-center gap-2 border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-800 font-medium px-3 py-2 rounded-lg text-sm transition-colors"
           title="Gerenciar etapas">
            ⚙️ Etapas
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Board Kanban (scroll horizontal) -->
<div class="pb-4" id="kanbanBoard"
     data-move-url="<?= APP_URL ?>/pipeline/move"
     data-stats-url="<?= APP_URL ?>/api/dashboard/stats"
     data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
    <div class="flex flex-wrap gap-4 items-start">

    <?php foreach ($stages as $stage):
        // Clientes nesta etapa (pode ser array vazio)
        $stageClients = $grouped[$stage['id']] ?? [];
        // Total do funil nesta coluna
        $totalValue = array_sum(array_column($stageClients, 'deal_value'));
    ?>
    <!-- Coluna da Etapa -->
    <div class="kanban-column w-72 flex flex-col"
         data-stage-id="<?= $stage['id'] ?>">

        <!-- Cabeçalho da coluna -->
        <div class="rounded-t-xl px-4 py-3 text-white font-semibold text-sm flex items-center justify-between gap-2"
             style="background-color: <?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex items-baseline gap-2 min-w-0 flex-1">
                <span class="truncate"><?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($totalValue > 0): ?>
                <span class="kanban-value-total text-xs opacity-80 font-normal whitespace-nowrap"><?= format_currency($totalValue) ?></span>
                <?php endif; ?>
            </div>
            <span class="bg-white bg-opacity-20 px-2 py-0.5 rounded-full text-xs flex-shrink-0">
                <?= count($stageClients) ?>
            </span>
        </div>

        <!-- Área de drop dos cartões -->
        <div class="kanban-drop-zone flex-1 min-h-24 bg-gray-200 dark:bg-zinc-800/50 rounded-b-xl p-2 space-y-2"
             data-stage-id="<?= $stage['id'] ?>">

            <?php foreach ($stageClients as $client): ?>
            <?php $hasValue = ($client['deal_value'] ?? 0) > 0; ?>
            <!-- Cartão do cliente (draggable) -->
            <div class="kanban-card bg-white dark:bg-zinc-900 rounded-lg shadow border border-gray-300 dark:border-zinc-700 p-3 cursor-grab hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-400 dark:hover:bg-zinc-800 transition-all"
                 draggable="true"
                 data-client-id="<?= $client['id'] ?>"
                 data-current-stage="<?= $client['pipeline_stage_id'] ?>"
                 data-deal-value="<?= (float)($client['deal_value'] ?? 0) ?>">

                <!-- Nome (+ avatar inline quando nao ha valor) e empresa -->
                <div class="flex items-center justify-between gap-2">
                    <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                       class="block font-semibold text-gray-800 dark:text-white text-sm hover:text-indigo-700 dark:hover:text-indigo-400 truncate flex-1 min-w-0"
                       onclick="event.stopPropagation()">
                        <?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php if (!$hasValue && $client['assigned_name']): ?>
                    <div class="w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                         title="<?= htmlspecialchars($client['assigned_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= strtoupper(substr($client['assigned_name'], 0, 1)) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($client['company']): ?>
                <p class="text-xs text-gray-400 dark:text-zinc-500 truncate mt-0.5 flex items-center gap-1">
                    🏢 <?= htmlspecialchars($client['company'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>

                <?php if ($hasValue): ?>
                <!-- Rodapé do cartão: valor + avatar -->
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100 dark:border-zinc-700">
                    <span class="text-xs font-bold text-green-700 dark:text-green-400">
                        <?= format_currency($client['deal_value']) ?>
                    </span>
                    <?php if ($client['assigned_name']): ?>
                    <div class="w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xs font-bold"
                         title="<?= htmlspecialchars($client['assigned_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= strtoupper(substr($client['assigned_name'], 0, 1)) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Placeholder visível quando a coluna está vazia -->
            <?php if (empty($stageClients)): ?>
            <div class="kanban-empty text-center py-6 text-gray-400 dark:text-zinc-500 text-xs">
                Arraste um cartão aqui
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    </div>
</div>

<!-- Notificação de movimento (toast) -->
<div id="kanbanToast"
     class="fixed bottom-6 right-6 bg-gray-800 text-white text-sm px-4 py-2 rounded-lg shadow-xl
            opacity-0 transition-opacity duration-300 pointer-events-none">
    Cliente movido!
</div>

<!-- Script Kanban (drag & drop + AJAX) -->
<script nonce="<?= CSP_NONCE ?>" src="<?= APP_URL ?>/assets/js/pipeline.js"></script>
