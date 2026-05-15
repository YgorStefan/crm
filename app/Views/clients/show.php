<?php
$interactionTypes = [
    'call'     => ['label' => 'Ligação',  'icon' => '📞', 'color' => 'blue'],
    'email'    => ['label' => 'E-mail',   'icon' => '📧', 'color' => 'green'],
    'meeting'  => ['label' => 'Reunião',  'icon' => '🤝', 'color' => 'purple'],
    'whatsapp' => ['label' => 'WhatsApp', 'icon' => '💬', 'color' => 'teal'],
    'note'     => ['label' => 'Nota',     'icon' => '📝', 'color' => 'yellow'],
    'other'    => ['label' => 'Outro',    'icon' => 'ℹ️', 'color' => 'gray'],
];
?>

<div class="max-w-6xl mx-auto">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <a href="<?= APP_URL ?>/clients" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400 bg-gray-100 hover:bg-indigo-50 dark:bg-zinc-800 dark:hover:bg-indigo-900/30 px-3 py-1.5 rounded-lg transition-all">
                ↩ Clientes
            </a>
            <div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white flex items-baseline gap-2 flex-wrap">
                    <span><?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($client['company']): ?>
                        <span class="text-base font-normal text-gray-500 dark:text-zinc-400">·&nbsp;<?= htmlspecialchars($client['company'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </h3>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>/edit"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors" title="Editar">
                ✏️ Editar
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Coluna esquerda: dados do cliente -->
        <div class="lg:col-span-1 space-y-4">

            <!-- Card: informações -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                    <h4 class="font-semibold text-gray-700 dark:text-zinc-200">Informações</h4>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium text-white"
                        style="background-color: <?= htmlspecialchars($client['stage_color'] ?? '#6366f1', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($client['stage_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="px-5 py-4 space-y-3 text-sm">
                    <?php
                    // Máscaras: CPF/CNPJ e telefone armazenados sem máscara no banco
                    $formatPhone = static function (?string $raw): ?string {
                        $digits = preg_replace('/\D/', '', (string) $raw);
                        $len = strlen($digits);
                        if ($len === 11) {
                            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
                        }
                        if ($len === 10) {
                            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 6), substr($digits, 6));
                        }
                        return $raw;
                    };
                    $formatDoc = static function (?string $raw): ?string {
                        $digits = preg_replace('/\D/', '', (string) $raw);
                        $len = strlen($digits);
                        if ($len === 11) {
                            return sprintf('%s.%s.%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 3), substr($digits, 9));
                        }
                        if ($len === 14) {
                            return sprintf('%s.%s.%s/%s-%s', substr($digits, 0, 2), substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 4), substr($digits, 12));
                        }
                        return $raw;
                    };

                    $infos = [
                        '📧 E-mail' => $client['email'],
                        '📱 Telefone' => $formatPhone($client['phone'] ?? null),
                        '🆔 CPF/CNPJ' => $formatDoc($client['cnpj_cpf'] ?? null),
                        '🎂 Nascimento' => !empty($client['birth_date'])
                            ? date('d/m/Y', strtotime($client['birth_date']))
                            : null,
                        '🏆 Fechamento' => !empty($client['closed_at'])
                            ? date('d/m/Y', strtotime($client['closed_at']))
                            : null,
                        '🤝 Indicado por' => $client['referido_por'] ?? null,
                        '📍 Cidade/UF' => trim(($client['city'] ?? '') . ' ' . ($client['state'] ?? '')),
                        '📦 Origem' => $client['source'],
                        '👤 Responsável' => $client['assigned_name'],
                    ];
                    foreach ($infos as $label => $value):
                        if (empty($value))
                            continue;
                        ?>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 dark:text-zinc-500"><?= $label ?></span>
                            <span
                                class="text-gray-700 dark:text-zinc-200 font-medium text-right"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($client['deal_value'] > 0): ?>
                        <div class="flex justify-between gap-2 pt-2 border-t border-gray-100 dark:border-zinc-800">
                            <span class="text-gray-400 dark:text-zinc-500">💰 Valor</span>
                            <span class="text-green-700 font-bold">
                                R$ <?= number_format($client['deal_value'], 2, ',', '.') ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card: nota (visível apenas se o cliente tiver nota) -->
            <?php if (!empty($client['notes'])): ?>
            <div class="bg-white dark:bg-zinc-900 shadow-sm border border-gray-100 dark:border-zinc-800 rounded-xl p-4 text-sm text-gray-700 dark:text-zinc-200" id="notes-card">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-semibold">📝 Nota</p>
                    <div class="flex gap-3">
                        <button type="button" id="btn-edit-notes"
                            class="text-gray-500 hover:text-gray-700" title="Editar Nota">
                            ✏️
                        </button>
                        <button type="button" id="btn-delete-notes"
                            class="text-red-500 hover:text-red-700" title="Excluir Nota">
                            🗑️
                        </button>
                    </div>
                </div>
                <!-- Estado: visualização -->
                <div id="notes-view">
                    <?php if (!empty($client['notes'])): ?>
                        <p class="whitespace-pre-wrap" id="notes-text"><?= htmlspecialchars($client['notes'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <p class="text-gray-400 italic" id="notes-text"></p>
                    <?php endif; ?>
                </div>
                <!-- Estado: edição (oculto) -->
                <div id="notes-edit" style="display:none">
                    <textarea id="notes-textarea" rows="4"
                        class="w-full px-2 py-1 border border-gray-300 rounded text-sm text-gray-800 bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none mb-2 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"><?= htmlspecialchars($client['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="flex gap-2">
                        <button type="button" id="notes-save-btn"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                            Salvar
                        </button>
                        <button type="button" id="notes-cancel-btn"
                            class="border border-gray-300 text-gray-600 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <span id="notes-save-error" class="text-xs text-red-500 self-center" style="display:none">Erro ao salvar.</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Card: adicionar tarefa rápida -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                    <h4 class="font-semibold text-gray-700 dark:text-zinc-200">✅ Nova Tarefa</h4>
                </div>
                <div class="px-5 py-4">
                    <button type="button" id="btn-open-new-task"
                        class="w-full inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition-colors">
                        ➕ Nova Tarefa
                    </button>
                </div>
            </div>
        </div>

        <!-- Coluna direita: histórico + tarefas -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Registrar interação -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                    <h4 class="font-semibold text-gray-700 dark:text-zinc-200">📋 Registrar Interação</h4>
                </div>
                <form method="POST" action="<?= APP_URL ?>/interactions/store" class="px-5 py-4">
                    <input type="hidden" name="_csrf_token"
                        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <select name="type"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <?php foreach ($interactionTypes as $key => $info): ?>
                                <option value="<?= $key ?>"><?= $info['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="datetime-local" name="occurred_at" value="<?= date('Y-m-d\TH:i') ?>"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                    </div>
                    <textarea name="description" required rows="2" placeholder="Descreva o contato realizado..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none mb-3 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"></textarea>
                    <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Salvar Interação
                    </button>
                </form>
            </div>

            <!-- Timeline de interações -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                    <h4 class="font-semibold text-gray-700 dark:text-zinc-200">
                        🕐 Histórico de Contatos
                        <span
                            class="ml-2 text-xs bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 px-2 py-0.5 rounded-full"><?= count($interactions) ?></span>
                    </h4>
                </div>
                <?php if (empty($interactions)): ?>
                    <div class="px-5 py-8 text-center text-gray-400 dark:text-zinc-500 text-sm">Nenhuma interação registrada ainda.</div>
                <?php else: ?>
                    <div class="divide-y divide-gray-50 dark:divide-zinc-800">
                        <?php foreach ($interactions as $inter):
                            $typeInfo = $interactionTypes[$inter['type']] ?? $interactionTypes['other'];
                            ?>
                            <div class="px-5 py-4 flex gap-3" data-interaction-id="<?= $inter['id'] ?>">
                                <!-- Ícone do tipo (sempre visível) -->
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-zinc-800/30 flex items-center justify-center">
                                    <?= $typeInfo['icon'] ?>
                                </div>

                                <!-- Estado: visualização (padrão) -->
                                <div class="flex-1 min-w-0 inter-view">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-gray-500 dark:text-zinc-400 inter-type-label">
                                            <?= $typeInfo['label'] ?> · <?= htmlspecialchars($inter['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="text-xs text-gray-400 dark:text-zinc-500 flex-shrink-0 inter-date-label">
                                            <?= date('d/m/Y H:i', strtotime($inter['occurred_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-zinc-200 mt-1 inter-description cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-800/30 rounded px-1 -mx-1"
                                       title="Clique para editar">
                                        <?= nl2br(htmlspecialchars($inter['description'], ENT_QUOTES, 'UTF-8')) ?>
                                    </p>
                                </div>

                                <!-- Estado: edição (oculto) -->
                                <div class="flex-1 min-w-0 inter-edit" style="display:none">
                                    <div class="grid grid-cols-2 gap-2 mb-2">
                                        <select class="inter-edit-type px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                            <?php foreach ($interactionTypes as $key => $info): ?>
                                                <option value="<?= $key ?>" <?= $inter['type'] === $key ? 'selected' : '' ?>>
                                                    <?= $info['label'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="datetime-local" class="inter-edit-date px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                               value="<?= date('Y-m-d\TH:i', strtotime($inter['occurred_at'])) ?>">
                                    </div>
                                    <textarea class="inter-edit-desc w-full px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none mb-2 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" rows="3"><?= htmlspecialchars($inter['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <div class="flex gap-2">
                                        <button type="button" class="inter-save-btn bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">Salvar</button>
                                        <button type="button" class="inter-cancel-btn border border-gray-300 dark:border-zinc-700 text-gray-600 dark:text-zinc-300 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors">Cancelar</button>
                                        <span class="inter-save-error text-xs text-red-500 self-center" style="display:none">Erro ao salvar.</span>
                                    </div>
                                </div>

                                <!-- Botão deletar (sempre visível, estado view) -->
                                <form method="POST" action="<?= APP_URL ?>/interactions/<?= $inter['id'] ?>/delete"
                                    onsubmit="return confirm('Remover esta interação?')">
                                    <input type="hidden" name="_csrf_token"
                                        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="text-gray-300 hover:text-red-400 text-sm inter-delete-btn"
                                        title="Remover">✕</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tarefas vinculadas -->
            <div id="tasks-card" class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                    <h4 class="font-semibold text-gray-700 dark:text-zinc-200">✅ Tarefas</h4>
                </div>
                <div id="tasks-list" class="divide-y divide-gray-50 dark:divide-zinc-800">
                    <?php
                    $priorityColors = ['low' => 'text-green-600', 'medium' => 'text-yellow-600', 'high' => 'text-red-600'];
                    $statusLabels = ['pending' => 'Pendente', 'in_progress' => 'Em andamento', 'done' => 'Concluída', 'cancelled' => 'Cancelada'];
                    ?>
                    <?php if (empty($tasks)): ?>
                        <p id="tasks-empty" class="px-5 py-6 text-sm text-gray-400 dark:text-zinc-500 text-center">Nenhuma tarefa cadastrada ainda.</p>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="px-5 py-3 flex items-center justify-between gap-3">
                                <div>
                                    <p
                                        class="text-sm font-medium text-gray-700 dark:text-zinc-200 <?= $task['status'] === 'done' ? 'line-through text-gray-400 dark:text-zinc-500' : '' ?>">
                                        <?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">
                                        Vence: <?= date('d/m/Y H:i', strtotime($task['due_date'])) ?>
                                        · <span
                                            class="<?= $priorityColors[$task['priority']] ?? '' ?>"><?= ucfirst($task['priority']) ?></span>
                                    </p>
                                </div>
                                <span class="text-xs bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300 px-2 py-1 rounded-full flex-shrink-0">
                                    <?= $statusLabels[$task['status']] ?? $task['status'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            // FRAG-03: identificação por coluna estruturada, não por nome de string
            $isVendaFechada = !empty($client['stage_name'])
                && !empty($client['is_won_stage']);

            if ($isVendaFechada): ?>
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden" id="cotas-section">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                        <h4 class="font-semibold text-gray-700 dark:text-zinc-200">🏦 Cotas de Consórcio</h4>
                        <button type="button" id="btn-add-cota"
                            class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                            + Adicionar cota
                        </button>
                    </div>

                    <!-- Lista de cotas existentes -->
                    <div id="cotas-list" class="divide-y divide-gray-50 dark:divide-zinc-800">
                        <?php if (empty($sales)): ?>
                            <p class="px-5 py-6 text-sm text-gray-400 dark:text-zinc-500 text-center" id="cotas-empty">Nenhuma cota cadastrada
                                ainda.</p>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                                <div class="px-5 py-4 flex items-start justify-between gap-3" data-sale-id="<?= $sale['id'] ?>">
                                    <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm flex-1">
                                        <div><span class="text-gray-400 dark:text-zinc-500 text-xs">Grupo:</span> <span
                                                class="text-gray-700 dark:text-zinc-200 font-medium"><?= htmlspecialchars($sale['grupo'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div><span class="text-gray-400 dark:text-zinc-500 text-xs">Cota:</span> <span
                                                class="text-gray-700 dark:text-zinc-200 font-medium"><?= htmlspecialchars($sale['cota'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div><span class="text-gray-400 dark:text-zinc-500 text-xs">Tipo:</span> <span
                                                class="text-gray-700 dark:text-zinc-200 font-medium"><?= htmlspecialchars($sale['tipo'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div><span class="text-gray-400 dark:text-zinc-500 text-xs">Crédito:</span> <span
                                                class="text-green-700 font-bold">R$
                                                <?= number_format($sale['credito_contratado'] ?? 0, 2, ',', '.') ?></span></div>
                                    </div>
                                    <button type="button"
                                        class="btn-del-cota text-gray-300 hover:text-red-400 text-sm flex-shrink-0 mt-1"
                                        data-sale-id="<?= $sale['id'] ?>" data-client-id="<?= $client['id'] ?>"
                                        title="Remover cota">✕</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card: Pagamentos - visível somente para Venda Fechada -->
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden" id="pagamentos-section">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <h4 class="font-semibold text-gray-700 dark:text-zinc-200">💰 Pagamentos</h4>
                    </div>
                    <div id="pagamentos-list" class="divide-y divide-gray-50 dark:divide-zinc-800">
                        <?php if (empty($sales)): ?>
                            <p class="px-5 py-6 text-sm text-gray-400 dark:text-zinc-500 text-center">Nenhuma cota cadastrada.</p>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                                <div class="px-5 py-4 flex items-center justify-between gap-3" data-sale-id="<?= $sale['id'] ?>">
                                    <div class="text-sm">
                                        <span class="text-gray-500 dark:text-zinc-400 text-xs">Cota:</span>
                                        <span
                                            class="text-gray-700 dark:text-zinc-200 font-medium"><?= htmlspecialchars($sale['cota'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-gray-400 dark:text-zinc-500 mx-1">·</span>
                                        <span class="text-gray-500 dark:text-zinc-400 text-xs">Tipo:</span>
                                        <span
                                            class="text-gray-700 dark:text-zinc-200 font-medium"><?= htmlspecialchars($sale['tipo'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php if ($sale['is_paid']): ?>
                                        <button type="button"
                                            class="btn-pago-status inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-medium px-3 py-1.5 rounded-lg cursor-default"
                                            data-sale-id="<?= $sale['id'] ?>" disabled>
                                            ✓ Pago em <?= htmlspecialchars($sale['paid_at_formatted'], ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                            class="btn-marcar-pago inline-flex items-center gap-1 bg-amber-100 hover:bg-amber-200 text-amber-800 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors"
                                            data-sale-id="<?= $sale['id'] ?>" data-client-id="<?= $client['id'] ?>">
                                            Marcar como pago
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script nonce="<?= CSP_NONCE ?>">
    window.crmCsrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>';
    window.syncCsrfForms = function (token) {
        window.crmCsrfToken = token;
        document.querySelectorAll('input[name="_csrf_token"]').forEach(function (el) {
            el.value = token;
        });
    };
    </script>

    <script nonce="<?= CSP_NONCE ?>">
    (function () {
        const appUrl = '<?= rtrim(APP_URL, '/') ?>';

        // ---- CLI-10: edição inline de interações ----
        document.querySelectorAll('[data-interaction-id]').forEach(function (row) {
            const id        = row.dataset.interactionId;
            const viewDiv   = row.querySelector('.inter-view');
            const editDiv   = row.querySelector('.inter-edit');
            const descEl    = row.querySelector('.inter-description');
            const typeLabel = row.querySelector('.inter-type-label');
            const dateLabel = row.querySelector('.inter-date-label');
            const editType  = row.querySelector('.inter-edit-type');
            const editDate  = row.querySelector('.inter-edit-date');
            const editDesc  = row.querySelector('.inter-edit-desc');
            const saveBtn   = row.querySelector('.inter-save-btn');
            const cancelBtn = row.querySelector('.inter-cancel-btn');
            const errorEl   = row.querySelector('.inter-save-error');
            const deleteBtn = row.querySelector('.inter-delete-btn');

            // Guarda valores originais para restaurar no Cancelar
            let origType = editType ? editType.value : '';
            let origDate = editDate ? editDate.value : '';
            let origDesc = editDesc ? editDesc.value : '';

            // Ativar edição ao clicar na descrição
            if (descEl) {
                descEl.addEventListener('click', function () {
                    origType = editType.value;
                    origDate = editDate.value;
                    origDesc = editDesc.value;
                    viewDiv.style.display = 'none';
                    editDiv.style.display = '';
                    if (deleteBtn) deleteBtn.style.display = 'none';
                    errorEl.style.display = 'none';
                    editDesc.focus();
                });
            }

            // Cancelar: restaurar estado original
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    editType.value = origType;
                    editDate.value = origDate;
                    editDesc.value = origDesc;
                    editDiv.style.display = 'none';
                    viewDiv.style.display = '';
                    if (deleteBtn) deleteBtn.style.display = '';
                });
            }

            // Salvar via AJAX
            if (saveBtn) {
                saveBtn.addEventListener('click', function () {
                    const desc = editDesc.value.trim();
                    if (!desc) { errorEl.textContent = 'Descrição não pode estar vazia.'; errorEl.style.display = ''; return; }

                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Salvando...';
                    errorEl.style.display = 'none';

                    const body = new URLSearchParams({
                        description: desc,
                        type:        editType.value,
                        occurred_at: editDate.value,
                    });

                    fetch(appUrl + '/interactions/' + id + '/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-Token': window.crmCsrfToken,
                        },
                        body: body.toString(),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.csrf_token) window.syncCsrfForms(data.csrf_token);
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Salvar';

                        if (!data.success) {
                            errorEl.textContent = 'Erro ao salvar.';
                            errorEl.style.display = '';
                            return;
                        }

                        // Re-renderizar row com novos valores
                        const typeLabels = {
                            call: 'Ligação', email: 'E-mail', meeting: 'Reunião',
                            whatsapp: 'WhatsApp', note: 'Nota', other: 'Outro'
                        };
                        const newType = editType.value;
                        const userName = typeLabel.textContent.trim().split(' · ')[1] || '';
                        typeLabel.textContent = (typeLabels[newType] || newType) + ' · ' + userName;

                        // Atualizar data exibida — formatar YYYY-MM-DDTHH:MM para DD/MM/YYYY HH:MM
                        const dt = editDate.value;
                        if (dt && dt.length >= 16) {
                            const [datePart, timePart] = dt.split('T');
                            const [y, m, d] = datePart.split('-');
                            dateLabel.textContent = d + '/' + m + '/' + y + ' ' + timePart;
                        }

                        // Atualizar descrição exibida
                        descEl.textContent = desc;

                        // Atualizar valores originais
                        origType = newType;
                        origDate = editDate.value;
                        origDesc = desc;

                        // Voltar para view
                        editDiv.style.display = 'none';
                        viewDiv.style.display = '';
                        if (deleteBtn) deleteBtn.style.display = '';
                    })
                    .catch(function () {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Salvar';
                        errorEl.textContent = 'Erro de conexão.';
                        errorEl.style.display = '';
                    });
                });
            }
        });
    })();
    </script>

    <script nonce="<?= CSP_NONCE ?>">
    (function () {
        const clientId = <?= (int) $client['id'] ?>;
        const appUrl   = '<?= rtrim(APP_URL, '/') ?>';

        // ---- CLI-11: edição inline de notas ----
        const btnEdit   = document.getElementById('btn-edit-notes');
        const notesView = document.getElementById('notes-view');
        const notesEdit = document.getElementById('notes-edit');
        const notesText = document.getElementById('notes-text');
        const textarea  = document.getElementById('notes-textarea');
        const saveBtn   = document.getElementById('notes-save-btn');
        const cancelBtn  = document.getElementById('notes-cancel-btn');
        const errorEl    = document.getElementById('notes-save-error');
        const deleteBtn  = document.getElementById('btn-delete-notes');

        if (!btnEdit) return;

        let origNotes = textarea ? textarea.value : '';

        btnEdit.addEventListener('click', function () {
            origNotes = textarea.value;
            notesView.style.display = 'none';
            notesEdit.style.display = '';
            errorEl.style.display = 'none';
            textarea.focus();
        });

        cancelBtn.addEventListener('click', function () {
            textarea.value = origNotes;
            notesEdit.style.display = 'none';
            notesView.style.display = '';
        });

        saveBtn.addEventListener('click', function () {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Salvando...';
            errorEl.style.display = 'none';

            const body = new URLSearchParams({ notes: textarea.value });

            fetch(appUrl + '/clients/' + clientId + '/update-notes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': window.crmCsrfToken,
                },
                body: body.toString(),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.csrf_token) window.syncCsrfForms(data.csrf_token);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salvar';

                if (!data.success) {
                    errorEl.textContent = 'Erro ao salvar nota.';
                    errorEl.style.display = '';
                    return;
                }

                // Atualizar texto exibido e estado original
                const newText = textarea.value;
                notesText.textContent = newText;
                origNotes = newText;

                notesEdit.style.display = 'none';
                notesView.style.display = '';
            })
            .catch(function () {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salvar';
                errorEl.textContent = 'Erro de conexão.';
                errorEl.style.display = '';
            });
        });
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                if (!confirm('Excluir a nota deste cliente?')) return;
                deleteBtn.disabled = true;

                fetch(appUrl + '/clients/' + clientId + '/update-notes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': window.crmCsrfToken,
                    },
                    body: new URLSearchParams({ notes: '' }).toString(),
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    deleteBtn.disabled = false;
                    if (data.csrf_token) window.syncCsrfForms(data.csrf_token);
                    if (data.success) {
                        notesText.textContent = '';
                        textarea.value = '';
                        origNotes = '';
                        notesEdit.style.display = 'none';
                        notesView.style.display = '';
                    }
                })
                .catch(function () { deleteBtn.disabled = false; });
            });
        }
    })();
    </script>

    <?php if ($isVendaFechada): ?>
        <!-- Modal: Adicionar cota de consórcio -->
        <div id="cota-modal-overlay" style="display:none;"
            class="fixed inset-0 bg-gray-900 bg-opacity-60 z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
                <h5 class="text-base font-semibold text-gray-800 dark:text-white mb-4">Nova Cota de Consórcio</h5>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Grupo</label>
                            <input type="text" id="cota-grupo" data-mask="digits" placeholder="Ex: 0042"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Cota</label>
                            <input type="text" id="cota-cota" data-mask="digits" placeholder="Ex: 128"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Tipo <span
                                class="text-red-500">*</span></label>
                        <select id="cota-tipo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Selecione...</option>
                            <option value="Imóvel">Imóvel</option>
                            <option value="Veículo">Veículo</option>
                            <option value="Serviço">Serviço</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-zinc-300 mb-1">Crédito contratado (R$)</label>
                        <input type="text" id="cota-credito" data-mask="currency" placeholder="R$ 0,00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" id="cota-cancel"
                        class="px-4 py-2 border border-gray-300 dark:border-zinc-700 text-gray-700 dark:text-zinc-200 rounded-lg text-sm hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors">
                        Cancelar
                    </button>
                    <button type="button" id="cota-save"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">
                        Salvar cota
                    </button>
                </div>
            </div>
        </div>

        <script nonce="<?= CSP_NONCE ?>">
            (function () {
                const clientId = <?= (int) $client['id'] ?>;
                const appUrl = '<?= rtrim(APP_URL, '/') ?>';
                const overlay = document.getElementById('cota-modal-overlay');
                const btnAdd = document.getElementById('btn-add-cota');
                const btnCancel = document.getElementById('cota-cancel');
                const btnSave = document.getElementById('cota-save');
                const cotasList = document.getElementById('cotas-list');

                // Abre modal
                btnAdd.addEventListener('click', function () {
                    document.getElementById('cota-grupo').value = '';
                    document.getElementById('cota-cota').value = '';
                    var sel = document.getElementById('cota-tipo');
                    sel.value = '';
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                    document.getElementById('cota-credito').value = '';
                    overlay.style.display = 'flex';
                });

                // Fecha ao clicar no overlay ou no Cancelar
                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) overlay.style.display = 'none';
                });
                btnCancel.addEventListener('click', function () { overlay.style.display = 'none'; });

                // Salva cota via AJAX
                btnSave.addEventListener('click', function () {
                    const tipo = document.getElementById('cota-tipo').value;
                    if (!tipo) { alert('Selecione o Tipo de consórcio.'); return; }

                    const creditoRaw = (document.getElementById('cota-credito').value || '').replace(/\D/g, '');
                    const credito = creditoRaw ? (parseInt(creditoRaw, 10) / 100).toFixed(2) : '0.00';

                    const body = new URLSearchParams({
                        _csrf_token: window.crmCsrfToken,
                        grupo: document.getElementById('cota-grupo').value.trim(),
                        cota: document.getElementById('cota-cota').value.trim(),
                        tipo: tipo,
                        credito_contratado: credito,
                    });

                    fetch(appUrl + '/clients/' + clientId + '/sales', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.csrf_token) window.syncCsrfForms(data.csrf_token);
                            if (!data.success) { alert('Erro ao salvar cota.'); return; }

                            // Remove mensagem "Nenhuma cota" se existir
                            const emptyMsg = document.getElementById('cotas-empty');
                            if (emptyMsg) emptyMsg.remove();

                            // Adiciona novo card
                            const s = data.sale;
                            const credito = parseFloat(s.credito_contratado || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const html = '<div class="px-5 py-4 flex items-start justify-between gap-3" data-sale-id="' + s.id + '">'
                                + '<div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm flex-1">'
                                + '<div><span class="text-gray-400 text-xs">Grupo:</span> <span class="text-gray-700 font-medium">' + (s.grupo || '—') + '</span></div>'
                                + '<div><span class="text-gray-400 text-xs">Cota:</span> <span class="text-gray-700 font-medium">' + (s.cota || '—') + '</span></div>'
                                + '<div><span class="text-gray-400 text-xs">Tipo:</span> <span class="text-gray-700 font-medium">' + s.tipo + '</span></div>'
                                + '<div><span class="text-gray-400 text-xs">Crédito:</span> <span class="text-green-700 font-bold">R$ ' + credito + '</span></div>'
                                + '</div>'
                                + '<button type="button" class="btn-del-cota text-gray-300 hover:text-red-400 text-sm flex-shrink-0 mt-1" data-sale-id="' + s.id + '" data-client-id="' + clientId + '" title="Remover cota">✕</button>'
                                + '</div>';
                            cotasList.insertAdjacentHTML('beforeend', html);
                            overlay.style.display = 'none';
                        })
                        .catch(function () { alert('Erro de conexão ao salvar cota.'); });
                });

                // Delegação de evento: excluir cota
                cotasList.addEventListener('click', function (e) {
                    const btn = e.target.closest('.btn-del-cota');
                    if (!btn) return;
                    if (!confirm('Remover esta cota?')) return;

                    const saleId = btn.dataset.saleId;
                    const body = new URLSearchParams({ _csrf_token: window.crmCsrfToken });

                    fetch(appUrl + '/clients/' + clientId + '/sales/' + saleId + '/delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.csrf_token) window.syncCsrfForms(data.csrf_token);
                            if (!data.success) { alert('Erro ao remover cota.'); return; }
                            const card = cotasList.querySelector('[data-sale-id="' + saleId + '"]');
                            if (card) card.remove();
                            if (!cotasList.querySelector('[data-sale-id]')) {
                                cotasList.innerHTML = '<p class="px-5 py-6 text-sm text-gray-400 text-center" id="cotas-empty">Nenhuma cota cadastrada ainda.</p>';
                            }
                        })
                        .catch(function () { alert('Erro de conexão ao remover cota.'); });
                });

                // Delegação de evento: marcar cota como paga
                const pagamentosList = document.getElementById('pagamentos-list');
                if (pagamentosList) {
                    pagamentosList.addEventListener('click', function (e) {
                        const btn = e.target.closest('.btn-marcar-pago');
                        if (!btn) return;

                        btn.disabled = true;
                        btn.textContent = 'Salvando...';

                        const saleId = btn.dataset.saleId;
                        const body = new URLSearchParams({ _csrf_token: window.crmCsrfToken });

                        fetch(appUrl + '/clients/' + clientId + '/sales/' + saleId + '/paid', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: body.toString(),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (data.csrf_token) window.syncCsrfForms(data.csrf_token);
                                if (!data.success) {
                                    btn.disabled = false;
                                    btn.textContent = 'Marcar como pago';
                                    alert('Erro ao registrar pagamento.');
                                    return;
                                }
                                // Troca botão para estado "pago" sem recarregar
                                btn.classList.remove('btn-marcar-pago', 'bg-amber-100', 'hover:bg-amber-200', 'text-amber-800');
                                btn.classList.add('btn-pago-status', 'bg-green-100', 'text-green-700', 'cursor-default');
                                btn.disabled = true;
                                btn.textContent = '✓ Pago em ' + (data.paid_at_formatted || '—');
                            })
                            .catch(function () {
                                btn.disabled = false;
                                btn.textContent = 'Marcar como pago';
                                alert('Erro de conexão.');
                            });
                    });
                }
            })();
        </script>
    <?php endif; ?>

    <!-- Modal: Nova Tarefa (mesmo padrão de /tasks) -->
    <div id="newTaskModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4" style="display:none">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Nova Tarefa</h4>
                <button type="button" id="newTaskClose"
                    class="text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-2xl">&times;</button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg px-3 py-2">
                    <span class="text-sm text-indigo-700 dark:text-indigo-300">👥 Cliente: <span class="font-medium"><?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?></span></span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Título <span class="text-red-500">*</span></label>
                    <input type="text" id="newTask_title" required placeholder="O que precisa ser feito?"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Prazo <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="newTask_due_date" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Prioridade</label>
                        <select id="newTask_priority"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="low">Baixa</option>
                            <option value="medium" selected>Média</option>
                            <option value="high">Alta</option>
                        </select>
                    </div>
                </div>

                <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Responsável</label>
                        <select id="newTask_assigned_to"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $user['id'] == ($_SESSION['user']['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Descrição</label>
                    <textarea id="newTask_description" rows="2"
                        placeholder="Detalhes da tarefa (opcional)..."
                        class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" id="newTaskCancel"
                        class="px-4 py-2 border border-gray-300 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 dark:border-zinc-700 rounded-lg text-sm hover:bg-gray-100 transition-colors">
                        Cancelar
                    </button>
                    <button type="button" id="newTaskSave"
                        class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?= CSP_NONCE ?>">
    (function () {
        const appUrl = '<?= rtrim(APP_URL, '/') ?>';
        const clientId = <?= (int) $client['id'] ?>;

        const modal      = document.getElementById('newTaskModal');
        const btnOpen    = document.getElementById('btn-open-new-task');
        const btnClose   = document.getElementById('newTaskClose');
        const btnCancel  = document.getElementById('newTaskCancel');
        const btnSave    = document.getElementById('newTaskSave');
        const inpTitle   = document.getElementById('newTask_title');
        const inpDue     = document.getElementById('newTask_due_date');
        const selPrio    = document.getElementById('newTask_priority');
        const selAssign  = document.getElementById('newTask_assigned_to');
        const inpDesc    = document.getElementById('newTask_description');
        const tasksList  = document.getElementById('tasks-list');

        if (!btnOpen) return;

        const PRIO_LABEL  = { low: 'Low', medium: 'Medium', high: 'High' };
        const PRIO_CLASS  = { low: 'text-green-600', medium: 'text-yellow-600', high: 'text-red-600' };

        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        function todayAt8() {
            const d = new Date();
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T08:00';
        }

        function openModal() {
            inpTitle.value = '';
            inpDue.value   = todayAt8();
            selPrio.value  = 'medium';
            inpDesc.value  = '';
            modal.style.display = 'flex';
            setTimeout(function () { inpTitle.focus(); }, 50);
        }
        function closeModal() {
            modal.style.display = 'none';
        }

        btnOpen.addEventListener('click', openModal);
        btnClose.addEventListener('click', closeModal);
        btnCancel.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        function formatDueDateLabel(dt) {
            // dt = "YYYY-MM-DDTHH:MM" -> "DD/MM/YYYY HH:MM"
            if (!dt || dt.length < 16) return dt;
            const [datePart, timePart] = dt.split('T');
            const [y, m, d] = datePart.split('-');
            return d + '/' + m + '/' + y + ' ' + timePart;
        }

        function appendTaskRow(task) {
            // Remove placeholder vazio se existir
            const empty = document.getElementById('tasks-empty');
            if (empty) empty.remove();

            const row = document.createElement('div');
            row.className = 'px-5 py-3 flex items-center justify-between gap-3';

            const left = document.createElement('div');
            const titleP = document.createElement('p');
            titleP.className = 'text-sm font-medium text-gray-700 dark:text-zinc-200';
            titleP.textContent = task.title;
            const meta = document.createElement('p');
            meta.className = 'text-xs text-gray-400 dark:text-zinc-500 mt-0.5';
            const prioSpan = document.createElement('span');
            prioSpan.className = PRIO_CLASS[task.priority] || '';
            prioSpan.textContent = PRIO_LABEL[task.priority] || task.priority;
            meta.appendChild(document.createTextNode('Vence: ' + formatDueDateLabel(task.due_date) + ' · '));
            meta.appendChild(prioSpan);
            left.appendChild(titleP);
            left.appendChild(meta);

            const status = document.createElement('span');
            status.className = 'text-xs bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300 px-2 py-1 rounded-full flex-shrink-0';
            status.textContent = 'Pendente';

            row.appendChild(left);
            row.appendChild(status);
            tasksList.appendChild(row);
        }

        function notify(message, type) {
            if (typeof window.crmToast === 'function') {
                window.crmToast(message, type);
            } else {
                alert(message);
            }
        }

        btnSave.addEventListener('click', async function () {
            const title = inpTitle.value.trim();
            const due   = inpDue.value;
            if (!title || !due) {
                notify('Título e prazo são obrigatórios.', 'error');
                return;
            }

            btnSave.disabled = true;
            const originalLabel = btnSave.textContent;
            btnSave.textContent = 'Salvando...';

            const body = new URLSearchParams({
                _csrf_token: window.crmCsrfToken,
                client_id: String(clientId),
                title: title,
                due_date: due,
                priority: selPrio.value,
                description: inpDesc.value,
            });
            if (selAssign) body.append('assigned_to', selAssign.value);

            try {
                const resp = await fetch(appUrl + '/tasks/store', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body.toString(),
                });

                if (!resp.ok) {
                    notify('Erro ao salvar tarefa. Verifique os campos e tente novamente.', 'error');
                    return;
                }
                const data = await resp.json();
                if (data && data.csrf_token) window.syncCsrfForms(data.csrf_token);
                if (!data || !data.success) {
                    notify('Erro ao salvar tarefa.', 'error');
                    return;
                }

                appendTaskRow({
                    title: title,
                    due_date: due,
                    priority: selPrio.value,
                });
                closeModal();
                notify('Tarefa criada com sucesso!', 'success');
            } catch (e) {
                notify('Erro de rede ao salvar tarefa.', 'error');
            } finally {
                btnSave.disabled = false;
                btnSave.textContent = originalLabel;
            }
        });
    })();
    </script>
</div>