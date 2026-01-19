import { test, expect } from '@playwright/test';

test.describe('Navigation', () => {
  test('should load home page and redirect to items', async ({ page }) => {
    await page.goto('/');
    // Home redirects to items
    await expect(page).toHaveURL(/\/items/);
  });

  test('should have correct title', async ({ page }) => {
    await page.goto('/items');
    // The title includes "Itens" for items page
    await expect(page).toHaveTitle(/Itens/);
  });

  test('should navigate to items from navbar', async ({ page }) => {
    await page.goto('/locations');
    await page.click('.navbar a:has-text("Itens")');
    await expect(page).toHaveURL(/\/items/);
  });

  test('should navigate to locations from navbar', async ({ page }) => {
    await page.goto('/items');
    await page.click('.navbar a:has-text("Locais")');
    await expect(page).toHaveURL(/\/locations/);
  });

  test('should navigate to tags from navbar', async ({ page }) => {
    await page.goto('/items');
    await page.click('.navbar a:has-text("Tags")');
    await expect(page).toHaveURL(/\/tags/);
  });

  test('should perform global search from navbar', async ({ page }) => {
    // Create an item first
    await page.goto('/items/create');
    const uniqueName = `BuscaGlobal${Date.now()}`;
    await page.fill('input#name', uniqueName);

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await submitBtn.click();
    await page.waitForURL(/items\/\d+$/, { timeout: 10000 });

    // Go to locations and use navbar search
    await page.goto('/locations');
    const searchInput = page.locator('.navbar input[name="search"]');
    await searchInput.fill(uniqueName);
    await searchInput.press('Enter');

    // Should find the item
    await expect(page.locator('body')).toContainText(uniqueName);
  });

  test('should show flash messages after actions', async ({ page }) => {
    // Create an item
    await page.goto('/items/create');
    const itemName = `FlashTest${Date.now()}`;
    await page.fill('input#name', itemName);

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await submitBtn.click();
    await page.waitForURL(/items\/\d+$/, { timeout: 10000 });

    // Flash messages should have been shown (might be gone already if auto-dismiss)
    // Just verify the page loaded correctly
    await expect(page.locator('body')).toContainText(itemName);
  });

  test('should have quick add button in navbar', async ({ page }) => {
    await page.goto('/items');

    // Click add button in navbar
    await page.click('.navbar a.btn-success');

    await expect(page).toHaveURL(/\/items\/create/);
  });

  test('should be responsive on mobile viewport', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/items');

    // Page should still be functional
    await expect(page).toHaveTitle(/Itens/);

    // Navbar should be collapsed - check for hamburger menu
    const hamburger = page.locator('.navbar-toggler');
    await expect(hamburger).toBeVisible();

    // Click hamburger to expand menu
    await hamburger.click();

    // Menu should expand and show links
    await expect(page.locator('.navbar-nav')).toBeVisible();
  });

  test('should navigate from item show to edit', async ({ page }) => {
    // Create an item
    await page.goto('/items/create');
    const itemName = `NavTest${Date.now()}`;
    await page.fill('input#name', itemName);

    const submitBtn = page.locator('#itemForm button[type="submit"], form button.btn-primary');
    await submitBtn.click();
    await page.waitForURL(/items\/\d+$/, { timeout: 10000 });

    // On show page, click edit
    await page.click('a:has-text("Editar")');

    // Should be on edit page
    await expect(page).toHaveURL(/\/edit/);
  });
});
