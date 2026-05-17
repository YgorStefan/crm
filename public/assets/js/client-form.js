(function () {
    'use strict';

    class ClientForm {
        #form;

        constructor(formEl) {
            this.#form = formEl;
            this.#applyMasks();
            this.#initViaCep();
            this.#initConditionals();
            this.#fixDatesBeforeSubmit();
        }

        static async #fetchAddress(cep) {
            const resp = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
            return resp.json();
        }

        #applyMasks() {
            const f = this.#form;

            const phone = f.querySelector('[name="phone"]');
            phone?.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').substring(0, 11);
                if (v.length > 6)      v = '(' + v.substring(0, 2) + ') ' + v.substring(2, 7) + '-' + v.substring(7);
                else if (v.length > 2) v = '(' + v.substring(0, 2) + ') ' + v.substring(2);
                else if (v.length > 0) v = '(' + v;
                this.value = v;
            });

            const cpf = f.querySelector('[name="cnpj_cpf"]');
            cpf?.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').substring(0, 14);
                if (v.length <= 11) {
                    v = v.replace(/(\d{3})(\d)/, '$1.$2')
                         .replace(/(\d{3})(\d)/, '$1.$2')
                         .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                } else {
                    v = v.replace(/(\d{2})(\d)/, '$1.$2')
                         .replace(/(\d{3})(\d)/, '$1.$2')
                         .replace(/(\d{3})(\d)/, '$1/$2')
                         .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                }
                this.value = v;
            });

            const maskDate = el => el?.addEventListener('input', function () {
                let v = this.value.replace(/\D/g, '').substring(0, 8);
                if (v.length > 4)      v = v.substring(0, 2) + '/' + v.substring(2, 4) + '/' + v.substring(4);
                else if (v.length > 2) v = v.substring(0, 2) + '/' + v.substring(2);
                this.value = v;
            });
            maskDate(f.querySelector('[name="birth_date"]'));
            maskDate(f.querySelector('[name="closed_at"]'));
        }

        #initViaCep() {
            const f = this.#form;
            const zipInput = f.querySelector('[name="zip_code"]');
            const statusEl = document.getElementById('cep_status');
            if (!zipInput) return;

            let lastCep = '';
            zipInput.addEventListener('input', async function () {
                let v = this.value.replace(/\D/g, '').substring(0, 8);
                if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5);
                this.value = v;
                const digits = v.replace(/\D/g, '');
                if (digits.length !== 8 || digits === lastCep) return;
                lastCep = digits;
                if (statusEl) statusEl.textContent = 'Buscando...';
                try {
                    const data = await ClientForm.#fetchAddress(digits);
                    if (statusEl) statusEl.textContent = '';
                    if (data.erro) return;
                    const set = (name, val) => { const el = f.querySelector('[name="' + name + '"]'); if (el) el.value = val || ''; };
                    set('address', data.logradouro);
                    set('neighborhood', data.bairro);
                    set('city', data.localidade);
                    const state = f.querySelector('[name="state"]');
                    if (state) state.value = (data.uf || '').toUpperCase();
                } catch (e) {
                    if (statusEl) statusEl.textContent = '';
                }
            });
        }

        #initConditionals() {
            const f = this.#form;

            const sourceSelect    = f.querySelector('[name="source"]');
            const indicacaoWrap   = document.getElementById('indicacao_wrapper');
            sourceSelect?.addEventListener('change', function () {
                if (indicacaoWrap) indicacaoWrap.style.display = this.value === 'Indicação' ? 'block' : 'none';
            });

            const stageSelect    = f.querySelector('[name="pipeline_stage_id"]');
            const closedAtWrap   = document.getElementById('closed_at_wrapper');
            const closedAtInput  = f.querySelector('[name="closed_at"]');
            stageSelect?.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const isVF = opt && opt.dataset.vendaFechada === '1';
                if (closedAtWrap) closedAtWrap.style.display = isVF ? 'block' : 'none';
                if (!isVF && closedAtInput) closedAtInput.value = '';
            });
        }

        #fixDatesBeforeSubmit() {
            const f = this.#form;
            f.addEventListener('submit', e => {
                // Remove máscaras dos campos numéricos
                ['phone', 'cnpj_cpf', 'zip_code'].forEach(name => {
                    const el = f.querySelector('[name="' + name + '"]');
                    if (el) el.value = el.value.replace(/\D/g, '');
                });

                // Valida e converte datas DD/MM/AAAA → YYYY-MM-DD
                const dateRegex = /^\d{2}\/\d{2}\/\d{4}$/;
                const dateFields = [
                    f.querySelector('[name="birth_date"]'),
                    f.querySelector('[name="closed_at"]'),
                ];
                for (const field of dateFields) {
                    if (!field || !field.value) continue;
                    if (!field.value.includes('/')) continue;
                    if (!dateRegex.test(field.value)) {
                        e.preventDefault();
                        field.focus();
                        const errId = field.name + '_err';
                        let errEl = document.getElementById(errId);
                        if (!errEl) {
                            errEl = document.createElement('p');
                            errEl.id = errId;
                            errEl.className = 'text-xs text-red-500 mt-1';
                            field.insertAdjacentElement('afterend', errEl);
                        }
                        errEl.textContent = 'Data inválida. Use DD/MM/AAAA.';
                        return;
                    }
                    const parts = field.value.split('/');
                    field.value = parts[2] + '-' + parts[1] + '-' + parts[0];
                }
            });
        }
    }

    window.CRM = window.CRM || {};
    document.querySelectorAll('[data-crm-widget="client-form"]').forEach(el => {
        window.CRM.clientForm = new ClientForm(el);
    });
})();
