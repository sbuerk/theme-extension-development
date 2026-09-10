import { expect, test } from '@playwright/test';

/**
 * The seeded showcase in a real browser, in both delivery trees.
 *
 * The functional tests already compare the markup of the two trees. What only a
 * browser shows is what happens after the markup: that the stylesheet and the
 * images load, that the script of the theme runs, and that the pages behave -
 * which is what a person opening a development instance looks at.
 */
const showcase = [
    '/',
    '/typography',
    '/media',
    '/empty',
    '/elements',
    '/elements/core',
    '/elements/menu',
    '/elements/theme',
    '/styleguide',
];

const trees = [
    { name: 'site set tree', prefix: '' },
    { name: 'sys_template tree', prefix: '/legacy' },
];

for (const tree of trees) {
    test.describe(tree.name, () => {
        for (const path of showcase) {
            const url = tree.prefix + path;

            test(`${url} renders through the theme`, async ({ page }) => {
                const errors: string[] = [];
                page.on('pageerror', (error) => errors.push(error.message));
                // Every stylesheet, script and image the page requests arrives -
                // a lazily loaded image below the fold is not requested. A
                // favicon is not the theme's, and the instance has none.
                page.on('response', (response) => {
                    if (response.status() >= 400 && !response.url().endsWith('/favicon.ico')) {
                        errors.push(`HTTP ${response.status()} ${response.url()}`);
                    }
                });
                page.on('requestfailed', (request) => errors.push(`failed ${request.url()}`));

                const response = await page.goto(url);
                expect(response?.status()).toBe(200);
                await expect(page.locator('[data-theme-page-layout]')).toHaveCount(1);
                await expect(page.locator('.theme-site-header__brand')).toHaveAttribute('href', `${tree.prefix}/`);
                await expect(page.getByText('has no rendering definition')).toHaveCount(0);

                // The inline head script of the theme ran: it marks the root
                // before anything else ("Appearance.typoscript").
                await expect(page.locator('html')).toHaveAttribute('data-js', '');
                // The stylesheet was applied, not only linked: without it the
                // body keeps the browser's transparent background.
                const background = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
                expect(background).not.toBe('rgba(0, 0, 0, 0)');
                await page.waitForLoadState('networkidle');

                expect(errors).toEqual([]);
            });
        }

        test(`${tree.prefix}/media loads every image`, async ({ page }) => {
            await page.goto(`${tree.prefix}/media`);
            const images = page.locator('main img');
            expect(await images.count()).toBeGreaterThan(0);
            for (const image of await images.all()) {
                await image.scrollIntoViewIfNeeded();
                await expect.poll(() => image.evaluate((element: HTMLImageElement) => element.complete && element.naturalWidth)).toBeGreaterThan(0);
            }
        });

        test(`${tree.prefix}/ keeps the hidden pages out of the main navigation`, async ({ page }) => {
            await page.goto(`${tree.prefix}/`);
            const navigation = page.locator('nav.theme-nav-main');
            await expect(navigation.getByRole('link', { name: 'Elements', exact: true })).toHaveCount(1);
            for (const hidden of ['Styleguide', 'Login', 'Members', 'Frontend users']) {
                await expect(navigation.getByRole('link', { name: hidden, exact: true })).toHaveCount(0);
            }
        });
    });
}

test('the appearance switcher applies a choice and keeps it across a reload', async ({ page }) => {
    await page.goto('/typography');
    const root = page.locator('html');

    await page.locator('[data-theme-appearance="dark"]').click();
    await expect(root).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('[data-theme-appearance="dark"]')).toHaveAttribute('aria-pressed', 'true');

    await page.reload();
    await expect(root).toHaveAttribute('data-theme', 'dark');

    // "auto" is the absence of the attribute: the operating system decides.
    await page.locator('[data-theme-appearance="auto"]').click();
    await expect(root).not.toHaveAttribute('data-theme', /.+/);
});

test('the main navigation collapses behind a toggle on a narrow screen', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await page.goto('/elements');
    const toggle = page.locator('.theme-nav-main__toggle');

    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('nav.theme-nav-main').getByRole('link', { name: 'Typography' })).toBeVisible();
});

test('the second level of the main menu stays closed until hovered, on a wide screen', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/typography');
    const item = page.locator('nav.theme-nav-main .theme-nav-main__item').filter({ has: page.getByRole('link', { name: 'Elements', exact: true }) });
    const secondLevel = item.locator('.theme-nav-main__list--sub');

    await expect(secondLevel).toBeHidden();
    await item.hover();
    await expect(secondLevel).toBeVisible();
    await expect(secondLevel.getByRole('link', { name: 'Core elements' })).toBeVisible();
});
