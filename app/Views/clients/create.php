<?php
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts = '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/client-form.js?v=' . $_jsV('client-form.js') . '"></script>';
unset($_jsV);
?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= APP_URL ?>/clients" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400 bg-gray-100 hover:bg-indigo-50 dark:bg-zinc-800 dark:hover:bg-indigo-900/30 px-3 py-1.5 rounded-lg transition-all">
            ↩ Voltar
        </a>
        <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Novo Cliente</h3>
    </div>

    <form method="POST" action="<?= APP_URL ?>/clients/store"
        data-crm-widget="client-form" data-mode="create"
        class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Seção: Dados Pessoais -->
        <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800">
            <h4 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide mb-4">Dados Pessoais</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                        Nome <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="Nome completo ou razão social">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">E-mail</label>
                    <input type="email" name="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="contato@empresa.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Telefone / WhatsApp</label>
                    <input type="text" name="phone"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="(11) 99999-9999">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Empresa</label>
                    <input type="text" name="company"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="Nome da empresa">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">CPF / CNPJ</label>
                    <input type="text" name="cnpj_cpf"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="000.000.000-00 ou 00.000.000/0001-00">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Data de Nascimento</label>
                    <input type="text" name="birth_date" id="birth_date" maxlength="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="DD/MM/AAAA">
                </div>
            </div>
        </div>

        <!-- Seção: Endereço -->
        <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800">
            <h4 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide mb-4">Endereço</h4>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">CEP <span id="cep_status" class="text-xs text-indigo-500 font-normal"></span></label>
                    <input type="text" name="zip_code" maxlength="10"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="00000-000">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Logradouro</label>
                    <input type="text" name="address"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="Rua, Av., Travessa...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Número</label>
                    <input type="text" name="address_number" maxlength="20"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="123">
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Bairro</label>
                    <input type="text" name="neighborhood" id="neighborhood"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="Bairro">
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Complemento</label>
                    <input type="text" name="address_complement" maxlength="100"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="Apto 42, Bloco B...">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Cidade</label>
                    <input type="text" name="city"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="São Paulo">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">UF</label>
                    <select name="state"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                        <option value="">—</option>
                        <?php foreach (['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'] as $uf): ?>
                            <option value="<?= $uf ?>"><?= $uf ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Seção: CRM -->
        <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800">
            <h4 class="text-sm font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide mb-4">Informações Comerciais</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                        Etapa do Funil <span class="text-red-500">*</span>
                    </label>
                    <select name="pipeline_stage_id" id="pipeline_stage_select" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?= $stage['id'] ?>"
                                data-venda-fechada="<?= !empty($stage['is_won_stage']) ? '1' : '0' ?>">
                                <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="closed_at_wrapper" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Data de Fechamento</label>
                    <input type="text" name="closed_at" id="closed_at" maxlength="10"
                        placeholder="DD/MM/AAAA"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Responsável</label>
                    <select name="assigned_to"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
                        <option value="">Sem responsável</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= ($user['id'] == ($_SESSION['user']['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Crédito contratado (R$)</label>
                    <input type="text" name="deal_value" data-mask="currency"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="R$ 0,00">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Origem do Lead</label>
                    <select name="source" id="source_select"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500">
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Nome de quem indicou</label>
                    <input type="text" name="referido_por" id="referido_por"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="Nome da pessoa que indicou">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Nota</label>
                    <textarea name="notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        placeholder="Observações gerais sobre este cliente..."></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-800/30 dark:border-t dark:border-zinc-800 flex justify-end gap-3">
            <a href="<?= APP_URL ?>/clients"
                class="px-4 py-2 border border-gray-300 dark:border-zinc-700 text-gray-700 dark:text-zinc-200 rounded-lg text-sm hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors">
                Cancelar
            </a>
            <button type="submit"
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">
                Salvar Cliente
            </button>
        </div>
    </form>
</div>
