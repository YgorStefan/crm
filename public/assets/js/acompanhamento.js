/**
 * acompanhamento.js — Gráfico de clientes por etapa do pipeline (Chart.js 4)
 * Eixo esquerdo: etapas do pipeline | Eixo direito: Abordados (escala independente)
 */

(function () {
    'use strict';

    if (typeof acompanhamentoData === 'undefined' || typeof Chart === 'undefined') return;

    const canvas = document.getElementById('chartAcompanhamento');
    if (!canvas) return;

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6b7280';

    const abordados = typeof acompanhamentoAbordados !== 'undefined' ? acompanhamentoAbordados : 0;

    // Labels: Abordados primeiro, depois as etapas
    const labels = ['Abordados'].concat(acompanhamentoData.map(function (s) { return s.name; }));

    // Cores das etapas
    function hexToRgba(hex, alpha) {
        var h = hex.replace('#', '');
        var r = parseInt(h.substring(0, 2), 16);
        var g = parseInt(h.substring(2, 4), 16);
        var b = parseInt(h.substring(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    var stageBg     = acompanhamentoData.map(function (s) { return hexToRgba(s.color, 0.75); });
    var stageBorder = acompanhamentoData.map(function (s) { return s.color; });

    // Dataset 1: Abordados — usa eixo direito (yAbordados)
    // Valores nulos nas posições das etapas para não renderizar barra
    var dataAbordados = [abordados].concat(acompanhamentoData.map(function () { return null; }));

    // Dataset 2: Etapas — usa eixo esquerdo (yStages)
    // Valor nulo na posição de Abordados
    var dataStages = [null].concat(acompanhamentoData.map(function (s) { return parseInt(s.total, 10); }));

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Abordados',
                    data: dataAbordados,
                    backgroundColor: ['rgba(20,184,166,0.75)'],
                    borderColor: ['#14b8a6'],
                    borderWidth: 2,
                    borderRadius: 5,
                    borderSkipped: false,
                    yAxisID: 'yAbordados',
                },
                {
                    label: 'Clientes',
                    data: dataStages,
                    backgroundColor: stageBg,
                    borderColor: stageBorder,
                    borderWidth: 2,
                    borderRadius: 5,
                    borderSkipped: false,
                    yAxisID: 'yStages',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: function (item) {
                        return item.parsed.y !== null;
                    },
                    callbacks: {
                        label: function (ctx) {
                            var suffix = ctx.dataset.label === 'Abordados' ? ' contato(s)' : ' cliente(s)';
                            return ' ' + ctx.parsed.y + suffix;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 12 } },
                },
                yStages: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function (val) { return Number.isInteger(val) ? val : null; }
                    },
                    grid: { color: '#f1f5f9' },
                    title: {
                        display: true,
                        text: 'Clientes',
                        font: { size: 11 },
                        color: '#9ca3af',
                    }
                },
                yAbordados: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function (val) { return Number.isInteger(val) ? val : null; }
                    },
                    grid: { drawOnChartArea: false },
                }
            }
        }
    });

})();
