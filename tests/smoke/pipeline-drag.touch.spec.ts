import { test, expect } from '@playwright/test';
import { getKanbanDragTarget, login, touchDragCard } from './helpers';

// Roda apenas no projeto "tablet-touch" (playwright.config.ts), que usa um
// dispositivo com hasTouch: true equivalente a um tablet 12.1" em paisagem.
test.describe('Pipeline (Kanban) — toque em tablet', () => {
    test('arrasta um cartão para a coluna vizinha via toque real (CDP)', async ({ page, browserName }) => {
        test.skip(browserName !== 'chromium', 'Input.dispatchTouchEvent via CDP só é suportado no Chromium.');

        await login(page);
        await page.goto('/pipeline');
        await expect(page.locator('#kanbanBoard')).toBeVisible();

        const target = await getKanbanDragTarget(page);
        expect(target.targetStageId).not.toBe(target.sourceStageId);

        const movePromise = page.waitForResponse(
            (resp) => resp.url().includes('/pipeline/move') && resp.request().method() === 'POST',
        );

        await touchDragCard(page, target.from, target.to);

        const response = await movePromise;
        expect(response.ok()).toBeTruthy();

        await page.reload();
        const zoneAfter = page.locator(`.kanban-drop-zone[data-stage-id="${target.targetStageId}"]`);
        await expect(zoneAfter.getByText('Cliente E2E Kanban')).toBeVisible();
    });
});
