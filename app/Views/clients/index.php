<?php
?>
<!-- Cabeçalho da página -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Clientes</h3>
        <p class="text-sm text-gray-500 mt-1"><?= isset($pagination) ? (int) $pagination['total_items'] : count($clients) ?> cliente(s) encontrado(s)</p>
    </div>
    <a href="<?= APP_URL ?>/clients/create"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
              font-medium px-4 py-2 rounded-lg text-sm transition-colors">
        ➕ Novo Cliente
    </a>
</div>

<!-- Filtros de busca -->
<form id="filterForm" method="GET" action="<?= APP_URL ?>/clients"
      class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

    <input type="text" name="search" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>"
           placeholder="Buscar por nome, empresa..."
           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">

    <select name="stage_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todas as etapas</option>
        <?php foreach ($stages as $stage): ?>
        <option value="<?= $stage['id'] ?>" <?= $filters['stage_id'] == $stage['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="assigned_to" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todos os responsáveis</option>
        <?php foreach ($users as $user): ?>
        <option value="<?= $user['id'] ?>" <?= $filters['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="tipo_venda" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todos os tipos</option>
        <option value="Imóvel"  <?= ($filters['tipo_venda'] ?? '') === 'Imóvel'  ? 'selected' : '' ?>>Imóvel</option>
        <option value="Veículo" <?= ($filters['tipo_venda'] ?? '') === 'Veículo' ? 'selected' : '' ?>>Veículo</option>
        <option value="Serviço" <?= ($filters['tipo_venda'] ?? '') === 'Serviço' ? 'selected' : '' ?>>Serviço</option>
    </select>

    <div class="flex gap-2">
        <button type="submit"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            Filtrar
        </button>
        <a href="<?= APP_URL ?>/clients"
           class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            Limpar
        </a>
    </div>
</form>
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
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($clients)): ?>
    <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-4">👥</div>
        <p class="text-lg font-medium">Nenhum cliente encontrado</p>
        <p class="text-sm mt-1">
            <a href="<?= APP_URL ?>/clients/create" class="text-indigo-600 hover:underline">Cadastre o primeiro cliente</a>
        </p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Nome</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden md:table-cell">Empresa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden lg:table-cell">Contato</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Etapa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden lg:table-cell">Valor</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden xl:table-cell">Responsável</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($clients as $client): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <!-- Nome + Empresa (mobile) -->
                    <td class="px-4 py-3">
                        <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                           class="font-medium text-indigo-700 hover:text-indigo-900">
                            <?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php if (!empty($client['has_overdue'])): ?>
                        <span class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                            ⚠ Em atraso
                        </span>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 md:hidden">
                            <?= htmlspecialchars($client['company'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </td>
                    <!-- Empresa -->
                    <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                        <?= htmlspecialchars($client['company'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Contato -->
                    <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">
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
                    <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">
                        <?php if ($client['deal_value'] > 0): ?>
                        R$ <?= number_format($client['deal_value'], 2, ',', '.') ?>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- Responsável -->
                    <td class="px-4 py-3 text-gray-500 hidden xl:table-cell">
                        <?= htmlspecialchars($client['assigned_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Ações -->
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                               class="text-indigo-600 hover:text-indigo-800 text-xs font-medium" title="Ver detalhes">👁️</a>
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>/edit"
                               class="text-amber-600 hover:text-amber-800 text-xs font-medium" title="Editar">✏️</a>
                            <button
                                onclick="openQuickInteraction(<?= (int)$client['id'] ?>, <?= json_encode($client['name']) ?>)"
                                class="text-green-600 hover:text-green-800 text-xs font-medium" title="Nova interação">💬</button>
                            <button
                                onclick="openQuickTask(<?= (int)$client['id'] ?>, <?= json_encode($client['name']) ?>)"
                                class="text-purple-600 hover:text-purple-800 text-xs font-medium" title="Nova tarefa">📅</button>
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
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h4 class="font-bold text-gray-800">💬 Nova Interação — <span id="qiClientName"></span></h4>
            <button onclick="document.getElementById('modalQuickInteraction').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/interactions/store" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="client_id" id="qiClientId">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="call">📞 Ligação</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                    <option value="email">📧 E-mail</option>
                    <option value="meeting">🤝 Reunião</option>
                    <option value="note">📝 Nota</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição <span class="text-red-500">*</span></label>
                <textarea name="description" rows="3" required placeholder="O que aconteceu?"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data e hora</label>
                <input type="datetime-local" name="occurred_at" id="qiOccurredAt"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg text-sm transition-colors">Salvar</button>
                <button type="button" onclick="document.getElementById('modalQuickInteraction').classList.add('hidden')"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-lg text-sm transition-colors">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Nova Tarefa Rápida -->
<div id="modalQuickTask" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h4 class="font-bold text-gray-800">📅 Nova Tarefa — <span id="qtClientName"></span></h4>
            <button onclick="document.getElementById('modalQuickTask').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/tasks/store" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="client_id" id="qtClientId">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                <input type="text" name="title" required placeholder="O que precisa ser feito?"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prazo <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="due_date" id="qtDueDate" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                    <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="low">Baixa</option>
                        <option value="medium" selected>Média</option>
                        <option value="high">Alta</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg text-sm transition-colors">Salvar</button>
                <button type="button" onclick="document.getElementById('modalQuickTask').classList.add('hidden')"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-lg text-sm transition-colors">Cancelar</button>
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
