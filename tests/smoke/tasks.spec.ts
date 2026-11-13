import { test, expect, type Page } from '@playwright/test';
import { E2E_ASSIGNEE_NAME, login, setE2eAssigneeActive } from './helpers';

function nextMonthDate(day: number): string {
    const now = new Date();
    const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    return `${nextMonth.getFullYear()}-${String(nextMonth.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// as mensagens ficam guardadas e o alert e aceito na hora, porque com ele aberto o
// javascript da pagina para e nenhuma verificacao seguinte consegue rodar.
function collectAlerts(page: Page): string[] {
    const messages: string[] = [];
    page.on('dialog', async (dialog) => {
        messages.push(dialog.message());
        await dialog.accept();
    });
    return messages;
}

async function postFromPage(page: Page, path: string, body: Record<string, string> = {}): Promise<boolean> {
    return page.evaluate(async ({ path, body }) => {
        const appUrl = document.querySelector<HTMLMetaElement>('meta[name="app-url"]')!.content;
        const { ok, data } = await (window as any).CRM.api.post(appUrl + path, body);
        return ok && data?.success === true;
    }, { path, body });
}

// o chamador avanca o calendario um mes antes, o que evita colidir com tarefas de outros specs criadas no mes atual.
async function openNewTaskModalOnDay(page: Page, day = 10): Promise<string> {
    const dateStr = nextMonthDate(day);

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

async function openKanbanClientPage(page: Page): Promise<void> {
    await page.goto('/clients?search=' + encodeURIComponent('Cliente E2E Kanban'));
    await page.getByRole('link', { name: 'Cliente E2E Kanban', exact: true }).first().click();
    await expect(page).toHaveURL(/\/clients\/\d+$/);
}

async function selectE2eAssignee(page: Page, selectId: string): Promise<void> {
    const value = await page.locator(`#${selectId} option`, { hasText: E2E_ASSIGNEE_NAME }).getAttribute('value');
    await page.locator(`#${selectId}`).selectOption(value!);
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

    test('nova tarefa continua aberta com aviso de erro quando a sessão caiu antes de salvar', async ({ page }) => {
        await page.locator('.fc-next-button').click();
        await openNewTaskModalOnDay(page, 18);
        await page.locator('#task_title').fill(`Tarefa E2E ${Date.now()}`);
        await page.context().clearCookies();
        const alerts = collectAlerts(page);

        const storePromise = page.waitForResponse(
            (resp) => resp.url().includes('/tasks/store') && resp.request().method() === 'POST',
        );
        await page.locator('#btnSaveTask').click();
        await storePromise;

        await expect.poll(() => alerts).toEqual(['Erro ao salvar tarefa. Verifique os campos e tente novamente.']);
        await expect(page.locator('#modalTask')).toBeVisible();
    });

    test('a edição continua aberta com aviso de erro quando a tarefa foi removida antes de salvar', async ({ page }) => {
        const taskTitle = `Tarefa E2E ${Date.now()}`;
        expect(await postFromPage(page, '/tasks/store', { title: taskTitle, due_date: `${nextMonthDate(20)}T09:00` })).toBe(true);

        await page.reload();
        await expect(page.locator('#fc-calendar')).toBeVisible();
        await page.locator('.fc-next-button').click();
        await page.getByText(taskTitle).click();
        await expect(page.locator('#modalTaskTitle')).toHaveText('Editar Tarefa');

        const taskId = await page.locator('#task_id').inputValue();
        expect(await postFromPage(page, `/tasks/${taskId}/delete`)).toBe(true);
        const alerts = collectAlerts(page);

        const updatePromise = page.waitForResponse(
            (resp) => resp.url().includes(`/tasks/${taskId}/update`) && resp.request().method() === 'POST',
        );
        await page.locator('#btnSaveTask').click();
        await updatePromise;

        await expect.poll(() => alerts).toEqual(['Tarefa não encontrada.']);
        await expect(page.locator('#modalTask')).toBeVisible();
    });

    test('nova tarefa mostra a mensagem do servidor quando o responsável foi desativado depois de a tela abrir', async ({ page }) => {
        await setE2eAssigneeActive(true);
        try {
            await page.reload();
            await expect(page.locator('#fc-calendar')).toBeVisible();
            await page.locator('.fc-next-button').click();
            await openNewTaskModalOnDay(page, 22);
            await page.locator('#task_title').fill(`Tarefa E2E ${Date.now()}`);
            await selectE2eAssignee(page, 'task_assigned_to');
            await setE2eAssigneeActive(false);
            const alerts = collectAlerts(page);

            const storePromise = page.waitForResponse(
                (resp) => resp.url().includes('/tasks/store') && resp.request().method() === 'POST',
            );
            await page.locator('#btnSaveTask').click();
            await storePromise;

            await expect.poll(() => alerts).toEqual(['Responsável inválido.']);
            await expect(page.locator('#modalTask')).toBeVisible();
        } finally {
            await setE2eAssigneeActive(false);
        }
    });
});

test.describe('Tarefas / modal da tela do cliente', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('nova tarefa reaberta sem salvar volta o responsável e a prioridade, no valor e no rótulo exibido', async ({ page }) => {
        await openKanbanClientPage(page);

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

    test('nova tarefa mostra a mensagem do servidor quando o responsável foi desativado depois de a tela abrir', async ({ page }) => {
        await setE2eAssigneeActive(true);
        try {
            await openKanbanClientPage(page);
            await page.locator('#btn-open-new-task').click();
            await expect(page.locator('#newTaskModal')).toBeVisible();
            await page.locator('#newTask_title').fill(`Tarefa E2E ${Date.now()}`);
            await selectE2eAssignee(page, 'newTask_assigned_to');
            await setE2eAssigneeActive(false);

            const storePromise = page.waitForResponse(
                (resp) => resp.url().includes('/tasks/store') && resp.request().method() === 'POST',
            );
            await page.locator('#newTaskSave').click();
            await storePromise;

            await expect(page.locator('#crm-toast-container')).toContainText('Responsável inválido.');
            await expect(page.locator('#newTaskModal')).toBeVisible();
        } finally {
            await setE2eAssigneeActive(false);
        }
    });
});
