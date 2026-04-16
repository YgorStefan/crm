<?php
// Função auxiliar para repopular os campos com os valores atuais do cliente
function val(array $client, string $key): string
{
    return htmlspecialchars($client[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>" class="text-gray-400 hover:text-gray-600">← Voltar</a>
        <h3 class="text-2xl font-bold text-gray-800">Editar: <?= val($client, 'name') ?></h3>
    </div>

    <form method="POST" action="<?= APP_URL ?>/clients/<?= $client['id'] ?>/update"
        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Seção: Dados Pessoais -->
        <div class="px-6 py-5 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Dados Pessoais</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="name" required value="<?= val($client, 'name') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" name="email" value="<?= val($client, 'email') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                    <input type="text" name="phone" value="<?= val($client, 'phone') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                    <input type="text" name="company" value="<?= val($client, 'company') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF / CNPJ</label>
                    <input type="text" name="cnpj_cpf" value="<?= val($client, 'cnpj_cpf') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                    <input type="text" name="birth_date" id="birth_date" maxlength="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="DD/MM/AAAA" value="<?php
                        $bd = $client['birth_date'] ?? '';
                        if ($bd && strlen($bd) === 10) {
                            $parts = explode('-', $bd);
                            echo $parts[2] . '/' . $parts[1] . '/' . $parts[0];
                        }
                        ?>">
                </div>
            </div>
        </div>

        <!-- Seção: Endereço -->
        <div class="px-6 py-5 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Endereço</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                    <input type="text" name="address" value="<?= val($client, 'address') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                    <input type="text" name="zip_code" value="<?= val($client, 'zip_code') ?>" maxlength="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                    <input type="text" name="city" value="<?= val($client, 'city') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                    <select name="state"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">—</option>
                        <?php foreach (['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'] as $uf): ?>
                            <option value="<?= $uf ?>" <?= val($client, 'state') === $uf ? 'selected' : '' ?>><?= $uf ?>
                            </option>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Etapa do Funil <span
                            class="text-red-500">*</span></label>
                    <select name="pipeline_stage_id" id="pipeline_stage_select" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?= $stage['id'] ?>"
                                data-venda-fechada="<?= stripos($stage['name'], 'venda fechada') !== false ? '1' : '0' ?>"
                                <?= $client['pipeline_stage_id'] == $stage['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php
                $currentStage = array_values(array_filter($stages, fn($s) => $s['id'] == $client['pipeline_stage_id']));
                $currentIsVF  = !empty($currentStage) && stripos($currentStage[0]['name'], 'venda fechada') !== false;
                ?>
                <div id="closed_at_wrapper" <?= $currentIsVF ? '' : 'style="display:none;"' ?>>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Fechamento</label>
                    <input type="text" name="closed_at" id="closed_at" maxlength="10"
                        placeholder="DD/MM/AAAA"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        value="<?php
                            $ca = $client['closed_at'] ?? '';
                            if ($ca && strlen($ca) === 10) {
                                $p = explode('-', $ca);
                                echo $p[2] . '/' . $p[1] . '/' . $p[0];
                            }
                        ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                    <select name="assigned_to"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">Sem responsável</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $client['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Crédito contratado (R$)</label>
                    <input type="text" name="deal_value"
                        value="<?= number_format($client['deal_value'] ?? 0, 2, ',', '.') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Origem</label>
                    <select name="source" id="source_select"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">Desconhecida</option>
                        <?php foreach (['Google Ads', 'Indicação', 'LinkedIn', 'Instagram', 'Site Orgânico', 'Evento', 'Lista fria', 'AVA Pro', 'Amigo/Conhecido', 'Outro'] as $src): ?>
                            <option value="<?= $src ?>" <?= val($client, 'source') === $src ? 'selected' : '' ?>><?= $src ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="indicacao_wrapper" <?= (val($client, 'source') !== 'Indicação') ? 'style="display:none;"' : '' ?>
                    class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome de quem indicou</label>
                    <input type="text" name="referido_por" id="referido_por"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        placeholder="Nome da pessoa que indicou" value="<?= val($client, 'referido_por') ?>">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota</label>
                    <textarea name="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"><?= val($client, 'notes') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="px-6 py-4 bg-gray-50 flex flex-col sm:flex-row justify-between gap-3">
            <!-- Placeholder para manter o layout (form delete fica fora) -->
            <div>
                <button type="button" form="form-delete-client"
                    onclick="if(confirm('Tem certeza que deseja remover este cliente?')) document.getElementById('form-delete-client').submit();"
                    class="px-4 py-2 border border-red-300 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-colors">
                    🗑️ Remover Cliente
                </button>
            </div>
            <!-- Salvar (direita) -->
            <div class="flex gap-3">
                <a href="<?= APP_URL ?>/clients/<?= $client['id'] ?>"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-100 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">
                    Salvar Alterações
                </button>
            </div>
        </div>
    </form>

<!-- Form de delete FORA do form de edição (HTML não suporta forms aninhados) -->
<form id="form-delete-client" method="POST" action="<?= APP_URL ?>/clients/<?= $client['id'] ?>/delete" style="display:none;">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
</form>
</div>

<script>
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
    // Seleciona apenas o form principal de edição (não o form de delete)
    document.querySelector('form[action$="/update"]').addEventListener('submit', function () {
        ['phone', 'cnpj_cpf', 'zip_code'].forEach(function (name) {
            const el = document.querySelector('[name="' + name + '"]');
            if (el) el.value = el.value.replace(/\D/g, '');
        });
        // birth_date: converter DD/MM/AAAA para YYYY-MM-DD para o banco
        // Se já está no formato YYYY-MM-DD (carregado do banco e não alterado), manter
        const bd = document.querySelector('[name="birth_date"]');
        if (bd && bd.value.includes('/') && bd.value.length === 10) {
            const parts = bd.value.split('/');
            bd.value = parts[2] + '-' + parts[1] + '-' + parts[0];
        } else if (bd && !bd.value.includes('-')) {
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