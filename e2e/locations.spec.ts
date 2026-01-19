import { test, expect } from '@playwright/test';

test.describe('Locations', () => {
  test('should display locations index page', async ({ page }) => {
    await page.goto('/locations');
    await expect(page).toHaveTitle(/Locais/);
    await expect(page.locator('h2')).toContainText('Locais');
  });

  test('should navigate to create location page', async ({ page }) => {
    await page.goto('/locations');
    await page.click('a:has-text("Novo Local")');
    await expect(page).toHaveURL(/locations\/create/);
    await expect(page.locator('.card-header')).toContainText('Novo Local');
  });

  test('should create a new location', async ({ page }) => {
    await page.goto('/locations/create');

    const locationName = `EscritorioE2E${Date.now()}`;

    await page.fill('input#name', locationName);
    await page.fill('textarea#description', 'Descricao do local de teste');

    // Click submit and wait for redirect
    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Should show location in the list after redirect
    await expect(page.locator('body')).toContainText(locationName);
  });

  test('should create hierarchical locations', async ({ page }) => {
    // Create parent location
    await page.goto('/locations/create');
    const parentName = `CasaE2E${Date.now()}`;
    await page.fill('input#name', parentName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Create child location
    await page.goto('/locations/create');
    const childName = `QuartoE2E${Date.now()}`;
    await page.fill('input#name', childName);
    await page.selectOption('select#parent_id', { label: parentName });

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Verify child was created in the list
    await expect(page.locator('body')).toContainText(childName);
  });

  test('should view location details', async ({ page }) => {
    // Create a location
    await page.goto('/locations/create');
    const locationName = `LocalDetalhes${Date.now()}`;
    await page.fill('input#name', locationName);
    await page.fill('textarea#description', 'Descricao para visualizar');

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Click on the location link
    await page.click(`a:has-text("${locationName}")`);

    // Should show details
    await expect(page.locator('h2')).toContainText(locationName);
  });

  test('should edit a location', async ({ page }) => {
    // Create a location
    await page.goto('/locations/create');
    const originalName = `LocalEditar${Date.now()}`;
    await page.fill('input#name', originalName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Click on the location
    await page.click(`a:has-text("${originalName}")`);
    await page.waitForURL(/locations\/\d+$/);

    // Click edit
    await page.click('a:has-text("Editar"), a[href*="edit"]');
    await expect(page).toHaveURL(/\/edit/);

    // Update the name
    const newName = `LocalEditado${Date.now()}`;
    await page.fill('input#name', newName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Should show updated name in list
    await expect(page.locator('body')).toContainText(newName);
  });

  test('should delete a location', async ({ page }) => {
    // Create a location
    await page.goto('/locations/create');
    const locationName = `LocalExcluir${Date.now()}`;
    await page.fill('input#name', locationName);

    await page.click('.card-body form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Click on the location
    await page.click(`a:has-text("${locationName}")`);
    await page.waitForURL(/locations\/\d+$/);

    // Click button that opens the delete modal
    await page.click('button[data-bs-toggle="modal"][data-bs-target="#deleteModal"]');

    // Wait for modal to be visible
    await page.waitForSelector('#deleteModal.show', { state: 'visible' });

    // Click the delete button inside the modal
    await page.click('#deleteModal form button[type="submit"]');
    await page.waitForURL(/\/locations/, { timeout: 10000 });

    // Should be on locations list
    await expect(page).toHaveURL(/\/locations/);
  });
});
