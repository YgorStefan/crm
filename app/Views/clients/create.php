<?php
?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= APP_URL ?>/clients" class="text-gray-400 hover:text-gray-600">← Voltar</a>
        <h3 class="text-2xl font-bold text-gray-800">Novo Cliente</h3>
    </div>

    <form method="POST" action="<?= APP_URL ?>/clients/store"
        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Seção: Dados Pessoais -->
        <div class="px-6 py-5 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Dados Pessoais</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nome <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="Nome completo ou razão social">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" name="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="contato@empresa.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefone / WhatsApp</label>
                    <input type="text" name="phone"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="(11) 99999-9999">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                    <input type="text" name="company"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="Nome da empresa">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF / CNPJ</label>
                    <input type="text" name="cnpj_cpf"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="000.000.000-00 ou 00.000.000/0001-00">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                    <input type="text" name="birth_date" id="birth_date" maxlength="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="DD/MM/AAAA">
                </div>
            </div>
        </div>

        <!-- Seção: Endereço -->
        <div class="px-6 py-5 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Endereço</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                    <input type="text" name="address"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="Rua, número, complemento">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                    <input type="text" name="zip_code" maxlength="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="00000-000">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                    <input type="text" name="city"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="São Paulo">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                    <select name="state"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">—</option>
                        <?php foreach (['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'] as $uf): ?>
                            <option value="<?= $uf ?>"><?= $uf ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Seção: CRM -->
        <div class="px-6 py-5 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Informações Comerciais</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Etapa do Funil <span class="text-red-500">*</span>
                    </label>
                    <select name="pipeline_stage_id" id="pipeline_stage_select" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?= $stage['id'] ?>"
                                data-venda-fechada="<?= !empty($stage['is_won_stage']) ? '1' : '0' ?>">
                                <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="closed_at_wrapper" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Fechamento</label>
                    <input type="text" name="closed_at" id="closed_at" maxlength="10"
                        placeholder="DD/MM/AAAA"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                    <select name="assigned_to"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">Sem responsável</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= ($user['id'] == ($_SESSION['user']['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Crédito contratado (R$)</label>
                    <input type="text" name="deal_value"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="0,00">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Origem do Lead</label>
                    <select name="source" id="source_select"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">Desconhecida</option>
                        <option>Google Ads</option>
                        <option>Indicação</option>
                        <option>LinkedIn</option>
                        <option>Instagram</option>
                        <option>Site Orgânico</option>
                        <option>Evento</option>
                        <option>Lista fria</option>
                        <option>AVA Pro</option>
                        <option>Amigo/Conhecido</option>
                        <option>Outro</option>
                    </select>
                </div>

                <div id="indicacao_wrapper" style="display:none;" class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome de quem indicou</label>
                    <input type="text" name="referido_por" id="referido_por"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="Nome da pessoa que indicou">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota</label>
                    <textarea name="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="Observações gerais sobre este cliente..."></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
            <a href="<?= APP_URL ?>/clients"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-100 transition-colors">
                Cancelar
            </a>
            <button type="submit"
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">
                Salvar Cliente
            </button>
        </div>
    </form>
</div>

<script nonce="<?= CSP_NONCE ?>">
    // Máscara: Telefone (11) 99999-9999
    document.querySelector('[name="phone"]').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 11);
        if (v.length > 6) v = '(' + v.substring(0, 2) + ') ' + v.substring(2, 7) + '-' + v.substring(7);
        else if (v.length > 2) v = '(' + v.substring(0, 2) + ') ' + v.substring(2);
        else if (v.length > 0) v = '(' + v;
        this.value = v;
    });

    // Máscara: CPF/CNPJ dinâmico
    document.querySelector('[name="cnpj_cpf"]').addEventListener('input', function () {
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

    // Máscara: CEP 00000-000
    document.querySelector('[name="zip_code"]').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 8);
        if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5);
        this.value = v;
    });

    // Máscara: Data de nascimento DD/MM/AAAA
    document.querySelector('[name="birth_date"]').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 8);
        if (v.length > 4) v = v.substring(0, 2) + '/' + v.substring(2, 4) + '/' + v.substring(4);
        else if (v.length > 2) v = v.substring(0, 2) + '/' + v.substring(2);
        this.value = v;
    });

    // Campo condicional: Indicação
    const sourceSelect = document.getElementById('source_select');
    const indicacaoWrapper = document.getElementById('indicacao_wrapper');
    sourceSelect.addEventListener('change', function () {
        indicacaoWrapper.style.display = this.value === 'Indicação' ? 'block' : 'none';
    });

    // Campo condicional: Data de Fechamento (só para etapa "Venda Fechada")
    const stageSelect = document.getElementById('pipeline_stage_select');
    const closedAtWrapper = document.getElementById('closed_at_wrapper');
    stageSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const isVF = opt && opt.dataset.vendaFechada === '1';
        closedAtWrapper.style.display = isVF ? 'block' : 'none';
        if (!isVF) document.getElementById('closed_at').value = '';
    });

    // Máscara: Data de fechamento DD/MM/AAAA
    document.getElementById('closed_at').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 8);
        if (v.length > 4) v = v.substring(0, 2) + '/' + v.substring(2, 4) + '/' + v.substring(4);
        else if (v.length > 2) v = v.substring(0, 2) + '/' + v.substring(2);
        this.value = v;
    });

    // Remove máscaras antes do submit: envia apenas dígitos ao servidor
    document.querySelector('form').addEventListener('submit', function () {
        ['phone', 'cnpj_cpf', 'zip_code'].forEach(function (name) {
            const el = document.querySelector('[name="' + name + '"]');
            if (el) el.value = el.value.replace(/\D/g, '');
        });
        // birth_date: converter DD/MM/AAAA para YYYY-MM-DD para o banco
        const bd = document.querySelector('[name="birth_date"]');
        if (bd && bd.value.length === 10) {
            const parts = bd.value.split('/');
            bd.value = parts[2] + '-' + parts[1] + '-' + parts[0];
        } else if (bd) {
            bd.value = '';
        }
        // closed_at: converter DD/MM/AAAA para YYYY-MM-DD
        const ca = document.getElementById('closed_at');
        if (ca && ca.value.includes('/') && ca.value.length === 10) {
            const parts = ca.value.split('/');
            ca.value = parts[2] + '-' + parts[1] + '-' + parts[0];
        } else if (ca && ca.value && !ca.value.includes('-')) {
            ca.value = '';
        }
    });
</script>