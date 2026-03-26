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
            <div class="px-5 py-3 flex items-center gap-3"
                 data-stage-id="<?= $stage['id'] ?>"
                 data-stage-name="<?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>"
                 data-stage-color="<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">

                <!-- MODO VISUALIZAÇÃO (padrão) -->
                <div class="view-mode flex items-center gap-3 flex-1">
                    <!-- Círculo de cor -->
                    <div class="w-4 h-4 rounded-full flex-shrink-0 color-preview"
                         style="background-color: <?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <!-- Nome e posição -->
                    <div>
                        <span class="text-sm font-medium text-gray-700 stage-name-text">
                            <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="ml-2 text-xs text-gray-400">Posição <?= $stage['position'] ?></span>
                    </div>
                </div>

                <!-- MODO EDIÇÃO (oculto por padrão: hidden) -->
                <div class="edit-mode hidden flex items-center gap-2 flex-1">
                    <input type="text" class="edit-name px-2 py-1 border border-gray-300 rounded text-sm flex-1
                           focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                           value="<?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="color" class="edit-color w-10 h-8 border border-gray-300 rounded cursor-pointer"
                           value="<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- AÇÕES -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <!-- Botões ↑↓ (visíveis sempre) -->
                    <button type="button" class="btn-move text-gray-400 hover:text-gray-600 text-sm leading-none"
                            data-direction="up" title="Mover para cima">↑</button>
                    <button type="button" class="btn-move text-gray-400 hover:text-gray-600 text-sm leading-none"
                            data-direction="down" title="Mover para baixo">↓</button>

                    <!-- Botão Editar (visível em view-mode) -->
                    <button type="button" class="btn-edit text-indigo-500 hover:text-indigo-700 text-sm">Editar</button>

                    <!-- Botão Salvar (oculto em view-mode: hidden) -->
                    <button type="button" class="btn-save hidden text-green-600 hover:text-green-800 text-sm">Salvar</button>

                    <!-- Botão Cancelar (oculto em view-mode: hidden) -->
                    <button type="button" class="btn-cancel hidden text-gray-400 hover:text-gray-600 text-sm">Cancelar</button>

                    <!-- Form de deleção (inalterado) -->
                    <form method="POST" action="<?= APP_URL ?>/pipeline/stages/<?= $stage['id'] ?>/delete"
                          onsubmit="return confirm('Remover a etapa \'<?= addslashes($stage['name']) ?>\'?\nTodos os clientes nesta etapa ficarão sem etapa se não houver outra.')">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600 text-sm" title="Remover etapa">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function () {
    let csrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>';
    const appUrl  = '<?= rtrim(APP_URL, '/') ?>';

    // --- Edição inline ---
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('[data-stage-id]');
            row.querySelector('.view-mode').classList.add('hidden');
            row.querySelector('.edit-mode').classList.remove('hidden');
            row.querySelector('.btn-edit').classList.add('hidden');
            row.querySelector('.btn-save').classList.remove('hidden');
            row.querySelector('.btn-cancel').classList.remove('hidden');
        });
    });

    document.querySelectorAll('.btn-cancel').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('[data-stage-id]');
            // Restaura valores originais do data attribute
            row.querySelector('.edit-name').value  = row.dataset.stageName;
            row.querySelector('.edit-color').value = row.dataset.stageColor;
            exitEditMode(row);
        });
    });

    document.querySelectorAll('.btn-save').forEach(btn => {
        btn.addEventListener('click', function () {
            const row  = this.closest('[data-stage-id]');
            const id   = row.dataset.stageId;
            const name = row.querySelector('.edit-name').value.trim();
            const color = row.querySelector('.edit-color').value;

            if (!name) { alert('O nome da etapa não pode ficar vazio.'); return; }

            const body = new URLSearchParams({ _csrf_token: csrfToken, name, color });
            fetch(appUrl + '/pipeline/stages/' + id + '/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            })
            .then(r => r.json())
            .then(data => {
                if (data.csrf_token) csrfToken = data.csrf_token;
                if (!data.success) { alert('Erro ao salvar. Tente novamente.'); return; }
                // Atualiza DOM e data attributes
                row.querySelector('.stage-name-text').textContent = name;
                row.querySelector('.color-preview').style.backgroundColor = color;
                row.dataset.stageName  = name;
                row.dataset.stageColor = color;
                exitEditMode(row);
            })
            .catch(() => alert('Erro de comunicação. Tente novamente.'));
        });
    });

    function exitEditMode(row) {
        row.querySelector('.view-mode').classList.remove('hidden');
        row.querySelector('.edit-mode').classList.add('hidden');
        row.querySelector('.btn-edit').classList.remove('hidden');
        row.querySelector('.btn-save').classList.add('hidden');
        row.querySelector('.btn-cancel').classList.add('hidden');
    }

    // --- Reordenação ↑↓ ---
    document.querySelectorAll('.btn-move').forEach(btn => {
        btn.addEventListener('click', function () {
            const row       = this.closest('[data-stage-id]');
            const id        = row.dataset.stageId;
            const direction = this.dataset.direction;

            const body = new URLSearchParams({ _csrf_token: csrfToken, direction });
            fetch(appUrl + '/pipeline/stages/' + id + '/move', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            })
            .then(r => r.json())
            .then(data => {
                if (data.csrf_token) csrfToken = data.csrf_token;
                if (!data.success) return; // Já no topo/fundo — nenhuma ação
                // Reordena visualmente: recarrega a lista
                location.reload();
            })
            .catch(() => alert('Erro de comunicação. Tente novamente.'));
        });
    });
})();
</script>
