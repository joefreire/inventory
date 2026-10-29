import path from 'path';
import { expect, test } from './fixtures';

const FIXTURE = path.resolve('tests/fixtures/purchases/aliexpress.json');

test.describe('Purchases', () => {
  test('imports the JSON by upload and lists the lines', async ({ page }) => {
    await page.goto('/compras/importar');
    await page.getByLabel('Arquivo JSON das compras').setInputFiles(FIXTURE);
    await page.getByRole('button', { name: 'Importar' }).click();

    await expect(page).toHaveURL(/\/compras\/importar$/);
    await expect(page.locator('.toast-wrap')).toContainText('pedidos novos');

    await page.goto('/compras?mostrar=todos');
    await expect(page.getByText('SONOFF ZBMINIR2 Interruptor I...')).toBeVisible();
    await expect(page.getByText('Cabo USB-C 1m...')).toBeVisible();
  });

  test('refuses a file that is not JSON', async ({ page }) => {
    await page.goto('/compras/importar');
    await page.getByLabel('Arquivo JSON das compras').setInputFiles({ name: 'x.json', mimeType: 'application/json', buffer: Buffer.from('nao e json') });
    await page.getByRole('button', { name: 'Importar' }).click();
    await expect(page.getByText('Esse arquivo não é um JSON válido.')).toBeVisible();
  });

  test('the parts menu leads to Compras', async ({ page }) => {
    await page.goto('/items');
    await page.getByRole('button', { name: 'Menu' }).click();
    await page.getByRole('link', { name: 'Compras' }).click();
    await expect(page).toHaveURL(/\/compras$/);
    await expect(page.getByRole('heading', { name: 'Compras' })).toBeVisible();
  });
});
