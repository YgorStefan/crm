/**
 * acompanhamento.js — Grafico de Acompanhamento Semanal (Chart.js 4)
 * ============================================================
 * Le acompanhamentoData injetado pelo PHP e renderiza um grafico
 * de barras agrupadas com duas series:
 *   - Importados: total importado por semana (indigo)
 *   - Abordados:  total com data_mensagem preenchida por semana (emerald)
 *
 * Filtro client-side via select#filtroLista — nenhum AJAX necessario.
 * Chart.js 4.4.0 carregado globalmente via CDN em main.php.
 * ============================================================
 */

(function () {
    'use strict';

    // Guarda de seguranca — aborta se dependencias ausentes
    if (typeof acompanhamentoData === 'undefined' || typeof Chart === 'undefined') return;

    const canvas = document.getElementById('chartAcompanhamento');
    if (!canvas) return;

    // --- Configuracao global (mesma de dashboard.js) ---
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6b7280';

    // --- Cores das series (per D-01/D-02 e Claude discretion) ---
    const COR_IMPORTADOS_BG   = 'rgba(99, 102, 241, 0.75)';  // indigo-500 com 75% opacidade
    const COR_IMPORTADOS_BDR  = 'rgb(99, 102, 241)';
    const COR_ABORDADOS_BG    = 'rgba(16, 185, 129, 0.75)';  // emerald-500 com 75% opacidade
    const COR_ABORDADOS_BDR   = 'rgb(16, 185, 129)';

    // --- Funcao auxiliar: monta config de datasets para uma lista ---
    function buildDatasets(listaKey) {
        const lista = acompanhamentoData.listas[listaKey] || acompanhamentoData.listas['Todos'];
        return [
            {
                label: 'Importados',
                data: lista.importados.map(Number),
                backgroundColor: COR_IMPORTADOS_BG,
                borderColor:     COR_IMPORTADOS_BDR,
                borderWidth: 2,
                borderRadius: 5,
                borderSkipped: false,
            },
            {
                label: 'Abordados',
                data: lista.abordados.map(Number),
                backgroundColor: COR_ABORDADOS_BG,
                borderColor:     COR_ABORDADOS_BDR,
                borderWidth: 2,
                borderRadius: 5,
                borderSkipped: false,
            }
        ];
    }

    // --- Instancia o grafico com a selecao inicial ("Todos") ---
    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels:   acompanhamentoData.semanas,
            datasets: buildDatasets('Todos'),
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',      // tooltip mostra as duas series ao mesmo tempo
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false,   // Legenda feita manualmente na view (HTML)
                },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 12 } },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function (val) {
                            return Number.isInteger(val) ? val : null;
                        }
                    },
                    grid: { color: '#f1f5f9' },
                }
            }
        }
    });

    // --- Filtro client-side: atualiza datasets ao mudar o dropdown ---
    const select = document.getElementById('filtroLista');
    if (select) {
        select.addEventListener('change', function () {
            const listaKey = this.value;
            const novosDatasets = buildDatasets(listaKey);
            // Atualiza dados sem recriar o grafico (Chart.js update API)
            chart.data.datasets[0].data = novosDatasets[0].data;
            chart.data.datasets[1].data = novosDatasets[1].data;
            chart.update();
        });
    }

})();
