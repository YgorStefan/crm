import { test, expect, type Page } from '@playwright/test';
import { login } from './helpers';

// o chamador avanca o calendario um mes antes, o que evita colidir com tarefas de outros specs criadas no mes atual.
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
async function loggedUserAndColega(page: Page, selectId = 'task_assigned_to') {
    const loggedUserId = (await page.locator('meta[name="user-id"]').getAttribute('content'))!;
    const read = async (selector: string) => {
        const option = page.locator(selector).first();
        return { id: (await option.getAttribute('value'))!, name: (await option.textContent())!.trim() };
    };
    return {
        me: await read(`#${selectId} option[value="${loggedUserId}"]`),
        colega: await read(`#${selectId} option:not([value="${loggedUserId}"])`),
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

    test('nova tarefa volta a prioridade escolhida sem salvar para a padrão, no rótulo exibido', async ({ page }) => {
        const priority = '.cs-wrapper:has(#task_priority)';

        await page.locator('.fc-next-button').click();
        await openNewTaskModalOnDay(page, 12);
        await page.locator(`${priority} .cs-trigger`).click();
        await page.locator(`${priority} .cs-option[data-value="high"]`).click();
        await expect(page.locator(`${priority} .cs-label`)).toHaveText('Alta');
        await page.locator('#modalTask button[data-action="close-modal"]').first().click();
        await expect(page.locator('#modalTask')).toBeHidden();

        await openNewTaskModalOnDay(page, 14);
        await expect(page.locator('#task_priority')).toHaveValue('medium');
        await expect(page.locator(`${priority} .cs-label`)).toHaveText('Média');
    });

    test('a edição da tarefa de um colega mostra o nome dele e a prioridade da tarefa no rótulo exibido', async ({ page }) => {
        const taskTitle = `Tarefa E2E ${Date.now()}`;
        const { colega } = await loggedUserAndColega(page);
        const assignee = '.cs-wrapper:has(#task_assigned_to)';
        const priority = '.cs-wrapper:has(#task_priority)';

        await page.locator('.fc-next-button').click();
        await openNewTaskModalOnDay(page, 16);
        await page.locator('#task_title').fill(taskTitle);
        await page.locator('#task_priority').selectOption('low');
        await page.locator('#task_assigned_to').selectOption(colega.id);
        const storePromise = page.waitForResponse(
            (resp) => resp.url().includes('/tasks/store') && resp.request().method() === 'POST',
        );
        await page.locator('#btnSaveTask').click();
        expect((await storePromise).ok()).toBeTruthy();
        await expect(page.locator('#modalTask')).toBeHidden();

        // recarrega para os rotulos voltarem ao padrao da pagina, assim so a abertura da edicao pode troca-los
        await page.reload();
        await expect(page.locator('#fc-calendar')).toBeVisible();
        await page.locator('.fc-next-button').click();

        await page.getByText(taskTitle).click();
        await expect(page.locator('#modalTaskTitle')).toHaveText('Editar Tarefa');
        await expect(page.locator(`${assignee} .cs-label`)).toHaveText(colega.name);
        await expect(page.locator(`${priority} .cs-label`)).toHaveText('Baixa');
    });
});

test.describe('Tarefas / modal da tela do cliente', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('nova tarefa reaberta sem salvar volta o responsável e a prioridade, no valor e no rótulo exibido', async ({ page }) => {
        await page.goto('/clients?search=' + encodeURIComponent('Cliente E2E Kanban'));
        await page.getByRole('link', { name: 'Cliente E2E Kanban', exact: true }).first().click();
        await expect(page).toHaveURL(/\/clients\/\d+$/);

        const { me, colega } = await loggedUserAndColega(page, 'newTask_assigned_to');
        const assignee = '.cs-wrapper:has(#newTask_assigned_to)';
        const priority = '.cs-wrapper:has(#newTask_priority)';

        await page.locator('#btn-open-new-task').click();
        await expect(page.locator('#newTaskModal')).toBeVisible();
        await page.locator(`${assignee} .cs-trigger`).click();
        await page.locator(`${assignee} .cs-option[data-value="${colega.id}"]`).click();
        await page.locator(`${priority} .cs-trigger`).click();
        await page.locator(`${priority} .cs-option[data-value="high"]`).click();
        await expect(page.locator(`${assignee} .cs-label`)).toHaveText(colega.name);
        await expect(page.locator(`${priority} .cs-label`)).toHaveText('Alta');
        await page.locator('#newTaskCancel').click();
        await expect(page.locator('#newTaskModal')).toBeHidden();

        await page.locator('#btn-open-new-task').click();
        await expect(page.locator('#newTask_assigned_to')).toHaveValue(me.id);
        await expect(page.locator(`${assignee} .cs-label`)).toHaveText(me.name);
        await expect(page.locator('#newTask_priority')).toHaveValue('medium');
        await expect(page.locator(`${priority} .cs-label`)).toHaveText('Média');
    });
});
