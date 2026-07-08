import { execFile } from 'node:child_process';
import path from 'node:path';
import { promisify } from 'node:util';
import type { CDPSession, Page } from '@playwright/test';
import { expect } from '@playwright/test';

const execFileAsync = promisify(execFile);

export const E2E_SELLER_EMAIL = 'e2e.seller@crm.local';
export const E2E_SELLER_PASSWORD = 'E2eSeller@1234';

export const DEFAULT_ADMIN_EMAIL = 'admin@crm.local';
export const DEFAULT_ADMIN_PASSWORD = 'Admin@1234';

/**
 * Limpa as tentativas de login registradas para o IP local (ver comentário
 * em tests/smoke/reset-rate-limit.php). Chamar antes de qualquer teste que
 * faça POST /login, para não esbarrar no rate-limit legítimo de produção.
 */
export async function resetLoginRateLimit(): Promise<void> {
    await execFileAsync('php', [path.join(__dirname, 'reset-rate-limit.php')]);
}

/** Loga com o usuário fixo sem `password_must_change` e aguarda o dashboard. */
export async function login(
    page: Page,
    email: string = E2E_SELLER_EMAIL,
    password: string = E2E_SELLER_PASSWORD,
): Promise<void> {
    await resetLoginRateLimit();
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

/**
 * Simula um arraste de toque real via CDP (`Input.dispatchTouchEvent`).
 *
 * Por quê: o Kanban (`public/assets/js/pipeline.js`) usa Pointer Events com
 * `setPointerCapture`, que só funciona quando a captura está associada a um
 * ponteiro genuíno do ponto de vista do Chromium. Eventos sintéticos via
 * `element.dispatchEvent(new PointerEvent(...))` NÃO satisfazem isso e
 * quebram o `setPointerCapture` (DOMException). Despachar toque real via
 * CDP contorna essa limitação e exercita o mesmo caminho de código que um
 * dedo real num tablet.
 *
 * `holdMs` deve ser MAIOR que o LONG_PRESS_MS (180ms) do pipeline.js para
 * que o "pick up" do cartão ocorra antes de iniciarmos o movimento.
 */
export async function touchDragCard(
    page: Page,
    from: { x: number; y: number },
    to: { x: number; y: number },
    holdMs = 260,
): Promise<void> {
    const client: CDPSession = await page.context().newCDPSession(page);

    await client.send('Input.dispatchTouchEvent', {
        type: 'touchStart',
        touchPoints: [{ x: from.x, y: from.y }],
    });

    // Fica parado além do long-press para o app "pegar" o cartão sem
    // confundir com rolagem de página.
    await page.waitForTimeout(holdMs);

    // Move em alguns passos intermediários para o app recalcular a
    // drop-zone destacada a cada posição (mesma lógica de um arraste real).
    const steps = 5;
    for (let i = 1; i <= steps; i++) {
        const x = from.x + ((to.x - from.x) * i) / steps;
        const y = from.y + ((to.y - from.y) * i) / steps;
        await client.send('Input.dispatchTouchEvent', {
            type: 'touchMove',
            touchPoints: [{ x, y }],
        });
        await page.waitForTimeout(30);
    }

    await client.send('Input.dispatchTouchEvent', {
        type: 'touchEnd',
        touchPoints: [],
    });

    await client.detach().catch(() => undefined);
}

export interface KanbanDragTarget {
    from: { x: number; y: number };
    to: { x: number; y: number };
    sourceStageId: string | null;
    targetStageId: string | null;
}

/**
 * Localiza o cartão de teste ("Cliente E2E Kanban", semeado por
 * tests/smoke/seed.php) e a coluna vizinha, retornando as coordenadas de
 * centro de cada um para simular o arraste (mouse ou toque).
 */
export async function getKanbanDragTarget(page: Page, cardText = 'Cliente E2E Kanban'): Promise<KanbanDragTarget> {
    const result = await page.evaluate((text) => {
        const cards = Array.from(document.querySelectorAll<HTMLElement>('.kanban-card'));
        const card = cards.find((c) => c.textContent?.includes(text));
        if (!card) return null;

        const columns = Array.from(document.querySelectorAll<HTMLElement>('.kanban-column'));
        const column = card.closest<HTMLElement>('.kanban-column');
        const idx = column ? columns.indexOf(column) : -1;
        const targetColumn = columns[idx + 1] ?? columns[idx - 1];
        const targetZone = targetColumn?.querySelector<HTMLElement>('.kanban-drop-zone');
        if (!targetZone) return null;

        const cardRect = card.getBoundingClientRect();
        const zoneRect = targetZone.getBoundingClientRect();

        return {
            from: { x: cardRect.x + cardRect.width / 2, y: cardRect.y + cardRect.height / 2 },
            to: { x: zoneRect.x + zoneRect.width / 2, y: zoneRect.y + Math.min(30, zoneRect.height / 2) },
            sourceStageId: card.dataset.currentStage ?? null,
            targetStageId: targetZone.dataset.stageId ?? null,
        };
    }, cardText);

    if (!result) {
        throw new Error(`Cartão "${cardText}" ou coluna vizinha não encontrados no board do Kanban.`);
    }

    return result;
}
