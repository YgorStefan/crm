/**
 * masks.js — Mascaras de input reutilizaveis para o CRM.
 *
 * Uso:
 *   <input data-mask="currency"> — formata como moeda BRL: 12345 → R$ 123,45
 *   <input data-mask="digits">   — permite apenas digitos
 *
 * Para forms: o valor enviado por inputs `data-mask="currency"` e convertido
 * para numero (123.45) antes do submit, automaticamente, para o campo `name`.
 *
 * Auto-discovery no DOMContentLoaded. Para aplicar em inputs criados
 * dinamicamente, chamar `window.CRMMasks.apply(input)`.
 */
(function () {
    'use strict';

    function onlyDigits(str) {
        return String(str || '').replace(/\D+/g, '');
    }

    function formatCurrencyBRL(digits) {
        if (!digits) return '';
        // Garante minimo 3 digitos (00,00 -> 000)
        digits = digits.replace(/^0+/, '') || '0';
        if (digits.length < 3) digits = digits.padStart(3, '0');
        var cents = digits.slice(-2);
        var reais = digits.slice(0, -2);
        var reaisFmt = reais.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return 'R$ ' + reaisFmt + ',' + cents;
    }

    function parseCurrencyValue(formatted) {
        var digits = onlyDigits(formatted);
        if (!digits) return '';
        var num = parseInt(digits, 10) / 100;
        return num.toFixed(2);
    }

    function applyCurrency(input) {
        if (input.dataset.maskApplied) return;
        input.dataset.maskApplied = 'currency';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'off');

        // Inicializa se ja tiver valor (ex: edit)
        if (input.value) {
            var initialDigits = onlyDigits(input.value);
            // Se o valor inicial parece um numero decimal (ex: "1234.56"), converte
            if (/^\d+\.\d{1,2}$/.test(input.value)) {
                var num = parseFloat(input.value);
                initialDigits = String(Math.round(num * 100));
            }
            input.value = formatCurrencyBRL(initialDigits);
        }

        input.addEventListener('input', function () {
            var caretFromEnd = input.value.length - (input.selectionStart || input.value.length);
            input.value = formatCurrencyBRL(onlyDigits(input.value));
            // Reposiciona caret aproximadamente
            var newPos = Math.max(0, input.value.length - caretFromEnd);
            try { input.setSelectionRange(newPos, newPos); } catch (e) { /* ignore */ }
        });

        // Antes do submit, converte o valor para numero puro no campo
        var form = input.closest('form');
        if (form && !form.dataset.maskHooked) {
            form.dataset.maskHooked = '1';
            form.addEventListener('submit', function () {
                var inputs = form.querySelectorAll('input[data-mask="currency"]');
                inputs.forEach(function (inp) {
                    inp.value = parseCurrencyValue(inp.value);
                });
            });
        }
    }

    function applyDigitsOnly(input) {
        if (input.dataset.maskApplied) return;
        input.dataset.maskApplied = 'digits';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'off');

        input.addEventListener('input', function () {
            var clean = onlyDigits(input.value);
            if (input.value !== clean) input.value = clean;
        });
        input.addEventListener('keypress', function (e) {
            if (e.key && e.key.length === 1 && !/\d/.test(e.key)) {
                e.preventDefault();
            }
        });
    }

    function apply(input) {
        var type = input && input.dataset && input.dataset.mask;
        if (type === 'currency') applyCurrency(input);
        else if (type === 'digits') applyDigitsOnly(input);
    }

    function applyAll(root) {
        (root || document).querySelectorAll('input[data-mask]').forEach(apply);
    }

    window.CRMMasks = { apply: apply, applyAll: applyAll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { applyAll(); });
    } else {
        applyAll();
    }
})();
