(function () {
    'use strict';

    class OverdueBanner {
        static #KEY = 'crm_overdue_dismissed_' + new Date().toISOString().slice(0, 10);

        #banner;
        #toggle;
        #dismiss;
        #list;
        #chevron;
        #lblCollapsed;
        #lblExpanded;

        constructor(banner) {
            this.#banner       = banner;
            this.#toggle       = banner.querySelector('#overdueToggle');
            this.#dismiss      = banner.querySelector('#overdueDismiss');
            this.#list         = banner.querySelector('#overdueList');
            this.#chevron      = banner.querySelector('#overdueChevron');
            this.#lblCollapsed = this.#toggle.querySelector('[data-label-collapsed]');
            this.#lblExpanded  = this.#toggle.querySelector('[data-label-expanded]');

            this.#toggle.addEventListener('click', () => this.#onToggle());
            this.#dismiss.addEventListener('click', () => this.#onDismiss());
        }

        #onToggle() {
            const isHidden = this.#list.classList.toggle('hidden');
            this.#chevron.style.transform = isHidden ? '' : 'rotate(180deg)';
            this.#lblCollapsed.classList.toggle('hidden', !isHidden);
            this.#lblExpanded.classList.toggle('hidden', isHidden);
        }

        #onDismiss() {
            try { localStorage.setItem(OverdueBanner.#KEY, '1'); } catch (e) {}
            this.#banner.style.display = 'none';
        }

        static isDismissed() {
            try { return localStorage.getItem(OverdueBanner.#KEY) === '1'; } catch (e) { return false; }
        }

        static init() {
            const banner = document.querySelector('[data-crm-widget="overdue-banner"]');
            if (!banner) return;
            if (OverdueBanner.isDismissed()) { banner.style.display = 'none'; return; }
            window.CRM = window.CRM || {};
            window.CRM.overdueBanner = new OverdueBanner(banner);
        }
    }

    class TaskCalendarManager {
        #calendarEl;
        #appUrl;
        #csrfToken;
        #userRole;
        #userId;
        #calendar;
        #filterStatus  = 'all';
        #filterPriority = 'all';
        #applyingFilters = false;
        #selectedDate  = null;

        constructor(el) {
            this.#calendarEl = el.querySelector('#fc-calendar');
            this.#appUrl     = document.querySelector('meta[name="app-url"]').content;
            this.#csrfToken  = document.querySelector('meta[name="csrf-token"]').content;
            this.#userRole   = document.querySelector('meta[name="user-role"]').content;
            this.#userId     = parseInt(document.querySelector('meta[name="user-id"]').content, 10);

            this.#initCalendar();
            this.#wireFilters();
            this.#wireModalButtons();
        }

        #refreshCsrf(data) {
            if (data.csrf_token) {
                this.#csrfToken = data.csrf_token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = data.csrf_token;
            }
        }

        #passesFilter(ev) {
            const status   = ev.extendedProps.status;
            const priority = ev.extendedProps.priority;
            if (this.#filterStatus === 'pending' && status !== 'pending' && status !== 'in_progress') return false;
            if (this.#filterStatus === 'done'    && status !== 'done') return false;
            if (this.#filterStatus === 'overdue') {
                if (status === 'done') return false;
                if (ev.start && ev.start > new Date()) return false;
            }
            if (this.#filterPriority !== 'all' && priority !== this.#filterPriority) return false;
            return true;
        }

        applyFilters() {
            if (this.#applyingFilters) return;
            this.#applyingFilters = true;
            try {
                this.#calendar.getEvents().forEach(ev => {
                    const wanted = this.#passesFilter(ev) ? 'auto' : 'none';
                    if (ev.display !== wanted) ev.setProp('display', wanted);
                });
            } finally {
                this.#applyingFilters = false;
            }
        }

        buildTooltip(ev) {
            const lines = [ev.title];
            const props = ev.extendedProps || {};
            if (props.client_name) lines.push('Cliente: ' + props.client_name);
            const prioLabel = { high: 'Alta', medium: 'Média', low: 'Baixa' }[props.priority] || props.priority;
            if (prioLabel) lines.push('Prioridade: ' + prioLabel);
            if (props.status === 'done') lines.push('(concluída)');
            return lines.join('\n');
        }

        #initCalendar() {
            this.#calendar = new FullCalendar.Calendar(this.#calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                height: 'auto',
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'dayGridMonth,timeGridWeek',
                },
                buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana' },
                events:          this.#appUrl + '/api/tasks/calendar',
                dayMaxEventRows: 3,
                moreLinkText:    n => '+' + n + ' mais',
                dateClick:       info => this.handleDateClick(info.dateStr),
                eventClick:      info => { info.jsEvent.preventDefault(); this.openEditTaskModal(info.event.id); },
                eventDidMount:   info => {
                    info.el.style.cursor = 'pointer';
                    info.el.setAttribute('title', this.buildTooltip(info.event));
                    if (info.event.extendedProps.status === 'done') {
                        info.el.style.textDecoration = 'line-through';
                        info.el.style.opacity = '0.6';
                    }
                },
                eventsSet: () => this.applyFilters(),
            });
            this.#calendar.render();
        }

        #wireFilters() {
            document.querySelectorAll('.fc-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.fc-filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    this.#filterStatus = btn.dataset.filter;
                    this.applyFilters();
                });
            });
            document.getElementById('fcPriorityFilter').addEventListener('change', e => {
                this.#filterPriority = e.target.value;
                this.applyFilters();
            });
        }

        #wireModalButtons() {
            document.getElementById('btnConflictView').addEventListener('click', () => {
                document.getElementById('modalDayConflict').style.display = 'none';
                this.#calendar.changeView('timeGridWeek', this.#selectedDate);
            });
            document.getElementById('btnConflictCreate').addEventListener('click', () => {
                document.getElementById('modalDayConflict').style.display = 'none';
                this.openNewTaskModal(this.#selectedDate);
            });
            document.getElementById('btnSaveTask').addEventListener('click', e => { e.preventDefault(); this.#saveTask(); });
            document.getElementById('btnDeleteTask').addEventListener('click', () => this.#deleteTask());
            document.getElementById('btnToggleDone').addEventListener('click', () => this.#toggleDone());

            ['modalTask', 'modalDayConflict'].forEach(id => {
                const el = document.getElementById(id);
                el.addEventListener('click', e => { if (e.target === el) el.style.display = 'none'; });
            });
            document.addEventListener('click', e => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;
                if (btn.dataset.action === 'close-modal') {
                    const target = document.getElementById(btn.dataset.target);
                    if (target) target.style.display = 'none';
                }
            });
            document.getElementById('btnCancelRecurrence').addEventListener('click', () => this.#cancelRecurrence());
            document.getElementById('task_recur_enabled').addEventListener('change', e => {
                document.getElementById('task_recur_select_wrap').classList.toggle('hidden', !e.target.checked);
            });
        }

        handleDateClick(dateStr) {
            this.#selectedDate = dateStr;
            const eventsOnDate = this.#calendar.getEvents().filter(e =>
                e.start && e.start.toISOString().slice(0, 10) === dateStr
            );
            if (eventsOnDate.length > 0) {
                const list = document.getElementById('conflictEventsList');
                list.innerHTML = '';
                eventsOnDate.forEach(ev => {
                    const div = document.createElement('div');
                    div.className = 'text-sm text-gray-700 dark:text-zinc-200 py-1 border-b border-gray-100 dark:border-zinc-800 last:border-0';
                    div.textContent = ev.title;
                    list.appendChild(div);
                });
                document.getElementById('modalDayConflict').style.display = 'flex';
            } else {
                this.openNewTaskModal(dateStr);
            }
        }

        openNewTaskModal(dateStr) {
            document.getElementById('modalTaskTitle').textContent = 'Nova Tarefa';
            document.getElementById('task_id').value = '';
            document.getElementById('task_title').value = '';
            document.getElementById('task_due_date').value = dateStr + 'T08:00';
            document.getElementById('task_priority').value = 'medium';
            document.getElementById('task_description').value = '';
            document.getElementById('taskActionBtns').style.display = 'none';
            document.getElementById('btnSaveTask').style.display = this.#userRole === 'viewer' ? 'none' : '';
            document.getElementById('taskClientLink').style.display = 'none';
            document.getElementById('taskRecurrenceRow').style.display = '';
            document.getElementById('task_recur_enabled').checked = false;
            document.getElementById('task_recur_select_wrap').classList.add('hidden');
            document.getElementById('btnCancelRecurrence').style.display = 'none';
            document.getElementById('modalTask').style.display = 'flex';
        }

        async openEditTaskModal(taskId) {
            try {
                const resp = await fetch(this.#appUrl + '/api/tasks/' + taskId);
                if (!resp.ok) return;
                const task = await resp.json();

                document.getElementById('modalTaskTitle').textContent = 'Editar Tarefa';
                document.getElementById('task_id').value = task.id;
                document.getElementById('task_title').value = task.title;
                document.getElementById('task_due_date').value = task.due_date.replace(' ', 'T').slice(0, 16);
                document.getElementById('task_priority').value = task.priority;
                document.getElementById('task_description').value = task.description || '';
                const assignedEl = document.getElementById('task_assigned_to');
                if (assignedEl && task.assigned_to) assignedEl.value = task.assigned_to;

                const canEdit = this.#userRole === 'admin' ||
                    (this.#userRole === 'seller' && (task.assigned_to == this.#userId || task.created_by == this.#userId));
                document.getElementById('taskActionBtns').style.display = canEdit ? 'flex' : 'none';
                document.getElementById('taskRecurrenceRow').style.display = 'none';
                const isRecurring = (task.recurrence_type && task.recurrence_type !== 'none') || !!task.recurrence_parent_id;
                document.getElementById('btnCancelRecurrence').style.display = canEdit && isRecurring ? '' : 'none';
                document.getElementById('btnSaveTask').style.display = canEdit ? '' : 'none';

                const btnToggle = document.getElementById('btnToggleDone');
                if (task.status === 'done') {
                    btnToggle.textContent = 'Reabrir';
                    btnToggle.dataset.nextStatus = 'pending';
                } else {
                    btnToggle.textContent = 'Concluída';
                    btnToggle.dataset.nextStatus = 'done';
                }

                const clientLinkEl = document.getElementById('taskClientLink');
                const clientNameEl = document.getElementById('taskClientName');
                const clientUrlEl  = document.getElementById('taskClientUrl');
                if (task.client_id) {
                    clientNameEl.textContent = task.client_name || 'Cliente #' + task.client_id;
                    clientUrlEl.href = this.#appUrl + '/clients/' + task.client_id;
                    clientLinkEl.style.display = 'flex';
                } else {
                    clientLinkEl.style.display = 'none';
                }
                document.getElementById('modalTask').style.display = 'flex';
            } catch (e) {
                console.error('Erro ao carregar tarefa:', e);
            }
        }

        async #saveTask() {
            const taskId = document.getElementById('task_id').value;
            const url = taskId
                ? this.#appUrl + '/tasks/' + taskId + '/update'
                : this.#appUrl + '/tasks/store';

            const body = new URLSearchParams({
                _csrf_token: this.#csrfToken,
                title:       document.getElementById('task_title').value,
                due_date:    document.getElementById('task_due_date').value,
                priority:    document.getElementById('task_priority').value,
                description: document.getElementById('task_description').value,
            });
            const assignedEl = document.getElementById('task_assigned_to');
            if (assignedEl) body.append('assigned_to', assignedEl.value);
            const recurCheckbox = document.getElementById('task_recur_enabled');
            if (!taskId && recurCheckbox && recurCheckbox.checked) {
                body.append('recurrence_type', document.getElementById('task_recurrence_type').value);
            }

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString(),
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.#refreshCsrf(data);
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert('Erro ao salvar tarefa. Verifique os campos e tente novamente.');
                }
            } catch (e) {
                alert('Erro ao salvar tarefa.');
            }
        }

        async #deleteTask() {
            const taskId = document.getElementById('task_id').value;
            if (!taskId) return;
            if (!window.confirm('Excluir esta tarefa permanentemente?')) return;
            try {
                const body = new URLSearchParams({ _csrf_token: this.#csrfToken });
                const resp = await fetch(this.#appUrl + '/tasks/' + taskId + '/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString(),
                });
                const data = await resp.json();
                this.#refreshCsrf(data);
                if (data.success) {
                    const ev = this.#calendar.getEventById(taskId);
                    if (ev) ev.remove();
                    document.getElementById('modalTask').style.display = 'none';
                } else {
                    alert('Erro ao excluir tarefa.');
                }
            } catch (e) {
                alert('Erro de rede ao excluir tarefa.');
            }
        }

        async #toggleDone() {
            const taskId = document.getElementById('task_id').value;
            if (!taskId) return;
            const btn = document.getElementById('btnToggleDone');
            const nextStatus = btn.dataset.nextStatus || 'done';
            try {
                const body = new URLSearchParams({ _csrf_token: this.#csrfToken, status: nextStatus });
                const resp = await fetch(this.#appUrl + '/tasks/' + taskId + '/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString(),
                });
                const data = await resp.json();
                this.#refreshCsrf(data);
                if (data.success) {
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert('Erro ao atualizar tarefa.');
                }
            } catch (e) {
                alert('Erro de rede ao atualizar tarefa.');
            }
        }

        async #cancelRecurrence() {
            const taskId = document.getElementById('task_id').value;
            if (!taskId) return;
            if (!window.confirm('Cancelar a série? As ocorrências pendentes serão removidas. As já concluídas permanecem no histórico.')) return;
            try {
                const body = new URLSearchParams({ _csrf_token: this.#csrfToken });
                const resp = await fetch(this.#appUrl + '/tasks/' + taskId + '/cancel-recurrence', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body.toString(),
                });
                const data = await resp.json();
                this.#refreshCsrf(data);
                if (data.success) {
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert(data.error || 'Erro ao cancelar série.');
                }
            } catch (e) {
                alert('Erro de rede ao cancelar série.');
            }
        }

        static init() {
            const el = document.querySelector('[data-crm-widget="task-calendar"]');
            if (!el) return;
            window.CRM = window.CRM || {};
            window.CRM.taskCalendar = new TaskCalendarManager(el);
        }
    }

    // Auto-instanciação direta — defer garante DOM pronto (padrão Fase 2)
    OverdueBanner.init();
    TaskCalendarManager.init();
})();
