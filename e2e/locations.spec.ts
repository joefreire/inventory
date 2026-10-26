import { expect, test } from './fixtures';
import { acceptConfirm, createItem, createLocation, unique } from './helpers';

test.describe('Boxes', () => {
  test('creates a box and shows its NFC link', async ({ page }) => {
    const name = unique('Caixa ESPs');
    await createLocation(page, name);

    await expect(page.locator('.tape', { hasText: name })).toBeVisible();
    await expect(page.getByText('Caixa vazia.', { exact: false })).toBeVisible();
    await expect(page.locator('#nfcUrl')).toHaveValue(/\/n\/[a-z0-9]{4,16}$/);
  });

  test('the NFC link opens the box', async ({ page }) => {
    const name = unique('Caixa tag');
    const boxUrl = await createLocation(page, name);
    const tagPath = new URL(await page.locator('#nfcUrl').inputValue()).pathname;

    await page.goto('/items');
    await page.goto(tagPath);
    await expect(page).toHaveURL(boxUrl);
  });

  test('a box inside another shows in both places', async ({ page }) => {
    const parent = unique('Armario');
    const parentUrl = await createLocation(page, parent);

    await page.getByRole('button', { name: 'Mais ações' }).click();
    await page.getByRole('link', { name: 'Nova caixa dentro desta' }).click();
    await expect(page.getByLabel('Dentro de').locator('option:checked')).toHaveText(parent);

    const child = unique('Gaveta');
    await page.getByLabel('Nome na etiqueta').fill(child);
    await page.getByRole('button', { name: 'Criar caixa' }).click();
    await page.waitForURL(/\/locations$/);

    await page.goto(parentUrl);
    await page.getByRole('link', { name: child }).click();
    await expect(page.getByRole('link', { name: parent })).toBeVisible();
  });

  test('"Cadastrar aqui" preselects the box', async ({ page }) => {
    const name = unique('Caixa sensores');
    await createLocation(page, name);

    await page.getByRole('link', { name: 'Cadastrar aqui' }).click();
    await expect(page).toHaveURL(/\/items\/create\?location_id=\d+$/);
    await expect(page.getByLabel('Caixa', { exact: true }).locator('option:checked')).toHaveText(name);
  });

  test('"Guardar item aqui" moves an existing part into the box', async ({ page }) => {
    const part = unique('Buzzer');
    await createItem(page, { name: part });
    const box = unique('Caixa som');
    const boxUrl = await createLocation(page, box);

    await page.getByRole('button', { name: 'Guardar item aqui' }).click();
    const sheet = page.locator('#guardarItem');
    await sheet.getByLabel('Filtrar peças').fill(part.toLowerCase());
    await sheet.getByRole('button', { name: part }).click();

    await page.waitForURL(boxUrl);
    await expect(page.locator('.rows').getByRole('link', { name: part })).toBeVisible();
    await expect(page.getByRole('heading', { name: '1 peça' })).toBeVisible();
  });

  test('edits a box', async ({ page }) => {
    const boxUrl = await createLocation(page, unique('Caixa velha'));

    await page.getByRole('button', { name: 'Mais ações' }).click();
    await page.getByRole('link', { name: 'Editar caixa' }).click();
    const newName = unique('Caixa nova');
    await page.getByLabel('Nome na etiqueta').fill(newName);
    await page.getByLabel('Onde fica').fill('prateleira de cima');
    await page.getByRole('button', { name: 'Salvar alterações' }).click();

    await page.waitForURL(/\/locations$/);
    await page.goto(boxUrl);
    await expect(page.locator('.tape', { hasText: newName })).toBeVisible();
    await expect(page.getByText('prateleira de cima')).toBeVisible();
  });

  test('deleting a box leaves its parts without a box', async ({ page }) => {
    const box = unique('Caixa que sai');
    await createLocation(page, box);
    const itemUrl = await createItem(page, { name: unique('Transistor'), location: box });

    await page.goto('/locations');
    await page.getByRole('link', { name: box }).click();
    await page.getByRole('button', { name: 'Mais ações' }).click();
    await page.getByRole('link', { name: 'Editar caixa' }).click();
    acceptConfirm(page);
    await page.getByRole('button', { name: 'Excluir caixa' }).click();

    await page.waitForURL(/\/locations$/);
    await expect(page.getByRole('link', { name: box })).toHaveCount(0);
    await page.goto(itemUrl);
    await expect(page.getByText('Ainda sem caixa.')).toBeVisible();
  });
});
