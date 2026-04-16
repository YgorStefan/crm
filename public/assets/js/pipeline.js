(function () {
    'use strict';

    // --- Referências globais ---
    const board = document.getElementById('kanbanBoard');
    const toast = document.getElementById('kanbanToast');
    const moveUrl = board?.dataset.moveUrl;   // URL da rota POST /pipeline/move
    const statsUrl = board?.dataset.statsUrl; // URL da rota GET /api/dashboard/stats
    const csrfToken = board?.dataset.csrf;    // Token CSRF da sessão (estável, sem rotação)

    if (!board) return; // Sai silenciosamente se o board não existir na página

    // Cartão que está sendo arrastado no momento
    let draggedCard = null;

    // Flag para evitar múltiplas requisições simultâneas (race condition CSRF)
    let isMoving = false;

    // EVENTOS NOS CARTÕES

    /**
     * Adiciona os event listeners em todos os cartões do board.
     * Chamada também após mover um cartão (para reanexar nos novos elementos).
     */
    function bindCardEvents() {
        document.querySelectorAll('.kanban-card').forEach(card => {
            // Remove listeners antigos para evitar duplicação
            card.removeEventListener('dragstart', onDragStart);
            card.removeEventListener('dragend', onDragEnd);

            card.addEventListener('dragstart', onDragStart);
            card.addEventListener('dragend', onDragEnd);
        });
    }

    /**
     * dragstart: dispara quando o usuário começa a arrastar um cartão.
     * Guarda a referência do cartão arrastado e aplica estilo visual.
     */
    function onDragStart(e) {
        draggedCard = this;
        // Pequeno delay para que o estado "fantasma" seja renderizado
        // antes de aplicar a classe de opacidade.
        setTimeout(() => this.classList.add('dragging'), 0);
        // Passa o ID do cliente no dataTransfer (método alternativo para
        // compatibilidade com browsers que bloqueiam acesso ao DOM durante drag)
        e.dataTransfer.setData('text/plain', this.dataset.clientId);
        e.dataTransfer.effectAllowed = 'move';
    }

    /**
     * dragend: dispara quando o arrastar termina (com ou sem soltar).
     * Remove os estilos visuais temporários.
     */
    function onDragEnd() {
        this.classList.remove('dragging');
        draggedCard = null;
        // Remove highlight de todas as zonas de drop
        document.querySelectorAll('.kanban-drop-zone').forEach(z => z.classList.remove('drag-over'));
    }

    document.querySelectorAll('.kanban-drop-zone').forEach(zone => {

        /**
         * dragover: mantém a zona "receptiva" ao cartão enquanto
         * o mouse está sobre ela. Sem preventDefault(), o browser
         * não permitiria o "drop".
         */
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function () {
            this.classList.remove('drag-over');
        });

        /**
         * drop: dispara quando o cartão é solto sobre a zona.
         * Move o elemento no DOM e chama a API para persistir.
         */
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            if (!draggedCard || isMoving) return;

            const newStageId = parseInt(this.dataset.stageId, 10);
            const oldStageId = parseInt(draggedCard.dataset.currentStage, 10);
            const clientId = parseInt(draggedCard.dataset.clientId, 10);

            // Sem mudança de coluna? Nada a fazer.
            if (newStageId === oldStageId) return;

            // 1. Move o cartão no DOM (feedback imediato ao usuário)
            const emptyPlaceholder = this.querySelector('.kanban-empty');
            if (emptyPlaceholder) emptyPlaceholder.remove(); // remove o "Arraste aqui"
            this.appendChild(draggedCard);
            draggedCard.dataset.currentStage = newStageId;

            // Atualiza os contadores e totais das colunas
            updateColumnCounters();

            // 2. Persiste a mudança via AJAX
            moveClient(clientId, newStageId);
        });
    });

    /**
     * Envia a mudança de etapa para o servidor via Fetch API (AJAX).
     * O servidor responde com JSON: {"success": true/false}.
     *
     * @param {number} clientId  ID do cliente
     * @param {number} stageId   ID da nova etapa
     */
    async function moveClient(clientId, stageId) {
        isMoving = true;
        try {
            const response = await fetch(moveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    client_id: clientId,
                    stage_id: stageId,
                    _csrf_token: csrfToken,
                }),
            });

            const data = await response.json();

            if (data.success) {
                showToast('✅ Cliente movido com sucesso!', 'success');
                refreshCharts();
            } else {
                showToast('❌ Erro ao mover. Tente novamente.', 'error');
            }
        } catch (err) {
            console.error('[Kanban] Erro na requisição:', err);
            showToast('❌ Falha de rede. Tente novamente.', 'error');
        } finally {
            isMoving = false;
        }
    }

    /**
     * Atualiza o contador de cartões e o total de valores no cabeçalho de cada coluna.
     * Chamado após cada movimento para manter os dados corretos sem recarregar a página.
     */
    function updateColumnCounters() {
        document.querySelectorAll('.kanban-column').forEach(col => {
            const zone = col.querySelector('.kanban-drop-zone');
            const counter = col.querySelector('[class*="rounded-full"]');
            const cards = zone.querySelectorAll('.kanban-card');
            const count = cards.length;

            if (counter) counter.textContent = count;

            // Soma o deal_value de todos os cartões da coluna
            let total = 0;
            cards.forEach(card => {
                total += parseFloat(card.dataset.dealValue) || 0;
            });

            // Atualiza ou cria/remove o elemento de total de valores no cabeçalho
            const header = col.querySelector('.rounded-t-xl');
            let valueEl = col.querySelector('.kanban-value-total');

            if (total > 0) {
                const formatted = 'R$ ' + total.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                if (valueEl) {
                    valueEl.textContent = formatted;
                } else if (header) {
                    const div = document.createElement('div');
                    div.className = 'kanban-value-total text-xs opacity-80 mt-0.5';
                    div.textContent = formatted;
                    header.querySelector('.text-right').appendChild(div);
                }
            } else if (valueEl) {
                valueEl.remove();
            }

            // Mostra placeholder se a coluna ficou vazia
            if (count === 0 && !zone.querySelector('.kanban-empty')) {
                const empty = document.createElement('div');
                empty.className = 'kanban-empty text-center py-6 text-gray-400 text-xs';
                empty.textContent = 'Arraste um cartão aqui';
                zone.appendChild(empty);
            }
        });
    }

    /**
     * Atualiza os gráficos Chart.js da página (se existirem) via API.
     * Opera nos charts de ID "chartPipeline" e "chartValues".
     * No-op se Chart.js não estiver carregado ou os charts não existirem na página.
     */
    function refreshCharts() {
        if (typeof Chart === 'undefined' || !statsUrl) return;

        const chartBar = Chart.getChart('chartPipeline');
        const chartDoughnut = Chart.getChart('chartValues');
        if (!chartBar && !chartDoughnut) return;

        fetch(statsUrl)
            .then(r => r.json())
            .then(({ pipeline: p }) => {
                if (chartBar) {
                    chartBar.data.labels = p.labels;
                    chartBar.data.datasets[0].data = p.counts.map(Number);
                    chartBar.data.datasets[0].backgroundColor = p.colors.map(c => c + 'cc');
                    chartBar.data.datasets[0].borderColor = p.colors;
                    chartBar.update();
                }
                if (chartDoughnut) {
                    chartDoughnut.data.labels = p.labels;
                    chartDoughnut.data.datasets[0].data = p.values.map(Number);
                    chartDoughnut.data.datasets[0].backgroundColor = p.colors.map(c => c + 'cc');
                    chartDoughnut.data.datasets[0].borderColor = p.colors;
                    chartDoughnut.update();
                }
            })
            .catch(() => {}); // silencia erros de rede no refresh dos gráficos
    }

    /**
     * Exibe uma notificação "toast" temporária na tela.
     *
     * @param {string} message  Texto da mensagem
     * @param {string} type     'success' | 'error'
     */
    function showToast(message, type = 'success') {
        toast.textContent = message;
        toast.style.backgroundColor = type === 'success' ? '#1e293b' : '#dc2626';
        toast.style.opacity = '1';
        // Oculta automaticamente após 2,5 segundos
        setTimeout(() => { toast.style.opacity = '0'; }, 2500);
    }

    // Inicialização
    bindCardEvents();

})();
