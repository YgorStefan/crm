import { test, expect, type Page } from '@playwright/test';
import { login } from './helpers';

// o chamador avanca o calendario um mes antes, o que evita colidir com tarefas de outros specs criadas no mes atual.
/**
 * Clica num dia do mês seguinte. Se o dia já tiver evento, o próprio modal de
 * conflito oferece "Criar novo", que abre o modal de nova tarefa normalmente
 * para a mesma data.
 */
async function openNewTaskModalOnDay(page: Page, day = 10): Promise<string> {
    const now = new Date();
    const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    const dateStr = `${nextMonth.getFullYear()}-${String(nextMonth.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    // o centro da celula cai no chip de uma tarefa que ja esteja no dia, e clique em
    // evento abre a edicao em vez de disparar dateClick; a faixa do numero do dia nunca tem evento.
    await page.locator(`.fc-daygrid-day[data-date="${dateStr}"] .fc-daygrid-day-top`).click();

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

// o seed garante admin@crm.local ativo no tenant, entao sempre existe um colega na lista do select.
async function loggedUserAndColega(page: Page) {
    const loggedUserId = (await page.locator('meta[name="user-id"]').getAttribute('content'))!;
    const read = async (selector: string) => {
        const option = page.locator(selector).first();
        return { id: (await option.getAttribute('value'))!, name: (await option.textContent())!.trim() };
    };
    return {
        me: await read(`#task_assigned_to option[value="${loggedUserId}"]`),
        colega: await read(`#task_assigned_to option:not([value="${loggedUserId}"])`),
    };
}

test.describe('Tarefas / Calendário', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/tasks');
        await expect(page.locator('#fc-calendar')).toBeVisible();
    });

    test('cria uma tarefa a partir do calendário e a conclui', async ({ page }) => {
        const taskTitle = `Tarefa E2E ${Date.now()}`;

        await page.locator('.fc-next-button').click();
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


    test('nova tarefa volta o responsável para o usuário logado depois de editar a de um colega', async ({ page }) => {
        const taskTitle = `Tarefa E2E ${Date.now()}`;
        const { me, colega } = await loggedUserAndColega(page);

        await page.locator('.fc-next-button').click();
        await openNewTaskModalOnDay(page, 12);
        await page.locator('#task_title').fill(taskTitle);
        await page.locator('#task_assigned_to').selectOption(colega.id);
        const storePromise = page.waitForResponse(
            (resp) => resp.url().includes('/tasks/store') && resp.request().method() === 'POST',
        );
        await page.locator('#btnSaveTask').click();
        expect((await storePromise).ok()).toBeTruthy();
        await expect(page.locator('#modalTask')).toBeHidden();

        // recarrega para o select voltar ao estado inicial, assim so a edicao (e nao a criacao acima) pode sujar o responsavel de novo
        await page.reload();
        await expect(page.locator('#fc-calendar')).toBeVisible();
        await page.locator('.fc-next-button').click();

        await page.getByText(taskTitle).click();
        await expect(page.locator('#modalTaskTitle')).toHaveText('Editar Tarefa');
        await expect(page.locator('#task_assigned_to')).toHaveValue(colega.id);
        await page.locator('#modalTask button[data-action="close-modal"]').first().click();
        await expect(page.locator('#modalTask')).toBeHidden();

        await openNewTaskModalOnDay(page, 14);
        await expect(page.locator('#modalTaskTitle')).toHaveText('Nova Tarefa');
        await expect(page.locator('#task_assigned_to')).toHaveValue(me.id);
    });

    test('nova tarefa volta o responsável escolhido à mão sem salvar, no valor e no rótulo exibido', async ({ page }) => {
        const { me, colega } = await loggedUserAndColega(page);
        const assignee = '.cs-wrapper:has(#task_assigned_to)';

        await page.locator('.fc-next-button').click();
        await openNewTaskModalOnDay(page, 12);
        await page.locator(`${assignee} .cs-trigger`).click();
        await page.locator(`${assignee} .cs-option[data-value="${colega.id}"]`).click();
        await expect(page.locator(`${assignee} .cs-label`)).toHaveText(colega.name);
        await page.locator('#modalTask button[data-action="close-modal"]').first().click();
        await expect(page.locator('#modalTask')).toBeHidden();

        await openNewTaskModalOnDay(page, 14);
        await expect(page.locator('#task_assigned_to')).toHaveValue(me.id);
        await expect(page.locator(`${assignee} .cs-label`)).toHaveText(me.name);
    });
});
