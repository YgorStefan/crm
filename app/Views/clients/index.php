<?php
?>
<!-- Toolbar unificada: titulo + filtros + acoes em uma linha -->
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-3 mb-4">
    <form id="filterForm" method="GET" action="<?= APP_URL ?>/clients"
          class="flex flex-col lg:flex-row lg:items-center gap-2">

        <div class="flex items-baseline gap-3 lg:mr-2 flex-shrink-0">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Clientes</h3>
            <span class="text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap"><?= isset($pagination) ? (int) $pagination['total_items'] : count($clients) ?> encontrado(s)</span>
        </div>

        <div class="grid grid-cols-2 lg:flex lg:items-center gap-2 flex-1">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Buscar por nome, empresa..."
                   class="col-span-2 lg:flex-1 lg:min-w-[180px] px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-400 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">

            <select name="stage_id" class="lg:w-36 px-2 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">Etapa</option>
                <?php foreach ($stages as $stage): ?>
                <option value="<?= $stage['id'] ?>" <?= $filters['stage_id'] == $stage['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="assigned_to" class="lg:w-36 px-2 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">Responsável</option>
                <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $filters['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="tipo_venda" class="lg:w-32 px-2 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">Tipo</option>
                <option value="Imóvel"  <?= ($filters['tipo_venda'] ?? '') === 'Imóvel'  ? 'selected' : '' ?>>Imóvel</option>
                <option value="Veículo" <?= ($filters['tipo_venda'] ?? '') === 'Veículo' ? 'selected' : '' ?>>Veículo</option>
                <option value="Serviço" <?= ($filters['tipo_venda'] ?? '') === 'Serviço' ? 'selected' : '' ?>>Serviço</option>
            </select>
        </div>

        <div class="flex items-center gap-1.5 flex-shrink-0">
            <button type="submit"
                    title="Aplicar filtros" data-tooltip="Aplicar filtros"
                    class="has-tooltip inline-flex items-center justify-center w-9 h-9 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            </button>
            <a href="<?= APP_URL ?>/clients"
               title="Limpar filtros" data-tooltip="Limpar filtros"
               class="has-tooltip inline-flex items-center justify-center w-9 h-9 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200 rounded-lg transition-colors">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </a>
            <a href="<?= APP_URL ?>/clients/create"
               class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-3 py-2 rounded-lg text-sm transition-colors ml-1 whitespace-nowrap">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Novo
            </a>
        </div>
    </form>
</div>
<script nonce="<?= CSP_NONCE ?>">
// Ao submeter o formulário de filtros, reseta para a página 1
(function () {
    var filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function () {
            var pageInput = this.querySelector('input[name="page"]');
            if (!pageInput) {
                pageInput = document.createElement('input');
                pageInput.type = 'hidden';
                pageInput.name = 'page';
                this.appendChild(pageInput);
            }
            pageInput.value = '1';
        });
    }
})();
</script>

<!-- Tabela de clientes -->
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
    <?php if (empty($clients)): ?>
    <div class="text-center py-16 text-gray-400 dark:text-slate-500">
        <div class="text-5xl mb-4">👥</div>
        <p class="text-lg font-medium">Nenhum cliente encontrado</p>
        <p class="text-sm mt-1">
            <a href="<?= APP_URL ?>/clients/create" class="text-indigo-600 hover:underline">Cadastre o primeiro cliente</a>
        </p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-600">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-slate-400">Nome</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-slate-400 hidden md:table-cell">Empresa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-slate-400 hidden lg:table-cell">Contato</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-slate-400">Etapa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-slate-400 hidden lg:table-cell">Valor</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-slate-400 hidden xl:table-cell">Responsável</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600 dark:text-slate-400">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                <?php foreach ($clients as $client): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <!-- Nome + Empresa (mobile) -->
                    <td class="px-4 py-3">
                        <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                           class="font-medium text-indigo-700 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                            <?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php if (!empty($client['has_overdue'])): ?>
                        <span class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">
                            ⚠ Em atraso
                        </span>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 dark:text-slate-500 md:hidden">
                            <?= htmlspecialchars($client['company'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </td>
                    <!-- Empresa -->
                    <td class="px-4 py-3 text-gray-600 dark:text-slate-400 hidden md:table-cell">
                        <?= htmlspecialchars($client['company'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Contato -->
                    <td class="px-4 py-3 text-gray-500 dark:text-slate-400 hidden lg:table-cell">
                        <?php if ($client['email']): ?>
                        <div><?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($client['phone']): ?>
                        <div class="text-xs"><?= htmlspecialchars($client['phone'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </td>
                    <!-- Etapa com badge colorido -->
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium text-white"
                              style="background-color: <?= htmlspecialchars($client['stage_color'] ?? '#6366f1', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($client['stage_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <!-- Valor do negócio -->
                    <td class="px-4 py-3 text-gray-600 dark:text-slate-400 hidden lg:table-cell">
                        <?php if ($client['deal_value'] > 0): ?>
                        R$ <?= number_format($client['deal_value'], 2, ',', '.') ?>
                        <?php else: ?>
                        <span class="text-gray-400 dark:text-slate-500">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- Responsável -->
                    <td class="px-4 py-3 text-gray-500 dark:text-slate-400 hidden xl:table-cell">
                        <?= htmlspecialchars($client['assigned_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Ações -->
                    <td class="px-4 py-3 text-center">
                        <div class="inline-flex items-center gap-0.5 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg p-1">
                            <!-- Ver -->
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                               data-tooltip="Ver detalhes"
                               class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 transition-colors">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <!-- Editar -->
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>/edit"
                               data-tooltip="Editar"
                               class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/40 transition-colors">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <!-- Nova interação -->
                            <button
                                onclick="openQuickInteraction(<?= (int)$client['id'] ?>, <?= htmlspecialchars(json_encode($client['name']), ENT_QUOTES, 'UTF-8') ?>)"
                                data-tooltip="Nova interação"
                                class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/40 transition-colors">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </button>
                            <!-- Nova tarefa -->
                            <button
                                onclick="openQuickTask(<?= (int)$client['id'] ?>, <?= htmlspecialchars(json_encode($client['name']), ENT_QUOTES, 'UTF-8') ?>)"
                                data-tooltip="Nova tarefa"
                                class="has-tooltip w-7 h-7 flex items-center justify-center rounded-md text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/40 transition-colors">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($pagination)): ?>
<?php require VIEW_PATH . '/components/pagination.php'; ?>
<?php endif; ?>

<!-- Modal: Nova Interação Rápida -->
<div id="modalQuickInteraction" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
            <h4 class="font-bold text-gray-800 dark:text-white">💬 Nova Interação — <span id="qiClientName"></span></h4>
            <button onclick="document.getElementById('modalQuickInteraction').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300 text-xl">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/interactions/store" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="client_id" id="qiClientId">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tipo</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="call">📞 Ligação</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                    <option value="email">📧 E-mail</option>
                    <option value="meeting">🤝 Reunião</option>
                    <option value="note">📝 Nota</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Descrição <span class="text-red-500">*</span></label>
                <textarea name="description" rows="3" required placeholder="O que aconteceu?"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Data e hora</label>
                <input type="datetime-local" name="occurred_at" id="qiOccurredAt"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg text-sm transition-colors">Salvar</button>
                <button type="button" onclick="document.getElementById('modalQuickInteraction').classList.add('hidden')"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200 font-medium py-2 rounded-lg text-sm transition-colors">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Nova Tarefa Rápida -->
<div id="modalQuickTask" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
            <h4 class="font-bold text-gray-800 dark:text-white">📅 Nova Tarefa — <span id="qtClientName"></span></h4>
            <button onclick="document.getElementById('modalQuickTask').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300 text-xl">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/tasks/store" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="client_id" id="qtClientId">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Título <span class="text-red-500">*</span></label>
                <input type="text" name="title" required placeholder="O que precisa ser feito?"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Prazo <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="due_date" id="qtDueDate" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Prioridade</label>
                    <select name="priority" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="low">Baixa</option>
                        <option value="medium" selected>Média</option>
                        <option value="high">Alta</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg text-sm transition-colors">Salvar</button>
                <button type="button" onclick="document.getElementById('modalQuickTask').classList.add('hidden')"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-200 font-medium py-2 rounded-lg text-sm transition-colors">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
function openQuickInteraction(clientId, clientName) {
    document.getElementById('qiClientId').value = clientId;
    document.getElementById('qiClientName').textContent = clientName;
    const now = new Date();
    now.setSeconds(0, 0);
    document.getElementById('qiOccurredAt').value = now.toISOString().slice(0, 16);
    document.getElementById('modalQuickInteraction').classList.remove('hidden');
}
function openQuickTask(clientId, clientName) {
    document.getElementById('qtClientId').value = clientId;
    document.getElementById('qtClientName').textContent = clientName;
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(12, 0, 0, 0);
    document.getElementById('qtDueDate').value = tomorrow.toISOString().slice(0, 16);
    document.getElementById('modalQuickTask').classList.remove('hidden');
}
</script>
