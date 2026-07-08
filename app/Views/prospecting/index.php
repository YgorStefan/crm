<?php
/** @var bool $has_gmaps_key */
$_vV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/vendor/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/vendor/' . $f) : '0';
$_jsV = static fn(string $f): string => is_file(__DIR__ . '/../../../public/assets/js/' . $f)
    ? (string) filemtime(__DIR__ . '/../../../public/assets/js/' . $f) : '0';

$pageScripts = '
<script nonce="' . CSP_NONCE . '" src="' . APP_URL . '/assets/vendor/xlsx.full.min.js?v=' . $_vV('xlsx.full.min.js') . '"></script>
<script nonce="' . CSP_NONCE . '" defer src="' . APP_URL . '/assets/js/prospecting.js?v=' . $_jsV('prospecting.js') . '"></script>';
unset($_vV, $_jsV);
?>

<?php if (!$has_gmaps_key): ?>
<div class="mb-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-lg px-4 py-3 flex items-start gap-3">
    <span class="text-amber-500 mt-0.5">⚠️</span>
    <div>
        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Chave de API não configurada</p>
        <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
            Para usar a prospecção, um admin deve adicionar a Chave API Google Maps em
            <a href="<?= APP_URL ?>/settings" class="underline hover:text-amber-900 dark:hover:text-amber-200">Configurações</a>.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Painel de busca -->
<div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 p-5 mb-5">
    <h3 class="text-base font-semibold text-gray-700 dark:text-zinc-200 mb-1">Buscar Leads</h3>
    <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">Encontre empresas pelo Google Maps e exporte os contatos para Excel.</p>

    <form id="prospectingForm" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="searchTerm" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Termo de Busca
                </label>
                <input type="text" id="searchTerm" placeholder="Ex: Dentista, Oficina mecânica"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    <?= !$has_gmaps_key ? 'disabled' : '' ?>>
            </div>
            <div>
                <label for="searchLocation" class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                    Cidade / Estado
                </label>
                <input type="text" id="searchLocation" placeholder="Ex: Curitiba PR"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    <?= !$has_gmaps_key ? 'disabled' : '' ?>>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="onlyWithPhone"
                class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 dark:bg-zinc-700 dark:border-zinc-600"
                <?= !$has_gmaps_key ? 'disabled' : '' ?>>
            <label for="onlyWithPhone" class="text-sm text-gray-600 dark:text-zinc-400 cursor-pointer">
                Apenas leads com telefone
            </label>
        </div>

        <div>
            <button type="submit" id="searchBtn"
                <?= !$has_gmaps_key ? 'disabled' : '' ?>
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium px-5 py-2 rounded-lg text-sm transition-colors">
                <span id="searchBtnIcon">🔍</span>
                <span id="searchBtnText">Buscar Leads</span>
                <span id="searchBtnProgress" class="hidden text-xs font-normal opacity-80"></span>
            </button>
        </div>

        <!-- Barra de progresso -->
        <div id="progressBar" class="hidden">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-zinc-400 mb-1">
                <span id="progressLabel">Iniciando busca...</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-zinc-700 rounded-full h-1.5">
                <div id="progressFill" class="bg-indigo-600 h-1.5 rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>
    </form>
</div>

<!-- Área de resultados -->
<div id="resultsArea" class="hidden">
    <!-- Estatísticas -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
        <p id="statsLine" class="text-sm text-gray-600 dark:text-zinc-400"></p>
        <button id="downloadBtn" disabled
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium px-4 py-2 rounded-lg text-sm transition-colors">
            ⬇️ <span>Baixar Excel</span>
        </button>
    </div>

    <!-- Tabela -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-zinc-800 border-b border-gray-100 dark:border-zinc-700">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-zinc-300 w-1/2">Nome da Empresa</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-zinc-300 w-1/3">Telefone</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-zinc-300">Status</th>
                    </tr>
                </thead>
                <tbody id="resultsBody" class="divide-y divide-gray-100 dark:divide-zinc-800">
                </tbody>
            </table>
        </div>
    </div>
</div>
