import { expect, test } from './fixtures';
import { acceptConfirm, createItem, createLocation, fillItem, unique } from './helpers';

test.describe('Parts', () => {
  test('registers a part without a photo', async ({ page }) => {
    const name = unique('Resistor 10k');
    await createItem(page, { name, quantity: 25, description: 'Resistor de filme 1/4W' });

    await expect(page.locator('#qtyValue')).toHaveText('25');
    await expect(page.getByText('Resistor de filme 1/4W')).toBeVisible();
    await expect(page.getByText('Ainda sem caixa.')).toBeVisible();
    await expect(page.locator('.toast-wrap')).toContainText('Peça salva.');
  });

  test('the form refuses a part without a name', async ({ page }) => {
    await page.goto('/items/create');
    await page.getByRole('button', { name: 'Salvar peça' }).click();
    await expect(page).toHaveURL(/\/items\/create$/);
    await expect(page.locator('.toast-wrap')).toContainText('Falta o nome da peça.');
  });

  test('"Sugerir pelo nome" without a name asks for it', async ({ page }) => {
    await page.goto('/items/create');
    await page.getByRole('button', { name: /Sugerir pelo nome/ }).click();
    await expect(page.locator('.toast-wrap')).toContainText('Digite o nome da peça primeiro.');
  });

  test('saves tags typed on the form and filters by them', async ({ page }) => {
    const tag = unique('e2e-tag').replace(' ', '-').toLowerCase();
    const name = unique('ESP32 DevKit');
    await createItem(page, { name, tags: `${tag}, wifi` });

    await page.getByRole('link', { name: tag }).click();
    await expect(page).toHaveURL(/\/items\?tag_id=\d+$/);
    await expect(page.getByRole('link', { name })).toBeVisible();
    await expect(page.locator('.rows .row-item')).toHaveCount(1);
  });

  test('search finds the part by name', async ({ page }) => {
    const name = unique('Sensor DS18B20');
    await createItem(page, { name });

    await page.goto('/items');
    await page.getByRole('searchbox', { name: 'Buscar' }).fill(name);
    await page.getByRole('searchbox', { name: 'Buscar' }).press('Enter');
    await expect(page).toHaveURL(/search=/);
    await expect(page.getByRole('link', { name })).toBeVisible();
    await expect(page.getByText('1 peça para')).toBeVisible();
  });

  test('search with no match offers to ask', async ({ page }) => {
    await page.goto('/items?search=' + encodeURIComponent(unique('nada-com-isso')));
    await expect(page.getByRole('heading', { name: 'Nada com esse filtro' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Perguntar' }).first()).toBeVisible();
  });

  test('edits a part', async ({ page }) => {
    const itemUrl = await createItem(page, { name: unique('Capacitor') });

    await page.getByRole('button', { name: 'Mais ações' }).click();
    await page.getByRole('link', { name: 'Editar peça' }).click();
    await expect(page).toHaveURL(/\/edit$/);

    const newName = unique('Capacitor 100uF');
    await fillItem(page, { name: newName, quantity: 7 });
    await page.getByRole('button', { name: 'Salvar alterações' }).click();

    await expect(page).toHaveURL(itemUrl);
    await expect(page.getByRole('heading', { name: newName })).toBeVisible();
    await expect(page.locator('#qtyValue')).toHaveText('7');
  });

  test('the stepper changes the quantity and it sticks', async ({ page }) => {
    await createItem(page, { name: unique('LED vermelho'), quantity: 3 });

    const plus = page.getByRole('button', { name: 'Somar uma' });
    const minus = page.getByRole('button', { name: 'Tirar uma' });
    const saved = () => page.waitForResponse((r) => r.url().endsWith('/quantity') && r.ok());

    await Promise.all([saved(), plus.click()]);
    await Promise.all([saved(), plus.click()]);
    await Promise.all([saved(), minus.click()]);
    await expect(page.locator('#qtyValue')).toHaveText('4');

    await page.reload();
    await expect(page.locator('#qtyValue')).toHaveText('4');
  });

  test('the stepper does not go below zero', async ({ page }) => {
    await createItem(page, { name: unique('Fusivel'), quantity: 1 });
    const minus = page.getByRole('button', { name: 'Tirar uma' });

    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/quantity')), minus.click()]);
    await expect(page.locator('#qtyValue')).toHaveText('0');
    await expect(page.locator('#qtyLabel')).toHaveText('unidades');
    await minus.click();
    await expect(page.locator('#qtyValue')).toHaveText('0');
  });

  test('changes the box from the part page', async ({ page }) => {
    const box = unique('Gaveta');
    await createLocation(page, box);
    await createItem(page, { name: unique('Relé 5V') });

    await page.getByRole('button', { name: /Trocar caixa/ }).click();
    await Promise.all([
      page.waitForResponse((r) => r.url().endsWith('/move') && r.ok()),
      page.locator('#trocarCaixa').getByRole('button', { name: box }).click(),
    ]);
    // the page reloads itself after the move
    await expect(page.locator('.rows .tape', { hasText: box })).toBeVisible();
  });

  test('"Salvar e próxima" returns to the form in the same box', async ({ page }) => {
    const box = unique('Caixa sequencia');
    await createLocation(page, box);
    const name = unique('Diodo');

    await page.goto('/items/create');
    await fillItem(page, { name, location: box });
    await page.getByRole('button', { name: 'Salvar e próxima' }).click();

    await expect(page).toHaveURL(/\/items\/create\?location_id=\d+$/);
    await expect(page.getByLabel('Caixa', { exact: true }).locator('option:checked')).toHaveText(box);
    await expect(page.getByLabel('Nome', { exact: true })).toHaveValue('');
    await expect(page.locator('.toast-wrap')).toContainText(`Peça salva: ${name}. Pode tirar a próxima foto.`);
  });

  test('deletes a part after confirming', async ({ page }) => {
    const name = unique('Peça descartável');
    await createItem(page, { name });

    await page.getByRole('button', { name: 'Mais ações' }).click();
    acceptConfirm(page);
    await page.getByRole('button', { name: 'Excluir peça' }).click();

    await expect(page).toHaveURL(/\/items$/);
    await page.goto('/items?search=' + encodeURIComponent(name));
    await expect(page.getByRole('heading', { name: 'Nada com esse filtro' })).toBeVisible();
  });

  test('cancelling the confirm keeps the part', async ({ page }) => {
    const itemUrl = await createItem(page, { name: unique('Peça que fica') });

    await page.getByRole('button', { name: 'Mais ações' }).click();
    page.once('dialog', (dialog) => dialog.dismiss());
    await page.getByRole('button', { name: 'Excluir peça' }).click();

    await expect(page).toHaveURL(itemUrl);
  });

  test('AI without a key answers with an error, not a fake success', async ({ page }) => {
    await page.goto('/items/create');
    await page.getByLabel('Nome', { exact: true }).fill('Arduino Nano');
    const [response] = await Promise.all([
      page.waitForResponse((r) => r.url().endsWith('/items/analyze')),
      page.getByRole('button', { name: /Sugerir pelo nome/ }).click(),
    ]);
    expect(response.ok()).toBe(false);
    await expect(page.getByLabel('Nome', { exact: true })).toHaveValue('Arduino Nano');
  });
});
