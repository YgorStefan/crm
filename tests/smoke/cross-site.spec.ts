import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { E2E_SELLER_EMAIL, E2E_SELLER_PASSWORD, login, resetLoginRateLimit } from './helpers';

// um host .test nunca resolve; o page.route responde por ele, e o navegador trata a pagina como
// outro site de verdade, com a regra samesite do cookie de sessao e os cabecalhos sec-fetch reais.
const OUTRO_SITE = 'http://outro-site.test/';

function setCookies(resp: APIResponse): string[] {
    return resp.headersArray()
        .filter((h) => h.name.toLowerCase() === 'set-cookie')
        .map((h) => h.value);
}

async function outroSiteCom(page: Page, html: string): Promise<void> {
    await page.route(OUTRO_SITE, (route) => route.fulfill({ contentType: 'text/html', body: html }));
    await page.goto(OUTRO_SITE);
}

test.describe('Link do CRM clicado em outro site', () => {
    test('o cookie de sessao sai com samesite lax', async ({ request }) => {
        const resp = await request.get('/login');

        expect(setCookies(resp).join('\n')).toMatch(/SameSite=Lax/i);
    });

    test('quem esta logado continua logado depois de clicar no link', async ({ page, baseURL }) => {
        await login(page);
        await outroSiteCom(page, `<a id="link" href="${baseURL}/clients">Abrir a ficha no CRM</a>`);

        await page.locator('#link').click();

        await expect(page).toHaveURL(/\/clients$/);
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
    });

    test('quem nao esta logado cai no login e volta para o link depois de entrar', async ({ page, baseURL }) => {
        await outroSiteCom(page, `<a id="link" href="${baseURL}/clients">Abrir a ficha no CRM</a>`);

        await page.locator('#link').click();

        await expect(page).toHaveURL(/\/login$/);
        await resetLoginRateLimit();
        await page.locator('#email').fill(E2E_SELLER_EMAIL);
        await page.locator('#password').fill(E2E_SELLER_PASSWORD);
        await page.getByRole('button', { name: 'Entrar' }).click();
        await expect(page).toHaveURL(/\/clients$/);
    });
});
