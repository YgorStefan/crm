(function () {
    'use strict';

    class ClientIndexManager {
        constructor() {
            this.#wireFilterReset();
            this.#wireModals();
        }

        #wireFilterReset() {
            const form = document.getElementById('filterForm');
            if (!form) return;
            form.addEventListener('submit', function () {
                let pageInput = this.querySelector('input[name="page"]');
                if (!pageInput) {
                    pageInput = document.createElement('input');
                    pageInput.type = 'hidden';
                    pageInput.name = 'page';
                    this.appendChild(pageInput);
                }
                pageInput.value = '1';
            });
        }

        // Escuta no document porque os modais ficam fora do container data-crm-widget
        #wireModals() {
            this.#wireQuickTaskRecurrence();

            document.addEventListener('click', e => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                if (btn.dataset.action === 'open-quick-interaction') {
                    document.getElementById('qiClientId').value = btn.dataset.clientId;
                    document.getElementById('qiClientName').textContent = btn.dataset.clientName;
                    const now = new Date();
                    now.setSeconds(0, 0);
                    document.getElementById('qiOccurredAt').value = now.toISOString().slice(0, 16);
                    document.getElementById('modalQuickInteraction').classList.remove('hidden');
                }

                if (btn.dataset.action === 'open-quick-task') {
                    document.getElementById('qtClientId').value = btn.dataset.clientId;
                    document.getElementById('qtClientName').textContent = btn.dataset.clientName;
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    tomorrow.setHours(12, 0, 0, 0);
                    document.getElementById('qtDueDate').value = tomorrow.toISOString().slice(0, 16);
                    // Reset recorrência
                    const chk = document.getElementById('qt_recur_enabled');
                    const wrap = document.getElementById('qt_recur_select_wrap');
                    const type = document.getElementById('qt_recurrence_type');
                    const lbl  = document.getElementById('qt_recurrenceBtnLabel');
                    const menu = document.getElementById('qt_recurrenceMenu');
                    if (chk)  chk.checked = false;
                    if (wrap) wrap.classList.add('hidden');
                    if (type) type.value = 'none';
                    if (lbl)  lbl.textContent = 'Semanal';
                    if (menu) menu.classList.add('hidden');
                    document.getElementById('modalQuickTask').classList.remove('hidden');
                }

                if (btn.dataset.action === 'close-modal') {
                    const target = document.getElementById(btn.dataset.target);
                    if (target) target.classList.add('hidden');
                }
            });
        }

        #wireQuickTaskRecurrence() {
            const chk  = document.getElementById('qt_recur_enabled');
            const wrap = document.getElementById('qt_recur_select_wrap');
            const type = document.getElementById('qt_recurrence_type');
            const btn  = document.getElementById('qt_recurrenceBtn');
            const lbl  = document.getElementById('qt_recurrenceBtnLabel');
            const menu = document.getElementById('qt_recurrenceMenu');
            if (!chk) return;

            chk.addEventListener('change', () => {
                wrap?.classList.toggle('hidden', !chk.checked);
                if (!chk.checked && type) type.value = 'none';
                if (chk.checked && type && type.value === 'none') { type.value = 'weekly'; if (lbl) lbl.textContent = 'Semanal'; }
            });

            btn?.addEventListener('click', () => menu?.classList.toggle('hidden'));

            menu?.querySelectorAll('.qt-recurrence-opt').forEach(opt => {
                opt.addEventListener('click', () => {
                    if (type) type.value = opt.dataset.value;
                    if (lbl)  lbl.textContent = opt.dataset.label;
                    menu.classList.add('hidden');
                });
            });

            document.addEventListener('click', e => {
                if (menu && !menu.classList.contains('hidden') && !btn?.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.add('hidden');
                }
            });
        }

        static init() {
            if (!document.querySelector('[data-crm-widget="client-index"]')) return;
            window.CRM = window.CRM || {};
            window.CRM.clientIndex = new ClientIndexManager();
        }
    }

    // Auto-instanciação direta — defer garante DOM pronto (padrão Fase 2)
    ClientIndexManager.init();
})();
