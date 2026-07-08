import { test, expect, type Page } from '@playwright/test';
import { login } from './helpers';

/**
 * Avança o calendário um mês (evita colidir com tarefas de outros specs
 * criadas no mês atual) e clica num dia. Se o dia já tiver evento, o próprio
 * modal de conflito oferece "Criar novo", que abre o modal de nova tarefa
 * normalmente para a mesma data.
 */
async function openNewTaskModalOnDay(page: Page): Promise<string> {
    await page.locator('.fc-next-button').click();

    const now = new Date();
    const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    const dateStr = `${nextMonth.getFullYear()}-${String(nextMonth.getMonth() + 1).padStart(2, '0')}-10`;

    await page.locator(`.fc-daygrid-day[data-date="${dateStr}"]`).click();

    const modalTask = page.locator('#modalTask');
    const modalConflict = page.locator('#modalDayConflict');
    // Ambos os modais existem sempre no DOM (só um fica visível por vez), então
    // `modalTask.or(modalConflict)` resolveria para 2 elementos e violaria o
    // modo estrito do Playwright. Filtramos por `:visible` para casar só o que
    // está de fato exibido no momento.
    await expect(page.locator('#modalTask:visible, #modalDayConflict:visible')).toBeVisible();

    if (await modalConflict.isVisible()) {
        await page.locator('#btnConflictCreate').click();
    }

    await expect(modalTask).toBeVisible();
    return dateStr;
}

test.describe('Tarefas / Calendário', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/tasks');
        await expect(page.locator('#fc-calendar')).toBeVisible();
    });

    test('cria uma tarefa a partir do calendário e a conclui', async ({ page }) => {
        const taskTitle = `Tarefa E2E ${Date.now()}`;

        await openNewTaskModalOnDay(page);
        await expect(page.locator('#modalTaskTitle')).toHaveText('Nova Tarefa');

        await page.locator('#task_title').fill(taskTitle);
        await page.locator('#task_priority').selectOption('high');

        const storePromise = page.waitForResponse(
            (resp) => resp.url().includes('/tasks/store') && resp.request().method() === 'POST',
        );
        await page.locator('#btnSaveTask').click();
        const storeResponse = await storePromise;
        expect(storeResponse.ok()).toBeTruthy();

        await expect(page.locator('#modalTask')).toBeHidden();
        await expect(page.getByText(taskTitle)).toBeVisible();

        // Reabre a tarefa recém-criada pelo evento no calendário e marca como concluída.
        await page.getByText(taskTitle).click();
        await expect(page.locator('#modalTask')).toBeVisible();
        await expect(page.locator('#task_title')).toHaveValue(taskTitle);

        const updatePromise = page.waitForResponse(
            (resp) => /\/tasks\/\d+\/update/.test(resp.url()) && resp.request().method() === 'POST',
        );
        await page.locator('#btnToggleDone').click();
        const updateResponse = await updatePromise;
        expect(updateResponse.ok()).toBeTruthy();

        await expect(page.locator('#modalTask')).toBeHidden();
    });
});
