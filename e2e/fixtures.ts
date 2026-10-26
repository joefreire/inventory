import { test as base, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { E2E_EMAIL, E2E_PASSWORD } from './helpers';

/**
 * One login per worker, not one shared by all: with a single session, a request
 * from another worker consumes the flash messages ("Peça salva.") of this one.
 */
export const test = base.extend<object, { workerStorageState: string }>({
  storageState: ({ workerStorageState }, use) => use(workerStorageState),

  workerStorageState: [async ({ browser }, use) => {
    const id = test.info().parallelIndex;
    const file = path.resolve(test.info().project.outputDir, `.auth/worker-${id}.json`);

    const page = await browser.newPage({ storageState: undefined, baseURL: test.info().project.use.baseURL });
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(E2E_EMAIL);
    await page.getByLabel('Senha').fill(E2E_PASSWORD);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL(/\/items$/);
    await page.context().storageState({ path: file });
    await page.close();
    await use(file);
  }, { scope: 'worker' }],
});

export { expect };
