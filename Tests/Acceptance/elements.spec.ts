import { expect, test } from '@playwright/test';

/**
 * The tabs and the accordion content elements on the seeded "/elements/theme",
 * in a browser.
 *
 * The functional tests hold the markup to the contracts of ".theme-tabs" and
 * ".theme-accordion". What they cannot show is that the markup a content
 * element renders is markup the theme's script binds and the browser groups:
 * tabs rendered from records that switch, and an accordion whose items close
 * each other. The styleguide spec proves the same of literal specimens, which
 * is not the same thing - a content element can break the contract in a way
 * a specimen never does.
 */
test.describe('the tabs content element', () => {
    test('switches its panels with the arrow keys once the script has bound it', async ({ page }) => {
        await page.goto('/elements/theme');
        const group = page.locator('#c817 .theme-tabs');
        const tab = (name: string) => group.getByRole('tab', { name, exact: true });

        await expect(group).toHaveAttribute('data-theme-tabs-bound', '');
        await expect(tab('Site set')).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('#c817-tab-1-panel')).toBeVisible();
        await expect(page.locator('#c817-tab-2-panel')).toBeHidden();
        await expect(page.locator('#c817-tab-2-panel')).toHaveAttribute('aria-labelledby', 'c817-tab-2');

        await tab('Site set').focus();
        await page.keyboard.press('ArrowRight');
        await expect(tab('Static include')).toBeFocused();
        await expect(tab('Static include')).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('#c817-tab-2-panel')).toBeVisible();
        await expect(page.locator('#c817-tab-1-panel')).toBeHidden();

        // The rich text of the panel, with its link resolved rather than left
        // as a "t3://" reference.
        await expect(page.locator('#c817-tab-2-panel a')).toHaveAttribute('href', '/');

        await tab('Without the script').click();
        await expect(page.locator('#c817-tab-3-panel')).toBeVisible();
        await expect(page.locator('#c817-tab-2-panel')).toBeHidden();
    });

    test('shows every panel under its heading without JavaScript', async ({ browser }) => {
        const context = await browser.newContext({ javaScriptEnabled: false });
        const page = await context.newPage();
        await page.goto('/elements/theme');

        await expect(page.locator('#c817 .theme-tabs__list')).toBeHidden();
        for (const panel of ['#c817-tab-1-panel', '#c817-tab-2-panel', '#c817-tab-3-panel']) {
            await expect(page.locator(panel)).toBeVisible();
            await expect(page.locator(`${panel} .theme-tabs__heading`)).toBeVisible();
        }
        await context.close();
    });
});

test.describe('the accordion content element', () => {
    test('keeps one item open at a time', async ({ page }) => {
        await page.goto('/elements/theme');
        const items = page.locator('#c818 details.theme-accordion__item');
        await expect(items).toHaveCount(3);
        for (const item of await items.all()) {
            await expect(item).not.toHaveAttribute('open', '');
        }

        await items.nth(0).locator('summary').click();
        await expect(items.nth(0)).toHaveAttribute('open', '');
        await expect(items.nth(0).locator('.theme-accordion__panel')).toBeVisible();

        await items.nth(1).locator('summary').click();
        await expect(items.nth(1)).toHaveAttribute('open', '');
        // The shared name closed the first one, with no script involved.
        await expect(items.nth(0)).not.toHaveAttribute('open', '');
    });
});

test.describe('the notice content element', () => {
    test('draws all six kinds, each with its own glyph', async ({ page }) => {
        await page.goto('/elements/theme');
        for (const [uid, kind, role] of [[811, 'note', 'note'], [812, 'info', 'status'], [813, 'tip', 'note'], [814, 'success', 'status'], [815, 'warning', 'alert'], [816, 'danger', 'alert']]) {
            const notice = page.locator(`#c${uid} .theme-alert`);
            await expect(notice).toHaveClass(new RegExp(`theme-alert--${kind}`));
            await expect(notice).toHaveAttribute('role', `${role}`);
            await expect(notice.locator('.theme-alert__icon svg')).toBeVisible();
        }
    });
});
