<?php
/**
 * View: pipeline/index.php — Board Kanban
 * Variáveis: $stages, $grouped (array indexado por stage_id), $csrf_token
 */
?>

<!-- Cabeçalho -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Pipeline de Vendas</h3>
        <p class="text-sm text-gray-500 mt-1">Arraste os cartões para mover clientes entre etapas</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= APP_URL ?>/clients/create"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
            ➕ Novo Cliente
        </a>
        <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
        <a href="<?= APP_URL ?>/pipeline/stages"
           class="inline-flex items-center gap-2 border border-gray-300 text-gray-600 hover:bg-gray-50 font-medium px-4 py-2 rounded-lg text-sm transition-colors">
            ⚙️ Etapas
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Board Kanban (quebra de linha automática) -->
<div class="flex flex-wrap items-start gap-4 pb-6" id="kanbanBoard"
     data-move-url="<?= APP_URL ?>/pipeline/move"
     data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach ($stages as $stage):
        // Clientes nesta etapa (pode ser array vazio)
        $stageClients = $grouped[$stage['id']] ?? [];
        // Total do funil nesta coluna
        $totalValue = array_sum(array_column($stageClients, 'deal_value'));
    ?>
    <!-- Coluna da Etapa -->
    <div class="kanban-column w-full sm:w-72 flex flex-col"
         data-stage-id="<?= $stage['id'] ?>">

        <!-- Cabeçalho da coluna -->
        <div class="rounded-t-xl px-4 py-3 text-white font-semibold text-sm flex items-center justify-between"
             style="background-color: <?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">
            <span><?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <div class="text-right">
                <span class="bg-white bg-opacity-20 px-2 py-0.5 rounded-full text-xs">
                    <?= count($stageClients) ?>
                </span>
                <?php if ($totalValue > 0): ?>
                <div class="text-xs opacity-80 mt-0.5">R$ <?= number_format($totalValue, 2, ',', '.') ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Área de drop dos cartões -->
        <div class="kanban-drop-zone flex-1 min-h-24 bg-gray-100 rounded-b-xl p-2 space-y-2"
             data-stage-id="<?= $stage['id'] ?>">

            <?php foreach ($stageClients as $client): ?>
            <!-- Cartão do cliente (draggable) -->
            <div class="kanban-card bg-white rounded-lg shadow-sm border border-gray-200 p-3 cursor-grab
                        hover:shadow-md transition-shadow"
                 draggable="true"
                 data-client-id="<?= $client['id'] ?>"
                 data-current-stage="<?= $client['pipeline_stage_id'] ?>">

                <!-- Nome e empresa -->
                <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                   class="block font-semibold text-gray-800 text-sm hover:text-indigo-700 truncate"
                   onclick="event.stopPropagation()">
                    <?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php if ($client['company']): ?>
                <p class="text-xs text-gray-400 truncate mt-0.5">
                    🏢 <?= htmlspecialchars($client['company'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>

                <!-- Rodapé do cartão -->
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                    <?php if ($client['deal_value'] > 0): ?>
                    <span class="text-xs font-bold text-green-700">
                        R$ <?= number_format($client['deal_value'], 2, ',', '.') ?>
                    </span>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>

                    <?php if ($client['assigned_name']): ?>
                    <div class="flex items-center gap-1">
                        <div class="w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xs font-bold"
                             title="<?= htmlspecialchars($client['assigned_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= strtoupper(substr($client['assigned_name'], 0, 1)) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Placeholder visível quando a coluna está vazia -->
            <?php if (empty($stageClients)): ?>
            <div class="kanban-empty text-center py-6 text-gray-400 text-xs">
                Arraste um cartão aqui
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- Notificação de movimento (toast) -->
<div id="kanbanToast"
     class="fixed bottom-6 right-6 bg-gray-800 text-white text-sm px-4 py-2 rounded-lg shadow-xl
            opacity-0 transition-opacity duration-300 pointer-events-none">
    Cliente movido!
</div>

<!-- Script Kanban (drag & drop + AJAX) -->
<script src="<?= APP_URL ?>/assets/js/pipeline.js"></script>
