import { test, expect, type APIResponse } from '@playwright/test';
import { E2E_SELLER_EMAIL, E2E_SELLER_PASSWORD, login, resetLoginRateLimit } from './helpers';

// um host .test nunca resolve; o page.route responde por ele, e o navegador trata a pagina como
// outro site de verdade, com a regra do cookie samesite strict e os cabecalhos sec-fetch reais.
const OUTRO_SITE = 'http://outro-site.test/';

const CHEGADA_DE_OUTRO_SITE = {
    'Sec-Fetch-Site': 'cross-site',
    'Sec-Fetch-Mode': 'navigate',
    'Sec-Fetch-Dest': 'document',
};

function setCookies(resp: APIResponse): string[] {
    return resp.headersArray()
        .filter((h) => h.name.toLowerCase() === 'set-cookie')
        .map((h) => h.value);
}

test.describe('Link do CRM clicado em outro site', () => {
    test('chegada de outro site sem cookie recebe a recarga, sem sessao nova', async ({ request, baseURL }) => {
        const resp = await request.get('/clients?busca=a%26b', { headers: CHEGADA_DE_OUTRO_SITE });

        expect(resp.status()).toBe(200);
        expect(setCookies(resp)).toEqual([]);
        expect(resp.headers()['cache-control']).toBe('no-store');
        expect(resp.headers()['referrer-policy']).toBe('no-referrer');
        expect(await resp.text()).toContain(`content="0;url=${baseURL}/clients?busca=a%26b"`);
    });

    test('chegada do proprio crm sem cookie segue para o login', async ({ request }) => {
        const resp = await request.get('/clients', {
            headers: { ...CHEGADA_DE_OUTRO_SITE, 'Sec-Fetch-Site': 'same-origin' },
            maxRedirects: 0,
        });

        expect(resp.status()).toBe(302);
        expect(resp.headers()['location']).toMatch(/\/login$/);
    });

    test('quem esta logado continua logado depois de clicar no link', async ({ page, baseURL }) => {
        await login(page);
        await page.route(OUTRO_SITE, (route) => route.fulfill({
            contentType: 'text/html',
            body: `<a id="link" href="${baseURL}/clients">Abrir a ficha no CRM</a>`,
        }));

        await page.goto(OUTRO_SITE);
        await page.locator('#link').click();

        await expect(page).toHaveURL(/\/clients$/);
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
    });

    test('quem nao esta logado cai no login e volta para o link depois de entrar', async ({ page, baseURL }) => {
        await page.route(OUTRO_SITE, (route) => route.fulfill({
            contentType: 'text/html',
            body: `<a id="link" href="${baseURL}/clients">Abrir a ficha no CRM</a>`,
        }));

        await page.goto(OUTRO_SITE);
        await page.locator('#link').click();

        await expect(page).toHaveURL(/\/login$/);
        await resetLoginRateLimit();
        await page.locator('#email').fill(E2E_SELLER_EMAIL);
        await page.locator('#password').fill(E2E_SELLER_PASSWORD);
        await page.getByRole('button', { name: 'Entrar' }).click();
        await expect(page).toHaveURL(/\/clients$/);
    });
});
