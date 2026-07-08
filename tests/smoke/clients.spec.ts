import { test, expect } from '@playwright/test';
import { login } from './helpers';

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

    test('lista de clientes carrega com a tabela de resultados', async ({ page }) => {
        await page.goto('/clients');
        await expect(page.locator('table')).toBeVisible();
    });
});
