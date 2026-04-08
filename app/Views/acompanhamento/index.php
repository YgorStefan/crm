<?php
$temDados = !empty($stages) && array_sum(array_column($stages, 'total')) > 0;
?>

<div class="max-w-4xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Acompanhamento de Prospecção</h3>
            <p class="text-sm text-gray-500 mt-1">Clientes por etapa do pipeline</p>
        </div>
    </div>

    <?php if (!$temDados): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="text-5xl mb-4">📭</div>
            <p class="text-gray-500 font-medium">Nenhum cliente cadastrado no pipeline ainda.</p>
            <p class="text-sm text-gray-400 mt-1">Adicione clientes em <a href="<?= APP_URL ?>/clients/create"
                    class="text-indigo-600 hover:underline">Novo Cliente</a> para começar.</p>
        </div>
    <?php else: ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <canvas id="chartAcompanhamento" height="280"></canvas>
        </div>

        <!-- Cards de resumo por etapa -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4">
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
</script>
<script src="<?= APP_URL ?>/assets/js/acompanhamento.js"></script>
