import { expect, Page } from '@playwright/test';

export const E2E_EMAIL = process.env.E2E_EMAIL ?? 'e2e@inventario.test';
export const E2E_PASSWORD = process.env.E2E_PASSWORD ?? 'senha-dos-testes-e2e';

/** Unique name, so tests running in parallel never see each other's data. */
export function unique(prefix: string): string {
  return `${prefix} ${Date.now()}${Math.floor(Math.random() * 1000)}`;
}

export interface ItemFields {
  name: string;
  quantity?: number;
  description?: string;
  tags?: string;
  location?: string;
}

/** Fills the part form (create or edit) without photos. */
export async function fillItem(page: Page, item: ItemFields): Promise<void> {
  await page.getByLabel('Nome', { exact: true }).fill(item.name);
  if (item.quantity !== undefined) await page.getByLabel('Quantidade').fill(String(item.quantity));
  if (item.description) await page.getByLabel('O que é').fill(item.description);
  if (item.tags) await page.getByLabel('Tags', { exact: true }).fill(item.tags);
  if (item.location) await page.getByLabel('Caixa', { exact: true }).selectOption({ label: item.location });
}

/** Registers a part and waits for its page. Returns the part URL. */
export async function createItem(page: Page, item: ItemFields): Promise<string> {
  await page.goto('/items/create');
  await fillItem(page, item);
  await page.getByRole('button', { name: 'Salvar peça' }).click();
  await page.waitForURL(/\/items\/\d+$/);
  await expect(page.getByRole('heading', { name: item.name })).toBeVisible();
  return page.url();
}

/** Creates a box and returns its page URL. */
export async function createLocation(page: Page, name: string, parent?: string): Promise<string> {
  await page.goto('/locations/create');
  await page.getByLabel('Nome na etiqueta').fill(name);
  if (parent) await page.getByLabel('Dentro de').selectOption({ label: parent });
  await page.getByRole('button', { name: 'Criar caixa' }).click();
  await page.waitForURL(/\/locations$/);
  await page.getByRole('link', { name }).first().click();
  await page.waitForURL(/\/locations\/\d+$/);
  return page.url();
}

/** Delete buttons ask with window.confirm. */
export function acceptConfirm(page: Page): void {
  page.once('dialog', (dialog) => dialog.accept());
}
