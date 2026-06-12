<?php
/**
 * Campos compartilhados da modal de tarefa: título, prazo, prioridade,
 * responsável (somente admin), descrição e recorrência.
 *
 * Uso (o chamador mantém o chrome da modal — wrapper, header e footer):
 *   <?php $p = 'task'; require VIEW_PATH . '/components/task-modal-fields.php'; ?>
 *
 * Variáveis esperadas no escopo:
 *   $p                 string  Prefixo de IDs/classes — 'task', 'newTask', 'qt'
 *   $users             array   Usuários ativos (necessário se $showAssigned)
 *   $showAssigned      bool    Exibe select de responsável p/ admin (default true)
 *   $showDescription   bool    Exibe textarea de descrição (default true)
 *   $recurrenceDefault string  Valor inicial do tipo de recorrência (default 'weekly')
 *
 * IDs gerados: {$p}_title, {$p}_due_date, {$p}_priority, {$p}_assigned_to,
 * {$p}_description, {$p}RecurrenceRow, {$p}_recur_enabled, {$p}_recur_select_wrap,
 * {$p}_recurrence_type, {$p}_recurrenceBtn, {$p}_recurrenceBtnLabel,
 * {$p}_recurrenceMenu e classe {$p}-recurrence-opt — o JS de cada tela
 * referencia esses nomes.
 */
$showAssigned      = $showAssigned      ?? true;
$showDescription   = $showDescription   ?? true;
$recurrenceDefault = $recurrenceDefault ?? 'weekly';
?>
<div>
    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Título <span
            class="text-red-500">*</span></label>
    <input type="text" id="<?= $p ?>_title" name="title" required placeholder="O que precisa ser feito?"
        class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
</div>

<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Prazo <span
                class="text-red-500">*</span></label>
        <input type="datetime-local" id="<?= $p ?>_due_date" name="due_date" required
            class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Prioridade</label>
        <select id="<?= $p ?>_priority" name="priority"
            class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <option value="low">Baixa</option>
            <option value="medium" selected>Média</option>
            <option value="high">Alta</option>
        </select>
    </div>
</div>

<?php if ($showAssigned && (($_SESSION['user']['role'] ?? '') === 'admin')): ?>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Responsável</label>
        <select id="<?= $p ?>_assigned_to" name="assigned_to"
            class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $user['id'] == ($_SESSION['user']['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>

<?php if ($showDescription): ?>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Descrição</label>
        <textarea id="<?= $p ?>_description" name="description" rows="2"
            placeholder="Detalhes da tarefa (opcional)..."
            class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
    </div>
<?php endif; ?>

<div id="<?= $p ?>RecurrenceRow">
    <label class="flex items-center gap-2 cursor-pointer select-none">
        <input type="checkbox" id="<?= $p ?>_recur_enabled"
            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
        <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Repetir tarefa</span>
    </label>
    <div id="<?= $p ?>_recur_select_wrap" class="mt-2 hidden">
        <input type="hidden" id="<?= $p ?>_recurrence_type" name="recurrence_type" value="<?= $recurrenceDefault ?>">
        <div class="relative">
            <button type="button" id="<?= $p ?>_recurrenceBtn"
                class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none text-left flex items-center justify-between">
                <span id="<?= $p ?>_recurrenceBtnLabel">Semanal</span>
                <span class="text-gray-400 text-xs">▲</span>
            </button>
            <div id="<?= $p ?>_recurrenceMenu"
                class="hidden absolute bottom-full left-0 w-full mb-1 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-700 rounded-lg shadow-lg overflow-hidden z-10">
                <button type="button" data-value="weekly" data-label="Semanal"
                    class="<?= $p ?>-recurrence-opt w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-white hover:bg-indigo-50 dark:hover:bg-zinc-700">Semanal</button>
                <button type="button" data-value="monthly" data-label="Mensal"
                    class="<?= $p ?>-recurrence-opt w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-white hover:bg-indigo-50 dark:hover:bg-zinc-700">Mensal</button>
                <button type="button" data-value="yearly" data-label="Anual"
                    class="<?= $p ?>-recurrence-opt w-full px-3 py-2 text-sm text-left text-gray-700 dark:text-white hover:bg-indigo-50 dark:hover:bg-zinc-700">Anual</button>
            </div>
        </div>
    </div>
</div>
