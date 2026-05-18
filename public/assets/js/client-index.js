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
                    document.getElementById('modalQuickTask').classList.remove('hidden');
                }

                if (btn.dataset.action === 'close-modal') {
                    const target = document.getElementById(btn.dataset.target);
                    if (target) target.classList.add('hidden');
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
