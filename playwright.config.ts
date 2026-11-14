import { defineConfig, devices } from '@playwright/test';

const PORT = Number(process.env.E2E_PORT) || 8000;
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || `http://localhost:${PORT}`;
const E2E_DB_NAME = process.env.E2E_DB_NAME;

// o seed e os helpers abrem o php como processo filho e herdam este ambiente, entao o DB_NAME vai para o process.env e eles usam o mesmo banco do servidor.
if (E2E_DB_NAME) {
  process.env.DB_NAME = E2E_DB_NAME;
}

/**
 * Config E2E — sobe o servidor embutido do PHP (mesmo comando do dev local,
 * ver README) e roda os specs em tests/smoke/. O projeto "touch" reroda o
 * spec do Kanban com viewport/touch de tablet para validar o Pointer Events
 * de public/assets/js/pipeline.js em touch de verdade (page.touchscreen).
 */
export default defineConfig({
  testDir: './tests/smoke',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  timeout: 30_000,
  globalSetup: require.resolve('./tests/smoke/global-setup.ts'),

  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      testIgnore: /\.touch\.spec\.ts$/,
    },
    {
      name: 'tablet-touch',
      // Viewport/touch de tablet (~12"), mas forçando Chromium: o preset
      // 'iPad Pro 11 landscape' usa WebKit por padrão, e o drag por toque
      // (tests/smoke/helpers.ts) depende de Input.dispatchTouchEvent via
      // CDP, suportado apenas no Chromium.
      use: { ...devices['iPad Pro 11 landscape'], browserName: 'chromium' },
      testMatch: /\.touch\.spec\.ts$/,
    },
  ],

  webServer: {
    // o variables_order com E poe o ambiente no $_ENV qualquer que seja o php.ini: com GPCS o
    // valor vazio nao chega nem ao $_ENV nem ao $_SERVER, e o .env repoe o que estiver nele (medido).
    command: `php -d variables_order=EGPCS -S localhost:${PORT} -t public router.php`,
    url: `${BASE_URL}/login`,
    // o Playwright soma este env ao process.env; o .env so preenche o que o ambiente nao trouxe.
    // as GOOGLE_* vazias desligam a integracao no servidor que o Playwright sobe, mesmo com
    // as credenciais no .env. nao cobre um servidor que ja estava no ar, que o
    // reuseExistingServer reaproveita, como o que o CI sobe antes do Playwright.
    env: {
      APP_URL: `http://localhost:${PORT}`,
      GOOGLE_CLIENT_ID: '',
      GOOGLE_CLIENT_SECRET: '',
      ...(E2E_DB_NAME ? { DB_NAME: E2E_DB_NAME } : {}),
    },
    // true (não `!process.env.CI`) de propósito: no CI o servidor já é
    // subido manualmente ANTES do Playwright (ver .github/workflows/ci.yml)
    // para que o k6 rode logo em seguida contra o mesmo processo/estado —
    // com reuseExistingServer:true, o Playwright detecta o servidor já no
    // ar e o reaproveita em vez de tentar subir outro na mesma porta.
    reuseExistingServer: true,
    timeout: 20_000,
  },
});
