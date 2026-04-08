<?php
$totalClientes = !empty($stages) ? array_sum(array_column($stages, 'total')) : 0;
$temDados = $totalClientes > 0 || $abordados > 0;
?>

<div class="max-w-4xl mx-auto">

    <!-- Cabeçalho com navegação de meses -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Acompanhamento de Prospecção</h3>
            <p class="text-sm text-gray-500 mt-1">Clientes por etapa do pipeline</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= APP_URL ?>/acompanhamento?mes=<?= urlencode($prevMes) ?>"
               class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                ← Anterior
            </a>
            <span class="px-3 py-1.5 text-sm font-semibold text-gray-800 bg-gray-100 rounded-lg min-w-[130px] text-center">
                <?= htmlspecialchars($mesLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if (!$isMesAtual): ?>
                <a href="<?= APP_URL ?>/acompanhamento?mes=<?= urlencode($nextMes) ?>"
                   class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                    Próximo →
                </a>
            <?php else: ?>
                <span class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-300 cursor-default">
                    Próximo →
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$temDados): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="text-5xl mb-4">📭</div>
            <p class="text-gray-500 font-medium">Nenhum dado registrado em <?= htmlspecialchars($mesLabel, ENT_QUOTES, 'UTF-8') ?>.</p>
            <?php if ($isMesAtual): ?>
                <p class="text-sm text-gray-400 mt-1">Adicione clientes em <a href="<?= APP_URL ?>/clients/create" class="text-indigo-600 hover:underline">Novo Cliente</a> para começar.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <canvas id="chartAcompanhamento" height="280"></canvas>
        </div>

        <!-- Cards de resumo -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4">

            <!-- Card: Abordados -->
            <div class="bg-teal-50 border border-teal-200 rounded-xl p-4 flex items-center gap-3">
                <span class="inline-block w-3 h-3 rounded-full flex-shrink-0 bg-teal-500"></span>
                <div class="min-w-0">
                    <p class="text-xs text-teal-600 truncate font-medium">Abordados</p>
                    <p class="text-lg font-bold text-teal-800"><?= (int)$abordados ?></p>
                </div>
            </div>

            <?php foreach ($stages as $stage): ?>
                <?php if ((int)$stage['total'] === 0) continue; ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
                    <span class="inline-block w-3 h-3 rounded-full flex-shrink-0"
                          style="background-color: <?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>"></span>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-lg font-bold text-gray-800"><?= (int)$stage['total'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<script>
    const acompanhamentoData = <?= json_encode(array_values($stages), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const acompanhamentoAbordados = <?= (int)$abordados ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/acompanhamento.js"></script>
