(function () {
    'use strict';

    if (typeof pipelineData === 'undefined' || typeof Chart === 'undefined') return;

    Chart.defaults.font.family = "'Inter', sans-serif";

    let chartInstance = null;

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function getChartColors() {
        if (isDark()) {
            return {
                gridColor: 'rgba(255,255,255,0.06)',
                tickColor: '#94a3b8',
                tooltipBg: '#1e293b',
                tooltipBorder: 'rgba(255,255,255,0.1)',
            };
        }
        return {
            gridColor: '#f1f5f9',
            tickColor: '#6b7280',
            tooltipBg: '#fff',
            tooltipBorder: '#e2e8f0',
        };
    }

    function renderChart() {
        const ctxBar = document.getElementById('chartPipeline');
        if (!ctxBar) return;

        if (chartInstance) {
            chartInstance.destroy();
        }

        const c = getChartColors();

        chartInstance = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: pipelineData.labels,
                datasets: [{
                    label: 'Clientes',
                    data: pipelineData.counts,
                    backgroundColor: pipelineData.colors.map(c => c + 'cc'),
                    borderColor: pipelineData.colors,
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: c.tooltipBg,
                        borderColor: c.tooltipBorder,
                        borderWidth: 1,
                        titleColor: isDark() ? '#e2e8f0' : '#374151',
                        bodyColor: isDark() ? '#94a3b8' : '#6b7280',
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} cliente${ctx.parsed.y !== 1 ? 's' : ''}`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: c.tickColor,
                            callback: val => Number.isInteger(val) ? val : null,
                        },
                        grid: { color: c.gridColor }
                    },
                    x: {
                        ticks: { color: c.tickColor },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    renderChart();

    // Re-render quando o tema muda
    document.addEventListener('themeChange', renderChart);

})();
