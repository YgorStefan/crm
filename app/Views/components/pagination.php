<?php
/**
 * Componente de paginação reutilizável.
 *
 * Espera a variável $pagination com as chaves:
 *   - current_page  (int)
 *   - total_pages   (int)
 *   - total_items   (int)
 *   - per_page      (int)
 *   - base_url      (string)  ex: '/clients'
 *   - query_params  (array)   filtros ativos que devem ser preservados nas URLs
 *
 * Uso:
 *   <?php require VIEW_PATH . '/components/pagination.php'; ?>
 */

if (!isset($pagination) || !is_array($pagination)) {
    return;
}

$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages  = (int) ($pagination['total_pages']  ?? 1);
$totalItems  = (int) ($pagination['total_items']  ?? 0);
$perPage     = (int) ($pagination['per_page']     ?? 25);
$baseUrl     = $pagination['base_url'] ?? '';
$queryParams = is_array($pagination['query_params'] ?? null) ? $pagination['query_params'] : [];

if ($totalItems === 0) {
    return;
}

// Calcula intervalo exibido  (ex: "Mostrando 26–50 de 120 resultados")
$rangeStart = ($currentPage - 1) * $perPage + 1;
$rangeEnd   = min($currentPage * $perPage, $totalItems);

/**
 * Monta a URL de uma página preservando os filtros activos.
 */
function paginationUrl(string $baseUrl, array $queryParams, int $page, int $perPage): string
{
    $params = array_filter($queryParams, fn($v) => $v !== '' && $v !== null);
    $params['page']     = $page;
    $params['per_page'] = $perPage;
    return htmlspecialchars($baseUrl . '?' . http_build_query($params), ENT_QUOTES, 'UTF-8');
}

/**
 * Calcula os números de página a exibir com elipses.
 * Retorna um array de int|null onde null representa "...".
 */
function paginationRange(int $current, int $total): array
{
    if ($total <= 1) {
        return [1];
    }

    $delta = 2; // páginas adjacentes à atual mostradas de cada lado
    $range = [];

    for ($i = max(1, $current - $delta); $i <= min($total, $current + $delta); $i++) {
        $range[] = $i;
    }

    // Prepend: sempre mostrar página 1 e ellipsis se necessário
    if ($range[0] > 1) {
        if ($range[0] > 2) {
            array_unshift($range, null); // ellipsis
        }
        array_unshift($range, 1);
    }

    // Append: sempre mostrar última página e ellipsis se necessário
    $last = end($range);
    if ($last < $total) {
        if ($last < $total - 1) {
            $range[] = null; // ellipsis
        }
        $range[] = $total;
    }

    return $range;
}

$pages = paginationRange($currentPage, $totalPages);
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4 px-1">

    <!-- Informações de resultado e seletor de itens por página -->
    <div class="flex items-center gap-3 text-sm text-gray-500">
        <span>
            Mostrando
            <strong class="text-gray-700"><?= $rangeStart ?></strong>–<strong class="text-gray-700"><?= $rangeEnd ?></strong>
            de
            <strong class="text-gray-700"><?= $totalItems ?></strong>
            resultado(s)
        </span>

        <label class="flex items-center gap-1 text-sm text-gray-500">
            Itens por página:
            <select id="perPageSelect"
                    class="per-page-select ml-1 px-2 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    data-base-url="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>"
                    data-query-params="<?= htmlspecialchars(http_build_query(array_filter($queryParams, fn($v) => $v !== '' && $v !== null)), ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ([15, 25, 50, 100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <!-- Botões de navegação de página -->
    <?php if ($totalPages > 1): ?>
    <nav class="flex items-center gap-1" aria-label="Paginação">

        <!-- Anterior -->
        <?php if ($currentPage > 1): ?>
            <a href="<?= paginationUrl($baseUrl, $queryParams, $currentPage - 1, $perPage) ?>"
               class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                Anterior
            </a>
        <?php else: ?>
            <span class="px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-400 cursor-not-allowed">
                Anterior
            </span>
        <?php endif; ?>

        <!-- Números de página com ellipsis -->
        <?php foreach ($pages as $p): ?>
            <?php if ($p === null): ?>
                <span class="px-2 py-1.5 text-sm text-gray-400">...</span>
            <?php elseif ($p === $currentPage): ?>
                <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold">
                    <?= $p ?>
                </span>
            <?php else: ?>
                <a href="<?= paginationUrl($baseUrl, $queryParams, $p, $perPage) ?>"
                   class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                    <?= $p ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Próximo -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= paginationUrl($baseUrl, $queryParams, $currentPage + 1, $perPage) ?>"
               class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                Próximo
            </a>
        <?php else: ?>
            <span class="px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-400 cursor-not-allowed">
                Próximo
            </span>
        <?php endif; ?>

    </nav>
    <?php endif; ?>

</div>

<script nonce="<?= CSP_NONCE ?>">
(function () {
    // Redireciona ao alterar "itens por página", resetando para página 1
    document.querySelectorAll('.per-page-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var base   = this.dataset.baseUrl || '';
            var query  = this.dataset.queryParams || '';
            var params = new URLSearchParams(query);
            params.set('per_page', this.value);
            params.set('page', '1');
            window.location.href = base + '?' + params.toString();
        });
    });
})();
</script>
