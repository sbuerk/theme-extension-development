import { expect, type Page, test } from '@playwright/test';

/**
 * The backend accounts of the seed. Credentials: "docs/development/instances.md".
 *
 * Kept to what both core versions render alike: the login, the page tree with
 * both roots, and which modules an account is given. The backend UI itself is
 * the core's, and a test of it here would be a test of TYPO3.
 */
async function login(page: Page, username: string, password: string): Promise<void> {
    await page.goto('/typo3/');
    await page.locator('#t3-username').fill(username);
    await page.locator('#t3-password').fill(password);
    await page.locator('#t3-login-submit').click();
    await expect(page.locator('.t3js-scaffold')).toBeVisible({ timeout: 30_000 });
}

/**
 * Both site roots are in the page tree - matched by their exact names, because
 * one of them is a prefix of the other.
 */
async function expectBothRoots(page: Page): Promise<void> {
    const tree = page.locator('typo3-backend-navigation-component-pagetree');
    await expect(tree).toBeVisible({ timeout: 30_000 });
    await expect(tree.getByText('Theme demo', { exact: true })).toBeVisible({ timeout: 30_000 });
    await expect(tree.getByText('Theme demo (sys_template delivery)', { exact: true })).toBeVisible();
}

test('the administrator sees both trees and the admin modules', async ({ page }) => {
    await login(page, 'john-doe', 'John-Doe-1701D.');
    await expectBothRoots(page);
    // The positive control of the editor test below: an admin only module.
    await expect(page.locator('[data-modulemenu-identifier="site_configuration"]')).toHaveCount(1);
});

test('the editor is not an administrator and sees both mounted trees', async ({ page }) => {
    await login(page, 'erika-editor', 'Erika-Editor-1701D.');
    await expectBothRoots(page);

    // An editor gets the modules of the group, and no admin only module -
    // "site_configuration" is "access => admin" on both core versions.
    await expect(page.locator('[data-modulemenu-identifier="web_layout"]')).toHaveCount(1);
    await expect(page.locator('[data-modulemenu-identifier="site_configuration"]')).toHaveCount(0);
});

test('a wrong backend password is refused', async ({ page }) => {
    await page.goto('/typo3/');
    await page.locator('#t3-username').fill('erika-editor');
    await page.locator('#t3-password').fill('not the password');
    await page.locator('#t3-login-submit').click();
    await expect(page.locator('#t3-login-error')).toBeVisible();
});
