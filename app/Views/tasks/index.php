<?php
/**
 * View: tasks/index.php — Lista de Tarefas
 * Variáveis: $tasks, $overdue, $users, $filters, $csrf_token
 */
$statusLabels   = ['pending' => 'Pendente', 'in_progress' => 'Em andamento', 'done' => 'Concluída', 'cancelled' => 'Cancelada'];
$priorityMeta   = [
    'low'    => ['label' => 'Baixa',  'class' => 'bg-green-100 text-green-700'],
    'medium' => ['label' => 'Média',  'class' => 'bg-yellow-100 text-yellow-700'],
    'high'   => ['label' => 'Alta',   'class' => 'bg-red-100 text-red-700'],
];
$statusColors   = [
    'pending'     => 'bg-gray-100 text-gray-600',
    'in_progress' => 'bg-blue-100 text-blue-700',
    'done'        => 'bg-green-100 text-green-700',
    'cancelled'   => 'bg-red-100 text-red-600',
];
$today = new DateTime();
?>

<!-- Cabeçalho -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Tarefas</h3>
        <p class="text-sm text-gray-500 mt-1"><?= count($tasks) ?> tarefa(s) encontrada(s)</p>
    </div>
    <button onclick="document.getElementById('modalNewTask').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
        ➕ Nova Tarefa
    </button>
</div>

<!-- Alerta: Tarefas Atrasadas -->
<?php if (!empty($overdue)): ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
    <p class="text-sm font-semibold text-red-700 mb-2">⚠️ <?= count($overdue) ?> tarefa(s) atrasada(s)</p>
    <div class="space-y-1">
        <?php foreach (array_slice($overdue, 0, 3) as $t): ?>
        <div class="text-sm text-red-600 flex justify-between">
            <span><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="text-xs text-red-400">Venceu em <?= date('d/m/Y', strtotime($t['due_date'])) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<form method="GET" action="<?= APP_URL ?>/tasks"
      class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3">

    <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todos os status</option>
        <?php foreach ($statusLabels as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
    </select>

    <select name="priority" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todas as prioridades</option>
        <?php foreach ($priorityMeta as $val => $meta): ?>
        <option value="<?= $val ?>" <?= $filters['priority'] === $val ? 'selected' : '' ?>><?= $meta['label'] ?></option>
        <?php endforeach; ?>
    </select>

    <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
    <select name="assigned_to" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        <option value="">Todos os responsáveis</option>
        <?php foreach ($users as $user): ?>
        <option value="<?= $user['id'] ?>" <?= $filters['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
    </select>
    <?php else: ?>
    <div></div>
    <?php endif; ?>

    <div class="flex gap-2">
        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition-colors">Filtrar</button>
        <a href="<?= APP_URL ?>/tasks" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 rounded-lg transition-colors">Limpar</a>
    </div>
</form>

<!-- Lista de tarefas -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($tasks)): ?>
    <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-4">✅</div>
        <p class="text-lg font-medium">Nenhuma tarefa encontrada</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-100">
        <?php foreach ($tasks as $task):
            $isOverdue = $task['status'] === 'pending' && strtotime($task['due_date']) < time();
            $prMeta = $priorityMeta[$task['priority']] ?? $priorityMeta['medium'];
        ?>
        <div class="px-5 py-4 flex items-start gap-4 <?= $isOverdue ? 'bg-red-50' : '' ?>">

            <!-- Checkbox de conclusão rápida (AJAX) -->
            <div class="flex-shrink-0 mt-0.5">
                <input type="checkbox"
                       class="task-done-check w-4 h-4 rounded text-indigo-600 cursor-pointer"
                       data-task-id="<?= $task['id'] ?>"
                       data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
                       data-url="<?= APP_URL ?>/tasks/<?= $task['id'] ?>/update"
                       <?= $task['status'] === 'done' ? 'checked' : '' ?>>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <p class="text-sm font-semibold text-gray-800 <?= $task['status'] === 'done' ? 'line-through text-gray-400' : '' ?>">
                        <?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $prMeta['class'] ?>"><?= $prMeta['label'] ?></span>
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $statusColors[$task['status']] ?? '' ?>">
                        <?= $statusLabels[$task['status']] ?? $task['status'] ?>
                    </span>
                    <?php if ($isOverdue): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">⚠️ Atrasada</span>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-400">
                    <?php if ($task['client_name']): ?>
                    <span>👥 <a href="<?= APP_URL ?>/clients/<?= $task['client_id'] ?>" class="hover:text-indigo-600"><?= htmlspecialchars($task['client_name'], ENT_QUOTES, 'UTF-8') ?></a></span>
                    <?php endif; ?>
                    <span>👤 <?= htmlspecialchars($task['assigned_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="<?= $isOverdue ? 'text-red-500 font-semibold' : '' ?>">
                        📅 <?= date('d/m/Y H:i', strtotime($task['due_date'])) ?>
                    </span>
                </div>
            </div>

            <!-- Ações -->
            <div class="flex-shrink-0 flex gap-1">
                <!-- Mudar status via select -->
                <form method="POST" action="<?= APP_URL ?>/tasks/<?= $task['id'] ?>/update">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <select name="status" onchange="this.form.submit()"
                            class="text-xs border border-gray-200 rounded px-1 py-1 bg-white focus:outline-none">
                        <?php foreach ($statusLabels as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $task['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Deletar -->
                <form method="POST" action="<?= APP_URL ?>/tasks/<?= $task['id'] ?>/delete"
                      onsubmit="return confirm('Remover esta tarefa?')">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="text-gray-300 hover:text-red-400 text-sm px-1">✕</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Nova Tarefa -->
<div id="modalNewTask" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <h4 class="text-lg font-bold text-gray-800">✅ Nova Tarefa</h4>
            <button onclick="document.getElementById('modalNewTask').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/tasks/store" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                <input type="text" name="title" required placeholder="O que precisa ser feito?"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prazo <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="due_date" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                    <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="low">🟢 Baixa</option>
                        <option value="medium" selected>🟡 Média</option>
                        <option value="high">🔴 Alta</option>
                    </select>
                </div>
            </div>

            <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                <select name="assigned_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $user['id'] == ($_SESSION['user']['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                <textarea name="description" rows="2" placeholder="Detalhes da tarefa (opcional)..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalNewTask').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-100 transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">
                    Criar Tarefa
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Checkbox de conclusão rápida via AJAX
 * Ao marcar/desmarcar, envia POST para /tasks/{id}/update
 */
document.querySelectorAll('.task-done-check').forEach(function(checkbox) {
    checkbox.addEventListener('change', async function() {
        const newStatus = this.checked ? 'done' : 'pending';
        const url       = this.dataset.url;
        const csrf      = this.dataset.csrf;
        const row       = this.closest('div[class*="px-5"]');

        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `_csrf_token=${encodeURIComponent(csrf)}&status=${newStatus}`,
            });
            // Recarrega a lista para refletir a mudança
            if (resp.ok) window.location.reload();
        } catch(e) {
            alert('Erro ao atualizar tarefa. Tente novamente.');
            this.checked = !this.checked; // reverte
        }
    });
});
</script>
