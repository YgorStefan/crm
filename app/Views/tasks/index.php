<?php
?>

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

<!-- Calendario FullCalendar -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
    <div id="fc-calendar"></div>
</div>

<!-- Modal: Criacao e Edicao de Tarefa -->
<div id="modalTask" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <h4 id="modalTaskTitle" class="text-lg font-bold text-gray-800">Nova Tarefa</h4>
            <button onclick="document.getElementById('modalTask').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <input type="hidden" id="task_id" value="">
            <input type="hidden" id="task_csrf" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titulo <span
                        class="text-red-500">*</span></label>
                <input type="text" id="task_title" name="title" required placeholder="O que precisa ser feito?"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prazo <span
                            class="text-red-500">*</span></label>
                    <input type="datetime-local" id="task_due_date" name="due_date" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                    <select id="task_priority" name="priority"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="low">Baixa</option>
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                    </select>
                </div>
            </div>

            <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Responsavel</label>
                    <select id="task_assigned_to" name="assigned_to"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user['id'] == ($_SESSION['user']['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descricao</label>
                <textarea id="task_description" name="description" rows="2"
                    placeholder="Detalhes da tarefa (opcional)..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalTask').classList.add('hidden')"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-100 transition-colors">
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

<!-- Modal: Conflito de dia -->
<div id="modalDayConflict"
    class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100">
            <h4 class="text-lg font-bold text-gray-800">Ja existem eventos neste dia:</h4>
        </div>
        <div class="px-6 py-4">
            <div id="conflictEventsList" class="space-y-1 mb-4 max-h-40 overflow-y-auto"></div>
            <div class="flex gap-3">
                <button id="btnConflictView"
                    class="flex-1 px-4 py-2 border border-indigo-300 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl = document.getElementById('fc-calendar');
        const appUrl = '<?= APP_URL ?>';
        const csrfToken = '<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>';
        let selectedDate = null;

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
                month: 'Mes',
                week: 'Semana'
            },
            // Eventos carregados do feed JSON backend
            events: appUrl + '/api/tasks/calendar',
            // Clicar em data
            dateClick: function (info) {
                handleDateClick(info.dateStr);
            },
            // Clicar no titulo do evento abre modal de edicao
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                openEditTaskModal(info.event.id);
            }
        });
        calendar.render();

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
                    div.className = 'text-sm text-gray-700 py-1 border-b border-gray-100 last:border-0';
                    div.textContent = ev.title;
                    list.appendChild(div);
                });
                document.getElementById('modalDayConflict').classList.remove('hidden');
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
            document.getElementById('modalTask').classList.remove('hidden');
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
                document.getElementById('modalTask').classList.remove('hidden');
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
                    document.getElementById('modalTask').classList.add('hidden');
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
            document.getElementById('modalDayConflict').classList.add('hidden');
            calendar.changeView('timeGridWeek', selectedDate);
        });
        document.getElementById('btnConflictCreate').addEventListener('click', function () {
            document.getElementById('modalDayConflict').classList.add('hidden');
            openNewTaskModal(selectedDate);
        });

        // Fecha modais ao clicar no backdrop
        ['modalTask', 'modalDayConflict'].forEach(function (id) {
            document.getElementById(id).addEventListener('click', function (e) {
                if (e.target === this) this.classList.add('hidden');
            });
        });

        // Expoe calendario
        window.__calendar = calendar;
    });
</script>