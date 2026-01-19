import { test, expect } from '@playwright/test';

test.describe('Tags', () => {
  test('should display tags index page', async ({ page }) => {
    await page.goto('/tags');
    await expect(page).toHaveTitle(/Tags/);
    await expect(page.locator('h2')).toContainText('Tags');
  });

  test('should navigate to create tag page', async ({ page }) => {
    await page.goto('/tags');
    await page.click('a:has-text("Nova Tag")');
    await expect(page).toHaveURL(/tags\/create/);
    await expect(page.locator('.card-header')).toContainText('Nova Tag');
  });

  test('should create a new tag', async ({ page }) => {
    await page.goto('/tags/create');

    const tagName = `eletronica-e2e-${Date.now()}`;

    await page.fill('input#name', tagName);
    await page.fill('input#color', '#ff5500');

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Should show tag in the list
    await expect(page.locator('body')).toContainText(tagName);
  });

  test('should create tag with default color', async ({ page }) => {
    await page.goto('/tags/create');

    const tagName = `tag-default-${Date.now()}`;

    await page.fill('input#name', tagName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Should show tag
    await expect(page.locator('body')).toContainText(tagName);
  });

  test('should view tag details', async ({ page }) => {
    // Create a tag
    await page.goto('/tags/create');
    const tagName = `tag-detalhes-${Date.now()}`;
    await page.fill('input#name', tagName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Click on the tag
    await page.click(`a:has-text("${tagName}")`);

    // Should show tag name in badge
    await expect(page.locator('.badge.fs-4')).toContainText(tagName);
  });

  test('should edit a tag', async ({ page }) => {
    // Create a tag
    await page.goto('/tags/create');
    const originalName = `tag-editar-${Date.now()}`;
    await page.fill('input#name', originalName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Click on the tag
    await page.click(`a:has-text("${originalName}")`);
    await page.waitForURL(/tags\/\d+$/);

    // Click edit
    await page.click('a:has-text("Editar")');
    await expect(page).toHaveURL(/\/edit/);

    // Update the name
    const newName = `tag-editada-${Date.now()}`;
    await page.fill('input#name', newName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Should show updated name in list
    await expect(page.locator('body')).toContainText(newName);
  });

  test('should show color preview when creating tag', async ({ page }) => {
    await page.goto('/tags/create');

    const tagName = `preview-test-${Date.now()}`;
    await page.fill('input#name', tagName);

    // The preview should update
    await expect(page.locator('#tagPreviewName')).toContainText(tagName);
  });

  test('should add tag to item', async ({ page }) => {
    // Create a tag
    await page.goto('/tags/create');
    const tagName = `filtro-tag-${Date.now()}`;
    await page.fill('input#name', tagName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Create an item with this tag
    await page.goto('/items/create');
    const itemName = `ItemComTag${Date.now()}`;
    await page.fill('input#name', itemName);
    await page.fill('input#tags', tagName);

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await submitBtn.click();
    await page.waitForURL(/items\/\d+$/, { timeout: 10000 });

    // Should show tag on item page
    await expect(page.locator('body')).toContainText(tagName);
  });

  test('should delete a tag', async ({ page }) => {
    // Create a tag
    await page.goto('/tags/create');
    const tagName = `tag-excluir-${Date.now()}`;
    await page.fill('input#name', tagName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Click on the tag
    await page.click(`a:has-text("${tagName}")`);
    await page.waitForURL(/tags\/\d+$/);

    // Click button that opens the delete modal
    await page.click('button[data-bs-toggle="modal"][data-bs-target="#deleteModal"]');

    // Wait for modal to be visible
    await page.waitForSelector('#deleteModal.show', { state: 'visible' });

    // Click the delete button inside the modal
    await page.click('#deleteModal form button[type="submit"]');
    await page.waitForURL(/\/tags/, { timeout: 10000 });

    // Should be on tags list
    await expect(page).toHaveURL(/\/tags/);
  });
});
