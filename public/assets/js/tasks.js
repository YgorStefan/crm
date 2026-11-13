(function () {
    'use strict';

    // dia no fuso do navegador: o toISOString e utc, e em -03:00 ja e o dia seguinte depois das 21h.
    const pad = n => String(n).padStart(2, '0');
    const localDate = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const localDateTime = d => localDate(d) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    const addDays = (ymd, days) => {
        const [y, m, d] = ymd.split('-').map(Number);
        return localDate(new Date(y, m - 1, d + days));
    };

    const storage = {
        get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (e) {} },
    };

    class OverdueBanner {
        static #KEY = 'crm_overdue_dismissed_' + localDate(new Date());

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
        static #GOOGLE_NOTICES = new Map([
            ['degraded',  'O Google Agenda não respondeu agora. As tarefas do CRM estão todas aqui, e os eventos do Google voltam na próxima atualização.'],
            ['reconnect', 'A conexão com o Google Agenda expirou. Reconecte para voltar a ver seus eventos e enviar as tarefas para a agenda.'],
        ]);

        #calendarEl;
        #appUrl;
        #userRole;
        #userId;
        #calendar;
        #filterStatus  = 'all';
        #filterPriority = 'all';
        #applyingFilters = false;
        #selectedDate  = null;
        #teamMode = false;
        #googleBarState = null;
        #googleEvent = null;

        constructor(el) {
            this.#calendarEl = el.querySelector('#fc-calendar');
            this.#appUrl     = document.querySelector('meta[name="app-url"]').content;
            this.#userRole   = document.querySelector('meta[name="user-role"]').content;
            this.#userId     = parseInt(document.querySelector('meta[name="user-id"]').content, 10);

            this.#wireGoogleBar(el);
            this.#initCalendar();
            this.#wireFilters();
            this.#wireModalButtons();
            this.#wireGoogleEventModal();
        }

        #passesFilter(ev) {
            const status   = ev.extendedProps.status;
            const priority = ev.extendedProps.priority;
            // evento do google nao tem status nem prioridade, entao qualquer filtro ativo o esconde.
            if (ev.extendedProps.source === 'google') {
                return this.#filterStatus === 'all' && this.#filterPriority === 'all';
            }
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
            if (props.source === 'google') {
                lines.push('Evento do Google Agenda');
                return lines.join('\n');
            }
            if (props.assigned_name) lines.push('Responsável: ' + props.assigned_name);
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
                    right:  'dayGridMonth,timeGridWeek,timeGridDay',
                },
                buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia' },
                // o corpo fica entre chaves para nao devolver a promise: o fullcalendar tambem
                // aceita promise como retorno, e a doc nao diz o que ele faz com as duas formas juntas.
                events:          (info, ok, fail) => { this.#fetchEvents(info).then(ok, fail); },
                dayMaxEventRows: 3,
                moreLinkText:    n => '+' + n + ' mais',
                dateClick:       info => this.handleDateClick(info.dateStr),
                eventClick:      info => {
                    info.jsEvent.preventDefault();
                    if (info.event.extendedProps.source === 'google') {
                        this.#openGoogleEventModal(info.event);
                    } else {
                        this.openEditTaskModal(info.event.id);
                    }
                },
                eventClassNames: arg => (arg.event.extendedProps.source === 'google' ? ['crm-google-event'] : []),
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

        async #fetchEvents(info) {
            // sem a janela o feed carrega todas as tarefas do tenant.
            const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
            if (this.#teamMode) params.set('team', '1');
            const resp = await fetch(this.#appUrl + '/api/tasks/calendar?' + params.toString(), {
                headers: { Accept: 'application/json' },
            });
            // sessao caida chega como 200 com a tela de login, porque o fetch segue o
            // redirect, entao so uma lista em json conta como resposta do feed.
            if (!resp.ok) throw new Error('O calendário respondeu ' + resp.status + '.');
            const events = await resp.json();
            if (!Array.isArray(events)) throw new Error('O calendário não devolveu uma lista de eventos.');
            this.#showGoogleStatus(resp.headers.get('X-CRM-Google-Status'));
            return events;
        }

        #showGoogleStatus(status) {
            const notice = document.getElementById('googleFeedNotice');
            if (!notice) return;
            const text = TaskCalendarManager.#GOOGLE_NOTICES.get(status);
            // conta que ja abriu revogada tem o reconectar na barra, e o aviso repetiria o mesmo pedido.
            const hide = !text || (status === 'reconnect' && this.#googleBarState === 'revoked');
            notice.classList.toggle('hidden', hide);
            if (hide) return;

            notice.querySelector('[data-google-feed-text]').textContent = text;
            const link = notice.querySelector('[data-google-feed-reconnect]');
            const reconnect = status === 'reconnect';
            link.classList.toggle('hidden', !reconnect);
            if (reconnect) link.href = this.#appUrl + '/google/connect';
        }

        #wireGoogleBar(el) {
            const bar = el.querySelector('[data-google-bar]');
            this.#googleBarState = bar ? bar.dataset.googleBar : null;

            const hint = el.querySelector('[data-google-hint]');
            if (hint) {
                const key = 'crm_google_hint_dismissed_' + this.#userId;
                if (storage.get(key) !== '1') {
                    hint.classList.remove('hidden');
                    hint.querySelector('[data-google-hint-dismiss]').addEventListener('click', () => {
                        storage.set(key, '1');
                        hint.classList.add('hidden');
                    });
                }
            }

            el.querySelector('[data-google-disconnect]')?.addEventListener('click', () => this.#disconnectGoogle());

            const team = el.querySelector('[data-google-team]');
            team?.addEventListener('click', () => {
                this.#teamMode = !this.#teamMode;
                team.setAttribute('aria-pressed', String(this.#teamMode));
                this.#calendar.refetchEvents();
            });
        }

        async #disconnectGoogle() {
            if (!window.confirm('Desconectar o Google Agenda? Os eventos já enviados continuam na sua agenda do Google.')) return;
            try {
                const { ok, data } = await CRM.api.post(this.#appUrl + '/google/disconnect');
                if (ok && data?.success) {
                    window.location.reload();
                } else {
                    alert(data?.error || 'Não foi possível desconectar o Google Agenda.');
                }
            } catch (e) {
                alert('Erro de rede ao desconectar o Google Agenda.');
            }
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

            ['modalTask', 'modalDayConflict', 'modalGoogleEvent'].forEach(id => {
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
            document.getElementById('task_recurrenceBtn').addEventListener('click', () => {
                document.getElementById('task_recurrenceMenu').classList.toggle('hidden');
            });
            document.querySelectorAll('.task-recurrence-opt').forEach(opt => {
                opt.addEventListener('click', () => {
                    document.getElementById('task_recurrence_type').value = opt.dataset.value;
                    document.getElementById('task_recurrenceBtnLabel').textContent = opt.dataset.label;
                    document.getElementById('task_recurrenceMenu').classList.add('hidden');
                });
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('#task_recurrenceBtn') && !e.target.closest('#task_recurrenceMenu')) {
                    document.getElementById('task_recurrenceMenu').classList.add('hidden');
                }
            });
        }

        #wireGoogleEventModal() {
            document.getElementById('btnSaveGoogleEvent').addEventListener('click', () => this.#saveGoogleEvent());
            document.getElementById('btnDeleteGoogleEvent').addEventListener('click', () => this.#deleteGoogleEvent());
            document.getElementById('googleEvent_all_day').addEventListener('change', e => this.#switchGoogleAllDay(e.target.checked));
        }

        #googleFields() {
            return {
                title:       document.getElementById('googleEvent_title'),
                allDay:      document.getElementById('googleEvent_all_day'),
                start:       document.getElementById('googleEvent_start'),
                end:         document.getElementById('googleEvent_end'),
                description: document.getElementById('googleEvent_description'),
            };
        }

        // o fim de evento de dia inteiro e exclusivo no google e no fullcalendar, entao o
        // campo mostra o ultimo dia e o envio soma um dia de volta.
        static #googleDates(ev, allDay) {
            if (allDay) {
                const start = localDate(ev.start);
                return { start, end: ev.end ? addDays(localDate(ev.end), -1) : start };
            }
            const start = localDateTime(ev.start);
            return { start, end: ev.end ? localDateTime(ev.end) : start };
        }

        static #setGoogleDates(fields, allDay, start, end) {
            // o tipo muda antes do valor, porque o navegador descarta o valor que nao cabe no tipo.
            const type = allDay ? 'date' : 'datetime-local';
            fields.start.type = type;
            fields.end.type = type;
            fields.start.value = start;
            fields.end.value = end;
        }

        #openGoogleEventModal(ev) {
            const props = ev.extendedProps;
            const allDay = typeof props.all_day === 'boolean' ? props.all_day : ev.allDay;
            const canEdit = props.can_edit === true;
            const dates = TaskCalendarManager.#googleDates(ev, allDay);
            this.#googleEvent = {
                googleId:  String(props.google_id || ''),
                calendarId: ev.id,
                recurring: props.recurring === true,
                initial:   { title: ev.title, description: props.description || '', allDay, ...dates },
            };

            const f = this.#googleFields();
            f.title.value = ev.title;
            f.description.value = props.description || '';
            f.allDay.checked = allDay;
            TaskCalendarManager.#setGoogleDates(f, allDay, dates.start, dates.end);
            [f.title, f.start, f.end, f.description].forEach(el => { el.readOnly = !canEdit; });
            f.allDay.disabled = !canEdit;

            document.getElementById('googleEventRecurring').classList.toggle('hidden', !this.#googleEvent.recurring);
            document.getElementById('googleEventReadOnly').classList.toggle('hidden', canEdit);
            const save = document.getElementById('btnSaveGoogleEvent');
            const remove = document.getElementById('btnDeleteGoogleEvent');
            save.style.display = canEdit ? '' : 'none';
            remove.style.display = canEdit ? '' : 'none';
            save.disabled = false;
            remove.disabled = false;
            document.getElementById('btnCloseGoogleEvent').textContent = canEdit ? 'Cancelar' : 'Fechar';

            const link = document.getElementById('googleEventLink');
            const href = String(props.html_link || '');
            if (href.startsWith('https://')) {
                link.href = href;
                link.style.display = '';
            } else {
                link.removeAttribute('href');
                link.style.display = 'none';
            }

            this.#showGoogleError('');
            document.getElementById('modalGoogleEvent').style.display = 'flex';
        }

        #switchGoogleAllDay(allDay) {
            const f = this.#googleFields();
            const initial = this.#googleEvent.initial;
            const startDate = f.start.value.slice(0, 10);
            const endDate = f.end.value.slice(0, 10);
            if (allDay) {
                TaskCalendarManager.#setGoogleDates(f, true, startDate, endDate);
                return;
            }
            // de volta ao horario, cada data recebe a hora que o evento tinha, ou um horario de trabalho.
            const startTime = initial.allDay ? '09:00' : initial.start.slice(11);
            const endTime = initial.allDay ? '10:00' : initial.end.slice(11);
            TaskCalendarManager.#setGoogleDates(f, false, startDate + 'T' + startTime, endDate + 'T' + endTime);
        }

        #showGoogleError(message) {
            const el = document.getElementById('googleEventError');
            el.textContent = message;
            el.classList.toggle('hidden', message === '');
        }

        #closeGoogleEventModal() {
            document.getElementById('modalGoogleEvent').style.display = 'none';
        }

        #googleEventUrl(target, action) {
            return this.#appUrl + '/api/google/events/' + encodeURIComponent(target.googleId) + '/' + action;
        }

        // o modal pode ser fechado e aberto em outro evento enquanto o pedido esta em voo,
        // entao a resposta so mexe no modal se ele ainda mostra o evento do pedido.
        #isCurrentGoogleEvent(target) {
            return this.#googleEvent?.calendarId === target.calendarId;
        }

        async #saveGoogleEvent() {
            const target = this.#googleEvent;
            const f = this.#googleFields();
            const initial = target.initial;
            const current = {
                title:       f.title.value,
                description: f.description.value,
                allDay:      f.allDay.checked,
                start:       f.start.value,
                end:         f.end.value,
            };
            if (!current.start || !current.end) {
                this.#showGoogleError('Preencha o início e o fim.');
                return;
            }
            // os dois campos tem o mesmo formato, entao a ordem do texto e a ordem das datas.
            if (current.end < current.start) {
                this.#showGoogleError('O fim não pode ser antes do início.');
                return;
            }

            const body = {};
            const allDayChanged = current.allDay !== initial.allDay;
            if (current.title !== initial.title) body.title = current.title;
            if (current.description !== initial.description) body.description = current.description;
            if (allDayChanged) body.all_day = current.allDay ? '1' : '0';
            if (allDayChanged || current.start !== initial.start) body.start = current.start;
            if (allDayChanged || current.end !== initial.end) {
                body.end = current.allDay ? addDays(current.end, 1) : current.end;
            }
            if (Object.keys(body).length === 0) {
                this.#closeGoogleEventModal();
                return;
            }

            const btn = document.getElementById('btnSaveGoogleEvent');
            btn.disabled = true;
            try {
                const { ok, data } = await CRM.api.post(this.#googleEventUrl(target, 'update'), body);
                if (ok && data?.success) {
                    if (this.#isCurrentGoogleEvent(target)) this.#closeGoogleEventModal();
                    this.#calendar.refetchEvents();
                } else if (this.#isCurrentGoogleEvent(target)) {
                    this.#showGoogleError(data?.error || 'Não foi possível salvar o evento no Google Agenda.');
                }
            } catch (e) {
                if (this.#isCurrentGoogleEvent(target)) this.#showGoogleError('Erro de rede ao salvar o evento no Google Agenda.');
            } finally {
                if (this.#googleEvent === target) btn.disabled = false;
            }
        }

        async #deleteGoogleEvent() {
            const target = this.#googleEvent;
            const question = target.recurring
                ? 'Excluir esta ocorrência do Google Agenda? As outras datas da série continuam.'
                : 'Excluir este evento do Google Agenda?';
            if (!window.confirm(question)) return;
            const btn = document.getElementById('btnDeleteGoogleEvent');
            btn.disabled = true;
            try {
                const { ok, data } = await CRM.api.post(this.#googleEventUrl(target, 'delete'));
                if (ok && data?.success) {
                    this.#calendar.getEventById(target.calendarId)?.remove();
                    if (this.#isCurrentGoogleEvent(target)) this.#closeGoogleEventModal();
                } else if (this.#isCurrentGoogleEvent(target)) {
                    this.#showGoogleError(data?.error || 'Não foi possível excluir o evento do Google Agenda.');
                }
            } catch (e) {
                if (this.#isCurrentGoogleEvent(target)) this.#showGoogleError('Erro de rede ao excluir o evento do Google Agenda.');
            } finally {
                if (this.#googleEvent === target) btn.disabled = false;
            }
        }

        handleDateClick(dateStr) {
            this.#selectedDate = dateStr;
            // o dateStr do dia clicado e a data local, entao o evento entra pela data local dele.
            const eventsOnDate = this.#calendar.getEvents().filter(e =>
                e.start && localDate(e.start) === dateStr
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
            const assignedEl = document.getElementById('task_assigned_to');
            if (assignedEl) {
                for (const opt of assignedEl.options) opt.selected = opt.defaultSelected;
                window.CRMSelect?.sync?.(assignedEl);
            }
            document.getElementById('taskActionBtns').style.display = 'none';
            document.getElementById('btnSaveTask').style.display = this.#userRole === 'viewer' ? 'none' : '';
            document.getElementById('taskClientLink').style.display = 'none';
            document.getElementById('taskRecurrenceRow').style.display = '';
            document.getElementById('task_recur_enabled').checked = false;
            document.getElementById('task_recur_select_wrap').classList.add('hidden');
            document.getElementById('task_recurrence_type').value = 'weekly';
            document.getElementById('task_recurrenceBtnLabel').textContent = 'Semanal';
            document.getElementById('task_recurrenceMenu').classList.add('hidden');
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
                // o fetch segue redirect, entao sessao caida e tarefa sumida chegam como
                // 200 com html: so o success do json prova que o servidor gravou.
                const { ok, data } = await CRM.api.post(url, body);
                if (ok && data?.success) {
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert(data?.error || 'Erro ao salvar tarefa. Verifique os campos e tente novamente.');
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
                const { ok, data } = await CRM.api.post(this.#appUrl + '/tasks/' + taskId + '/delete');
                if (ok && data?.success) {
                    const ev = this.#calendar.getEventById(taskId);
                    if (ev) ev.remove();
                    document.getElementById('modalTask').style.display = 'none';
                } else {
                    alert(data?.error || 'Erro ao excluir tarefa.');
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
                const { ok, data } = await CRM.api.post(this.#appUrl + '/tasks/' + taskId + '/update', { status: nextStatus });
                if (ok && data?.success) {
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert(data?.error || 'Erro ao atualizar tarefa.');
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
                const { ok, data } = await CRM.api.post(this.#appUrl + '/tasks/' + taskId + '/cancel-recurrence');
                if (ok && data?.success) {
                    document.getElementById('modalTask').style.display = 'none';
                    this.#calendar.refetchEvents();
                } else {
                    alert(data?.error || 'Erro ao cancelar série.');
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
