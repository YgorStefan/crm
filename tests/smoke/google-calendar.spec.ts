import { test, expect, type Page, type Request } from '@playwright/test';
import { answerDialogs, login } from './helpers';

// o servidor da suite sobe sem GOOGLE_CLIENT_ID, entao a barra de conexao nao existe aqui;
// os estados conectados chegam pelo feed e pelas rotas falsas abaixo, so com dados ficticios.

const pad = (n: number) => String(n).padStart(2, '0');

// dia 15 do mes corrente sempre aparece na visao Mes que a tela abre.
function dayInView(offset = 0): string {
    const now = new Date();
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(15 + offset)}`;
}

type FeedEvent = Record<string, unknown>;

function googleEvent(overrides: Partial<{ title: string; start: string; end: string; props: Record<string, unknown> }> = {}): FeedEvent {
    const day = dayInView();
    return {
        id: 'g_evtfalso123',
        title: overrides.title ?? 'Reunião E2E do Google',
        start: overrides.start ?? `${day}T14:00:00`,
        end: overrides.end ?? `${day}T15:30:00`,
        color: '#64748b',
        extendedProps: {
            source: 'google',
            google_id: 'evtfalso123',
            can_edit: true,
            recurring: false,
            html_link: 'https://calendar.google.com/calendar/event?eid=ZXZlbnRvLWZhbHNv',
            description: 'Pauta de teste',
            all_day: false,
            ...(overrides.props ?? {}),
        },
    };
}

function taskEvent(overrides: Record<string, unknown> = {}, start = `${dayInView(1)} 09:00:00`): FeedEvent {
    return {
        id: 990001,
        title: 'Tarefa E2E do feed falso',
        start,
        color: '#f59e0b',
        extendedProps: {
            status: 'pending',
            priority: 'medium',
            client_id: null,
            client_name: null,
            recurrence_type: 'none',
            recurrence_parent_id: null,
            ...overrides,
        },
    };
}

interface Feed {
    events: FeedEvent[];
    status: string | null;
    requests: URL[];
}

async function mockFeed(page: Page, events: FeedEvent[], status: string | null = 'ok'): Promise<Feed> {
    const feed: Feed = { events, status, requests: [] };
    await page.route((url) => url.pathname.endsWith('/api/tasks/calendar'), async (route) => {
        feed.requests.push(new URL(route.request().url()));
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            headers: feed.status === null ? {} : { 'X-CRM-Google-Status': feed.status },
            body: JSON.stringify(feed.events),
        });
    });
    return feed;
}

// guarda o corpo de cada post para a rota do evento e responde com o que o teste pedir.
async function mockGoogleEventRoute(page: Page, action: 'update' | 'delete', reply: object = { success: true }): Promise<URLSearchParams[]> {
    const bodies: URLSearchParams[] = [];
    await page.route((url) => url.pathname.endsWith(`/api/google/events/evtfalso123/${action}`), async (route) => {
        bodies.push(new URLSearchParams(route.request().postData() ?? ''));
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(reply) });
    });
    return bodies;
}

function sentFields(body: URLSearchParams): Record<string, string> {
    const fields = Object.fromEntries(body.entries());
    delete fields._csrf_token;
    return fields;
}

async function openTasks(page: Page): Promise<void> {
    await page.goto('/tasks');
    await expect(page.locator('#fc-calendar')).toBeVisible();
}

test.describe('Calendário / sem credencial do Google', () => {
    test('a tela fica como hoje, sem barra do Google, e o feed vai com a janela visível', async ({ page }) => {
        await login(page);
        const feedRequest = page.waitForRequest((r: Request) => new URL(r.url()).pathname.endsWith('/api/tasks/calendar'));
        await openTasks(page);

        const url = new URL((await feedRequest).url());
        expect(url.searchParams.get('start')).toMatch(/^\d{4}-\d{2}-\d{2}T/);
        expect(url.searchParams.get('end')).toMatch(/^\d{4}-\d{2}-\d{2}T/);
        expect(url.searchParams.has('team')).toBe(false);

        await expect(page.locator('[data-google-bar]')).toHaveCount(0);
        await expect(page.locator('[data-google-hint]')).toHaveCount(0);
        await expect(page.locator('[data-google-team]')).toHaveCount(0);
        await expect(page.locator('#googleFeedNotice')).toBeHidden();
    });

    test('o botão Dia abre a visão de um dia', async ({ page }) => {
        await login(page);
        await openTasks(page);

        await page.getByRole('button', { name: 'Dia', exact: true }).click();
        await expect(page.locator('.fc-timeGridDay-view')).toBeVisible();
    });
});

test.describe('Calendário / eventos e avisos do Google com feed falso', () => {
    test.use({ timezoneId: 'America/Sao_Paulo' });

    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('o aviso discreto aparece com o feed degradado e some quando ele volta ok', async ({ page }) => {
        const feed = await mockFeed(page, [], 'degraded');
        await openTasks(page);

        await expect(page.locator('#googleFeedNotice')).toBeVisible();
        await expect(page.locator('#googleFeedNotice')).toContainText('O Google Agenda não respondeu');
        await expect(page.locator('[data-google-feed-reconnect]')).toBeHidden();

        feed.status = 'ok';
        await page.locator('.fc-next-button').click();
        await expect(page.locator('#googleFeedNotice')).toBeHidden();
    });

    test('o feed pedindo reconexão mostra o aviso com o link para reconectar', async ({ page }) => {
        await mockFeed(page, [], 'reconnect');
        await openTasks(page);

        await expect(page.locator('#googleFeedNotice')).toContainText('A conexão com o Google Agenda expirou');
        const link = page.locator('[data-google-feed-reconnect]');
        await expect(link).toBeVisible();
        expect(await link.getAttribute('href')).toMatch(/\/google\/connect$/);
    });

    test('falha de rede no feed não quebra a tela', async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        let calls = 0;
        await page.route((url) => url.pathname.endsWith('/api/tasks/calendar'), async (route) => {
            calls++;
            await route.abort('failed');
        });
        await openTasks(page);
        await expect.poll(() => calls).toBe(1);

        await page.locator('.fc-next-button').click();
        await expect.poll(() => calls).toBe(2);
        await expect(page.locator('#fc-calendar')).toBeVisible();
        expect(errors).toEqual([]);
    });

    test('o evento do Google abre o modal próprio com os valores e o título como texto', async ({ page }) => {
        const title = '<img src=x id=injetado>Reunião E2E do Google';
        await mockFeed(page, [googleEvent({ title })]);
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        const modal = page.locator('#modalGoogleEvent');
        await expect(modal).toBeVisible();
        await expect(page.locator('#modalTask')).toBeHidden();
        await expect(page.locator('#googleEvent_title')).toHaveValue(title);
        await expect(page.locator('#googleEvent_start')).toHaveAttribute('type', 'datetime-local');
        await expect(page.locator('#googleEvent_start')).toHaveValue(`${dayInView()}T14:00`);
        await expect(page.locator('#googleEvent_end')).toHaveValue(`${dayInView()}T15:30`);
        await expect(page.locator('#googleEvent_description')).toHaveValue('Pauta de teste');
        await expect(page.locator('#googleEvent_all_day')).not.toBeChecked();
        await expect(page.locator('#googleEventLink')).toHaveAttribute('href', 'https://calendar.google.com/calendar/event?eid=ZXZlbnRvLWZhbHNv');
        await expect(page.locator('#googleEventRecurring')).toBeHidden();
        await expect(page.locator('#googleEventReadOnly')).toBeHidden();
        await expect(page.locator('#btnSaveGoogleEvent')).toBeVisible();
        await expect(page.locator('#btnDeleteGoogleEvent')).toBeVisible();
        await expect(page.locator('#injetado')).toHaveCount(0);
    });

    test('salvar manda só o título quando só ele mudou e recarrega o feed', async ({ page }) => {
        const feed = await mockFeed(page, [googleEvent()]);
        const bodies = await mockGoogleEventRoute(page, 'update');
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#googleEvent_title').fill('Reunião E2E renomeada');
        const before = feed.requests.length;
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#modalGoogleEvent')).toBeHidden();
        expect(bodies).toHaveLength(1);
        expect(sentFields(bodies[0])).toEqual({ title: 'Reunião E2E renomeada' });
        expect(bodies[0].get('_csrf_token')).toBeTruthy();
        await expect.poll(() => feed.requests.length).toBeGreaterThan(before);
    });

    test('salvar sem mudar nada só fecha o modal', async ({ page }) => {
        await mockFeed(page, [googleEvent()]);
        const bodies = await mockGoogleEventRoute(page, 'update');
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#modalGoogleEvent')).toBeHidden();
        expect(bodies).toHaveLength(0);
    });

    test('mudar o horário manda início e fim no formato local', async ({ page }) => {
        await mockFeed(page, [googleEvent()]);
        const bodies = await mockGoogleEventRoute(page, 'update');
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#googleEvent_start').fill(`${dayInView(2)}T08:15`);
        await page.locator('#googleEvent_end').fill(`${dayInView(2)}T09:00`);
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#modalGoogleEvent')).toBeHidden();
        expect(sentFields(bodies[0])).toEqual({ start: `${dayInView(2)}T08:15`, end: `${dayInView(2)}T09:00` });
    });

    test('fim antes do início não envia e avisa no modal', async ({ page }) => {
        await mockFeed(page, [googleEvent()]);
        const bodies = await mockGoogleEventRoute(page, 'update');
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#googleEvent_end').fill(`${dayInView()}T13:00`);
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#googleEventError')).toHaveText('O fim não pode ser antes do início.');
        await expect(page.locator('#modalGoogleEvent')).toBeVisible();
        expect(bodies).toHaveLength(0);
    });

    test('erro do servidor ao salvar fica no modal com a mensagem dele', async ({ page }) => {
        await mockFeed(page, [googleEvent()]);
        await mockGoogleEventRoute(page, 'update', { success: false, error: 'Mensagem falsa do servidor.' });
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#googleEvent_title').fill('Outro título');
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#googleEventError')).toHaveText('Mensagem falsa do servidor.');
        await expect(page.locator('#modalGoogleEvent')).toBeVisible();
    });

    test('evento de dia inteiro usa campos de data e mostra o último dia, mandando o fim exclusivo', async ({ page }) => {
        await mockFeed(page, [googleEvent({
            start: dayInView(),
            end: dayInView(2),
            props: { all_day: true },
        })]);
        const bodies = await mockGoogleEventRoute(page, 'update');
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await expect(page.locator('#googleEvent_all_day')).toBeChecked();
        await expect(page.locator('#googleEvent_start')).toHaveAttribute('type', 'date');
        await expect(page.locator('#googleEvent_end')).toHaveAttribute('type', 'date');
        await expect(page.locator('#googleEvent_start')).toHaveValue(dayInView());
        await expect(page.locator('#googleEvent_end')).toHaveValue(dayInView(1));

        await page.locator('#googleEvent_end').fill(dayInView(3));
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#modalGoogleEvent')).toBeHidden();
        expect(sentFields(bodies[0])).toEqual({ end: dayInView(4) });
    });

    test('marcar dia inteiro troca os campos e manda início, fim e o dia inteiro juntos', async ({ page }) => {
        await mockFeed(page, [googleEvent()]);
        const bodies = await mockGoogleEventRoute(page, 'update');
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#googleEvent_all_day').check();
        await expect(page.locator('#googleEvent_start')).toHaveAttribute('type', 'date');
        await expect(page.locator('#googleEvent_start')).toHaveValue(dayInView());
        await expect(page.locator('#googleEvent_end')).toHaveValue(dayInView());
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#modalGoogleEvent')).toBeHidden();
        expect(sentFields(bodies[0])).toEqual({ all_day: '1', start: dayInView(), end: dayInView(1) });
    });

    test('desmarcar e marcar de novo o dia inteiro volta os valores e não manda nada', async ({ page }) => {
        await mockFeed(page, [googleEvent()]);
        const bodies = await mockGoogleEventRoute(page, 'update');
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#googleEvent_all_day').check();
        await page.locator('#googleEvent_all_day').uncheck();
        await expect(page.locator('#googleEvent_start')).toHaveValue(`${dayInView()}T14:00`);
        await expect(page.locator('#googleEvent_end')).toHaveValue(`${dayInView()}T15:30`);
        await page.locator('#btnSaveGoogleEvent').click();

        await expect(page.locator('#modalGoogleEvent')).toBeHidden();
        expect(bodies).toHaveLength(0);
    });

    test('sem permissão de edição o modal fica somente leitura', async ({ page }) => {
        await mockFeed(page, [googleEvent({ props: { can_edit: false } })]);
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await expect(page.locator('#googleEventReadOnly')).toBeVisible();
        await expect(page.locator('#googleEvent_title')).not.toBeEditable();
        await expect(page.locator('#googleEvent_start')).not.toBeEditable();
        await expect(page.locator('#googleEvent_end')).not.toBeEditable();
        await expect(page.locator('#googleEvent_description')).not.toBeEditable();
        await expect(page.locator('#googleEvent_all_day')).toBeDisabled();
        await expect(page.locator('#btnSaveGoogleEvent')).toBeHidden();
        await expect(page.locator('#btnDeleteGoogleEvent')).toBeHidden();
        await expect(page.locator('#btnCloseGoogleEvent')).toHaveText('Fechar');
        await expect(page.locator('#googleEventLink')).toBeVisible();
    });

    test('o modal de evento editável depois de um somente leitura volta a editar', async ({ page }) => {
        const editable = googleEvent();
        const readOnly = { ...googleEvent({ title: 'Somente leitura E2E', start: `${dayInView(1)}T10:00:00`, end: `${dayInView(1)}T11:00:00`, props: { can_edit: false, google_id: 'outrofalso' } }), id: 'g_outrofalso' };
        await mockFeed(page, [editable, readOnly]);
        await openTasks(page);

        await page.getByText('Somente leitura E2E').click();
        await expect(page.locator('#googleEventReadOnly')).toBeVisible();
        await page.locator('#btnCloseGoogleEvent').click();

        await page.getByText('Reunião E2E do Google').click();
        await expect(page.locator('#googleEventReadOnly')).toBeHidden();
        await expect(page.locator('#googleEvent_title')).toBeEditable();
        await expect(page.locator('#googleEvent_all_day')).toBeEnabled();
        await expect(page.locator('#btnSaveGoogleEvent')).toBeVisible();
        await expect(page.locator('#btnCloseGoogleEvent')).toHaveText('Cancelar');
    });

    test('ocorrência de série avisa que a mudança vale só para ela', async ({ page }) => {
        await mockFeed(page, [googleEvent({ props: { recurring: true } })]);
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await expect(page.locator('#googleEventRecurring')).toBeVisible();
        await expect(page.locator('#googleEventRecurring')).toContainText('só para esta ocorrência');
    });

    test('excluir pede confirmação, manda o delete e tira o evento da tela', async ({ page }) => {
        await mockFeed(page, [googleEvent()]);
        const bodies = await mockGoogleEventRoute(page, 'delete');
        const dialogs = answerDialogs(page, [true]);
        await openTasks(page);

        await page.getByText('Reunião E2E do Google').click();
        await page.locator('#btnDeleteGoogleEvent').click();

        await expect(page.locator('#modalGoogleEvent')).toBeHidden();
        expect(dialogs).toEqual(['Excluir este evento do Google Agenda?']);
        expect(bodies).toHaveLength(1);
        expect(bodies[0].get('_csrf_token')).toBeTruthy();
        await expect(page.getByText('Reunião E2E do Google')).toHaveCount(0);
    });

    test('filtro de status ou de prioridade esconde os eventos do Google e deixa as tarefas', async ({ page }) => {
        // a meia-noite de hoje ja passou, entao o filtro de atrasadas alcanca os dois.
        const now = new Date();
        const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        await mockFeed(page, [
            googleEvent({ start: `${today}T00:00:00`, end: `${today}T00:30:00` }),
            taskEvent({}, `${today} 00:00:00`),
        ]);
        await openTasks(page);
        const google = page.getByText('Reunião E2E do Google');
        const task = page.getByText('Tarefa E2E do feed falso');
        await expect(google).toBeVisible();

        await page.locator('.fc-filter-btn[data-filter="overdue"]').click();
        await expect(google).toHaveCount(0);
        await expect(task).toBeVisible();

        await page.locator('.fc-filter-btn[data-filter="pending"]').click();
        await expect(google).toHaveCount(0);
        await expect(task).toBeVisible();

        await page.locator('.fc-filter-btn[data-filter="all"]').click();
        await expect(google).toBeVisible();

        await page.locator('#fcPriorityFilter').selectOption('medium');
        await expect(google).toHaveCount(0);
        await expect(task).toBeVisible();
    });

    test('a dica da tarefa mostra o responsável quando o feed traz o nome', async ({ page }) => {
        await mockFeed(page, [taskEvent({ assigned_name: 'Pessoa Colega' })]);
        await openTasks(page);

        const el = page.locator('.fc-event', { hasText: 'Tarefa E2E do feed falso' });
        await expect(el).toHaveAttribute('title', /Responsável: Pessoa Colega/);
    });
});
