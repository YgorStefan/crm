import { test, expect } from '@playwright/test';
import { login, setCookies } from './helpers';

const GOOGLE_USER_DATA_POLICY_URL = 'https://developers.google.com/terms/api-services-user-data-policy';

test.describe('Política de privacidade', () => {
    test('a página abre sem login e traz a declaração de Uso Limitado do Google', async ({ page }) => {
        const response = await page.goto('/privacidade');

        expect(response?.status()).toBe(200);
        // rota protegida tambem daria 200, pelo redirect para o login, entao so a
        // url prova que a pagina abriu sem sessao.
        await expect(page).toHaveURL(/\/privacidade$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Política de Privacidade' })).toBeVisible();
        await expect(page.locator('body')).toContainText('incluindo os requisitos de Uso Limitado');
        await expect(page.locator(`a[href="${GOOGLE_USER_DATA_POLICY_URL}"]`)).toBeVisible();
    });

    test('a tela de login tem link para a política de privacidade', async ({ page }) => {
        await page.goto('/login');

        const link = page.locator('a[href$="/privacidade"]');
        await expect(link).toHaveText('Política de privacidade');
        await link.click();
        await expect(page).toHaveURL(/\/privacidade$/);
    });

    test('a página sem cookie não cria sessão', async ({ request }) => {
        const resp = await request.get('/privacidade');

        expect(resp.status()).toBe(200);
        expect(await resp.text()).toContain('Política de Privacidade');
        expect(setCookies(resp)).toEqual([]);
    });

    test('a página com cookie preserva a sessão', async ({ page }) => {
        await login(page);

        const resp = await page.request.get('/privacidade');
        expect(resp.status()).toBe(200);
        expect(await resp.text()).toContain('Política de Privacidade');
        expect(setCookies(resp)).toEqual([]);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();
    });
});
