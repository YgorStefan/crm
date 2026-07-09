<?php
?>
<?php
// Variáveis injetadas pelo Controller::render() via extract($data)
$clients    = $clients ?? [];
$stages     = $stages ?? [];
$users      = $users ?? [];
$filters    = $filters ?? [];
$pagination = $pagination ?? [];
$csrf_token = $csrf_token ?? '';

$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts = '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/client-index.js?v=' . $_jsV('client-index.js') . '"></script>';
unset($_jsV);
?>
<!-- Toolbar unificada: titulo + filtros + acoes em uma linha -->
<div data-crm-widget="client-index"
     class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-3 mb-4">
    <form id="filterForm" method="GET" action="<?= APP_URL ?>/clients"
          class="flex flex-col lg:flex-row lg:flex-wrap lg:items-center gap-2">

        <div class="flex items-baseline gap-3 lg:mr-2 flex-shrink-0">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Clientes</h3>
            <span class="text-xs text-gray-500 dark:text-zinc-400 whitespace-nowrap"><?= isset($pagination) ? (int) $pagination['total_items'] : count($clients) ?> encontrado(s)</span>
        </div>

        <div class="grid grid-cols-2 lg:flex lg:flex-wrap lg:items-center gap-2 flex-1">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Buscar por nome, empresa..." aria-label="Buscar clientes"
                   class="col-span-2 lg:flex-none lg:w-64 lg:min-w-0 px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">

            <select name="stage_id" aria-label="Filtrar por etapa" class="lg:w-36 px-2 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">Etapa</option>
                <?php foreach ($stages as $stage): ?>
                <option value="<?= $stage['id'] ?>" <?= $filters['stage_id'] == $stage['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="assigned_to" aria-label="Filtrar por responsável" class="lg:w-36 px-2 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">Responsável</option>
                <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $filters['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="tipo_venda" aria-label="Filtrar por tipo de venda" class="lg:w-32 px-2 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">Tipo</option>
                <option value="Imóvel"  <?= ($filters['tipo_venda'] ?? '') === 'Imóvel'  ? 'selected' : '' ?>>Imóvel</option>
                <option value="Veículo" <?= ($filters['tipo_venda'] ?? '') === 'Veículo' ? 'selected' : '' ?>>Veículo</option>
                <option value="Serviço" <?= ($filters['tipo_venda'] ?? '') === 'Serviço' ? 'selected' : '' ?>>Serviço</option>
            </select>

            <button type="submit"
                    class="inline-flex items-center justify-center px-4 h-9 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors whitespace-nowrap">
                Filtrar
            </button>
            <a href="<?= APP_URL ?>/clients"
               class="inline-flex items-center justify-center px-4 h-9 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 font-medium rounded-lg text-sm transition-colors whitespace-nowrap">
                Limpar
            </a>
        </div>

        <input type="hidden" name="sort" value="<?= htmlspecialchars($filters['sort'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="dir"  value="<?= htmlspecialchars($filters['dir'],  ENT_QUOTES, 'UTF-8') ?>">
        <a href="<?= APP_URL ?>/clients/create"
           class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-3 py-2 rounded-lg text-sm transition-colors flex-shrink-0 whitespace-nowrap">
            ➕ Novo
        </a>
    </form>
</div>

<!-- Tabela de clientes -->
<div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
    <?php if (empty($clients)): ?>
    <div class="text-center py-16 text-gray-400 dark:text-zinc-500">
        <div class="text-5xl mb-4">👥</div>
        <p class="text-lg font-medium">Nenhum cliente encontrado</p>
        <p class="text-sm mt-1">
            <a href="<?= APP_URL ?>/clients/create" class="text-indigo-600 hover:underline">Cadastre o primeiro cliente</a>
        </p>
    </div>
    <?php else: ?>
    <?php
    $sortLink = function (string $col, string $label) use ($filters, $pagination): string {
        $active = ($filters['sort'] === $col);
        $nextDir = ($active && $filters['dir'] === 'asc') ? 'desc' : 'asc';
        if ($active) {
            $icon = $filters['dir'] === 'asc' ? ' ↑' : ' ↓';
        } else {
            $icon = '';
        }
        $params = array_merge(
            array_diff_key($pagination['query_params'], ['sort' => 1, 'dir' => 1]),
            ['sort' => $col, 'dir' => $nextDir, 'page' => 1]
        );
        $qs = http_build_query($params);
        $cls = $active
            ? 'text-indigo-600 dark:text-indigo-400 font-semibold'
            : 'hover:text-indigo-600 dark:hover:text-indigo-400';
        return "<a href=\"" . APP_URL . "/clients?{$qs}\" class=\"{$cls}\">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "{$icon}</a>";
    };
    ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-zinc-800/50 border-b border-gray-200 dark:border-zinc-700">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400"><?= $sortLink('name', 'Nome') ?></th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400 hidden md:table-cell">Empresa</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400 hidden lg:table-cell">Contato</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400"><?= $sortLink('stage', 'Etapa') ?></th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400 hidden lg:table-cell">Tipo</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400 hidden lg:table-cell"><?= $sortLink('value', 'Valor') ?></th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400 hidden xl:table-cell">Responsável</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600 dark:text-zinc-400">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                <?php foreach ($clients as $client): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800/30 transition-colors">
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
                        <p class="text-xs text-gray-400 dark:text-zinc-500 md:hidden">
                            <?= htmlspecialchars($client['company'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </td>
                    <!-- Empresa -->
                    <td class="px-4 py-3 text-gray-600 dark:text-zinc-400 hidden md:table-cell">
                        <?= htmlspecialchars($client['company'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Contato -->
                    <td class="px-4 py-3 text-gray-500 dark:text-zinc-400 hidden lg:table-cell">
                        <?php if ($client['email']): ?>
                        <div><?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($client['phone']): ?>
                        <div class="text-xs"><?= htmlspecialchars($client['phone'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </td>
                    <!-- Etapa com badge colorido -->
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium text-white"
                              style="background-color: <?= htmlspecialchars($client['stage_color'] ?? '#6366f1', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($client['stage_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <!-- Tipo de venda -->
                    <td class="px-4 py-3 text-gray-600 dark:text-zinc-400 hidden lg:table-cell">
                        <?= !empty($client['tipo_venda'])
                            ? htmlspecialchars($client['tipo_venda'], ENT_QUOTES, 'UTF-8')
                            : '<span class="text-gray-400 dark:text-zinc-500">—</span>' ?>
                    </td>
                    <!-- Valor do negócio -->
                    <td class="px-4 py-3 text-gray-600 dark:text-zinc-400 hidden lg:table-cell">
                        <?php if ($client['deal_value'] > 0): ?>
                        R$ <?= number_format($client['deal_value'], 2, ',', '.') ?>
                        <?php else: ?>
                        <span class="text-gray-400 dark:text-zinc-500">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- Responsável -->
                    <td class="px-4 py-3 text-gray-500 dark:text-zinc-400 hidden xl:table-cell">
                        <?= htmlspecialchars($client['assigned_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <!-- Ações -->
                    <td class="px-4 py-3 text-center">
                        <div class="actions-group inline-flex items-center gap-0.5 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-1">
                            <!-- Ver -->
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                               data-tooltip="Ver detalhes"
                               class="has-tooltip w-9 h-9 flex items-center justify-center rounded-md text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 transition-colors">
                                👁️
                            </a>
                            <!-- Editar -->
                            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>/edit"
                               data-tooltip="Editar"
                               class="has-tooltip w-9 h-9 flex items-center justify-center rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/40 transition-colors">
                                ✏️
                            </a>
                            <!-- Nova interação -->
                            <button
                                data-action="open-quick-interaction"
                                data-client-id="<?= (int)$client['id'] ?>"
                                data-client-name="<?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-tooltip="Nova interação"
                                class="has-tooltip w-9 h-9 flex items-center justify-center rounded-md text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/40 transition-colors">
                                💬
                            </button>
                            <!-- Nova tarefa -->
                            <button
                                data-action="open-quick-task"
                                data-client-id="<?= (int)$client['id'] ?>"
                                data-client-name="<?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-tooltip="Nova tarefa"
                                class="has-tooltip w-9 h-9 flex items-center justify-center rounded-md text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/40 transition-colors">
                                📅
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
<?php require_once VIEW_PATH . '/components/pagination.php'; ?>
<?php endif; ?>

<!-- Modal: Nova Interação Rápida -->
<div id="modalQuickInteraction" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
            <h4 class="font-bold text-gray-800 dark:text-white">💬 Nova Interação — <span id="qiClientName"></span></h4>
            <button data-action="close-modal" data-target="modalQuickInteraction" class="text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-xl">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/interactions/store" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="client_id" id="qiClientId">
            <div>
                <label for="qi-type" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Tipo</label>
                <select id="qi-type" name="type" class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="call">📞 Ligação</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                    <option value="email">📧 E-mail</option>
                    <option value="meeting">🤝 Reunião</option>
                    <option value="note">📝 Nota</option>
                </select>
            </div>
            <div>
                <label for="qi-description" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Descrição <span class="text-red-500">*</span></label>
                <textarea id="qi-description" name="description" rows="3" required placeholder="O que aconteceu?"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none"></textarea>
            </div>
            <div>
                <label for="qiOccurredAt" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Data e hora</label>
                <input type="datetime-local" name="occurred_at" id="qiOccurredAt"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg text-sm transition-colors">Salvar</button>
                <button type="button" data-action="close-modal" data-target="modalQuickInteraction"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 font-medium py-2 rounded-lg text-sm transition-colors">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Nova Tarefa Rápida -->
<div id="modalQuickTask" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
            <h4 class="font-bold text-gray-800 dark:text-white">📅 Nova Tarefa — <span id="qtClientName"></span></h4>
            <button data-action="close-modal" data-target="modalQuickTask" class="text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-xl">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/tasks/store" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="client_id" id="qtClientId">
            <?php
            $p = 'qt';
            $showAssigned = false;
            $showDescription = false;
            $recurrenceDefault = 'none';
            require_once VIEW_PATH . '/components/task-modal-fields.php';
            // Reseta defaults para não vazarem a outros includes da view
            unset($showAssigned, $showDescription, $recurrenceDefault);
            ?>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-lg text-sm transition-colors">Salvar</button>
                <button type="button" data-action="close-modal" data-target="modalQuickTask"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 font-medium py-2 rounded-lg text-sm transition-colors">Cancelar</button>
            </div>
        </form>
    </div>
</div>
