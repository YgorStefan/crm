import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.describe('Contatos Frios', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/cold-contacts');
    });

    test('importa um CSV e vê o card do mês com os contatos inseridos', async ({ page }) => {
        const listName = `Lista E2E ${Date.now()}`;
        const csv = 'Nome,Celular\nJoão da Silva E2E,11987654321\nMaria Souza E2E,11912345678\n';

        await page.locator('#import-tipo-lista').fill(listName);
        await page.locator('#import-csv-file').setInputFiles({
            name: 'contatos-e2e.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(csv, 'utf-8'),
        });

        await page.getByRole('button', { name: 'Importar' }).click();

        await expect(page).toHaveURL(/\/cold-contacts/);
        await expect(page.getByText(/contato\(s\) importado\(s\) com sucesso/)).toBeVisible();
        await expect(page.getByText('Importações por mês')).toBeVisible();
    });

    test('exporta a lista de contatos frios em CSV', async ({ page }) => {
        // Garante que existe pelo menos um mês importado para exportar.
        const listName = `Lista E2E Export ${Date.now()}`;
        await page.locator('#import-tipo-lista').fill(listName);
        await page.locator('#import-csv-file').setInputFiles({
            name: 'contatos-export-e2e.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from('Nome,Celular\nExport Teste E2E,11955554444\n', 'utf-8'),
        });
        await page.getByRole('button', { name: 'Importar' }).click();
        await expect(page.getByText(/contato\(s\) importado\(s\) com sucesso/)).toBeVisible();

        await page.locator('.btn-open-modal').first().click();
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.locator('#btnExportCsv').click(),
        ]);
        expect(download.suggestedFilename()).toMatch(/^contatos-frios-\d{4}-\d{2}\.csv$/);
    });
});
