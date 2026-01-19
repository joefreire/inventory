import { test, expect } from '@playwright/test';

test.describe('Items', () => {
  test('should display items index page', async ({ page }) => {
    await page.goto('/items');
    await expect(page).toHaveTitle(/Itens/);
    await expect(page.locator('body')).toContainText('Todos os Itens');
  });

  test('should navigate to create item page', async ({ page }) => {
    await page.goto('/items');
    await page.click('a:has-text("Novo Item")');
    await expect(page).toHaveURL(/items\/create/);
    await expect(page.locator('.card-header')).toContainText('Adicionar Novo Item');
  });

  test('should create a new item', async ({ page }) => {
    await page.goto('/items/create');

    const itemName = `ItemTesteE2E${Date.now()}`;

    await page.fill('input#name', itemName);
    await page.fill('textarea#description', 'Descricao do item de teste');
    await page.fill('input#quantity', '5');

    // Click submit button in the form, not in navbar
    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Should have item name visible after redirect
    await expect(page.locator('body')).toContainText(itemName);
  });

  test('should search items', async ({ page }) => {
    // First create an item
    await page.goto('/items/create');
    const uniqueName = `ResistorBusca${Date.now()}`;
    await page.fill('input#name', uniqueName);

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Now search for it
    await page.goto('/items');
    const searchInput = page.locator('.navbar input[name="search"]');
    await searchInput.fill(uniqueName);
    await searchInput.press('Enter');

    // Should find the item
    await expect(page.locator('body')).toContainText(uniqueName);
  });

  test('should view item details', async ({ page }) => {
    // First create an item
    await page.goto('/items/create');
    const itemName = `ItemDetalhe${Date.now()}`;
    await page.fill('input#name', itemName);
    await page.fill('textarea#description', 'Descricao do item');

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Should show item details
    await expect(page.locator('body')).toContainText(itemName);
    await expect(page.locator('body')).toContainText('Descricao do item');
  });

  test('should edit an item', async ({ page }) => {
    // First create an item
    await page.goto('/items/create');
    const originalName = `ItemEditar${Date.now()}`;
    await page.fill('input#name', originalName);

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Click edit
    await page.click('a:has-text("Editar")');
    await expect(page).toHaveURL(/edit/);

    // Update the name
    const newName = `ItemEditado${Date.now()}`;
    await page.fill('input#name', newName);

    const editSubmitBtn = page.locator('form button.btn-primary');
    await Promise.all([
      page.waitForNavigation(),
      editSubmitBtn.click(),
    ]);

    // Should show updated name
    await expect(page.locator('body')).toContainText(newName);
  });

  test('should delete an item', async ({ page }) => {
    // First create an item
    await page.goto('/items/create');
    const itemName = `ItemExcluir${Date.now()}`;
    await page.fill('input#name', itemName);

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Click button that opens the delete modal
    await page.click('button[data-bs-toggle="modal"][data-bs-target="#deleteModal"]');

    // Wait for modal to be visible
    await page.waitForSelector('#deleteModal.show', { state: 'visible' });

    // Click the delete button inside the modal
    await Promise.all([
      page.waitForNavigation(),
      page.click('#deleteModal form button[type="submit"]'),
    ]);

    // Should redirect to items list
    await expect(page).toHaveURL(/\/items/);
  });

  test('should add tags to item', async ({ page }) => {
    // Create an item with tags
    await page.goto('/items/create');
    const itemName = `ItemComTags${Date.now()}`;
    await page.fill('input#name', itemName);
    await page.fill('input#tags', 'eletronica, teste, e2e');

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Should show tags
    await expect(page.locator('body')).toContainText('eletronica');
  });
});
