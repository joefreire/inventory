import { expect, test } from './fixtures';
import { acceptConfirm, createItem, unique } from './helpers';

function tagName(prefix: string): string {
  return unique(prefix).replace(' ', '-').toLowerCase();
}

test.describe('Tags', () => {
  test('creates a tag with a color', async ({ page }) => {
    const name = tagName('smd');
    await page.goto('/tags/create');
    await page.getByLabel('Nome').fill(name);
    await page.getByRole('textbox', { name: 'Cor', exact: true }).fill('#aa3366');
    await page.getByRole('button', { name: 'Criar tag' }).click();

    await page.waitForURL(/\/tags$/);
    const row = page.getByRole('link', { name });
    await expect(row).toBeVisible();
    await expect(row.locator('.tag-dot')).toHaveAttribute('style', /#aa3366/);
  });

  test('refuses an invalid color', async ({ page }) => {
    await page.goto('/tags/create');
    await page.getByLabel('Nome').fill(tagName('cor-ruim'));
    await page.getByRole('textbox', { name: 'Cor', exact: true }).fill('vermelho');
    await page.getByRole('button', { name: 'Criar tag' }).click();
    // the browser blocks the submit (pattern) or the server sends it back
    await expect(page).toHaveURL(/\/tags\/create$/);
  });

  test('the tag page lists its parts', async ({ page }) => {
    const tag = tagName('wifi');
    const part = unique('ESP8266');
    await createItem(page, { name: part, tags: tag });

    await page.goto('/tags');
    await page.getByRole('link', { name: tag }).click();
    await expect(page).toHaveURL(/\/tags\/\d+$/);
    await expect(page.getByText('1 peça', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: part })).toBeVisible();
  });

  test('renames a tag and the part shows the new name', async ({ page }) => {
    const tag = tagName('velha');
    const itemUrl = await createItem(page, { name: unique('Display OLED'), tags: tag });

    await page.goto('/tags');
    await page.getByRole('link', { name: tag }).click();
    await page.getByRole('link', { name: 'Editar tag' }).click();
    const renamed = tagName('nova');
    await page.getByLabel('Nome').fill(renamed);
    await page.getByRole('button', { name: 'Salvar alterações' }).click();

    await page.waitForURL(/\/tags$/);
    await page.goto(itemUrl);
    await expect(page.getByRole('link', { name: renamed })).toBeVisible();
  });

  test('deleting a tag keeps the parts', async ({ page }) => {
    const tag = tagName('excluir');
    const part = unique('Bateria 18650');
    const itemUrl = await createItem(page, { name: part, tags: tag });

    await page.goto('/tags');
    await page.getByRole('link', { name: tag }).click();
    await page.getByRole('link', { name: 'Editar tag' }).click();
    acceptConfirm(page);
    await page.getByRole('button', { name: 'Excluir tag' }).click();

    await page.waitForURL(/\/tags$/);
    await expect(page.getByRole('link', { name: tag })).toHaveCount(0);
    await page.goto(itemUrl);
    await expect(page.getByRole('heading', { name: part })).toBeVisible();
    await expect(page.getByRole('link', { name: tag })).toHaveCount(0);
  });

  test('tapping a suggested tag adds it to the form', async ({ page }) => {
    const tag = tagName('sugerida');
    await createItem(page, { name: unique('Primeira'), tags: tag });

    await page.goto('/items/create');
    await page.getByRole('button', { name: tag }).click();
    await expect(page.getByLabel('Tags', { exact: true })).toHaveValue(new RegExp(tag));
  });
});
