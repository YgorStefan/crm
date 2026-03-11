<?php
/**
 * View: clients/show.php — Detalhes do cliente
 * Variáveis: $client, $interactions, $tasks, $stages, $users, $csrf_token
 */
$interactionTypes = [
    'call'     => ['label' => 'Ligação',   'icon' => '📞', 'color' => 'blue'],
    'email'    => ['label' => 'E-mail',    'icon' => '📧', 'color' => 'green'],
    'meeting'  => ['label' => 'Reunião',   'icon' => '🤝', 'color' => 'purple'],
    'whatsapp' => ['label' => 'WhatsApp',  'icon' => '💬', 'color' => 'teal'],
    'note'     => ['label' => 'Nota',      'icon' => '📝', 'color' => 'yellow'],
    'other'    => ['label' => 'Outro',     'icon' => '📌', 'color' => 'gray'],
];
?>

<div class="max-w-6xl mx-auto">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <a href="<?= APP_URL ?>/clients" class="text-gray-400 hover:text-gray-600">← Clientes</a>
            <div>
                <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if ($client['company']): ?>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($client['company'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>/edit"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
                ✏️ Editar
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Coluna esquerda: dados do cliente -->
        <div class="lg:col-span-1 space-y-4">

            <!-- Card: informações -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h4 class="font-semibold text-gray-700">Informações</h4>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium text-white"
                          style="background-color: <?= htmlspecialchars($client['stage_color'] ?? '#6366f1', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($client['stage_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="px-5 py-4 space-y-3 text-sm">
                    <?php
                    $infos = [
                        '📧 E-mail'       => $client['email'],
                        '📱 Telefone'     => $client['phone'],
                        '🆔 CPF/CNPJ'    => $client['cnpj_cpf'],
                        '📍 Cidade/UF'   => trim(($client['city'] ?? '') . ' ' . ($client['state'] ?? '')),
                        '📦 Origem'       => $client['source'],
                        '👤 Responsável' => $client['assigned_name'],
                    ];
                    foreach ($infos as $label => $value):
                        if (empty($value)) continue;
                    ?>
                    <div class="flex justify-between gap-2">
                        <span class="text-gray-400"><?= $label ?></span>
                        <span class="text-gray-700 font-medium text-right"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($client['deal_value'] > 0): ?>
                    <div class="flex justify-between gap-2 pt-2 border-t border-gray-100">
                        <span class="text-gray-400">💰 Valor</span>
                        <span class="text-green-700 font-bold">
                            R$ <?= number_format($client['deal_value'], 2, ',', '.') ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card: notas -->
            <?php if (!empty($client['notes'])): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-900">
                <p class="font-semibold mb-2">📝 Notas</p>
                <p class="whitespace-pre-wrap"><?= htmlspecialchars($client['notes'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php endif; ?>

            <!-- Card: adicionar tarefa rápida -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h4 class="font-semibold text-gray-700">✅ Nova Tarefa</h4>
                </div>
                <form method="POST" action="<?= APP_URL ?>/tasks/store" class="px-5 py-4 space-y-3">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <input type="text" name="title" required placeholder="Título da tarefa"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <input type="datetime-local" name="due_date" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="low">🟢 Baixa prioridade</option>
                        <option value="medium" selected>🟡 Média prioridade</option>
                        <option value="high">🔴 Alta prioridade</option>
                    </select>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition-colors">
                        Criar Tarefa
                    </button>
                </form>
            </div>
        </div>

        <!-- Coluna direita: histórico + tarefas -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Registrar interação -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h4 class="font-semibold text-gray-700">📋 Registrar Interação</h4>
                </div>
                <form method="POST" action="<?= APP_URL ?>/interactions/store" class="px-5 py-4">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <select name="type" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <?php foreach ($interactionTypes as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['icon'] ?> <?= $info['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="datetime-local" name="occurred_at"
                               value="<?= date('Y-m-d\TH:i') ?>"
                               class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <textarea name="description" required rows="2" placeholder="Descreva o contato realizado..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none mb-3"></textarea>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Salvar Interação
                    </button>
                </form>
            </div>

            <!-- Timeline de interações -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h4 class="font-semibold text-gray-700">
                        🕐 Histórico de Contatos
                        <span class="ml-2 text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full"><?= count($interactions) ?></span>
                    </h4>
                </div>
                <?php if (empty($interactions)): ?>
                <div class="px-5 py-8 text-center text-gray-400 text-sm">Nenhuma interação registrada ainda.</div>
                <?php else: ?>
                <div class="divide-y divide-gray-50">
                    <?php foreach ($interactions as $inter):
                        $typeInfo = $interactionTypes[$inter['type']] ?? $interactionTypes['other'];
                    ?>
                    <div class="px-5 py-4 flex gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-base">
                            <?= $typeInfo['icon'] ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-gray-500"><?= $typeInfo['label'] ?> · <?= htmlspecialchars($inter['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-xs text-gray-400 flex-shrink-0">
                                    <?= date('d/m/Y H:i', strtotime($inter['occurred_at'])) ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-700 mt-1"><?= nl2br(htmlspecialchars($inter['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                        <!-- Botão de deletar interação -->
                        <form method="POST" action="<?= APP_URL ?>/interactions/<?= $inter['id'] ?>/delete"
                              onsubmit="return confirm('Remover esta interação?')">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="text-gray-300 hover:text-red-400 text-sm" title="Remover">✕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tarefas vinculadas -->
            <?php if (!empty($tasks)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h4 class="font-semibold text-gray-700">✅ Tarefas</h4>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php
                    $priorityColors = ['low' => 'text-green-600', 'medium' => 'text-yellow-600', 'high' => 'text-red-600'];
                    $statusLabels   = ['pending' => 'Pendente', 'in_progress' => 'Em andamento', 'done' => 'Concluída', 'cancelled' => 'Cancelada'];
                    foreach ($tasks as $task):
                    ?>
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700 <?= $task['status'] === 'done' ? 'line-through text-gray-400' : '' ?>">
                                <?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Vence: <?= date('d/m/Y H:i', strtotime($task['due_date'])) ?>
                                · <span class="<?= $priorityColors[$task['priority']] ?? '' ?>"><?= ucfirst($task['priority']) ?></span>
                            </p>
                        </div>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full flex-shrink-0">
                            <?= $statusLabels[$task['status']] ?? $task['status'] ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
