<?php
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
        <form method="POST" action="<?= APP_URL ?>/pipeline/stages/store" class="px-5 py-4 flex gap-3 items-end">
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
                <div class="px-5 py-3 flex items-center gap-3" data-stage-id="<?= $stage['id'] ?>"
                    data-stage-name="<?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-stage-color="<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">

                    <!-- MODO VISUALIZAÇÃO -->
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

                    <!-- MODO EDIÇÃO -->
                    <div class="edit-mode hidden flex items-center gap-2 flex-1">
                        <input type="text" class="edit-name px-2 py-1 border border-gray-300 rounded text-sm flex-1
                           focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            value="<?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="color" class="edit-color w-10 h-8 border border-gray-300 rounded cursor-pointer"
                            value="<?= htmlspecialchars($stage['color'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <!-- AÇÕES -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <!-- Botões -->
                        <button type="button" class="btn-move text-gray-400 hover:text-gray-600 text-sm leading-none"
                            data-direction="up" title="Mover para cima">↑</button>
                        <button type="button" class="btn-move text-gray-400 hover:text-gray-600 text-sm leading-none"
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
                        <button type="button" class="btn-edit text-indigo-500 hover:text-indigo-700 text-sm">Editar</button>

                        <!-- Botão Salvar -->
                        <button type="button"
                            class="btn-save hidden text-green-600 hover:text-green-800 text-sm">Salvar</button>

                        <!-- Botão Cancelar -->
                        <button type="button"
                            class="btn-cancel hidden text-gray-400 hover:text-gray-600 text-sm">Cancelar</button>

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

<script nonce="<?= CSP_NONCE ?>">
    (function () {
        let csrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>';
        const appUrl = '<?= rtrim(APP_URL, '/') ?>';

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
                row.querySelector('.edit-name').value = row.dataset.stageName;
                row.querySelector('.edit-color').value = row.dataset.stageColor;
                exitEditMode(row);
            });
        });

        document.querySelectorAll('.btn-save').forEach(btn => {
            btn.addEventListener('click', function () {
                const row = this.closest('[data-stage-id]');
                const id = row.dataset.stageId;
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
                        row.dataset.stageName = name;
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

        // --- Reordenação ---
        document.querySelectorAll('.btn-move').forEach(btn => {
            btn.addEventListener('click', function () {
                const row = this.closest('[data-stage-id]');
                const id = row.dataset.stageId;
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
                        if (!data.success) return; // Já no topo/fundo
                        // Reordena visualmente: recarrega a lista
                        location.reload();
                    })
                    .catch(() => alert('Erro de comunicação. Tente novamente.'));
            });
        });
        // Won Stage Toggle (FRAG-03)
        document.querySelectorAll('.btn-won-toggle').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.stageId;
                this.classList.add('opacity-50', 'cursor-wait');
                this.setAttribute('aria-label', 'Salvando...');
                this.disabled = true;

                const body = new URLSearchParams({ _csrf_token: csrfToken });
                fetch(appUrl + '/pipeline/stages/' + id + '/toggle-won', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.csrf_token) csrfToken = data.csrf_token;
                        if (!data.success) { alert('Erro ao salvar. Tente novamente.'); return; }

                        const newState = data.is_won_stage === 1;

                        // Exclusividade mutua: se ligando, desliga todos os outros primeiro
                        if (newState) {
                            document.querySelectorAll('.btn-won-toggle').forEach(b => {
                                b.dataset.isWon = '0';
                                b.setAttribute('aria-pressed', 'false');
                                b.setAttribute('title', 'Definir como etapa de ganho');
                                b.className = b.className.replace('text-indigo-600 font-medium', 'text-gray-400 hover:text-indigo-500');
                                b.textContent = '\u2606';
                            });
                        }

                        // Atualiza a linha alvo
                        this.dataset.isWon = newState ? '1' : '0';
                        this.setAttribute('aria-pressed', newState ? 'true' : 'false');
                        this.setAttribute('title', newState ? 'Etapa de ganho ativa' : 'Definir como etapa de ganho');
                        this.textContent = newState ? '\u2605' : '\u2606';
                        this.className = this.className.replace(
                            newState ? 'text-gray-400 hover:text-indigo-500' : 'text-indigo-600 font-medium',
                            newState ? 'text-indigo-600 font-medium' : 'text-gray-400 hover:text-indigo-500'
                        );
                    })
                    .catch(() => alert('Erro de comunicacao. Tente novamente.'))
                    .finally(() => {
                        this.classList.remove('opacity-50', 'cursor-wait');
                        this.removeAttribute('aria-label');
                        this.disabled = false;
                    });
            });
        });
        // --- Deleção com confirmação segura (CR-02) ---
        document.querySelectorAll('.form-delete-stage').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const row = this.closest('[data-stage-id]');
                const name = row ? row.dataset.stageName : '';
                if (!confirm('Remover a etapa "' + name + '"?\n' +
                        'Todos os clientes nesta etapa ficarão sem etapa se não houver outra.')) {
                    e.preventDefault();
                }
            });
        });
    })();
</script>