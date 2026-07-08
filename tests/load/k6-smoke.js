import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Carga (k6) — smoke test dos endpoints mais sensíveis do CRM.
 *
 * Por padrão roda uma carga LEVE (poucos VUs, curta duração), pensada para
 * rodar dentro do pipeline de CI (GitHub Actions) contra o servidor PHP
 * embutido, sem virar gargalo do pipeline nem estressar o runner.
 *
 * Para uma carga mais pesada contra staging/produção, sobrescreva via
 * variáveis de ambiente (ver README.md, seção "Testes e CI"):
 *
 *   k6 run -e BASE_URL=https://staging.exemplo.com \
 *          -e CRM_EMAIL=usuario@exemplo.com -e CRM_PASSWORD='...' \
 *          -e VUS=20 -e DURATION=2m tests/load/k6-smoke.js
 *
 * Pré-requisito: as credenciais usadas precisam existir no banco alvo (no
 * CI local, o smoke usa o usuário fixo semeado por tests/smoke/seed.php).
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.CRM_EMAIL || 'e2e.seller@crm.local';
const PASSWORD = __ENV.CRM_PASSWORD || 'E2eSeller@1234';

export const options = {
    vus: Number(__ENV.VUS || 5),
    duration: __ENV.DURATION || '20s',
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<1500'],
        checks: ['rate>0.99'],
    },
};

function extractBetween(body, before, after) {
    const start = body.indexOf(before);
    if (start === -1) return null;
    const from = start + before.length;
    const end = body.indexOf(after, from);
    if (end === -1) return null;
    return body.substring(from, end);
}

/** Faz login e devolve o token CSRF estável da sessão (ver CsrfMiddleware). */
function login() {
    const loginPage = http.get(`${BASE_URL}/login`);
    const loginToken = extractBetween(loginPage.body, 'name="_csrf_token" value="', '"');

    const loginResp = http.post(
        `${BASE_URL}/login`,
        { email: EMAIL, password: PASSWORD, _csrf_token: loginToken },
        { redirects: 1 },
    );

    check(loginResp, {
        'login: redirecionou para o dashboard': (r) => r.url.includes('/dashboard'),
    });

    const csrfToken = extractBetween(loginResp.body, 'name="csrf-token" content="', '"');
    return csrfToken;
}

// O login é feito UMA VEZ POR VU (variável de módulo — o k6 reutiliza a
// mesma VM JS entre iterações de um mesmo VU) e reaproveitado nas demais
// iterações via cookie de sessão. Isso evita esbarrar no rate-limit legítimo
// de produção (5 POST /login por IP a cada 60s — ver RateLimitMiddleware),
// já que o número de logins passa a ser igual a `VUS`, não `VUS × iterações`.
let cachedCsrfToken = null;

function csrfToken() {
    if (!cachedCsrfToken) {
        cachedCsrfToken = login();
    }
    return cachedCsrfToken;
}

/** Extrai o cliente semeado "Cliente E2E Kanban" e sua etapa atual do HTML do pipeline. */
function findSeededKanbanCard(body) {
    const marker = 'Cliente E2E Kanban';
    const idx = body.indexOf(marker);
    if (idx === -1) return null;

    // Procura o início do <div class="kanban-card" ...> imediatamente antes do nome.
    const cardStart = body.lastIndexOf('<div class="kanban-card', idx);
    if (cardStart === -1) return null;
    const cardTagEnd = body.indexOf('>', cardStart);
    const cardTag = body.substring(cardStart, cardTagEnd);

    const clientIdMatch = cardTag.match(/data-client-id="(\d+)"/);
    const stageMatch = cardTag.match(/data-current-stage="(\d+)"/);
    if (!clientIdMatch || !stageMatch) return null;

    return { clientId: Number(clientIdMatch[1]), stageId: Number(stageMatch[1]) };
}

export default function crmSmokeScenario() {
    const token = csrfToken();

    const dashboard = http.get(`${BASE_URL}/dashboard`);
    check(dashboard, { 'dashboard: 200': (r) => r.status === 200 });

    const clients = http.get(`${BASE_URL}/clients`);
    check(clients, { 'clients: 200': (r) => r.status === 200 });

    const stats = http.get(`${BASE_URL}/api/dashboard/stats`);
    check(stats, { 'api/dashboard/stats: 200': (r) => r.status === 200 });

    const pipelinePage = http.get(`${BASE_URL}/pipeline`);
    check(pipelinePage, { 'pipeline: 200': (r) => r.status === 200 });

    const card = findSeededKanbanCard(pipelinePage.body);
    if (card && token) {
        // Alterna entre as duas primeiras etapas do funil (1 <-> 2), mesma
        // faixa usada pelo cliente semeado — mantém o smoke idempotente.
        const targetStage = card.stageId === 1 ? 2 : 1;
        const move = http.post(
            `${BASE_URL}/pipeline/move`,
            JSON.stringify({ client_id: card.clientId, stage_id: targetStage }),
            { headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token } },
        );
        check(move, { 'pipeline/move: 200': (r) => r.status === 200 });
    }

    sleep(1);
}
