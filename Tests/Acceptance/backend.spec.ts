import { expect, type Page, test } from '@playwright/test';

/**
 * The backend accounts of the seed. Credentials: "docs/development/instances.md".
 *
 * Kept to what both core versions render alike: the login, the page tree with
 * both roots, and which modules an account is given. The backend UI itself is
 * the core's, and a test of it here would be a test of TYPO3. The one
 * exception is the icon picker, whose icons are the theme's.
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

/**
 * The perceived lightness of a computed CSS colour, 0 for black and 1 for
 * white. Chromium and WebKit answer "rgb()", Firefox "color(srgb ...)" for a
 * colour the backend mixes, so both forms are read.
 */
function lightness(colour: string): number {
    const srgb = colour.match(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)/);
    const channels = srgb
        ? srgb.slice(1, 4).map(Number)
        : (colour.match(/[\d.]+/g) ?? []).slice(0, 3).map((value) => Number(value) / 255);
    const [red = 0, green = 0, blue = 0] = channels;
    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/**
 * The icons of the picker follow the backend scheme. The editor's scheme is
 * "auto", so the one of the browser decides. An "<img>" of the icon file draws
 * it in black whatever the scheme, which is what "InlineIconItems" and
 * "ShippedIconProvider" replace with the SVG itself, drawn in "currentColor".
 *
 * The record is "The full hero" of the showcase, id 801 in
 * "Configuration/DataFactory/theme-demo/Scenario.yaml", whose link icon field
 * shows the curated list of "IconPicker.tsconfig".
 */
for (const scheme of ['dark', 'light'] as const) {
    test(`the icons of the icon picker are drawn in the text colour of the ${scheme} scheme`, async ({ page }) => {
        await page.emulateMedia({ colorScheme: scheme });
        await login(page, 'john-doe', 'John-Doe-1701D.');
        // The backend shows its scaffold before it has imported all of its
        // modules. A navigation started then cancels those imports, and
        // WebKit answers it with "internal error" or never finishes it: 7 of
        // 30 runs against v12.4 before this wait, see "Three engines" in
        // "docs/testing/acceptance-tests.md".
        await page.waitForLoadState('networkidle');
        await page.goto('/typo3/record/edit?edit[tt_content][801]=edit');

        const form = page.frameLocator('#typo3-contentIframe');
        const field = form.locator('.t3js-formengine-field-item', {
            has: form.locator('select[name="data[tt_content][801][tx_theme_link_icon]"]'),
        });
        await expect(field).toBeVisible({ timeout: 30_000 });
        await expect(field.locator('img')).toHaveCount(0);
        const icon = field.locator('[data-identifier="theme-extension-development-solid-envelope"] svg path').last();
        await expect(icon).toBeAttached();

        const fill = await icon.evaluate((element) => getComputedStyle(element).fill);
        if (scheme === 'dark') {
            expect(lightness(fill), `fill ${fill}`).toBeGreaterThan(0.6);
        } else {
            expect(lightness(fill), `fill ${fill}`).toBeLessThan(0.4);
        }
    });
}
