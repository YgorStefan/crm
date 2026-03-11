<?php
/**
 * View: pipeline/stages.php — Gerenciamento de Etapas (Admin)
 * Variáveis: $stages, $csrf_token
 */
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= APP_URL ?>/pipeline" class="text-gray-400 hover:text-gray-600">← Pipeline</a>
        <h3 class="text-2xl font-bold text-gray-800">Etapas do Funil</h3>
    </div>

    <!-- Formulário para criar nova etapa -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="font-semibold text-gray-700">Adicionar Nova Etapa</h4>
        </div>
        <form method="POST" action="<?= APP_URL ?>/pipeline/stages/store"
              class="px-5 py-4 flex gap-3 items-end">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Etapa</label>
                <input type="text" name="name" required placeholder="Ex.: Demonstração"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                <input type="color" name="color" value="#6366f1"
                       class="w-12 h-10 border border-gray-300 rounded-lg cursor-pointer">
            </div>
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
                Adicionar
            </button>
        </form>
    </div>

    <!-- Lista de etapas -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="font-semibold text-gray-700">Etapas Atuais</h4>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($stages as $stage): ?>
            <div class="px-5 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded-full flex-shrink-0"
                         style="background-color: <?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div>
                        <span class="text-sm font-medium text-gray-700">
                            <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="ml-2 text-xs text-gray-400">Posição <?= $stage['position'] ?></span>
                    </div>
                </div>
                <form method="POST" action="<?= APP_URL ?>/pipeline/stages/<?= $stage['id'] ?>/delete"
                      onsubmit="return confirm('Remover a etapa \'<?= addslashes($stage['name']) ?>\'?\nTodos os clientes nesta etapa ficarão sem etapa se não houver outra.')">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="text-red-400 hover:text-red-600 text-sm" title="Remover etapa">
                        🗑️
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
