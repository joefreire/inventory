import path from 'path';
import { Page } from '@playwright/test';
import { expect, test } from './fixtures';
import { createItem, unique } from './helpers';

const FIXTURE = path.resolve('tests/fixtures/purchases/aliexpress.json');


/** Imports the fixture (idempotent: reimporting updates, never duplicates). */
async function importFixture(page: Page): Promise<void> {
  await page.goto('/compras/importar');
  await page.getByLabel('Arquivo JSON das compras').setInputFiles(FIXTURE);
  await page.getByRole('button', { name: 'Importar' }).click();
  await expect(page.locator('.toast-wrap')).toBeVisible();
}

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

  test('registers a part from a purchase and lists the purchase on its page', async ({ page }) => {
    await importFixture(page);

    await page.goto('/compras?mostrar=todos&q=Cabo USB-C');
    await page.getByRole('link', { name: 'Cadastrar peça' }).first().click();
    await expect(page.getByLabel('Nome', { exact: true })).toHaveValue('Cabo USB-C 1m...');
    await page.getByLabel('Nome', { exact: true }).fill(unique('Cabo USB-C'));
    await page.getByRole('button', { name: 'Salvar peça' }).click();
    await page.waitForURL(/\/items\/\d+$/);

    await expect(page.getByRole('heading', { name: 'Compras' })).toBeVisible();
    await expect(page.getByText(/AliExpress · .*pedido/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Abrir pedido na loja' })).toHaveAttribute('rel', 'noopener');
  });

  test('joins a purchase to an existing part, asking before adding units', async ({ page }) => {
    const name = unique('Parafuso junta');
    await importFixture(page);
    const itemUrl = await createItem(page, { name, quantity: 5 });

    await page.goto('/compras?mostrar=todos&q=Parafuso M3');
    await page.getByRole('button', { name: 'Juntar a uma peça que já tenho' }).first().click();
    await page.getByPlaceholder('Buscar peça...').fill(name);
    await page.locator('#joinResults').getByRole('button', { name: new RegExp(name) }).click();
    await expect(page.locator('#joinAsk')).toContainText('Somar 2 unidades');
    await page.getByRole('button', { name: 'Só ligar, sem somar' }).click();

    await page.waitForURL(itemUrl);
    await expect(page.locator('#qtyValue')).toHaveText('5');
    await expect(page.getByRole('heading', { name: 'Compras' })).toBeVisible();
  });

  test('dismisses a purchase', async ({ page }) => {
    await importFixture(page);
    await page.goto('/compras?mostrar=todos&q=SONOFF');
    const before = await page.getByRole('button', { name: 'Não guardo' }).count();
    test.skip(before === 0, 'line already handled by another test');
    await page.getByRole('button', { name: 'Não guardo' }).first().click();
    await expect(page.locator('.toast-wrap')).toContainText('pendentes');
  });
});
