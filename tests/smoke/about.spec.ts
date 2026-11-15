import { test, expect, type APIResponse } from '@playwright/test';
import { login } from './helpers';

function setCookies(resp: APIResponse): string[] {
    return resp.headersArray()
        .filter((h) => h.name.toLowerCase() === 'set-cookie')
        .map((h) => h.value);
}

test.describe('Página sobre o CRM', () => {
    test('abre sem login, sem redirecionar, e leva à política de privacidade', async ({ page }) => {
        const response = await page.goto('/sobre');

        expect(response?.status()).toBe(200);
        // o google recusa pagina inicial que redireciona, entao a url tem de continuar a mesma.
        await expect(page).toHaveURL(/\/sobre$/);
        await expect(page.getByRole('heading', { level: 1, name: 'CRM' })).toBeVisible();
        await expect(page.locator('body')).toContainText('Ver, criar, mudar e excluir eventos nas suas agendas do Google');

        await page.locator('footer a[href$="/privacidade"]').click();
        await expect(page).toHaveURL(/\/privacidade$/);
    });

    test('a página sem cookie não cria sessão', async ({ request }) => {
        const resp = await request.get('/sobre', { maxRedirects: 0 });

        expect(resp.status()).toBe(200);
        expect(setCookies(resp)).toEqual([]);
    });

    test('a página com cookie preserva a sessão', async ({ page }) => {
        await login(page);

        const resp = await page.request.get('/sobre');
        expect(resp.status()).toBe(200);
        expect(setCookies(resp)).toEqual([]);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();
    });
});
