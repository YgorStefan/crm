import { test, expect } from '@playwright/test';
import { DEFAULT_ADMIN_EMAIL, DEFAULT_ADMIN_PASSWORD, E2E_SELLER_EMAIL, login, resetLoginRateLimit } from './helpers';

test.describe('Autenticação', () => {
    test.beforeEach(async () => {
        await resetLoginRateLimit();
    });

    test('login com credenciais válidas chega ao dashboard', async ({ page }) => {
        await login(page);
        // O link de logout só existe no layout autenticado (main.php).
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();
    });

    test('login com credenciais inválidas mostra erro e não autentica', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill(E2E_SELLER_EMAIL);
        await page.locator('#password').fill('senha-errada-123');
        await page.getByRole('button', { name: 'Entrar' }).click();

        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByText(/e-mail ou senha/i)).toBeVisible();
    });

    test('usuário com password_must_change é forçado a trocar a senha antes de acessar o sistema', async ({ page }) => {
        // admin@crm.local é resetado pelo seed (tests/smoke/seed.php) com
        // password_must_change = 1 a cada execução da suíte.
        await page.goto('/login');
        await page.locator('#email').fill(DEFAULT_ADMIN_EMAIL);
        await page.locator('#password').fill(DEFAULT_ADMIN_PASSWORD);
        await page.getByRole('button', { name: 'Entrar' }).click();

        await expect(page).toHaveURL(/\/profile\/change-password/);

        // Tentar navegar direto para uma rota protegida deve continuar
        // redirecionando de volta para a troca de senha (AuthMiddleware).
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/profile\/change-password/);

        const newPassword = 'NovaSenha@1234';
        await page.locator('#current_password').fill(DEFAULT_ADMIN_PASSWORD);
        await page.locator('#new_password').fill(newPassword);
        await page.locator('#confirm_password').fill(newPassword);
        await page.getByRole('button', { name: 'Salvar nova senha' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page.locator('a.sidebar-logout[href$="/logout"]')).toBeVisible();

        // Login volta a funcionar normalmente com a nova senha (sem forçar
        // troca de novo, já que o flag foi limpo pelo ProfileController).
        await page.goto('/logout');
        await page.goto('/login');
        await page.locator('#email').fill(DEFAULT_ADMIN_EMAIL);
        await page.locator('#password').fill(newPassword);
        await page.getByRole('button', { name: 'Entrar' }).click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('logout encerra a sessão e bloqueia acesso a rotas protegidas', async ({ page }) => {
        await login(page);
        await page.goto('/logout');
        await expect(page).toHaveURL(/\/login/);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });
});
