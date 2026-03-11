<?php
/**
 * View: clients/edit.php
 * Variáveis: $client, $stages, $users, $csrf_token
 */
// Função auxiliar para repopular os campos com os valores atuais do cliente
function val(array $client, string $key): string {
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
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
                    <select name="state" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">—</option>
                        <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                        <option value="<?= $uf ?>" <?= val($client, 'state') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Etapa do Funil <span class="text-red-500">*</span></label>
                    <select name="pipeline_stage_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($stages as $stage): ?>
                        <option value="<?= $stage['id'] ?>" <?= $client['pipeline_stage_id'] == $stage['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stage['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                    <select name="assigned_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">Sem responsável</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $client['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor do Negócio (R$)</label>
                    <input type="text" name="deal_value" value="<?= number_format($client['deal_value'] ?? 0, 2, ',', '.') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Origem</label>
                    <select name="source" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">Desconhecida</option>
                        <?php foreach (['Google Ads','Indicação','LinkedIn','Instagram','Site Orgânico','Evento','Cold Call','Outro'] as $src): ?>
                        <option value="<?= $src ?>" <?= val($client,'source') === $src ? 'selected' : '' ?>><?= $src ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"><?= val($client, 'notes') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="px-6 py-4 bg-gray-50 flex flex-col sm:flex-row justify-between gap-3">
            <!-- Botão de excluir (esquerda) -->
            <form method="POST" action="<?= APP_URL ?>/clients/<?= $client['id'] ?>/delete"
                  onsubmit="return confirm('Tem certeza que deseja remover este cliente?')">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="px-4 py-2 border border-red-300 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-colors">
                    🗑️ Remover Cliente
                </button>
            </form>
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
</div>
