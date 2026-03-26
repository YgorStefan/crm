<?php
/**
 * View: acompanhamento/index.php
 * Variaveis: $chartData (array com 'semanas' e 'listas')
 */

// Detecta se ha dados para exibir (evita empty state sem dados)
$temDados = !empty($chartData['semanas']) && !empty($chartData['listas']['Todos']['importados'])
    && array_sum($chartData['listas']['Todos']['importados']) > 0;
?>

<div class="max-w-4xl mx-auto">

    <!-- Cabecalho da pagina -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Acompanhamento de Prospecção</h3>
            <p class="text-sm text-gray-500 mt-1">Volume semanal de contatos importados vs. abordados (últimas 4 semanas)</p>
        </div>
    </div>

    <?php if (!$temDados): ?>
    <!-- Empty state -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <div class="text-5xl mb-4">📭</div>
        <p class="text-gray-500 font-medium">Nenhum contato frio importado nas últimas 4 semanas.</p>
        <p class="text-sm text-gray-400 mt-1">Importe uma lista em <a href="<?= APP_URL ?>/cold-contacts" class="text-indigo-600 hover:underline">Contatos Frios</a> para começar.</p>
    </div>
    <?php else: ?>

    <!-- Card do grafico -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">

        <!-- Controles: dropdown de filtro + legenda -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <label for="filtroLista" class="text-sm font-medium text-gray-600">Filtrar por lista:</label>
                <select id="filtroLista" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach (array_keys($chartData['listas']) as $nome): ?>
                    <option value="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Legenda manual das series -->
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm bg-indigo-500"></span> Importados
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm bg-emerald-500"></span> Abordados
                </span>
            </div>
        </div>

        <!-- Canvas do grafico -->
        <canvas id="chartAcompanhamento" height="280"></canvas>
    </div>

    <?php endif; ?>

</div>

<!-- Dados injetados pelo PHP como JSON seguro (per padrao dashboard/index.php) -->
<script>
    const acompanhamentoData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/acompanhamento.js"></script>
