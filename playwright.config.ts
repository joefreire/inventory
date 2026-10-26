import { defineConfig, devices } from '@playwright/test';

/**
 * E2E against a server of its own (scripts/e2e-server.sh): port 8001, database
 * database/e2e.sqlite recreated on every run, AI without a key.
 * The front comes from public/build: run `npm run build` first (`npm run e2e` does).
 * Each worker logs in once (e2e/fixtures.ts); auth.spec.ts runs without a session.
 */
const port = Number(process.env.E2E_PORT ?? 8001);

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : 4,
  reporter: process.env.CI ? 'line' : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: `http://127.0.0.1:${port}`,
    locale: 'pt-BR',
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      // the owner uses the app on the phone; each worker logs in once (e2e/fixtures.ts)
      use: { ...devices['Pixel 7'] },
      testIgnore: /auth\.spec\.ts/,
    },
    {
      name: 'anonymous',
      use: { ...devices['Pixel 7'] },
      testMatch: /auth\.spec\.ts/,
    },
  ],

  webServer: {
    command: 'scripts/e2e-server.sh',
    url: `http://127.0.0.1:${port}/login`,
    env: { E2E_PORT: String(port) },
    // always a fresh database: never reuse a server left running
    reuseExistingServer: false,
    timeout: 120000,
  },
});
