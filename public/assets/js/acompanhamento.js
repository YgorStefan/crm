/**
 * acompanhamento.js — Gráfico de clientes por etapa do pipeline (Chart.js 4)
 */

(function () {
    'use strict';

    if (typeof acompanhamentoData === 'undefined' || typeof Chart === 'undefined') return;

    const canvas = document.getElementById('chartAcompanhamento');
    if (!canvas) return;

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6b7280';

    // "Abordados" como primeira barra com cor teal distinta
    const abordados = typeof acompanhamentoAbordados !== 'undefined' ? acompanhamentoAbordados : 0;

    const labels     = ['Abordados'].concat(acompanhamentoData.map(function (s) { return s.name; }));
    const totals     = [abordados].concat(acompanhamentoData.map(function (s) { return parseInt(s.total, 10); }));
    const bgColors   = ['rgba(20,184,166,0.75)'].concat(acompanhamentoData.map(function (s) {
        var hex = s.color.replace('#', '');
        var r = parseInt(hex.substring(0, 2), 16);
        var g = parseInt(hex.substring(2, 4), 16);
        var b = parseInt(hex.substring(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',0.75)';
    }));
    const borderColors = ['#14b8a6'].concat(acompanhamentoData.map(function (s) { return s.color; }));

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Quantidade',
                data: totals,
                backgroundColor: bgColors,
                borderColor: borderColors,
                borderWidth: 2,
                borderRadius: 5,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.parsed.y + (ctx.label === 'Abordados' ? ' contato(s)' : ' cliente(s)');
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

})();
