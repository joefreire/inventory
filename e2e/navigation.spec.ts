import { expect, test } from './fixtures';

test.describe('Navigation', () => {
  test('home opens the parts list', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/items$/);
    await expect(page).toHaveTitle(/Peças · Inventário/);
  });

  test('tab bar reaches every section', async ({ page }) => {
    await page.goto('/items');
    const tabs = page.getByRole('navigation', { name: 'Navegação principal' });

    await tabs.getByRole('link', { name: 'Caixas' }).click();
    await expect(page).toHaveURL(/\/locations$/);
    await expect(page.getByRole('heading', { name: 'Caixas' })).toBeVisible();

    await tabs.getByRole('link', { name: 'Tags' }).click();
    await expect(page).toHaveURL(/\/tags$/);

    await tabs.getByRole('link', { name: 'Perguntar' }).click();
    await expect(page).toHaveURL(/\/items\/ask$/);

    await tabs.getByRole('link', { name: 'Cadastrar peça' }).click();
    await expect(page).toHaveURL(/\/items\/create$/);
    await expect(page.getByRole('heading', { name: 'Cadastrar peça' })).toBeVisible();

    await tabs.getByRole('link', { name: 'Peças' }).click();
    await expect(page).toHaveURL(/\/items$/);
  });

  test('the active tab follows the page', async ({ page }) => {
    await page.goto('/locations');
    const tabs = page.getByRole('navigation', { name: 'Navegação principal' });
    await expect(tabs.getByRole('link', { name: 'Caixas' })).toHaveClass(/active/);
    await expect(tabs.getByRole('link', { name: 'Peças' })).not.toHaveClass(/active/);
  });

  test('no console errors and no CSP violations on the main pages', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });
    page.on('pageerror', (err) => errors.push(err.message));
    for (const path of ['/items', '/items/create', '/locations', '/locations/create', '/tags', '/items/ask']) {
      await page.goto(path);
      await page.waitForLoadState('networkidle');
    }
    expect(errors).toEqual([]);
  });

  test('security headers are sent', async ({ page }) => {
    const response = await page.goto('/items');
    const headers = response!.headers();
    expect(headers['content-security-policy']).toContain("script-src 'self'");
    expect(headers['x-frame-options']).toBe('DENY');
    expect(headers['x-content-type-options']).toBe('nosniff');
  });
});
