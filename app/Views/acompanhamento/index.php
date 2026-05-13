<?php
$totalClientes = !empty($stages) ? array_sum(array_column($stages, 'total')) : 0;
$temDados = $totalClientes > 0 || $abordados > 0;
?>

<div class="max-w-4xl mx-auto">

    <!-- Cabeçalho com navegação de meses -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Acompanhamento de Prospecção</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Clientes por etapa do pipeline</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= APP_URL ?>/acompanhamento?mes=<?= urlencode($prevMes) ?>"
               class="px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                ← Anterior
            </a>
            <span class="px-3 py-1.5 text-sm font-semibold text-gray-800 dark:text-white bg-gray-100 dark:bg-slate-700 rounded-lg min-w-[130px] text-center">
                <?= htmlspecialchars($mesLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if (!$isMesAtual): ?>
                <a href="<?= APP_URL ?>/acompanhamento?mes=<?= urlencode($nextMes) ?>"
                   class="px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                    Próximo →
                </a>
            <?php else: ?>
                <span class="px-3 py-1.5 text-sm border border-gray-200 dark:border-slate-700 rounded-lg text-gray-300 dark:text-slate-600 cursor-default">
                    Próximo →
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$temDados): ?>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-12 text-center">
            <div class="flex justify-center">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="text-gray-300 dark:text-slate-600 mb-4"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <p class="text-gray-500 dark:text-slate-400 font-medium">Nenhum dado registrado em <?= htmlspecialchars($mesLabel, ENT_QUOTES, 'UTF-8') ?>.</p>
            <?php if ($isMesAtual): ?>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-1">Adicione clientes em <a href="<?= APP_URL ?>/clients/create" class="text-indigo-600 dark:text-indigo-400 hover:underline">Novo Cliente</a> para começar.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Métrica: Abordados -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 px-6 py-4 flex items-center gap-4 mb-4">
            <span class="inline-block w-3 h-3 rounded-full flex-shrink-0" style="background-color:#14b8a6"></span>
            <div>
                <p class="text-xs text-gray-500 dark:text-slate-400 uppercase tracking-wide font-medium">Abordados no mês</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-white"><?= (int)$abordados ?></p>
            </div>
            <p class="text-xs text-gray-400 dark:text-slate-500 ml-2 self-end pb-1">contatos frios importados em <?= htmlspecialchars($mesLabel, ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <!-- Gráfico: Abordados + etapas do pipeline -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-6">
            <canvas id="chartAcompanhamento"></canvas>
        </div>

        <!-- Cards de resumo -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4">

            <?php foreach ($stages as $stage): ?>
                <?php if ((int)$stage['total'] === 0) continue; ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 flex items-center gap-3">
                    <span class="inline-block w-3 h-3 rounded-full flex-shrink-0"
                          style="background-color: <?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>"></span>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-slate-400 truncate"><?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-lg font-bold text-gray-800 dark:text-white"><?= (int)$stage['total'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<script nonce="<?= CSP_NONCE ?>">
    const acompanhamentoData = <?= json_encode(array_values($stages), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const acompanhamentoAbordados = <?= (int)$abordados ?>;
</script>
<script nonce="<?= CSP_NONCE ?>" src="<?= APP_URL ?>/assets/js/acompanhamento.js?v=7"></script>
