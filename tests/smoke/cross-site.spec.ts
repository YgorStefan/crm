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

test.describe('Chegada ao CRM vinda de outro site', () => {
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

    test('formulario de outro site enviado ao crm nao desloga quem esta logado', async ({ page, baseURL }) => {
        await login(page);
        const recusa = page.waitForResponse((r) => r.url().endsWith('/clients/store'));

        await outroSiteCom(page, `<form id="f" method="post" action="${baseURL}/clients/store">`
            + '<input name="name" value="Cliente de outro site"></form>'
            + '<script>document.getElementById("f").submit()</script>');

        const resp = await recusa;
        expect(resp.status()).toBe(403);
        expect(await resp.headerValue('set-cookie')).toBeNull();

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();
    });

    test('post de outro site e recusado antes de abrir sessao', async ({ request }) => {
        const resp = await request.post('/login', {
            headers: { 'Sec-Fetch-Site': 'cross-site', 'Sec-Fetch-Mode': 'navigate', 'Sec-Fetch-Dest': 'document' },
            form: { email: 'x@example.com', password: 'x' },
            maxRedirects: 0,
        });

        expect(resp.status()).toBe(403);
        expect(setCookies(resp)).toEqual([]);
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
