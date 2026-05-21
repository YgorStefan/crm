/**
 * prospecting.js — Módulo de Prospecção de Leads
 *
 * Fluxo: 3 chamadas AJAX sequenciais à API Places v1 com delay de 2s
 * entre páginas (exigido pelo Google para o nextPageToken ficar válido).
 * Resultados exibidos em tempo real na tabela. SheetJS gera o .xlsx no browser.
 */

(function () {
    'use strict';

    const APP_URL     = document.querySelector('meta[name="app-url"]')?.content ?? '';
    const CSRF_TOKEN  = () => document.getElementById('csrfToken')?.value ?? '';

    const form         = document.getElementById('prospectingForm');
    const searchBtn    = document.getElementById('searchBtn');
    const btnText      = document.getElementById('searchBtnText');
    const btnProgress  = document.getElementById('searchBtnProgress');
    const progressBar  = document.getElementById('progressBar');
    const progressFill = document.getElementById('progressFill');
    const progressLbl  = document.getElementById('progressLabel');
    const progressPct  = document.getElementById('progressPercent');
    const resultsArea  = document.getElementById('resultsArea');
    const resultsBody  = document.getElementById('resultsBody');
    const statsLine    = document.getElementById('statsLine');
    const downloadBtn  = document.getElementById('downloadBtn');

    /** Todos os leads acumulados das até 3 páginas */
    let allLeads = [];

    // ── Utilitários ────────────────────────────────────────────────

    function setProgress(pct, label) {
        progressFill.style.width = pct + '%';
        progressPct.textContent  = pct + '%';
        progressLbl.textContent  = label;
    }

    function setSearching(active) {
        searchBtn.disabled = active;
        if (active) {
            btnText.textContent = 'Buscando...';
            progressBar.classList.remove('hidden');
            progressFill.style.width = '0%';
        } else {
            btnText.textContent = 'Buscar Leads';
            btnProgress.classList.add('hidden');
            btnProgress.textContent = '';
        }
    }

    function appendRows(places) {
        places.forEach(function (p) {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition-colors';

            const phoneDisplay = p.phone !== '' ? p.phone : '—';
            const statusBadge  = p.status === 'com_telefone'
                ? '<span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded-full">✅ Com tel.</span>'
                : '<span class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded-full">⚠️ Sem tel.</span>';

            tr.innerHTML =
                '<td class="px-4 py-3 text-gray-800 dark:text-zinc-200 font-medium">' + escHtml(p.name) + '</td>' +
                '<td class="px-4 py-3 text-gray-600 dark:text-zinc-400">' + escHtml(phoneDisplay) + '</td>' +
                '<td class="px-4 py-3">' + statusBadge + '</td>';

            resultsBody.appendChild(tr);
        });
    }

    function updateStats() {
        const total    = allLeads.length;
        const withTel  = allLeads.filter(function (l) { return l.status === 'com_telefone'; }).length;
        const pct      = total > 0 ? Math.round((withTel / total) * 100) : 0;
        statsLine.textContent = total + ' lead(s) encontrado(s) · ' + withTel + ' com telefone (' + pct + '% de aproveitamento)';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function delay(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    // ── Chamada AJAX ───────────────────────────────────────────────

    async function fetchPage(term, location, pageToken, onlyWithPhone) {
        const body = new URLSearchParams({
            _csrf_token:   CSRF_TOKEN(),
            term:          term,
            location:      location,
            pageToken:     pageToken ?? '',
            onlyWithPhone: onlyWithPhone ? 'true' : 'false',
        });

        const res = await fetch(APP_URL + '/api/prospecting/search', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept':       'application/json',
                'X-CSRF-Token': CSRF_TOKEN(),
            },
            body:    body.toString(),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            throw new Error(data.error ?? 'Erro desconhecido');
        }

        return data; // { places, nextPageToken, total }
    }

    // ── Busca principal ────────────────────────────────────────────

    async function runSearch(term, location, onlyWithPhone) {
        allLeads = [];
        resultsBody.innerHTML = '';
        resultsArea.classList.remove('hidden');
        downloadBtn.disabled  = true;

        setSearching(true);
        setProgress(0, 'Iniciando busca...');

        let pageToken  = null;
        const maxPages = 3;

        for (let page = 1; page <= maxPages; page++) {
            setProgress(Math.round(((page - 1) / maxPages) * 100), 'Buscando página ' + page + ' de ' + maxPages + '...');

            if (page > 1 && pageToken) {
                setProgress(Math.round(((page - 1) / maxPages) * 100), 'Aguardando sincronização do Google... (2s)');
                await delay(1000);
                setProgress(Math.round(((page - 1) / maxPages) * 100), 'Aguardando sincronização do Google... (1s)');
                await delay(1000);
            }

            const data = await fetchPage(term, location, pageToken, onlyWithPhone);

            if (data.places && data.places.length > 0) {
                allLeads = allLeads.concat(data.places);
                appendRows(data.places);
                updateStats();
            }

            setProgress(Math.round((page / maxPages) * 100), 'Página ' + page + ' concluída');

            pageToken = data.nextPageToken ?? null;
            if (!pageToken) break;
        }

        setProgress(100, 'Busca concluída!');
        setSearching(false);

        if (allLeads.length === 0) {
            if (typeof window.crmToast === 'function') {
                window.crmToast('Nenhum lead encontrado para esta busca.', 'warning');
            }
        } else {
            if (typeof window.crmToast === 'function') {
                window.crmToast(allLeads.length + ' lead(s) encontrado(s)!', 'success');
            }
            downloadBtn.disabled = false;
            downloadBtn.classList.add('animate-pulse');
            setTimeout(function () { downloadBtn.classList.remove('animate-pulse'); }, 2000);
        }
    }

    // ── Submit do formulário ────────────────────────────────────────

    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const term     = document.getElementById('searchTerm')?.value.trim() ?? '';
            const location = document.getElementById('searchLocation')?.value.trim() ?? '';
            const onlyPhone = document.getElementById('onlyWithPhone')?.checked ?? false;

            if (!term || !location) {
                if (typeof window.crmToast === 'function') {
                    window.crmToast('Preencha o termo de busca e a cidade.', 'error');
                }
                return;
            }

            try {
                await runSearch(term, location, onlyPhone);
            } catch (err) {
                setSearching(false);
                setProgress(0, '');
                progressBar.classList.add('hidden');
                const msg = err.message ?? 'Erro inesperado. Tente novamente.';
                if (typeof window.crmToast === 'function') {
                    window.crmToast(msg, 'error');
                }
            }
        });
    }

    // ── Exportação Excel ───────────────────────────────────────────

    if (downloadBtn) {
        downloadBtn.addEventListener('click', function () {
            if (!allLeads.length || typeof XLSX === 'undefined') return;

            const rows = [['Nome da Empresa', 'Telefone', 'Status']].concat(
                allLeads.map(function (l) {
                    return [
                        l.name,
                        l.phone !== '' ? l.phone : '',
                        l.status === 'com_telefone' ? 'Com telefone' : 'Sem telefone',
                    ];
                })
            );

            const ws = XLSX.utils.aoa_to_sheet(rows);
            ws['!cols'] = [{ wch: 45 }, { wch: 20 }, { wch: 16 }];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Leads');

            const term     = document.getElementById('searchTerm')?.value.trim().replace(/[^a-zA-Z0-9]/g, '-') ?? 'leads';
            const filename = 'prospeccao-' + term + '-' + new Date().toISOString().slice(0, 10) + '.xlsx';
            XLSX.writeFile(wb, filename);
        });
    }
})();
