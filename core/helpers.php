<?php
// core/helpers.php — Funções auxiliares globais da aplicação
// Incluído automaticamente via core/bootstrap.php no processo de boot.

/**
 * Gera um link de navegação para o menu lateral, destacando o item ativo.
 *
 * @param string $href        Caminho relativo da rota (ex: '/dashboard').
 * @param string $icon        Ícone ou emoji exibido antes do rótulo.
 * @param string $label       Texto do link.
 * @param string $currentPath Caminho atual da requisição para comparação de ativo.
 * @return string             Marcação HTML do link de navegação.
 */
function navLink(string $href, string $icon, string $label, string $currentPath): string
{
    $active = ($currentPath === $href || str_starts_with($currentPath, $href . '/'));
    $base   = 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors';
    $cls    = $active
        ? "$base bg-indigo-600 text-white"
        : "$base text-indigo-200 hover:bg-indigo-800 hover:text-white";

    $safeHref  = htmlspecialchars(APP_URL . $href, ENT_QUOTES, 'UTF-8');
    $safeIcon  = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    return "<a href=\"{$safeHref}\" class=\"{$cls}\">{$safeIcon} <span>{$safeLabel}</span></a>";
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
