import { test, expect } from '@playwright/test';
import { login, waitForScreenScripts } from './helpers';

test.describe('Prospecção / exportação', () => {
    // sem fixar o fuso, um ci em utc passaria com o codigo antigo, porque ali o dia de
    // toISOString coincide com o dia local.
    test.use({ timezoneId: 'America/Sao_Paulo' });

    test('o arquivo exportado leva a data local do navegador', async ({ page }) => {
        // a busca de verdade chama a api paga do google, entao o teste responde no lugar dela.
        await page.route('**/api/prospecting/search', (route) => route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({
                success: true,
                places: [{ name: 'Clinica E2E', phone: '(41) 3333-4444', status: 'com_telefone' }],
                nextPageToken: null,
            }),
        }));
        await login(page);
        // 22:30 em -03:00 ja e o dia seguinte em utc, entao o erro aparece na data do nome.
        await page.clock.setFixedTime(new Date('2026-11-13T22:30:00-03:00'));
        await page.goto('/prospecting');
        await waitForScreenScripts(page);
        // o tenant do teste nao tem chave do maps, e sem ela a tela nasce com o formulario desabilitado.
        await page.evaluate(() => {
            document.querySelectorAll('#prospectingForm [disabled]').forEach((el) => el.removeAttribute('disabled'));
        });

        await page.locator('#searchTerm').fill('Dentista');
        await page.locator('#searchLocation').fill('Curitiba PR');
        await page.locator('#searchBtn').click();
        await expect(page.locator('#downloadBtn')).toBeEnabled();

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.locator('#downloadBtn').click(),
        ]);
        expect(download.suggestedFilename()).toBe('prospeccao-Dentista-2026-11-13.xlsx');
    });
});
