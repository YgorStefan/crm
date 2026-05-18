<?php
?>
<?php
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts = '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/pipeline-stages.js?v=' . $_jsV('pipeline-stages.js') . '"></script>';
unset($_jsV);
?>
<style nonce="<?= CSP_NONCE ?>">
    /* Arredonda o swatch interno do <input type="color"> em todos os browsers */
    input[type="color"] {
        padding: 2px;
        background-color: transparent;
    }
    input[type="color"]::-webkit-color-swatch-wrapper {
        padding: 0;
        border-radius: 0.5rem;
    }
    input[type="color"]::-webkit-color-swatch {
        border: none;
        border-radius: 0.5rem;
    }
    input[type="color"]::-moz-color-swatch {
        border: none;
        border-radius: 0.5rem;
    }
</style>
<div data-crm-widget="pipeline-stages" class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= APP_URL ?>/pipeline" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400 bg-gray-100 hover:bg-indigo-50 dark:bg-zinc-800 dark:hover:bg-indigo-900/30 px-3 py-1.5 rounded-lg transition-all">
            ↩ Pipeline
        </a>
        <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Etapas do Funil</h3>
    </div>

    <!-- Formulário para criar nova etapa -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
            <h4 class="font-semibold text-gray-700 dark:text-zinc-200">Adicionar Nova Etapa</h4>
        </div>
        <form method="POST" action="<?= APP_URL ?>/pipeline/stages/store" class="px-5 py-4 flex gap-3 items-end">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Nome da Etapa</label>
                <input type="text" name="name" required placeholder="Ex.: Demonstração"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Cor</label>
                <input type="color" name="color" value="#6366f1"
                    class="w-12 h-10 border border-gray-300 dark:border-zinc-700 rounded-lg cursor-pointer">
            </div>
            <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
                Adicionar
            </button>
        </form>
    </div>

    <!-- Lista de etapas -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
            <h4 class="font-semibold text-gray-700 dark:text-zinc-200">Etapas Atuais</h4>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-zinc-800">
            <?php foreach ($stages as $stage): ?>
                <div class="px-5 py-3 flex items-center gap-3" data-stage-id="<?= $stage['id'] ?>"
                    data-stage-name="<?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-stage-color="<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>"
                    data-client-count="<?= (int)($stage['client_count'] ?? 0) ?>">

                    <!-- MODO VISUALIZAÇÃO -->
                    <div class="view-mode flex items-center gap-3 flex-1">
                        <!-- Círculo de cor -->
                        <div class="w-4 h-4 rounded-full flex-shrink-0 color-preview"
                            style="background-color: <?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <!-- Nome e posição -->
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-zinc-200 stage-name-text">
                                <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="ml-2 text-xs text-gray-400 dark:text-zinc-500">Posição <?= $stage['position'] ?> · <?= (int)($stage['client_count'] ?? 0) ?> cliente(s)</span>
                        </div>
                    </div>

                    <!-- MODO EDIÇÃO -->
                    <div class="edit-mode hidden flex items-center gap-2 flex-1">
                        <input type="text" class="edit-name px-2 py-1 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded text-sm flex-1
                           focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            value="<?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="color" class="edit-color w-10 h-8 border border-gray-300 dark:border-zinc-700 rounded cursor-pointer"
                            value="<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <!-- AÇÕES -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <!-- Botões -->
                        <button type="button" class="btn-move text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-sm leading-none"
                            data-direction="up" title="Mover para cima">↑</button>
                        <button type="button" class="btn-move text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-sm leading-none"
                            data-direction="down" title="Mover para baixo">↓</button>

                        <!-- Botao Won Stage Toggle (FRAG-03) -->
                        <button type="button"
                            class="btn-won-toggle <?= !empty($stage['is_won_stage'])
                                ? 'text-indigo-600 font-medium text-sm'
                                : 'text-gray-400 hover:text-indigo-500 text-sm' ?> py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            data-stage-id="<?= $stage['id'] ?>"
                            data-is-won="<?= !empty($stage['is_won_stage']) ? '1' : '0' ?>"
                            aria-pressed="<?= !empty($stage['is_won_stage']) ? 'true' : 'false' ?>"
                            title="<?= !empty($stage['is_won_stage']) ? 'Etapa de ganho ativa' : 'Definir como etapa de ganho' ?>">
                            <?= !empty($stage['is_won_stage']) ? '★' : '☆' ?>
                        </button>

                        <!-- Botão Editar -->
                        <button type="button" class="btn-edit text-indigo-500 hover:text-indigo-700" title="Editar">
                            ✏️
                        </button>

                        <!-- Botão Salvar -->
                        <button type="button"
                            class="btn-save hidden text-green-600 hover:text-green-800 text-sm">Salvar</button>

                        <!-- Botão Cancelar -->
                        <button type="button"
                            class="btn-cancel hidden text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-sm">Cancelar</button>

                        <!-- Form de deleção -->
                        <form method="POST" action="<?= APP_URL ?>/pipeline/stages/<?= (int)$stage['id'] ?>/delete"
                            class="form-delete-stage">
                            <input type="hidden" name="_csrf_token"
                                value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="text-red-400 hover:text-red-600 text-sm"
                                title="Remover etapa">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

