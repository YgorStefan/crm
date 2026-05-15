<?php
?>

<!-- Alerta compacto: Tarefas Atrasadas (colapsavel + dispensavel por sessao) -->
<?php if (!empty($overdue)): ?>
    <div id="overdueBanner"
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
    <script nonce="<?= CSP_NONCE ?>">
    (function () {
        const banner   = document.getElementById('overdueBanner');
        const toggle   = document.getElementById('overdueToggle');
        const dismiss  = document.getElementById('overdueDismiss');
        const list     = document.getElementById('overdueList');
        const chevron  = document.getElementById('overdueChevron');
        const lblCol   = toggle.querySelector('[data-label-collapsed]');
        const lblExp   = toggle.querySelector('[data-label-expanded]');

        toggle.addEventListener('click', function () {
            const isHidden = list.classList.toggle('hidden');
            chevron.style.transform = isHidden ? '' : 'rotate(180deg)';
            lblCol.classList.toggle('hidden', !isHidden);
            lblExp.classList.toggle('hidden', isHidden);
        });

        dismiss.addEventListener('click', function () {
            banner.style.display = 'none';
        });
    })();
    </script>
<?php endif; ?>

<!-- Calendario FullCalendar -->
<div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4">
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
            <button onclick="document.getElementById('modalTask').style.display='none'"
                class="text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 text-2xl">&times;</button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <input type="hidden" id="task_id" value="">
            <input type="hidden" id="task_csrf" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div id="taskClientLink" style="display:none" class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg px-3 py-2 flex items-center justify-between">
                <span class="text-sm text-indigo-700 dark:text-indigo-300">👥 Cliente: <span id="taskClientName" class="font-medium"></span></span>
                <a id="taskClientUrl" href="#" class="text-xs text-indigo-600 hover:underline font-medium">Ver cadastro ➜</a>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Título <span
                        class="text-red-500">*</span></label>
                <input type="text" id="task_title" name="title" required placeholder="O que precisa ser feito?"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Prazo <span
                            class="text-red-500">*</span></label>
                    <input type="datetime-local" id="task_due_date" name="due_date" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Prioridade</label>
                    <select id="task_priority" name="priority"
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
                    <select id="task_assigned_to" name="assigned_to"
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
                <textarea id="task_description" name="description" rows="2"
                    placeholder="Detalhes da tarefa (opcional)..."
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
            </div>

            <div class="flex justify-between gap-3 pt-2">
                <div class="flex gap-2" id="taskActionBtns" style="display:none!important">
                    <button id="btnDeleteTask" title="Excluir"
                        class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors flex items-center">
                        🗑️
                    </button>
                    <button id="btnToggleDone"
                        class="px-4 py-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-sm transition-colors">
                        Concluída
                    </button>
                </div>
                <div class="flex gap-3 ml-auto">
                    <button type="button" onclick="document.getElementById('modalTask').style.display='none'"
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

<script nonce="<?= CSP_NONCE ?>">
    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl = document.getElementById('fc-calendar');
        const appUrl = '<?= APP_URL ?>';
        let csrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>';
        let selectedDate = null;

        // Estado dos filtros rapidos
        let filterStatus = 'all';
        let filterPriority = 'all';
        // Flag de reentrancia: ev.setProp dispara eventsSet, evita loop infinito
        let _applyingFilters = false;

        function passesFilter(ev) {
            const status = ev.extendedProps.status;
            const priority = ev.extendedProps.priority;
            // Filtro de status
            if (filterStatus === 'pending' && status !== 'pending' && status !== 'in_progress') return false;
            if (filterStatus === 'done' && status !== 'done') return false;
            if (filterStatus === 'overdue') {
                if (status === 'done') return false;
                if (ev.start && ev.start > new Date()) return false;
            }
            // Filtro de prioridade
            if (filterPriority !== 'all' && priority !== filterPriority) return false;
            return true;
        }

        function applyFilters() {
            if (_applyingFilters) return;
            _applyingFilters = true;
            try {
                calendar.getEvents().forEach(function (ev) {
                    const wanted = passesFilter(ev) ? 'auto' : 'none';
                    if (ev.display !== wanted) ev.setProp('display', wanted);
                });
            } finally {
                _applyingFilters = false;
            }
        }

        // Tooltip simples (titulo nativo) com descricao + cliente + prioridade
        function buildTooltip(ev) {
            const lines = [ev.title];
            const props = ev.extendedProps || {};
            if (props.client_name) lines.push('Cliente: ' + props.client_name);
            const prioLabel = { high: 'Alta', medium: 'Média', low: 'Baixa' }[props.priority] || props.priority;
            if (prioLabel) lines.push('Prioridade: ' + prioLabel);
            if (props.status === 'done') lines.push('(concluída)');
            return lines.join('\n');
        }

        // Inicializa calendario FullCalendar em pt-BR com visualizacao mes/semana
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana'
            },
            // Eventos carregados do feed JSON backend
            events: appUrl + '/api/tasks/calendar',
            // Mostra ate 3 eventos por dia; resto vira "+N mais" com popover do FullCalendar
            dayMaxEventRows: 3,
            moreLinkText: function (n) { return '+' + n + ' mais'; },
            // Clicar em data
            dateClick: function (info) {
                handleDateClick(info.dateStr);
            },
            // Clicar no titulo do evento abre modal de edicao
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                openEditTaskModal(info.event.id);
            },
            eventDidMount: function (info) {
                info.el.style.cursor = 'pointer';
                info.el.setAttribute('title', buildTooltip(info.event));
                if (info.event.extendedProps.status === 'done') {
                    info.el.style.textDecoration = 'line-through';
                    info.el.style.opacity = '0.6';
                }
            },
            // Reaplica filtros sempre que eventos sao (re)carregados
            eventsSet: function () { applyFilters(); }
        });
        calendar.render();

        // Wiring dos filtros
        document.querySelectorAll('.fc-filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.fc-filter-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                filterStatus = btn.dataset.filter;
                applyFilters();
            });
        });
        document.getElementById('fcPriorityFilter').addEventListener('change', function (e) {
            filterPriority = e.target.value;
            applyFilters();
        });

        // Verifica se ha eventos no dia clicado
        function handleDateClick(dateStr) {
            selectedDate = dateStr;
            const eventsOnDate = calendar.getEvents().filter(function (e) {
                return e.start && e.start.toISOString().slice(0, 10) === dateStr;
            });

            if (eventsOnDate.length > 0) {
                const list = document.getElementById('conflictEventsList');
                list.innerHTML = '';
                eventsOnDate.forEach(function (ev) {
                    const div = document.createElement('div');
                    div.className = 'text-sm text-gray-700 dark:text-zinc-200 py-1 border-b border-gray-100 dark:border-zinc-800 last:border-0';
                    div.textContent = ev.title;
                    list.appendChild(div);
                });
                document.getElementById('modalDayConflict').style.display = 'flex';
            } else {
                openNewTaskModal(dateStr);
            }
        }

        // Abre modal de criacao com data pre-preenchida
        function openNewTaskModal(dateStr) {
            document.getElementById('modalTaskTitle').textContent = 'Nova Tarefa';
            document.getElementById('task_id').value = '';
            document.getElementById('task_title').value = '';
            document.getElementById('task_due_date').value = dateStr + 'T08:00';
            document.getElementById('task_priority').value = 'medium';
            document.getElementById('task_description').value = '';
            document.getElementById('taskActionBtns').style.display = 'none';
            document.getElementById('modalTask').style.display = 'flex';
        }

        // Abre modal de edicao carregando dados via API
        async function openEditTaskModal(taskId) {
            try {
                const resp = await fetch(appUrl + '/api/tasks/' + taskId);
                if (!resp.ok) return;
                const task = await resp.json();
                document.getElementById('modalTaskTitle').textContent = 'Editar Tarefa';
                document.getElementById('task_id').value = task.id;
                document.getElementById('task_title').value = task.title;
                document.getElementById('task_due_date').value = task.due_date.replace(' ', 'T').slice(0, 16);
                document.getElementById('task_priority').value = task.priority;
                document.getElementById('task_description').value = task.description || '';
                // Preenche responsavel se admin
                const assignedEl = document.getElementById('task_assigned_to');
                if (assignedEl && task.assigned_to) {
                    assignedEl.value = task.assigned_to;
                }
                // Mostra botoes de acao (Excluir / Concluida) apenas em modo edicao
                document.getElementById('taskActionBtns').style.display = 'flex';
                // Ajusta label do botao Concluida conforme status atual
                const btnToggle = document.getElementById('btnToggleDone');
                if (task.status === 'done') {
                    btnToggle.textContent = 'Reabrir';
                    btnToggle.dataset.nextStatus = 'pending';
                } else {
                    btnToggle.textContent = 'Concluída';
                    btnToggle.dataset.nextStatus = 'done';
                }
                document.getElementById('modalTask').style.display = 'flex';
                // Mostra link do cliente se a tarefa estiver vinculada
                const clientLinkEl = document.getElementById('taskClientLink');
                const clientNameEl = document.getElementById('taskClientName');
                const clientUrlEl  = document.getElementById('taskClientUrl');
                if (task.client_id) {
                    clientNameEl.textContent = task.client_name || 'Cliente #' + task.client_id;
                    clientUrlEl.href = appUrl + '/clients/' + task.client_id;
                    clientLinkEl.style.display = 'flex';
                } else {
                    clientLinkEl.style.display = 'none';
                }
            } catch (e) {
                console.error('Erro ao carregar tarefa:', e);
            }
        }

        // Salva tarefa (criacao ou edicao) via AJAX
        document.getElementById('btnSaveTask').addEventListener('click', async function (e) {
            e.preventDefault();
            const taskId = document.getElementById('task_id').value;
            const url = taskId
                ? appUrl + '/tasks/' + taskId + '/update'
                : appUrl + '/tasks/store';

            const body = new URLSearchParams({
                _csrf_token: csrfToken,
                title: document.getElementById('task_title').value,
                due_date: document.getElementById('task_due_date').value,
                priority: document.getElementById('task_priority').value,
                description: document.getElementById('task_description').value,
            });

            // Adiciona assigned_to se presente (apenas admin)
            const assignedEl = document.getElementById('task_assigned_to');
            if (assignedEl) body.append('assigned_to', assignedEl.value);

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                if (resp.ok) {
                    const data = await resp.json();
                    if (data && data.csrf_token) csrfToken = data.csrf_token;
                    document.getElementById('modalTask').style.display = 'none';
                    calendar.refetchEvents();
                } else {
                    alert('Erro ao salvar tarefa. Verifique os campos e tente novamente.');
                }
            } catch (e) {
                alert('Erro ao salvar tarefa.');
            }
        });

        // Botoes do dialog de conflito
        document.getElementById('btnConflictView').addEventListener('click', function () {
            document.getElementById('modalDayConflict').style.display = 'none';
            calendar.changeView('timeGridWeek', selectedDate);
        });
        document.getElementById('btnConflictCreate').addEventListener('click', function () {
            document.getElementById('modalDayConflict').style.display = 'none';
            openNewTaskModal(selectedDate);
        });

        // Fecha modais ao clicar no backdrop
        ['modalTask', 'modalDayConflict'].forEach(function (id) {
            document.getElementById(id).addEventListener('click', function (e) {
                if (e.target === this) this.style.display = 'none';
            });
        });

        // Exclui tarefa via AJAX
        document.getElementById('btnDeleteTask').addEventListener('click', async function () {
            const taskId = document.getElementById('task_id').value;
            if (!taskId) return;
            if (!window.confirm('Excluir esta tarefa permanentemente?')) return;

            try {
                const body = new URLSearchParams({ _csrf_token: csrfToken });
                const resp = await fetch(appUrl + '/tasks/' + taskId + '/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                const data = await resp.json();
                if (data.csrf_token) csrfToken = data.csrf_token;
                if (data.success) {
                    const ev = calendar.getEventById(taskId);
                    if (ev) ev.remove();
                    document.getElementById('modalTask').style.display = 'none';
                } else {
                    alert('Erro ao excluir tarefa.');
                }
            } catch (e) {
                alert('Erro de rede ao excluir tarefa.');
            }
        });

        // Alterna status da tarefa entre done e pending
        document.getElementById('btnToggleDone').addEventListener('click', async function () {
            const taskId = document.getElementById('task_id').value;
            if (!taskId) return;
            const nextStatus = this.dataset.nextStatus || 'done';

            try {
                const body = new URLSearchParams({
                    _csrf_token: csrfToken,
                    status: nextStatus
                });
                const resp = await fetch(appUrl + '/tasks/' + taskId + '/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                const data = await resp.json();
                if (data.csrf_token) csrfToken = data.csrf_token;
                if (data.success) {
                    document.getElementById('modalTask').style.display = 'none';
                    calendar.refetchEvents();
                } else {
                    alert('Erro ao atualizar tarefa.');
                }
            } catch (e) {
                alert('Erro de rede ao atualizar tarefa.');
            }
        });

        // Expoe calendario
        window.__calendar = calendar;
    });
</script>