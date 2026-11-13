import { test, expect, type Page } from '@playwright/test';
import { answerDialogs, login } from './helpers';

async function createClient(page: Page): Promise<{ id: string; name: string }> {
    const name = `Cliente E2E ${Date.now()}`;
    await page.goto('/clients/create');
    await page.locator('input[name="name"]').fill(name);
    await page.locator('input[name="email"]').fill(`${Date.now()}@e2e-clients.local`);
    await page.locator('input[name="phone"]').fill(`119${String(Date.now()).slice(-8)}`);
    await page.locator('select[name="pipeline_stage_id"]').selectOption({ index: 0 });
    await page.getByRole('button', { name: 'Salvar Cliente' }).click();
    await expect(page).toHaveURL(/\/clients\/\d+$/);
    return { id: page.url().split('/').pop()!, name };
}

async function kanbanClientUrl(page: Page): Promise<string> {
    await page.goto('/clients?search=' + encodeURIComponent('Cliente E2E Kanban'));
    return (await page.getByRole('link', { name: 'Cliente E2E Kanban', exact: true }).first().getAttribute('href'))!;
}

test.describe('Clientes', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('cria um cliente e o encontra no detalhe e na listagem', async ({ page }) => {
        const uniqueName = `Cliente E2E ${Date.now()}`;
        // Telefone também precisa ser único: ClientController::store() rejeita
        // duplicados (ver "Já existe um cliente cadastrado com este telefone").
        const uniquePhone = `119${String(Date.now()).slice(-8)}`;

        await page.goto('/clients/create');
        await page.locator('input[name="name"]').fill(uniqueName);
        await page.locator('input[name="email"]').fill(`${Date.now()}@e2e-clients.local`);
        await page.locator('input[name="phone"]').fill(uniquePhone);
        await page.locator('select[name="pipeline_stage_id"]').selectOption({ index: 0 });
        await page.getByRole('button', { name: 'Salvar Cliente' }).click();

        // store() redireciona direto para /clients/{id} em caso de sucesso.
        await expect(page).toHaveURL(/\/clients\/\d+$/);
        await expect(page.getByRole('heading', { name: uniqueName })).toBeVisible();

        await page.goto('/clients');
        await expect(page.getByText(uniqueName)).toBeVisible();
    });

    test('a busca do cep preenche a uf no valor e no rótulo exibido do select', async ({ page }) => {
        let viaCepCalls = 0;
        // o fetch sai do localhost para outra origem, entao a resposta falsa precisa do cabecalho de cors.
        await page.route('https://viacep.com.br/ws/**', async (route) => {
            viaCepCalls++;
            await route.fulfill({
                status: 200,
                headers: { 'Access-Control-Allow-Origin': '*' },
                json: { cep: '80010-000', logradouro: 'Praça Tiradentes', bairro: 'Centro', localidade: 'Curitiba', uf: 'PR' },
            });
        });

        await page.goto('/clients/create');
        await page.locator('#zip_code').fill('80010000');

        await expect(page.locator('input[name="city"]')).toHaveValue('Curitiba');
        await expect(page.locator('#state')).toHaveValue('PR');
        await expect(page.locator('.cs-wrapper:has(#state) .cs-label')).toHaveText('PR');
        expect(viaCepCalls).toBe(1);
    });

    test('lista de clientes carrega com a tabela de resultados', async ({ page }) => {
        await page.goto('/clients');
        await expect(page.locator('table')).toBeVisible();
    });

    test('remover interação pede confirmação: cancelar mantém e aceitar remove', async ({ page }) => {
        const description = `Interação E2E ${Date.now()}`;
        const clientUrl = await kanbanClientUrl(page);
        await page.goto(clientUrl);
        await page.locator('form[action$="/interactions/store"] textarea[name="description"]').fill(description);
        await page.getByRole('button', { name: 'Salvar Interação' }).click();
        await expect(page.getByText('Interação registrada com sucesso!')).toBeVisible();

        const dialogs = answerDialogs(page, [false, true]);
        const row = page.locator('[data-interaction-id]', { hasText: description });
        // o goto espera o load, entao o clique so acontece com os scripts da tela ligados.
        await page.goto(clientUrl);
        await row.locator('.inter-delete-btn').click();
        await expect.poll(() => dialogs).toEqual(['Remover esta interação?']);
        await page.goto(clientUrl);
        await expect(row).toHaveCount(1);

        await row.locator('.inter-delete-btn').click();
        await expect(page.getByText('Interação removida.')).toBeVisible();
        await expect(row).toHaveCount(0);
        expect(dialogs).toEqual(['Remover esta interação?', 'Remover esta interação?']);
    });

    test('remover cliente pede confirmação: cancelar mantém e aceitar remove', async ({ page }) => {
        const client = await createClient(page);
        const dialogs = answerDialogs(page, [false, true]);

        await page.goto(`/clients/${client.id}/edit`);
        await page.getByRole('button', { name: 'Remover Cliente' }).click();
        await expect.poll(() => dialogs).toEqual(['Tem certeza que deseja remover este cliente?']);
        await page.goto('/clients?search=' + encodeURIComponent(client.name));
        await expect(page.getByRole('link', { name: client.name, exact: true })).not.toHaveCount(0);

        await page.goto(`/clients/${client.id}/edit`);
        await page.getByRole('button', { name: 'Remover Cliente' }).click();
        await expect(page.getByText('Cliente removido com sucesso.')).toBeVisible();
        await page.goto('/clients?search=' + encodeURIComponent(client.name));
        await expect(page.getByRole('link', { name: client.name, exact: true })).toHaveCount(0);
        expect(dialogs).toHaveLength(2);
    });

    test('o x do aviso fecha a mensagem na hora', async ({ page }) => {
        // com o relogio parado o aviso nao some sozinho depois de 5s, entao so o clique no x pode tira-lo.
        await page.clock.install();
        await page.clock.pauseAt(Date.now() + 1000);
        await createClient(page);
        await expect(page.locator('#flashMsg')).toContainText('Cliente cadastrado com sucesso!');
        await page.waitForLoadState('domcontentloaded');

        await page.locator('#flashMsg button').click();

        await expect(page.locator('#flashMsg')).toHaveCount(0);
    });
});
