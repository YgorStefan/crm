import { test, expect, type Page } from '@playwright/test';
import { login, setE2eAssigneeActive } from './helpers';

const E2E_ASSIGNEE_EMAIL = 'e2e.responsavel@crm.local';

// abre a edicao pelo goto, que espera o load, para os scripts da tela ja estarem ligados.
async function openAssigneeEdit(page: Page): Promise<void> {
    await page.goto('/admin/users');
    const href = await page.locator('tr', { hasText: E2E_ASSIGNEE_EMAIL }).locator('a[href$="/edit"]').getAttribute('href');
    await page.goto(href!);
}

test.describe('Painel Admin', () => {
    test.beforeEach(async ({ page }) => {
        await login(page); // e2e.seller@crm.local tem role admin (ver tests/smoke/seed.php)
        await page.goto('/admin');
    });

    test('cria um novo usuário e o encontra na listagem', async ({ page }) => {
        const uniqueEmail = `e2e.user.${Date.now()}@crm.local`;

        await page.getByRole('link', { name: 'Novo Usuário' }).click();
        await expect(page).toHaveURL(/\/admin\/users\/create/);
        await expect(page.locator('[name="avatar"]')).toHaveCount(0);

        await page.locator('input[name="name"]').fill('Usuário Criado via E2E');
        await page.locator('input[name="email"]').fill(uniqueEmail);
        await page.locator('input[name="password"]').fill('E2eNovoUsuario@1234');
        await page.locator('select[name="role"]').selectOption('seller');
        await page.getByRole('button', { name: 'Criar Usuário' }).click();

        await expect(page).toHaveURL(/\/admin/);
        await expect(page.getByText(uniqueEmail)).toBeVisible();
    });

    test('edita um usuário pelo formulário, que não tem mais o campo de avatar', async ({ page }) => {
        const uniqueEmail = `e2e.edit.${Date.now()}@crm.local`;

        await page.getByRole('link', { name: 'Novo Usuário' }).click();
        await page.locator('input[name="name"]').fill('Usuário Para Editar E2E');
        await page.locator('input[name="email"]').fill(uniqueEmail);
        await page.locator('input[name="password"]').fill('E2eEditarUsuario@1234');
        await page.getByRole('button', { name: 'Criar Usuário' }).click();
        await expect(page.getByText(uniqueEmail)).toBeVisible();

        await page.locator('tr', { hasText: uniqueEmail }).locator('a[href$="/edit"]').click();
        await expect(page).toHaveURL(/\/admin\/users\/\d+\/edit/);
        await expect(page.locator('[name="avatar"]')).toHaveCount(0);

        const newName = `Usuário Editado E2E ${Date.now()}`;
        await page.locator('input[name="name"]').fill(newName);
        await page.getByRole('button', { name: 'Salvar' }).click();

        await expect(page.getByText('Usuário atualizado com sucesso!')).toBeVisible();
        await expect(page.locator('tr', { hasText: uniqueEmail })).toContainText(newName);
    });

    test('o botão desativar da edição desativa o usuário', async ({ page }) => {
        page.on('dialog', (dialog) => dialog.accept());
        await setE2eAssigneeActive(true);
        try {
            await openAssigneeEdit(page);
            await page.getByRole('button', { name: 'Desativar' }).click();

            await expect(page.getByText('Usuário desativado com sucesso.')).toBeVisible();
            await expect(page.locator('tr', { hasText: E2E_ASSIGNEE_EMAIL })).toContainText('Inativo');
        } finally {
            await setE2eAssigneeActive(false);
        }
    });

    test('navega entre as abas de Organização e Ciclo de Pagamento', async ({ page }) => {
        // As abas são <a role="tab">, então a role acessível é "tab", não "link".
        await page.getByRole('tab', { name: 'Organização' }).click();
        await expect(page).toHaveURL(/tab=org/);

        await page.getByRole('tab', { name: 'Ciclo de Pagamento' }).click();
        await expect(page).toHaveURL(/tab=payment/);
    });
});
