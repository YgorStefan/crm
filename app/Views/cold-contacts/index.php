<?php
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';
$pageScripts =
    '<script nonce="' . CSP_NONCE . '" defer src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>' .
    '<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/cold-contacts.js?v=' . $_jsV('cold-contacts.js') . '"></script>';
unset($_jsV);
$_canEdit = in_array($_SESSION['user']['role'] ?? '', ['admin', 'seller']) ? '1' : '0';
?>
<div data-crm-widget="cold-contacts" data-can-edit="<?= $_canEdit ?>">

<!-- Cabeçalho da página -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Contatos Frios</h3>
        <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Importação e gestão de listas de prospecção</p>
    </div>
</div>

<!-- Formulário de importação -->
<?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'seller'])): ?>
<div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-4 mb-8">
    <div class="flex items-center gap-2 mb-3">
        <h4 class="font-semibold text-gray-700 dark:text-zinc-200">Importar lista</h4>
        <span class="has-tooltip text-gray-400 dark:text-zinc-500 cursor-help"
              data-tooltip="Coluna A = Nome, Coluna B = Celular. Header opcional. Formatos: .csv, .xls, .xlsx">
            ❓
        </span>
    </div>
    <form method="POST" action="<?= APP_URL ?>/cold-contacts/import" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <!-- Tipo de lista -->
            <div>
                <label for="import-tipo-lista" class="block text-xs font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Tipo de lista <span class="text-red-500">*</span>
                </label>
                <input id="import-tipo-lista" type="text" name="tipo_lista" required maxlength="100"
                    placeholder="Ex: Lista Webinar Jan"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Upload do arquivo -->
            <div>
                <label for="import-csv-file" class="block text-xs font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Arquivo CSV/XLSX
                </label>
                <input id="import-csv-file" type="file" name="csv_file" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                    class="w-full text-sm text-gray-600 dark:text-zinc-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-300">
            </div>

            <!-- Telefone enviado -->
            <div>
                <label for="import-tel-enviado" class="block text-xs font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Final Tel. Enviado
                </label>
                <input id="import-tel-enviado" type="text" name="telefone_enviado" maxlength="4" placeholder="Ex: 1234"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Data Mensagem -->
            <div>
                <label for="import-data-mensagem" class="block text-xs font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Data do Envio
                </label>
                <input id="import-data-mensagem" type="date" name="data_mensagem" max="9999-12-31"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Botão -->
            <div>
                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors whitespace-nowrap">
                    Importar
                </button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Cards Mensais -->
<div>
    <h4 class="font-semibold text-gray-700 dark:text-zinc-200 mb-4">Importações por mês</h4>

    <?php if (empty($summaries)): ?>
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-10 text-center">
            <p class="text-gray-400 dark:text-zinc-500 text-sm">Nenhuma lista importada ainda. Use o formulário acima para importar seu
                primeiro CSV.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($summaries as $s): ?>
                <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-500 transition-all">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <h5 class="font-semibold text-gray-800 dark:text-white capitalize">
                            <?= htmlspecialchars($s['month_label'], ENT_QUOTES, 'UTF-8') ?>
                        </h5>
                    </div>
                    <div class="px-5 py-4 flex items-center justify-between gap-2">
                        <div>
                            <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400"><?= (int) $s['total'] ?></p>
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">contato(s)</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <!-- Botão abre modal -->
                            <button type="button"
                                class="btn-open-modal bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/50 dark:text-indigo-300 font-medium px-3 py-2 rounded-lg text-sm transition-colors"
                                data-year-month="<?= htmlspecialchars($s['mes_ano'], ENT_QUOTES, 'UTF-8') ?>"
                                data-month-label="<?= htmlspecialchars($s['month_label'], ENT_QUOTES, 'UTF-8') ?>">
                                Ver lista
                            </button>
                            <!-- Botão excluir mês -->
                            <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'seller'])): ?>
                            <button type="button"
                                class="btn-delete-month bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/20 dark:hover:bg-red-900/30 dark:text-red-400 px-2.5 py-2 rounded-lg transition-colors flex items-center justify-center"
                                data-year-month="<?= htmlspecialchars($s['mes_ano'], ENT_QUOTES, 'UTF-8') ?>"
                                data-month-label="<?= htmlspecialchars($s['month_label'], ENT_QUOTES, 'UTF-8') ?>"
                                title="Excluir">
                                🗑️
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($pagination_cards)): ?>
    <?php $pagination = $pagination_cards; require VIEW_PATH . '/components/pagination.php'; ?>
    <?php endif; ?>
</div>

<!-- Modal de listagem de contatos -->
<div id="modalColdContacts"
    class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">

        <!-- Header da modal -->
        <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between flex-shrink-0">
            <h4 id="modalTitle" class="font-semibold text-gray-800 dark:text-white text-lg">Contatos do mês</h4>
            <button id="btnCloseModal"
                class="text-gray-400 dark:text-zinc-500 hover:text-gray-600 dark:hover:text-zinc-300 text-2xl font-bold leading-none">&times;</button>
        </div>

        <!-- Filtros e exportação -->
        <div class="px-6 py-3 border-b border-gray-100 dark:border-zinc-800 flex flex-col sm:flex-row gap-3 items-end flex-shrink-0">
            <div>
                <label for="filterTipoLista" class="block text-xs font-medium text-gray-600 dark:text-zinc-400 mb-1">Filtrar por tipo de lista</label>
                <input type="text" id="filterTipoLista" placeholder="Tipo de lista"
                    class="w-36 px-3 py-1.5 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label for="filterDia" class="block text-xs font-medium text-gray-600 dark:text-zinc-400 mb-1">Filtrar por dia (1-31)</label>
                <input type="number" id="filterDia" min="1" max="31" placeholder="Dia"
                    class="w-24 px-3 py-1.5 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label for="filterTelEnviado" class="block text-xs font-medium text-gray-600 dark:text-zinc-400 mb-1">Filtrar por tel. enviado</label>
                <input type="text" id="filterTelEnviado" placeholder="Ex: 1234"
                    class="w-32 px-3 py-1.5 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <button id="btnApplyFilter"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-1.5 rounded-lg text-sm transition-colors">
                Filtrar
            </button>
            <button id="btnClearFilter"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 font-medium px-4 py-1.5 rounded-lg text-sm transition-colors">
                Limpar
            </button>
            <div class="flex-1"></div>
            <button id="btnExportCsv"
                class="bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-1.5 rounded-lg text-sm transition-colors">
                Exportar CSV
            </button>
        </div>

        <!-- Barra de ação em lote -->
        <div id="bulkBar"
            class="hidden px-6 py-2 bg-indigo-50 dark:bg-indigo-900/20 border-b border-indigo-100 dark:border-indigo-800/40 flex flex-wrap items-center gap-3 flex-shrink-0">
            <span id="bulkCount" class="text-sm font-medium text-indigo-700 dark:text-indigo-300"></span>

            <label for="bulkTelEnviado" class="text-sm text-gray-600 dark:text-zinc-300 font-medium">Tel:</label>
            <input type="text" id="bulkTelEnviado" maxlength="4" placeholder="Ex: 1234"
                title="Deixe em branco para manter, ou preencha para alterar em todos"
                class="w-20 px-2 py-1 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">

            <label for="bulkDataMensagem" class="text-sm text-gray-600 dark:text-zinc-300 font-medium ml-2">Data:</label>
            <input type="date" id="bulkDataMensagem" max="9999-12-31"
                title="Deixe em branco para manter, ou preencha para alterar em todos"
                class="w-32 px-2 py-1 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">

            <button id="btnBulkSave"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-1 rounded-lg text-sm transition-colors ml-auto">
                Atualizar Marcados
            </button>
            <button id="btnBulkCancel" class="text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200 text-sm font-medium">
                Cancelar
            </button>
        </div>

        <!-- Corpo da modal: tabela de contatos -->
        <div id="modalBody" class="flex-1 overflow-y-auto overflow-x-auto px-6 py-4">
            <p class="text-gray-400 dark:text-zinc-500 text-sm text-center">Carregando...</p>
        </div>

        <!-- Footer da modal: total visível -->
        <div class="px-6 py-3 border-t border-gray-100 dark:border-zinc-800 flex-shrink-0">
            <p id="modalTotal" class="text-xs text-gray-500 dark:text-zinc-400"></p>
        </div>
    </div>
</div>

</div>
