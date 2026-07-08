<?php
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts = '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/tasks.js?v=' . $_jsV('tasks.js') . '"></script>';
unset($_jsV);
?>

<!-- Alerta compacto: Tarefas Atrasadas (colapsavel + dispensavel por sessao) -->
<?php if (!empty($overdue)): ?>
    <div id="overdueBanner" data-crm-widget="overdue-banner"
         class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 rounded-lg mb-4">
        <div class="flex items-center gap-2 px-3 py-2">
            <span class="text-red-500 flex-shrink-0">⚠️</span>
            <span class="text-sm font-medium text-red-700 dark:text-red-300 flex-1">
                <?= count($overdue) ?> tarefa(s) atrasada(s)
            </span>
            <button type="button" id="overdueToggle"
                class="inline-flex items-center gap-1 text-xs font-medium text-red-700 hover:text-red-900 dark:text-red-300 dark:hover:text-red-200 px-2 py-1 rounded-md hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors">
                <span data-label-collapsed>Ver lista</span>
                <span data-label-expanded class="hidden">Ocultar</span>
                <span id="overdueChevron" class="transition-transform inline-block">▼</span>
            </button>
            <button type="button" id="overdueDismiss"
                data-tooltip="Dispensar (volta no F5)"
                class="has-tooltip has-tooltip-right text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 p-1 rounded-md hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors">
                ✕
            </button>
        </div>
        <div id="overdueList" class="hidden border-t border-red-200 dark:border-red-800/50 px-3 py-2 space-y-1.5 max-h-60 overflow-y-auto">
            <?php foreach ($overdue as $t): ?>
                <div class="flex items-center justify-between bg-white dark:bg-zinc-900 rounded-md px-3 py-1.5 border border-red-100 dark:border-red-800/50">
                    <span class="text-sm font-medium text-red-800 dark:text-red-300 truncate flex-1 min-w-0">
                        <?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($t['client_name'])): ?>
                            <span class="text-xs text-red-400 dark:text-red-500 font-normal ml-1">— <?= htmlspecialchars($t['client_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="text-xs text-red-500 dark:text-red-400 flex-shrink-0 ml-3">Venceu <?= date('d/m/Y', strtotime($t['due_date'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Calendario FullCalendar -->
<div data-crm-widget="task-calendar"
     class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
    <!-- Filtros rapidos -->
    <div class="flex flex-wrap items-center gap-2 mb-3 pb-3 border-b border-gray-100 dark:border-zinc-800">
        <span class="text-xs font-medium text-gray-500 dark:text-zinc-400 mr-1">Filtrar:</span>
        <div class="inline-flex rounded-lg bg-gray-100 dark:bg-zinc-800 p-0.5" role="tablist">
            <button type="button" class="fc-filter-btn px-3 py-1 text-xs font-medium rounded-md transition-colors active" data-filter="all">Todas</button>
            <button type="button" class="fc-filter-btn px-3 py-1 text-xs font-medium rounded-md transition-colors" data-filter="pending">Pendentes</button>
            <button type="button" class="fc-filter-btn px-3 py-1 text-xs font-medium rounded-md transition-colors" data-filter="done">Concluídas</button>
            <button type="button" class="fc-filter-btn px-3 py-1 text-xs font-medium rounded-md transition-colors" data-filter="overdue">Atrasadas</button>
        </div>
        <select id="fcPriorityFilter" data-no-custom
            class="px-2 py-1 text-xs border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none ml-2">
            <option value="all">Qualquer prioridade</option>
            <option value="high">Alta</option>
            <option value="medium">Média</option>
            <option value="low">Baixa</option>
        </select>
    </div>
    <div id="fc-calendar"></div>
</div>

<style>
.fc-filter-btn {
    color: rgb(75 85 99);
}
.dark .fc-filter-btn {
    color: rgb(203 213 225);
}
.fc-filter-btn.active {
    background: white;
    color: rgb(67 56 202);
    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
}
.dark .fc-filter-btn.active {
    background: rgb(99 102 241);
    color: white;
}
</style>

<!-- Modal: Criacao e Edicao de Tarefa -->
<div id="modalTask" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4" style="display:none">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
            <h4 id="modalTaskTitle" class="text-lg font-bold text-gray-800 dark:text-white">Nova Tarefa</h4>
            <button data-action="close-modal" data-target="modalTask"
                class="text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-2xl">&times;</button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <input type="hidden" id="task_id" value="">

            <div id="taskClientLink" style="display:none" class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg px-3 py-2 flex items-center justify-between">
                <span class="text-sm text-indigo-700 dark:text-indigo-300">👥 Cliente: <span id="taskClientName" class="font-medium"></span></span>
                <a id="taskClientUrl" href="#" class="text-xs text-indigo-600 hover:underline font-medium">Ver cadastro ➜</a>
            </div>

            <?php $p = 'task'; require VIEW_PATH . '/components/task-modal-fields.php'; ?>

            <div class="flex flex-col sm:flex-row sm:justify-between gap-3 pt-2">
                <div class="flex flex-wrap gap-2" id="taskActionBtns" style="display:none!important">
                    <button id="btnDeleteTask" title="Excluir"
                        class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors flex items-center">
                        🗑️
                    </button>
                    <button id="btnCancelRecurrence" title="Cancelar série" style="display:none"
                        class="px-3 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm transition-colors whitespace-nowrap">
                        ↻ Cancelar série
                    </button>
                    <button id="btnToggleDone"
                        class="px-4 py-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-sm transition-colors">
                        Concluída
                    </button>
                </div>
                <div class="flex gap-3 sm:ml-auto">
                    <button type="button" data-action="close-modal" data-target="modalTask"
                        class="px-4 py-2 border border-gray-300 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 rounded-lg text-sm hover:bg-gray-100 transition-colors">
                        Cancelar
                    </button>
                    <button id="btnSaveTask"
                        class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Conflito de dia -->
<div id="modalDayConflict"
    class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4" style="display:none">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800">
            <h4 class="text-lg font-bold text-gray-800 dark:text-white">Já existem eventos neste dia:</h4>
        </div>
        <div class="px-6 py-4">
            <div id="conflictEventsList" class="space-y-1 mb-4 max-h-40 overflow-y-auto"></div>
            <div class="flex gap-3">
                <button id="btnConflictView"
                    class="flex-1 px-4 py-2 border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 rounded-lg text-sm font-medium hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors">
                    Ver existentes
                </button>
                <button id="btnConflictCreate"
                    class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
                    Criar novo
                </button>
            </div>
        </div>
    </div>
</div>

