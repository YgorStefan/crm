(function () {
    'use strict';

    // ── Classes auxiliares ─────────────────────────────────────────

    class FilterState {
        tipoLista = '';
        dia  = '';
        telefoneEnviado = '';
        page = 1;

        toQueryParams() {
            let p = '';
            if (this.tipoLista)       p += '&tipo_lista='       + encodeURIComponent(this.tipoLista);
            if (this.dia)             p += '&dia='              + encodeURIComponent(this.dia);
            if (this.telefoneEnviado) p += '&telefone_enviado=' + encodeURIComponent(this.telefoneEnviado);
            return p;
        }

        reset() {
            this.tipoLista = ''; this.dia = ''; this.telefoneEnviado = ''; this.page = 1;
        }
    }

    class TableRenderer {
        #canEdit;

        constructor(canEdit) {
            this.#canEdit = canEdit;
        }

        renderRow(c, editMode) {
            const tr = document.createElement('tr');
            tr.dataset.id = c.id;
            tr.className = editMode
                ? 'border-b border-gray-100 dark:border-zinc-800 bg-indigo-50 dark:bg-indigo-900/20'
                : 'border-b border-gray-100 dark:border-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-800/30';

            if (editMode) {
                this.#appendEmpty(tr);
                this.#appendInput(tr, 'phone', c.phone || '', 'w-full');
                this.#appendInput(tr, 'name',  c.name  || '', 'w-full');
                this.#appendText(tr, c.tipo_lista || '', 'py-2 px-2 text-gray-500 dark:text-zinc-400 text-xs');
                this.#appendInput(tr, 'telefone_enviado', c.telefone_enviado || '', 'w-16', 4);
                this.#appendDateInput(tr, 'data_mensagem', c.data_mensagem || '');
                const tdAct = document.createElement('td');
                tdAct.className = 'py-2 px-2 whitespace-nowrap';
                const bSave = document.createElement('button');
                bSave.className = 'btn-save text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 font-medium text-xs mr-2';
                bSave.dataset.id = c.id;
                bSave.textContent = 'Salvar';
                const bCancel = document.createElement('button');
                bCancel.className = 'btn-cancel text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200 font-medium text-xs';
                bCancel.dataset.id = c.id;
                bCancel.textContent = 'Cancelar';
                tdAct.appendChild(bSave);
                tdAct.appendChild(bCancel);
                tr.appendChild(tdAct);
            } else {
                const tdCb = document.createElement('td');
                tdCb.className = 'py-2 px-2';
                if (this.#canEdit) {
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.className = 'row-check rounded';
                    cb.dataset.id = c.id;
                    tdCb.appendChild(cb);
                }
                tr.appendChild(tdCb);
                this.#appendText(tr, c.phone || '', 'py-2 px-2 text-gray-800 dark:text-zinc-200');
                this.#appendText(tr, c.name  || '', 'py-2 px-2 text-gray-800 dark:text-zinc-200');
                this.#appendText(tr, c.tipo_lista || '', 'py-2 px-2 text-gray-500 dark:text-zinc-400 text-xs');
                this.#appendText(tr, c.telefone_enviado || '—', 'py-2 px-2 text-gray-500 dark:text-zinc-400 text-xs');
                this.#appendText(tr, c.data_mensagem ? this.#fmtDate(c.data_mensagem) : '—', 'py-2 px-2 text-gray-500 dark:text-zinc-400 text-xs');
                const tdAct = document.createElement('td');
                tdAct.className = 'py-2 px-2 whitespace-nowrap';
                if (this.#canEdit) {
                    const bEdit = document.createElement('button');
                    bEdit.className = 'btn-edit text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300';
                    bEdit.title = 'Editar';
                    bEdit.dataset.id = c.id;
                    bEdit.dataset.contact = JSON.stringify(c);
                    bEdit.textContent = '✏️';
                    const bDel = document.createElement('button');
                    bDel.className = 'btn-delete text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 ml-2';
                    bDel.title = 'Excluir';
                    bDel.dataset.id = c.id;
                    bDel.textContent = '🗑️';
                    tdAct.appendChild(bEdit);
                    tdAct.appendChild(bDel);
                }
                tr.appendChild(tdAct);
            }
            return tr;
        }

        #appendEmpty(tr) {
            const td = document.createElement('td');
            td.className = 'py-2 px-2';
            tr.appendChild(td);
        }

        #appendText(tr, text, cls) {
            const td = document.createElement('td');
            td.className = cls;
            td.textContent = text;
            tr.appendChild(td);
        }

        #appendInput(tr, name, value, widthCls, maxlength) {
            const td = document.createElement('td');
            td.className = 'py-2 px-2';
            const inp = document.createElement('input');
            inp.className = (widthCls || 'w-full') + ' border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none';
            inp.name = name;
            inp.value = value;
            if (maxlength) inp.maxLength = maxlength;
            td.appendChild(inp);
            tr.appendChild(td);
        }

        #appendDateInput(tr, name, value) {
            const td = document.createElement('td');
            td.className = 'py-2 px-2';
            const inp = document.createElement('input');
            inp.type = 'date';
            inp.max = '9999-12-31';
            inp.className = 'border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none';
            inp.name = name;
            inp.value = value;
            td.appendChild(inp);
            tr.appendChild(td);
        }

        #fmtDate(dateStr) {
            if (!dateStr) return '—';
            const p = dateStr.split('-');
            return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : dateStr;
        }

        renderPagination(pag) {
            const cur = pag.current_page;
            const total = pag.total_pages;
            const btn = (p, label, disabled) => {
                if (disabled) {
                    const s = document.createElement('span');
                    s.className = 'px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-400 cursor-not-allowed';
                    s.textContent = label;
                    return s;
                }
                const b = document.createElement('button');
                b.type = 'button';
                b.dataset.page = p;
                b.className = 'px-3 py-1.5 rounded-lg border text-sm transition-colors ' +
                    (p === cur ? 'border-indigo-600 bg-indigo-600 text-white font-semibold' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50');
                b.textContent = label;
                return b;
            };
            const range = [];
            const delta = 2;
            for (let i = Math.max(1, cur - delta); i <= Math.min(total, cur + delta); i++) range.push(i);
            if (range[0] > 1) { if (range[0] > 2) range.unshift(null); range.unshift(1); }
            const last = range[range.length - 1];
            if (last < total) { if (last < total - 1) range.push(null); range.push(total); }

            const bar = document.createElement('div');
            bar.id = 'modalPaginationBar';
            bar.className = 'flex items-center gap-1 mt-4 justify-center flex-wrap';
            bar.appendChild(btn(cur - 1, 'Anterior', cur <= 1));
            range.forEach(p => {
                if (p === null) {
                    const s = document.createElement('span');
                    s.className = 'px-2 py-1.5 text-sm text-gray-400';
                    s.textContent = '...';
                    bar.appendChild(s);
                } else {
                    bar.appendChild(btn(p, String(p), false));
                }
            });
            bar.appendChild(btn(cur + 1, 'Próximo', cur >= total));
            return bar;
        }
    }

    class ExcelService {
        async #importXlsx(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = ev => {
                    try {
                        const wb = XLSX.read(ev.target.result, { type: 'array' });
                        const sheetName = wb.SheetNames[0];
                        if (!sheetName) throw new Error('Planilha vazia ou sem abas.');
                        const csv = XLSX.utils.sheet_to_csv(wb.Sheets[sheetName], { FS: ',', RS: '\n' });
                        resolve(csv);
                    } catch (e) { reject(e); }
                };
                reader.onerror = () => reject(new Error('Não foi possível ler o arquivo.'));
                reader.readAsArrayBuffer(file);
            });
        }

        exportCsv(rows, filename) {
            const csv = rows.map(r =>
                r.map(f => '"' + String(f).replace(/"/g, '""') + '"').join(';')
            ).join('\r\n');
            const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        async handleImportSubmit(form, appUrl) {
            const fileInput = form.querySelector('input[name="csv_file"]');
            const file = fileInput?.files[0];
            if (!file) return false; // deixa o browser validar 'required'

            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'xls' && ext !== 'xlsx') return false; // CSV: submit nativo

            if (typeof XLSX === 'undefined') {
                alert('Erro: biblioteca de leitura de planilhas não carregou.');
                return true; // previne submit nativo mas não faz fetch
            }

            try {
                const csvString = await this.#importXlsx(file);
                const csvBlob = new Blob([new TextEncoder().encode(csvString)], { type: 'text/csv;charset=utf-8' });
                const csvFile = new File([csvBlob], file.name.replace(/\.(xls|xlsx)$/i, '.csv'), { type: 'text/csv' });

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('_csrf_token', csrf);
                fd.append('tipo_lista',       form.querySelector('[name="tipo_lista"]')?.value || '');
                fd.append('telefone_enviado', form.querySelector('[name="telefone_enviado"]')?.value || '');
                fd.append('data_mensagem',    form.querySelector('[name="data_mensagem"]')?.value || '');
                fd.append('csv_file', csvFile);

                const resp = await fetch(form.action, { method: 'POST', body: fd, redirect: 'manual' });
                if (resp.type === 'opaqueredirect' || resp.ok) {
                    window.location.href = (appUrl || '') + '/cold-contacts';
                } else {
                    alert('Erro ao enviar o arquivo (HTTP ' + resp.status + ').');
                }
            } catch (err) {
                alert('Erro ao ler o arquivo: ' + (err.message || 'Formato inválido.'));
            }
            return true;
        }
    }

    // ── Orquestrador ───────────────────────────────────────────────

    class ColdContactManager {
        #rootEl;
        #appUrl;
        #yearMonth = '';
        #monthLabel = '';
        #filterState = new FilterState();
        #renderer;
        #excelService = new ExcelService();

        constructor(rootEl) {
            this.#rootEl    = rootEl;
            this.#appUrl    = (document.querySelector('meta[name="app-url"]')?.content || '').replace(/\/$/, '');
            this.#renderer  = new TableRenderer(rootEl.dataset.canEdit === '1');
            this.#setupModalTriggers();
            this.#setupDeleteMonth();
            this.#setupFilters();
            this.#setupBulkActions();
            this.#setupExport();
            this.#setupImport();
            this.#initDateGuard();
        }

        #csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        #syncToken(token) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', token);
        }

        async #loadTable(page) {
            page = page || this.#filterState.page;
            this.#filterState.page = page;
            const modalBody = document.getElementById('modalBody');
            if (!modalBody) return;
            modalBody.innerHTML = '<p class="text-gray-400 text-sm text-center py-8">Carregando...</p>';

            const url = this.#appUrl + '/cold-contacts/list?month=' + encodeURIComponent(this.#yearMonth) +
                '&page=' + page + this.#filterState.toQueryParams();
            try {
                const resp = await fetch(url, { credentials: 'same-origin' });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                this.#renderTable(data.contacts || [], data.pagination || null);
            } catch (e) {
                if (modalBody) modalBody.innerHTML = '<p class="text-red-500 text-sm text-center py-8">Erro ao carregar contatos. Tente novamente.</p>';
            }
        }

        #renderTable(contacts, pagination) {
            const modalBody  = document.getElementById('modalBody');
            const modalTotal = document.getElementById('modalTotal');
            if (!modalBody) return;

            const totalShown = pagination ? pagination.total_items : contacts.length;
            if (modalTotal) modalTotal.textContent = totalShown + ' contato(s) no total';

            if (contacts.length === 0) {
                modalBody.innerHTML = '<p class="text-gray-400 text-sm text-center py-8">Nenhum contato encontrado com os filtros aplicados.</p>';
                return;
            }

            const table = document.createElement('table');
            table.className = 'w-full text-sm';

            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            headerRow.className = 'border-b border-gray-200 dark:border-zinc-700';
            const headers = [
                this.#renderer.constructor === TableRenderer && this.#rootEl.dataset.canEdit === '1'
                    ? '<input type="checkbox" id="checkAll" class="rounded">' : '',
                'Celular', 'Nome', 'Tipo de lista', 'Tel. enviado', 'Data mensagem', '',
            ];
            headers.forEach(h => {
                const th = document.createElement('th');
                th.className = 'py-2 px-2 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase';
                if (h.startsWith('<')) { th.innerHTML = h; th.className = 'py-2 px-2'; }
                else th.textContent = h;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            tbody.id = 'contactsTableBody';
            contacts.forEach(c => tbody.appendChild(this.#renderer.renderRow(c, false)));
            table.appendChild(tbody);

            modalBody.innerHTML = '';
            modalBody.appendChild(table);
            if (pagination && pagination.total_pages > 1) {
                modalBody.appendChild(this.#renderer.renderPagination(pagination));
            }

            // checkAll
            document.getElementById('checkAll')?.addEventListener('change', function () {
                document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
                updateBulkBar();
            });

            const updateBulkBar = () => this.#updateBulkBar();

            // event delegation na tabela
            tbody.addEventListener('click', e => this.#handleTableClick(e));
            tbody.addEventListener('change', e => {
                if (e.target.classList.contains('row-check')) this.#updateBulkBar();
            });

            // event delegation na paginação
            document.getElementById('modalPaginationBar')?.addEventListener('click', e => {
                const btn = e.target.closest('[data-page]');
                if (btn && !btn.disabled) {
                    const p = parseInt(btn.dataset.page, 10);
                    if (!isNaN(p)) this.#loadTable(p);
                }
            });
        }

        #handleTableClick(e) {
            const btn = e.target.closest('.btn-edit, .btn-delete, .btn-save, .btn-cancel');
            if (!btn) return;
            const id = btn.dataset.id;
            if (!id) return;

            if (btn.classList.contains('btn-edit')) {
                const contact = JSON.parse(btn.dataset.contact);
                const row = document.querySelector('#contactsTableBody tr[data-id="' + id + '"]');
                if (row) row.replaceWith(this.#renderer.renderRow(contact, true));
                document.getElementById('contactsTableBody')?.addEventListener('click', e => this.#handleTableClick(e), { once: true });

            } else if (btn.classList.contains('btn-cancel')) {
                this.#loadTable(this.#filterState.page);

            } else if (btn.classList.contains('btn-save')) {
                const row = document.querySelector('#contactsTableBody tr[data-id="' + id + '"]');
                if (!row) return;
                const fd = new FormData();
                fd.append('_csrf_token', this.#csrf());
                fd.append('phone',             row.querySelector('[name="phone"]')?.value || '');
                fd.append('name',              row.querySelector('[name="name"]')?.value || '');
                fd.append('telefone_enviado',  row.querySelector('[name="telefone_enviado"]')?.value || '');
                fd.append('data_mensagem',     row.querySelector('[name="data_mensagem"]')?.value || '');
                this.#saveContact(id, fd);

            } else if (btn.classList.contains('btn-delete')) {
                if (!confirm('Excluir este contato?')) return;
                this.#deleteContact(id);
            }
        }

        async #saveContact(id, formData) {
            try {
                const resp = await fetch(this.#appUrl + '/cold-contacts/' + id + '/update', { method: 'POST', body: formData, credentials: 'same-origin' });
                const data = await resp.json();
                if (data.success) { if (data.csrf_token) this.#syncToken(data.csrf_token); this.#loadTable(this.#filterState.page); }
                else alert('Erro ao salvar: ' + (data.error || 'Tente novamente.'));
            } catch (e) { alert('Erro de rede ao salvar.'); }
        }

        async #deleteContact(id) {
            const fd = new FormData();
            fd.append('_csrf_token', this.#csrf());
            try {
                const resp = await fetch(this.#appUrl + '/cold-contacts/' + id + '/delete', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await resp.json();
                if (data.success) { if (data.csrf_token) this.#syncToken(data.csrf_token); this.#loadTable(this.#filterState.page); }
                else alert('Erro ao excluir: ' + (data.error || 'Tente novamente.'));
            } catch (e) { alert('Erro de rede ao excluir.'); }
        }

        #setupModalTriggers() {
            const modal       = document.getElementById('modalColdContacts');
            const btnClose    = document.getElementById('btnCloseModal');
            const closeModal  = () => { modal?.classList.add('hidden'); this.#hideBulkBar(); };

            document.querySelectorAll('.btn-open-modal').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.#yearMonth  = btn.dataset.yearMonth;
                    this.#monthLabel = btn.dataset.monthLabel;
                    const modalTitle = document.getElementById('modalTitle');
                    if (modalTitle) {
                        const label = this.#monthLabel;
                        modalTitle.textContent = label.charAt(0).toUpperCase() + label.slice(1);
                    }
                    this.#filterState.reset();
                    this.#resetFilterInputs();
                    this.#hideBulkBar();
                    modal?.classList.remove('hidden');
                    this.#loadTable(1);
                });
            });

            btnClose?.addEventListener('click', closeModal);
            modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && !modal?.classList.contains('hidden')) closeModal();
            });
        }

        #setupDeleteMonth() {
            document.querySelectorAll('.btn-delete-month').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const yearMonth  = btn.dataset.yearMonth;
                    const monthLabel = btn.dataset.monthLabel;
                    if (!confirm('Excluir todos os contatos de ' + monthLabel + '? Esta ação não pode ser desfeita.')) return;
                    const card = btn.closest('.bg-white.rounded-xl');
                    try {
                        const resp = await fetch(this.#appUrl + '/cold-contacts/month/' + encodeURIComponent(yearMonth) + '/delete', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': this.#csrf() },
                            body: '_csrf_token=' + encodeURIComponent(this.#csrf()),
                            credentials: 'same-origin',
                        });
                        let data = null;
                        try { data = await resp.json(); } catch (_) {}
                        if (!resp.ok) {
                            if (resp.status === 403) alert('Sessão expirada ou token inválido. Recarregue a página.');
                            else alert('Erro ao excluir mês (HTTP ' + resp.status + ').');
                            return;
                        }
                        if (data?.success) {
                            if (data.csrf_token) this.#syncToken(data.csrf_token);
                            card?.remove();
                        } else {
                            alert('Erro ao excluir: ' + (data?.error || 'Tente novamente.'));
                        }
                    } catch (e) {
                        alert('Não foi possível excluir o mês. Verifique sua conexão.');
                    }
                });
            });
        }

        #resetFilterInputs() {
            const v = id => { const el = document.getElementById(id); if (el) el.value = ''; };
            v('filterTipoLista'); v('filterDia'); v('filterTelEnviado');
        }

        #setupFilters() {
            const apply = () => { this.#filterState.page = 1; this.#loadTable(1); };
            const clear = () => {
                this.#filterState.reset();
                this.#resetFilterInputs();
                this.#loadTable(1);
            };

            document.getElementById('btnApplyFilter')?.addEventListener('click', apply);
            document.getElementById('btnClearFilter')?.addEventListener('click', clear);

            ['filterTipoLista', 'filterDia', 'filterTelEnviado'].forEach(id => {
                document.getElementById(id)?.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        this.#filterState.tipoLista       = document.getElementById('filterTipoLista')?.value.trim() || '';
                        this.#filterState.dia             = document.getElementById('filterDia')?.value.trim() || '';
                        this.#filterState.telefoneEnviado = document.getElementById('filterTelEnviado')?.value.trim() || '';
                        apply();
                    }
                });
            });

            document.getElementById('btnApplyFilter')?.addEventListener('click', () => {
                this.#filterState.tipoLista       = document.getElementById('filterTipoLista')?.value.trim() || '';
                this.#filterState.dia             = document.getElementById('filterDia')?.value.trim() || '';
                this.#filterState.telefoneEnviado = document.getElementById('filterTelEnviado')?.value.trim() || '';
            });
        }

        #hideBulkBar() {
            const bulkBar = document.getElementById('bulkBar');
            bulkBar?.classList.add('hidden');
            const telEnv  = document.getElementById('bulkTelEnviado');
            const datMsg  = document.getElementById('bulkDataMensagem');
            if (telEnv) telEnv.value = '';
            if (datMsg) datMsg.value = '';
        }

        #updateBulkBar() {
            const checked = document.querySelectorAll('.row-check:checked');
            const bulkBar  = document.getElementById('bulkBar');
            const bulkCount = document.getElementById('bulkCount');
            if (checked.length === 0) {
                this.#hideBulkBar();
            } else {
                if (bulkCount) bulkCount.textContent = checked.length + ' contato(s) selecionado(s)';
                bulkBar?.classList.remove('hidden');
            }
        }

        #setupBulkActions() {
            document.getElementById('btnBulkCancel')?.addEventListener('click', () => {
                document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
                this.#hideBulkBar();
            });

            document.getElementById('btnBulkSave')?.addEventListener('click', async () => {
                const tel     = document.getElementById('bulkTelEnviado')?.value.trim() || '';
                const dataMsg = document.getElementById('bulkDataMensagem')?.value.trim() || '';
                if (tel && !/^\d{1,4}$/.test(tel)) { alert('Tel. enviado deve ser numérico com até 4 dígitos.'); return; }
                if (!tel && !dataMsg) { alert('Preencha ao menos um campo para atualizar em lote.'); return; }

                const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.dataset.id);
                if (!ids.length) return;

                const fd = new FormData();
                fd.append('_csrf_token', this.#csrf());
                fd.append('telefone_enviado', tel);
                fd.append('data_mensagem', dataMsg);
                ids.forEach(id => fd.append('ids[]', id));

                try {
                    const resp = await fetch(this.#appUrl + '/cold-contacts/bulk-update', { method: 'POST', body: fd, credentials: 'same-origin' });
                    const data = await resp.json();
                    if (data.success) {
                        if (data.csrf_token) this.#syncToken(data.csrf_token);
                        this.#hideBulkBar();
                        this.#loadTable(this.#filterState.page);
                    } else {
                        alert('Erro: ' + (data.error || 'Tente novamente.'));
                    }
                } catch (e) { alert('Erro de rede. Tente novamente.'); }
            });
        }

        #setupExport() {
            document.getElementById('btnExportCsv')?.addEventListener('click', () => {
                const checked = document.querySelectorAll('.row-check:checked');
                if (checked.length > 0) {
                    const rows = [['Celular', 'Nome', 'Tipo de lista', 'Telefone enviado', 'Data da mensagem']];
                    checked.forEach(cb => {
                        const tr = cb.closest('tr');
                        const editBtn = tr?.querySelector('.btn-edit');
                        if (!editBtn) return;
                        try {
                            const c = JSON.parse(editBtn.dataset.contact);
                            const dateFmt = c.data_mensagem ? c.data_mensagem.split('-').reverse().join('/') : '';
                            rows.push([c.phone || '', c.name || '', c.tipo_lista || '', c.telefone_enviado || '', dateFmt]);
                        } catch (_) {}
                    });
                    this.#excelService.exportCsv(rows, 'contatos-selecionados.csv');
                    return;
                }
                const params = this.#filterState.toQueryParams();
                window.location.href = this.#appUrl + '/cold-contacts/export?month=' + encodeURIComponent(this.#yearMonth) + params;
            });
        }

        #setupImport() {
            const form = document.querySelector('form[action*="cold-contacts/import"]');
            if (!form) return;
            form.addEventListener('submit', async e => {
                e.preventDefault();
                const handled = await this.#excelService.handleImportSubmit(form, this.#appUrl);
                if (!handled) form.submit();
            });
        }

        #initDateGuard() {
            document.addEventListener('change', e => {
                if (e.target.type !== 'date') return;
                const val = e.target.value;
                if (val && parseInt(val.split('-')[0], 10) > 9999) e.target.value = '';
            });
        }
    }

    window.CRM = window.CRM || {};
    document.querySelectorAll('[data-crm-widget="cold-contacts"]').forEach(el => {
        window.CRM.coldContacts = new ColdContactManager(el);
    });
})();
