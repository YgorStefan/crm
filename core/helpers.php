<?php
// core/helpers.php — Funções auxiliares globais da aplicação
// Incluído automaticamente via core/bootstrap.php no processo de boot.

/**
 * Gera um link de navegação para o menu lateral, destacando o item ativo.
 *
 * @param string $href        Caminho relativo da rota (ex: '/dashboard').
 * @param string $svgIcon     SVG inline (conteúdo HTML, não escapado).
 * @param string $label       Texto do link.
 * @param string $currentPath Caminho atual da requisição para comparação de ativo.
 * @return string             Marcação HTML do link de navegação.
 */
function navLink(string $href, string $svgIcon, string $label, string $currentPath): string
{
    $active = ($currentPath === $href || str_starts_with($currentPath, $href . '/'));
    $base   = 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors';
    $cls    = $active
        ? "$base bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
        : "$base text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200";

    $safeHref  = htmlspecialchars(APP_URL . $href, ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    // $svgIcon is trusted developer-authored inline SVG, never user input
    return "<a href=\"{$safeHref}\" class=\"{$cls}\">{$svgIcon}<span class=\"nav-label ml-1\">{$safeLabel}</span></a>";
}

/**
 * Formata um valor numérico como moeda no padrão brasileiro (R$).
 *
 * Aceita float ou string numérica. Retorna string vazia para valores inválidos.
 *
 * @param float|string $amount Valor a formatar.
 * @return string              Valor formatado, ex: "R$ 1.234,56".
 */
function format_currency(float|string $amount): string
{
    $value = is_string($amount) ? (float) str_replace(',', '.', $amount) : (float) $amount;

    if (!is_finite($value)) {
        return '';
    }

    return 'R$ ' . number_format($value, 2, ',', '.');
}
