import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login } from './helpers';

// o servidor da suite sobe sem GOOGLE_CLIENT_ID, entao o /google/connect nunca guarda um state
// e o finish so recebe state que nao confere; o state substituido por outra aba cai no mesmo aviso.

const STATE_WARNING = 'A conexão com o Google Agenda não foi concluída porque o pedido expirou ou foi substituído por outro. Tente conectar de novo.';

function setCookies(resp: APIResponse): string[] {
    return resp.headersArray()
        .filter((h) => h.name.toLowerCase() === 'set-cookie')
        .map((h) => h.value);
}

async function expectBackOnTasksWithWarning(page: Page): Promise<void> {
    await expect(page).toHaveURL(/\/tasks$/);
    await expect(page.locator('#flashMsg')).toContainText(STATE_WARNING);
}

test.describe('Google / volta do consentimento', () => {
    test('callback sem cookie nao cria sessao', async ({ request }) => {
        const resp = await request.get('/google/callback?code=codigo-falso&state=state-falso');

        expect(resp.status()).toBe(200);
        expect(setCookies(resp)).toEqual([]);
        expect(resp.headers()['cache-control']).toBe('no-store');
        expect(resp.headers()['referrer-policy']).toBe('no-referrer');
        expect(await resp.text()).toContain('/google/callback/finish?code=codigo-falso&amp;state=state-falso');
    });

    test('callback escapa os parametros', async ({ page }) => {
        const state = 'a"b\'c<d>&e=f';
        const finish = page.waitForRequest((r) => new URL(r.url()).pathname.endsWith('/google/callback/finish'));

        await page.goto('/google/callback?code=codigo-falso&state=' + encodeURIComponent(state) + '&error=access_denied');

        const sent = new URL((await finish).url()).searchParams;
        expect(sent.get('code')).toBe('codigo-falso');
        expect(sent.get('state')).toBe(state);
        expect(sent.get('error')).toBe('access_denied');
    });

    test('callback com cookie preserva a sessao', async ({ page }) => {
        await login(page);

        const resp = await page.request.get('/google/callback?code=codigo-falso&state=state-falso');
        expect(resp.status()).toBe(200);
        expect(await resp.text()).toContain('/google/callback/finish?');
        expect(setCookies(resp)).toEqual([]);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();
    });

    test('finish sem query volta com aviso', async ({ page }) => {
        await login(page);

        const resp = await page.goto('/google/callback/finish');

        expect(resp?.status()).toBe(200);
        await expectBackOnTasksWithWarning(page);
    });

    test('finish com state errado volta com aviso', async ({ page }) => {
        await login(page);

        const resp = await page.goto('/google/callback/finish?code=codigo-falso&state=state-de-outra-aba');

        expect(resp?.status()).toBe(200);
        await expectBackOnTasksWithWarning(page);
    });
});
