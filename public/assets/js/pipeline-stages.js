(function () {
    'use strict';

    class PipelineStagesManager {
        #appUrl;
        #csrfToken;

        constructor(container) {
            this.#appUrl    = document.querySelector('meta[name="app-url"]').content;
            this.#csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            this.#wireEdit(container);
            this.#wireMove(container);
            this.#wireWonToggle(container);
            this.#wireDelete(container);
        }

        #refreshCsrf(data) {
            if (data.csrf_token) {
                this.#csrfToken = data.csrf_token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = data.csrf_token;
            }
        }

        #wireEdit(container) {
            container.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', function () {
                    const row = this.closest('[data-stage-id]');
                    row.querySelector('.view-mode').classList.add('hidden');
                    row.querySelector('.edit-mode').classList.remove('hidden');
                    row.querySelector('.btn-edit').classList.add('hidden');
                    row.querySelector('.btn-save').classList.remove('hidden');
                    row.querySelector('.btn-cancel').classList.remove('hidden');
                });
            });

            container.querySelectorAll('.btn-cancel').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row = btn.closest('[data-stage-id]');
                    row.querySelector('.edit-name').value  = row.dataset.stageName;
                    row.querySelector('.edit-color').value = row.dataset.stageColor;
                    this.#exitEditMode(row);
                });
            });

            container.querySelectorAll('.btn-save').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row   = btn.closest('[data-stage-id]');
                    const id    = row.dataset.stageId;
                    const name  = row.querySelector('.edit-name').value.trim();
                    const color = row.querySelector('.edit-color').value;

                    if (!name) { alert('O nome da etapa não pode ficar vazio.'); return; }

                    const body = new URLSearchParams({ _csrf_token: this.#csrfToken, name, color });
                    fetch(this.#appUrl + '/pipeline/stages/' + id + '/update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.#refreshCsrf(data);
                            if (!data.success) { alert('Erro ao salvar. Tente novamente.'); return; }
                            row.querySelector('.stage-name-text').textContent        = name;
                            row.querySelector('.color-preview').style.backgroundColor = color;
                            row.dataset.stageName  = name;
                            row.dataset.stageColor = color;
                            this.#exitEditMode(row);
                        })
                        .catch(() => alert('Erro de comunicação. Tente novamente.'));
                });
            });
        }

        #exitEditMode(row) {
            row.querySelector('.view-mode').classList.remove('hidden');
            row.querySelector('.edit-mode').classList.add('hidden');
            row.querySelector('.btn-edit').classList.remove('hidden');
            row.querySelector('.btn-save').classList.add('hidden');
            row.querySelector('.btn-cancel').classList.add('hidden');
        }

        #wireMove(container) {
            container.querySelectorAll('.btn-move').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row       = btn.closest('[data-stage-id]');
                    const id        = row.dataset.stageId;
                    const direction = btn.dataset.direction;

                    const body = new URLSearchParams({ _csrf_token: this.#csrfToken, direction });
                    fetch(this.#appUrl + '/pipeline/stages/' + id + '/move', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.#refreshCsrf(data);
                            if (!data.success) return;
                            location.reload();
                        })
                        .catch(() => alert('Erro de comunicação. Tente novamente.'));
                });
            });
        }

        #wireWonToggle(container) {
            container.querySelectorAll('.btn-won-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.stageId;
                    btn.classList.add('opacity-50', 'cursor-wait');
                    btn.setAttribute('aria-label', 'Salvando...');
                    btn.disabled = true;

                    const body = new URLSearchParams({ _csrf_token: this.#csrfToken });
                    fetch(this.#appUrl + '/pipeline/stages/' + id + '/toggle-won', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.#refreshCsrf(data);
                            if (!data.success) { alert('Erro ao salvar. Tente novamente.'); return; }

                            const newState = data.is_won_stage === 1;
                            if (newState) {
                                container.querySelectorAll('.btn-won-toggle').forEach(b => {
                                    b.dataset.isWon = '0';
                                    b.setAttribute('aria-pressed', 'false');
                                    b.setAttribute('title', 'Definir como etapa de ganho');
                                    b.className = b.className.replace('text-indigo-600 font-medium', 'text-gray-400 hover:text-indigo-500');
                                    b.textContent = '☆';
                                });
                            }
                            btn.dataset.isWon = newState ? '1' : '0';
                            btn.setAttribute('aria-pressed', newState ? 'true' : 'false');
                            btn.setAttribute('title', newState ? 'Etapa de ganho ativa' : 'Definir como etapa de ganho');
                            btn.textContent = newState ? '★' : '☆';
                            btn.className = btn.className.replace(
                                newState ? 'text-gray-400 hover:text-indigo-500' : 'text-indigo-600 font-medium',
                                newState ? 'text-indigo-600 font-medium' : 'text-gray-400 hover:text-indigo-500'
                            );
                        })
                        .catch(() => alert('Erro de comunicação. Tente novamente.'))
                        .finally(() => {
                            btn.classList.remove('opacity-50', 'cursor-wait');
                            btn.removeAttribute('aria-label');
                            btn.disabled = false;
                        });
                });
            });
        }

        #wireDelete(container) {
            container.querySelectorAll('.form-delete-stage').forEach(form => {
                form.addEventListener('submit', function (e) {
                    const row   = this.closest('[data-stage-id]');
                    const name  = row ? row.dataset.stageName : '';
                    const count = row ? parseInt(row.dataset.clientCount || '0', 10) : 0;
                    const msg   = count > 0
                        ? count + ' cliente(s) nesta etapa perderão a etapa.'
                        : 'Esta etapa está vazia.';
                    if (!confirm('Remover a etapa "' + name + '"?\n' + msg)) e.preventDefault();
                });
            });
        }

        static init() {
            const container = document.querySelector('[data-crm-widget="pipeline-stages"]');
            if (!container) return;
            window.CRM = window.CRM || {};
            window.CRM.pipelineStages = new PipelineStagesManager(container);
        }
    }

    // Auto-instanciação direta — defer garante DOM pronto (padrão Fase 2)
    PipelineStagesManager.init();
})();
