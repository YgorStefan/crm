(function () {
    'use strict';

    // Verifica se os dados e o Chart.js estão disponíveis
    if (typeof pipelineData === 'undefined' || typeof Chart === 'undefined') return;

    // Define fontes e estilos padrão para todos os gráficos
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6b7280'; // text-gray-500

    // Barras: Número de Clientes por Etapa
    const ctxBar = document.getElementById('chartPipeline');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: pipelineData.labels,
                datasets: [{
                    label: 'Clientes',
                    data: pipelineData.counts,
                    // Usa as cores de cada etapa (definidas no banco de dados)
                    backgroundColor: pipelineData.colors.map(c => c + 'cc'), // 80% opacidade
                    borderColor: pipelineData.colors,
                    borderWidth: 2,
                    borderRadius: 6,  // bordas arredondadas nas barras
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }, // Não precisa de legenda (labels no eixo X)
                    tooltip: {
                        callbacks: {
                            // Personaliza o tooltip: "Prospecção: 5 clientes"
                            label: ctx => ` ${ctx.parsed.y} cliente${ctx.parsed.y !== 1 ? 's' : ''}`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            // Garante que o eixo Y mostre apenas números inteiros
                            stepSize: 1,
                            callback: val => Number.isInteger(val) ? val : null,
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Rosca (Doughnut): Valor Total (R$) por Etapa
    const ctxDoughnut = document.getElementById('chartValues');
    if (ctxDoughnut) {
        // Filtra etapas sem valor para não poluir o gráfico
        const hasValue = pipelineData.values.some(v => v > 0);

        if (!hasValue) {
            // Se não houver valores, exibe uma mensagem amigável
            ctxDoughnut.parentElement.innerHTML +=
                '<p class="text-center text-gray-400 text-sm mt-4">Nenhum valor de negócio registrado ainda.</p>';
            return;
        }

        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: pipelineData.labels,
                datasets: [{
                    data: pipelineData.values,
                    backgroundColor: pipelineData.colors.map(c => c + 'cc'),
                    borderColor: pipelineData.colors,
                    borderWidth: 2,
                    hoverOffset: 8, // Destaque ao passar o mouse
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%', // Define o "buraco" central da rosca
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 12,
                            boxWidth: 12,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            // Formata o valor em R$ no tooltip
                            label: ctx => {
                                const val = ctx.parsed;
                                const formatted = new Intl.NumberFormat('pt-BR', {
                                    style: 'currency', currency: 'BRL'
                                }).format(val);
                                return ` ${ctx.label}: ${formatted}`;
                            }
                        }
                    }
                }
            }
        });
    }

})();
