import { expect, Page, test } from '@playwright/test';
import { createLocation, E2E_EMAIL, E2E_PASSWORD, unique } from './helpers';

async function login(page: Page, open = true): Promise<void> {
  if (open) await page.goto('/login');
  await page.getByLabel('E-mail').fill(E2E_EMAIL);
  await page.getByLabel('Senha').fill(E2E_PASSWORD);
  await page.getByRole('button', { name: 'Entrar' }).click();
}

// No saved session: the app is public on the internet, so everything asks for login.
test.describe('Login', () => {
  for (const path of ['/', '/items', '/items/create', '/locations', '/tags', '/items/ask']) {
    test(`${path} without a session goes to login`, async ({ page }) => {
      await page.goto(path);
      await expect(page).toHaveURL(/\/login$/);
      await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible();
    });
  }

  test('wrong password stays on login with an error', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-mail').fill(E2E_EMAIL);
    await page.getByLabel('Senha').fill('senha-errada-123');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.locator('.invalid-feedback')).toBeVisible();
  });

  test('NFC link without a session returns to the box after login', async ({ page }) => {
    await login(page);
    const name = unique('Caixa NFC');
    const boxUrl = await createLocation(page, name);
    const tagPath = new URL(await page.locator('#nfcUrl').inputValue()).pathname;
    expect(tagPath).toMatch(/^\/n\/[a-z0-9]{4,16}$/);

    await page.context().clearCookies();
    await page.goto(tagPath);
    await expect(page).toHaveURL(/\/login$/);
    await login(page, false);
    await expect(page).toHaveURL(boxUrl);
    await expect(page.locator('.tape', { hasText: name })).toBeVisible();
  });

  test('"Sair" in the parts menu ends the session', async ({ page }) => {
    await login(page);
    await page.waitForURL(/\/items$/);
    await page.getByRole('button', { name: 'Menu' }).click();
    await page.getByRole('button', { name: /Sair/ }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto('/items');
    await expect(page).toHaveURL(/\/login$/);
  });
});
