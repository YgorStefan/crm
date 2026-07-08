import { test, expect } from '@playwright/test';
import { getKanbanDragTarget, login } from './helpers';

test.describe('Pipeline (Kanban)', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/pipeline');
        await expect(page.locator('#kanbanBoard')).toBeVisible();
    });

    test('arrasta um cartão para a coluna vizinha com o mouse e persiste a mudança', async ({ page }) => {
        const target = await getKanbanDragTarget(page);
        expect(target.targetStageId).not.toBeNull();
        expect(target.targetStageId).not.toBe(target.sourceStageId);

        const movePromise = page.waitForResponse(
            (resp) => resp.url().includes('/pipeline/move') && resp.request().method() === 'POST',
        );

        // pipeline.js só inicia o drag de mouse após o ponteiro passar de
        // DRAG_THRESHOLD (8px) — por isso movemos em etapas.
        await page.mouse.move(target.from.x, target.from.y);
        await page.mouse.down();
        await page.mouse.move(target.from.x + 20, target.from.y + 10, { steps: 5 });
        await page.mouse.move(target.to.x, target.to.y, { steps: 10 });
        await page.mouse.up();

        const response = await movePromise;
        expect(response.ok()).toBeTruthy();

        // Confirma a persistência recarregando a página (round-trip completo).
        await page.reload();
        const zoneAfter = page.locator(`.kanban-drop-zone[data-stage-id="${target.targetStageId}"]`);
        await expect(zoneAfter.getByText('Cliente E2E Kanban')).toBeVisible();
    });
});
