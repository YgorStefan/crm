(function () {
    'use strict';

    // --- Referências globais ---
    const board = document.getElementById('kanbanBoard');
    const toast = document.getElementById('kanbanToast');
    const moveUrl = board?.dataset.moveUrl;   // URL da rota POST /pipeline/move
    const statsUrl = board?.dataset.statsUrl; // URL da rota GET /api/dashboard/stats

    if (!board) return; // Sai silenciosamente se o board não existir na página

    // Distância (px) que o ponteiro precisa se mover antes de considerarmos
    // um arraste real (evita iniciar drag num simples toque/clique).
    const DRAG_THRESHOLD = 8;
    // Em touch, espera esse tempo parado antes de "pegar" o cartão — abaixo
    // disso, o gesto é tratado como rolagem da página (mouse não precisa
    // disso: o próprio movimento acima do threshold já inicia o drag).
    const LONG_PRESS_MS = 180;

    // Flag para evitar múltiplas requisições simultâneas (race condition CSRF)
    let isMoving = false;

    // Estado do arraste em andamento (null quando não há nenhum)
    let drag = null;

    /**
     * Adiciona os event listeners de pointer em todos os cartões do board.
     * Chamada também após mover um cartão (para reanexar nos novos elementos).
     */
    function bindCardEvents() {
        document.querySelectorAll('.kanban-card').forEach(card => {
            card.removeEventListener('pointerdown', onPointerDown);
            card.addEventListener('pointerdown', onPointerDown);
        });
    }

    /**
     * pointerdown: início de um possível arraste (mouse, touch ou pen).
     * Não inicia o drag imediatamente — só arma o estado e, para touch,
     * um timer de "long press" — para não conflitar com rolagem/clique normal.
     */
    function onPointerDown(e) {
        // Apenas botão principal do mouse; ignora clique direito/meio.
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        if (isMoving) return;

        const card = e.currentTarget;
        const rect = card.getBoundingClientRect();

        drag = {
            pointerId: e.pointerId,
            pointerType: e.pointerType,
            card,
            startX: e.clientX,
            startY: e.clientY,
            offsetX: e.clientX - rect.left,
            offsetY: e.clientY - rect.top,
            width: rect.width,
            height: rect.height,
            active: false,      // true quando o "pick up" já ocorreu
            longPressTimer: null,
            ghost: null,
            lastZone: null,
        };

        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
        document.addEventListener('pointercancel', onPointerUp);

        if (e.pointerType !== 'mouse') {
            // Touch/pen: só vira drag depois do long-press (dá tempo de
            // detectar rolagem vertical normal da página).
            drag.longPressTimer = setTimeout(() => startDrag(e), LONG_PRESS_MS);
        }
    }

    /**
     * pointermove: decide se promove o estado atual para "arrastando" e,
     * quando já está arrastando, move o clone flutuante e destaca a coluna
     * sob o ponteiro.
     */
    function onPointerMove(e) {
        if (!drag || e.pointerId !== drag.pointerId) return;

        if (!drag.active) {
            const dx = e.clientX - drag.startX;
            const dy = e.clientY - drag.startY;
            const moved = Math.hypot(dx, dy);

            if (drag.pointerType === 'mouse') {
                // Mouse: inicia assim que ultrapassar o threshold, sem espera.
                if (moved > DRAG_THRESHOLD) startDrag(e);
                return;
            }

            // Touch/pen: se moveu antes do long-press "pegar" o cartão,
            // é rolagem — cancela o drag e deixa o browser rolar normalmente.
            if (moved > DRAG_THRESHOLD) {
                cancelPendingDrag();
            }
            return;
        }

        // Já arrastando: impede rolagem/seleção e atualiza a UI.
        e.preventDefault();
        updateGhostPosition(e.clientX, e.clientY);
        highlightZoneUnderPointer(e.clientX, e.clientY);
    }

    /**
     * pointerup / pointercancel: finaliza o arraste (se houve) ou apenas
     * limpa o estado pendente (permitindo que o clique/tap normal do link
     * dentro do cartão continue funcionando).
     */
    function onPointerUp(e) {
        if (!drag || e.pointerId !== drag.pointerId) return;

        if (drag.active) {
            finishDrag(e.clientX, e.clientY);
        } else {
            cancelPendingDrag();
        }

        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        document.removeEventListener('pointercancel', onPointerUp);
        drag = null;
    }

    /** Cancela um drag que ainda não tinha "pegado" o cartão (sem side-effects). */
    function cancelPendingDrag() {
        if (!drag) return;
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
    }

    /**
     * "Pega" o cartão: cria o clone flutuante que acompanha o ponteiro,
     * aplica o estado visual de arraste e captura o ponteiro no cartão
     * original (garante que continuemos recebendo move/up mesmo se o dedo
     * sair da área do elemento).
     */
    function startDrag(e) {
        if (!drag || drag.active) return;
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);

        drag.active = true;
        drag.card.setPointerCapture(drag.pointerId);
        drag.card.classList.add('dragging');
        document.body.classList.add('kanban-dragging-active');

        const ghost = drag.card.cloneNode(true);
        ghost.classList.add('kanban-drag-ghost');
        ghost.style.width = drag.width + 'px';
        ghost.style.height = drag.height + 'px';
        document.body.appendChild(ghost);
        drag.ghost = ghost;

        updateGhostPosition(e.clientX, e.clientY);
        highlightZoneUnderPointer(e.clientX, e.clientY);
    }

    function updateGhostPosition(x, y) {
        if (!drag?.ghost) return;
        drag.ghost.style.left = (x - drag.offsetX) + 'px';
        drag.ghost.style.top = (y - drag.offsetY) + 'px';
    }

    /** Encontra a drop-zone sob o ponteiro (ignorando o clone) e a destaca. */
    function highlightZoneUnderPointer(x, y) {
        if (drag.ghost) drag.ghost.style.display = 'none';
        const el = document.elementFromPoint(x, y);
        if (drag.ghost) drag.ghost.style.display = '';

        const zone = el?.closest('.kanban-drop-zone') || null;
        if (zone === drag.lastZone) return;

        drag.lastZone?.classList.remove('drag-over');
        zone?.classList.add('drag-over');
        drag.lastZone = zone;
    }

    /**
     * Solta o cartão: remove o clone/estado visual e, se houver uma
     * drop-zone válida diferente da etapa atual, move o cartão no DOM e
     * persiste via AJAX — mesma lógica do antigo handler "drop" HTML5.
     */
    function finishDrag(x, y) {
        const { card, ghost, lastZone } = drag;

        ghost?.remove();
        card.classList.remove('dragging');
        document.body.classList.remove('kanban-dragging-active');
        lastZone?.classList.remove('drag-over');

        if (!lastZone || isMoving) return;

        const newStageId = Number.parseInt(lastZone.dataset.stageId, 10);
        const oldStageId = Number.parseInt(card.dataset.currentStage, 10);
        const clientId = Number.parseInt(card.dataset.clientId, 10);

        if (newStageId === oldStageId) return;

        // 1. Move o cartão no DOM (feedback imediato ao usuário)
        const emptyPlaceholder = lastZone.querySelector('.kanban-empty');
        if (emptyPlaceholder) emptyPlaceholder.remove(); // remove o "Arraste aqui"
        lastZone.appendChild(card);
        card.dataset.currentStage = newStageId;

        // 2. Persiste a mudança via AJAX (chamada ANTES da atualização visual
        // para garantir que erros de DOM não impeçam a persistência)
        moveClient(clientId, newStageId);

        // 3. Atualiza contadores e totais (não-crítico — se falhar, persistência já ocorreu)
        try {
            updateColumnCounters();
        } catch (counterErr) {
            console.warn('[Kanban] Falha ao atualizar contadores:', counterErr);
        }
    }

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
            const { data } = await CRM.api.postJson(moveUrl, {
                client_id: clientId,
                stage_id: stageId,
            });

            if (data && data.success) {
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
                    // Estrutura do header: <div.rounded-t-xl> > <div.flex.items-baseline> (título + valor)
                    // O placeholder anterior tentava append em .text-right, que não existe — quebrava o handler.
                    const titleWrap = header.querySelector('.flex.items-baseline') || header;
                    const span = document.createElement('span');
                    span.className = 'kanban-value-total text-xs opacity-80 font-normal whitespace-nowrap';
                    span.textContent = formatted;
                    titleWrap.appendChild(span);
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
