(function () {
    'use strict';

    class ClientShow {
        #rootEl;
        #clientId;
        #appUrl;

        constructor(rootEl) {
            this.#rootEl   = rootEl;
            this.#clientId = parseInt(rootEl.dataset.clientId, 10);
            this.#appUrl   = (document.querySelector('meta[name="app-url"]')?.content || '').replace(/\/$/, '');
            this.#initInteractions();
            this.#initNotes();
            this.#initSales();
            this.#initPayments();
            this.#initTaskModal();
        }

        #csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        #syncToken(token) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', token);
            document.querySelectorAll('input[name="_csrf_token"]').forEach(el => el.value = token);
        }

        #notify(message, type) {
            window.CRM?.toast?.show(message, type) ?? alert(message);
        }

        #initInteractions() {
            // Encontra todos os containers de interação e usa event delegation por container
            document.querySelectorAll('[data-interaction-id]').forEach(row => {
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

                let origType = editType?.value || '';
                let origDate = editDate?.value || '';
                let origDesc = editDesc?.value || '';

                descEl?.addEventListener('click', () => {
                    origType = editType?.value || '';
                    origDate = editDate?.value || '';
                    origDesc = editDesc?.value || '';
                    if (viewDiv) viewDiv.style.display = 'none';
                    if (editDiv) editDiv.style.display = '';
                    if (deleteBtn) deleteBtn.style.display = 'none';
                    if (errorEl) errorEl.style.display = 'none';
                    editDesc?.focus();
                });

                cancelBtn?.addEventListener('click', () => {
                    if (editType) editType.value = origType;
                    if (editDate) editDate.value = origDate;
                    if (editDesc) editDesc.value = origDesc;
                    if (editDiv) editDiv.style.display = 'none';
                    if (viewDiv) viewDiv.style.display = '';
                    if (deleteBtn) deleteBtn.style.display = '';
                });

                saveBtn?.addEventListener('click', () => {
                    const desc = editDesc?.value.trim() || '';
                    if (!desc) {
                        if (errorEl) { errorEl.textContent = 'Descrição não pode estar vazia.'; errorEl.style.display = ''; }
                        return;
                    }
                    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Salvando...'; }
                    if (errorEl) errorEl.style.display = 'none';

                    fetch(this.#appUrl + '/interactions/' + id + '/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-Token': this.#csrf(),
                        },
                        body: new URLSearchParams({
                            description: desc,
                            type: editType?.value || '',
                            occurred_at: editDate?.value || '',
                        }).toString(),
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.csrf_token) this.#syncToken(data.csrf_token);
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Salvar'; }
                        if (!data.success) {
                            if (errorEl) { errorEl.textContent = 'Erro ao salvar.'; errorEl.style.display = ''; }
                            return;
                        }
                        const typeLabels = { call: 'Ligação', email: 'E-mail', meeting: 'Reunião', whatsapp: 'WhatsApp', note: 'Nota', other: 'Outro' };
                        const newType = editType?.value || '';
                        const userName = typeLabel?.textContent.trim().split(' · ')[1] || '';
                        if (typeLabel) typeLabel.textContent = (typeLabels[newType] || newType) + ' · ' + userName;
                        const dt = editDate?.value || '';
                        if (dt && dt.length >= 16) {
                            const [datePart, timePart] = dt.split('T');
                            const [y, m, d] = datePart.split('-');
                            if (dateLabel) dateLabel.textContent = d + '/' + m + '/' + y + ' ' + timePart;
                        }
                        if (descEl) descEl.textContent = desc;
                        origType = newType; origDate = editDate?.value || ''; origDesc = desc;
                        if (editDiv) editDiv.style.display = 'none';
                        if (viewDiv) viewDiv.style.display = '';
                        if (deleteBtn) deleteBtn.style.display = '';
                    })
                    .catch(() => {
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Salvar'; }
                        if (errorEl) { errorEl.textContent = 'Erro de conexão.'; errorEl.style.display = ''; }
                    });
                });
            });
        }

        #initNotes() {
            const btnEdit   = document.getElementById('btn-edit-notes');
            const notesView = document.getElementById('notes-view');
            const notesEdit = document.getElementById('notes-edit');
            const notesText = document.getElementById('notes-text');
            const textarea  = document.getElementById('notes-textarea');
            const saveBtn   = document.getElementById('notes-save-btn');
            const cancelBtn = document.getElementById('notes-cancel-btn');
            const errorEl   = document.getElementById('notes-save-error');
            const deleteBtn = document.getElementById('btn-delete-notes');

            if (!btnEdit) return;

            let origNotes = textarea?.value || '';

            btnEdit.addEventListener('click', () => {
                origNotes = textarea?.value || '';
                if (notesView) notesView.style.display = 'none';
                if (notesEdit) notesEdit.style.display = '';
                if (errorEl) errorEl.style.display = 'none';
                textarea?.focus();
            });

            cancelBtn?.addEventListener('click', () => {
                if (textarea) textarea.value = origNotes;
                if (notesEdit) notesEdit.style.display = 'none';
                if (notesView) notesView.style.display = '';
            });

            const saveNotes = (notes) => {
                if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Salvando...'; }
                if (errorEl) errorEl.style.display = 'none';
                return fetch(this.#appUrl + '/clients/' + this.#clientId + '/update-notes', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': this.#csrf() },
                    body: new URLSearchParams({ notes }).toString(),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.csrf_token) this.#syncToken(data.csrf_token);
                    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Salvar'; }
                    if (!data.success) {
                        if (errorEl) { errorEl.textContent = 'Erro ao salvar nota.'; errorEl.style.display = ''; }
                        return;
                    }
                    if (notesText) notesText.textContent = notes;
                    origNotes = notes;
                    if (notesEdit) notesEdit.style.display = 'none';
                    if (notesView) notesView.style.display = '';
                })
                .catch(() => {
                    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Salvar'; }
                    if (errorEl) { errorEl.textContent = 'Erro de conexão.'; errorEl.style.display = ''; }
                });
            };

            saveBtn?.addEventListener('click', () => saveNotes(textarea?.value || ''));

            deleteBtn?.addEventListener('click', () => {
                if (!confirm('Excluir a nota deste cliente?')) return;
                deleteBtn.disabled = true;
                saveNotes('').finally(() => { deleteBtn.disabled = false; });
            });
        }

        #initSales() {
            const overlay  = document.getElementById('cota-modal-overlay');
            const btnAdd   = document.getElementById('btn-add-cota');
            const btnCancel = document.getElementById('cota-cancel');
            const btnSave  = document.getElementById('cota-save');
            const cotasList = document.getElementById('cotas-list');
            if (!btnAdd || !cotasList) return; // seção só existe para clientes com venda fechada

            btnAdd.addEventListener('click', () => {
                document.getElementById('cota-grupo').value = '';
                document.getElementById('cota-cota').value = '';
                const sel = document.getElementById('cota-tipo');
                if (sel) { sel.value = ''; sel.dispatchEvent(new Event('change', { bubbles: true })); }
                document.getElementById('cota-credito').value = '';
                if (overlay) overlay.style.display = 'flex';
            });

            overlay?.addEventListener('click', e => { if (e.target === overlay) overlay.style.display = 'none'; });
            btnCancel?.addEventListener('click', () => { if (overlay) overlay.style.display = 'none'; });

            btnSave?.addEventListener('click', () => {
                const tipo = document.getElementById('cota-tipo')?.value;
                if (!tipo) { alert('Selecione o Tipo de consórcio.'); return; }

                const creditoRaw = (document.getElementById('cota-credito')?.value || '').replace(/\D/g, '');
                const credito = creditoRaw ? (parseInt(creditoRaw, 10) / 100).toFixed(2) : '0.00';

                fetch(this.#appUrl + '/clients/' + this.#clientId + '/sales', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        _csrf_token: this.#csrf(),
                        grupo: document.getElementById('cota-grupo')?.value.trim() || '',
                        cota:  document.getElementById('cota-cota')?.value.trim() || '',
                        tipo,
                        credito_contratado: credito,
                    }).toString(),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.csrf_token) this.#syncToken(data.csrf_token);
                    if (!data.success) { alert('Erro ao salvar cota.'); return; }
                    document.getElementById('cotas-empty')?.remove();
                    const s = data.sale;
                    const creditoFmt = parseFloat(s.credito_contratado || 0)
                        .toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const div = document.createElement('div');
                    div.className = 'px-5 py-4 flex items-start justify-between gap-3';
                    div.dataset.saleId = s.id;

                    const grid = document.createElement('div');
                    grid.className = 'grid grid-cols-2 gap-x-6 gap-y-1 text-sm flex-1';

                    const cell = (label, val, valCls) => {
                        const d = document.createElement('div');
                        const lbl = document.createElement('span');
                        lbl.className = 'text-gray-400 text-xs';
                        lbl.textContent = label + ': ';
                        const v = document.createElement('span');
                        v.className = valCls || 'text-gray-700 font-medium';
                        v.textContent = val;
                        d.appendChild(lbl);
                        d.appendChild(v);
                        return d;
                    };
                    grid.appendChild(cell('Grupo', s.grupo || '—'));
                    grid.appendChild(cell('Cota', s.cota || '—'));
                    grid.appendChild(cell('Tipo', s.tipo));
                    grid.appendChild(cell('Crédito', 'R$ ' + creditoFmt, 'text-green-700 font-bold'));

                    const btnDel = document.createElement('button');
                    btnDel.type = 'button';
                    btnDel.className = 'btn-del-cota text-gray-300 hover:text-red-400 text-sm flex-shrink-0 mt-1';
                    btnDel.dataset.saleId = s.id;
                    btnDel.title = 'Remover cota';
                    btnDel.textContent = '✕';

                    div.appendChild(grid);
                    div.appendChild(btnDel);
                    cotasList.appendChild(div);
                    if (overlay) overlay.style.display = 'none';
                })
                .catch(() => alert('Erro de conexão ao salvar cota.'));
            });

            // Exclusão via event delegation
            cotasList.addEventListener('click', e => {
                const btn = e.target.closest('.btn-del-cota');
                if (!btn) return;
                if (!confirm('Remover esta cota?')) return;
                const saleId = btn.dataset.saleId;
                fetch(this.#appUrl + '/clients/' + this.#clientId + '/sales/' + saleId + '/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf_token: this.#csrf() }).toString(),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.csrf_token) this.#syncToken(data.csrf_token);
                    if (!data.success) { alert('Erro ao remover cota.'); return; }
                    cotasList.querySelector('[data-sale-id="' + saleId + '"]')?.remove();
                    if (!cotasList.querySelector('[data-sale-id]')) {
                        const p = document.createElement('p');
                        p.id = 'cotas-empty';
                        p.className = 'px-5 py-6 text-sm text-gray-400 text-center';
                        p.textContent = 'Nenhuma cota cadastrada ainda.';
                        cotasList.appendChild(p);
                    }
                })
                .catch(() => alert('Erro de conexão ao remover cota.'));
            });
        }

        #initPayments() {
            const pagamentosList = document.getElementById('pagamentos-list');
            if (!pagamentosList) return;

            pagamentosList.addEventListener('click', e => {
                const btn = e.target.closest('.btn-marcar-pago');
                if (!btn) return;
                btn.disabled = true;
                btn.textContent = 'Salvando...';
                const saleId = btn.dataset.saleId;
                fetch(this.#appUrl + '/clients/' + this.#clientId + '/sales/' + saleId + '/paid', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf_token: this.#csrf() }).toString(),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.csrf_token) this.#syncToken(data.csrf_token);
                    if (!data.success) {
                        btn.disabled = false;
                        btn.textContent = 'Marcar como pago';
                        alert('Erro ao registrar pagamento.');
                        return;
                    }
                    btn.classList.remove('btn-marcar-pago', 'bg-amber-100', 'hover:bg-amber-200', 'text-amber-800');
                    btn.classList.add('bg-green-100', 'text-green-700', 'cursor-default');
                    btn.disabled = true;
                    btn.textContent = '✓ Pago em ' + (data.paid_at_formatted || '—');
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = 'Marcar como pago';
                    alert('Erro de conexão.');
                });
            });
        }

        #initTaskModal() {
            const modal        = document.getElementById('newTaskModal');
            const btnOpen      = document.getElementById('btn-open-new-task');
            const btnClose     = document.getElementById('newTaskClose');
            const btnCancel    = document.getElementById('newTaskCancel');
            const btnSave      = document.getElementById('newTaskSave');
            const inpTitle     = document.getElementById('newTask_title');
            const inpDue       = document.getElementById('newTask_due_date');
            const selPrio      = document.getElementById('newTask_priority');
            const selAssign    = document.getElementById('newTask_assigned_to');
            const inpDesc      = document.getElementById('newTask_description');
            const tasksList    = document.getElementById('tasks-list');
            const recurChk     = document.getElementById('newTask_recur_enabled');
            const recurWrap    = document.getElementById('newTask_recur_select_wrap');
            const recurType    = document.getElementById('newTask_recurrence_type');
            const recurBtn     = document.getElementById('newTask_recurrenceBtn');
            const recurBtnLbl  = document.getElementById('newTask_recurrenceBtnLabel');
            const recurMenu    = document.getElementById('newTask_recurrenceMenu');
            if (!btnOpen) return;

            // Recorrência: toggle dropdown
            recurChk?.addEventListener('change', () => {
                recurWrap?.classList.toggle('hidden', !recurChk.checked);
            });
            recurBtn?.addEventListener('click', () => {
                recurMenu?.classList.toggle('hidden');
            });
            recurMenu?.querySelectorAll('.newTask-recurrence-opt').forEach(opt => {
                opt.addEventListener('click', () => {
                    if (recurType) recurType.value = opt.dataset.value;
                    if (recurBtnLbl) recurBtnLbl.textContent = opt.dataset.label;
                    recurMenu.classList.add('hidden');
                });
            });
            document.addEventListener('click', e => {
                if (recurMenu && !recurMenu.classList.contains('hidden') && !recurBtn?.contains(e.target) && !recurMenu.contains(e.target)) {
                    recurMenu.classList.add('hidden');
                }
            });

            const pad = n => n < 10 ? '0' + n : '' + n;
            const todayAt8 = () => {
                const d = new Date();
                return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T08:00';
            };
            const openModal = () => {
                if (inpTitle)  inpTitle.value  = '';
                if (inpDue)    inpDue.value    = todayAt8();
                if (selPrio)   selPrio.value   = 'medium';
                if (inpDesc)   inpDesc.value   = '';
                if (recurChk)  recurChk.checked = false;
                if (recurWrap) recurWrap.classList.add('hidden');
                if (recurType) recurType.value  = 'weekly';
                if (recurBtnLbl) recurBtnLbl.textContent = 'Semanal';
                if (recurMenu) recurMenu.classList.add('hidden');
                if (modal)     modal.style.display = 'flex';
                setTimeout(() => inpTitle?.focus(), 50);
            };
            const closeModal = () => { if (modal) modal.style.display = 'none'; };

            btnOpen.addEventListener('click', openModal);
            btnClose?.addEventListener('click', closeModal);
            btnCancel?.addEventListener('click', closeModal);
            modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });

            const PRIO_LABEL = { low: 'Baixa', medium: 'Média', high: 'Alta' };
            const PRIO_CLASS = { low: 'text-green-600', medium: 'text-yellow-600', high: 'text-red-600' };

            const appendTaskRow = task => {
                document.getElementById('tasks-empty')?.remove();
                const row = document.createElement('div');
                row.className = 'px-5 py-3 flex items-center justify-between gap-3';
                const left = document.createElement('div');
                const titleP = document.createElement('p');
                titleP.className = 'text-sm font-medium text-gray-700 dark:text-zinc-200';
                titleP.textContent = task.title;
                const meta = document.createElement('p');
                meta.className = 'text-xs text-gray-400 dark:text-zinc-500 mt-0.5';
                const due = task.due_date || '';
                let dueLabel = due;
                if (due.length >= 16) {
                    const [dp, tp] = due.split('T');
                    const [y, m, d] = dp.split('-');
                    dueLabel = d + '/' + m + '/' + y + ' ' + tp;
                }
                const prioSpan = document.createElement('span');
                prioSpan.className = PRIO_CLASS[task.priority] || '';
                prioSpan.textContent = PRIO_LABEL[task.priority] || task.priority;
                meta.appendChild(document.createTextNode('Vence: ' + dueLabel + ' · '));
                meta.appendChild(prioSpan);
                left.appendChild(titleP);
                left.appendChild(meta);
                const status = document.createElement('span');
                status.className = 'text-xs bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300 px-2 py-1 rounded-full flex-shrink-0';
                status.textContent = 'Pendente';
                row.appendChild(left);
                row.appendChild(status);
                tasksList?.appendChild(row);
            };

            btnSave?.addEventListener('click', async () => {
                const title = inpTitle?.value.trim() || '';
                const due   = inpDue?.value || '';
                if (!title || !due) { this.#notify('Título e prazo são obrigatórios.', 'error'); return; }

                const originalLabel = btnSave.textContent;
                btnSave.disabled = true;
                btnSave.textContent = 'Salvando...';

                const recurEnabled = recurChk?.checked && recurType?.value;
                const body = new URLSearchParams({
                    _csrf_token:    this.#csrf(),
                    client_id:      String(this.#clientId),
                    title, due_date: due,
                    priority:       selPrio?.value || 'medium',
                    description:    inpDesc?.value || '',
                    recurrence_type: recurEnabled ? recurType.value : 'none',
                });
                if (selAssign) body.append('assigned_to', selAssign.value);

                try {
                    const resp = await fetch(this.#appUrl + '/tasks/store', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: body.toString(),
                    });
                    if (!resp.ok) { this.#notify('Erro ao salvar tarefa.', 'error'); return; }
                    const data = await resp.json();
                    if (data?.csrf_token) this.#syncToken(data.csrf_token);
                    if (!data?.success) { this.#notify('Erro ao salvar tarefa.', 'error'); return; }
                    appendTaskRow({ title, due_date: due, priority: selPrio?.value || 'medium' });
                    closeModal();
                    this.#notify('Tarefa criada com sucesso!', 'success');
                } catch (e) {
                    this.#notify('Erro de rede ao salvar tarefa.', 'error');
                } finally {
                    btnSave.disabled = false;
                    btnSave.textContent = originalLabel;
                }
            });
        }
    }

    window.CRM = window.CRM || {};
    document.querySelectorAll('[data-crm-widget="client-show"]').forEach(el => {
        window.CRM.clientShow = new ClientShow(el);
    });
})();
