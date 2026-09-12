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
    '/forms',
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
            for (const hidden of ['Styleguide', 'Forms', 'Login', 'Members', 'Frontend users']) {
                await expect(navigation.getByRole('link', { name: hidden, exact: true })).toHaveCount(0);
            }
        });
    });
}

/**
 * The display settings behind the cog at the end of the header.
 *
 * Every test starts in a fresh browser context, so with nothing stored: the
 * instance renders the shipped constants, "auto", "neutral" and "on".
 */
test.describe('the display settings', () => {
    test('apply a choice at once and keep it across a reload', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/typography');
        const root = page.locator('html');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const panel = page.locator('#theme-settings-panel');

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(panel).toBeHidden();
        await trigger.click();
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(panel).toBeVisible();

        await panel.getByRole('radio', { name: 'Dark' }).check();
        await panel.getByRole('radio', { name: 'Ember' }).check();
        await panel.getByRole('switch', { name: 'Element outlines' }).uncheck();
        await expect(root).toHaveAttribute('data-theme', 'dark');
        await expect(root).toHaveAttribute('data-palette', 'ember');
        await expect(root).toHaveAttribute('data-theme-content-outline', 'off');

        await page.reload();
        await expect(root).toHaveAttribute('data-theme', 'dark');
        await expect(root).toHaveAttribute('data-palette', 'ember');
        await expect(root).toHaveAttribute('data-theme-content-outline', 'off');
        await trigger.click();
        await expect(panel.getByRole('radio', { name: 'Dark' })).toBeChecked();
        await expect(panel.getByRole('radio', { name: 'Ember' })).toBeChecked();
        await expect(panel.getByRole('switch', { name: 'Element outlines' })).not.toBeChecked();

        // The stored choice is applied by the inline head script before first
        // paint, not by the module script afterwards: with the module blocked,
        // the root still carries all three.
        await page.route('**/theme.js*', (route) => route.abort());
        await page.reload();
        await expect(root).toHaveAttribute('data-theme', 'dark');
        await expect(root).toHaveAttribute('data-palette', 'ember');
        await expect(root).toHaveAttribute('data-theme-content-outline', 'off');
    });

    test('close on Escape and return focus to the button', async ({ page }) => {
        await page.goto('/typography');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const panel = page.locator('#theme-settings-panel');

        await trigger.click();
        await panel.getByRole('radio', { name: 'Light' }).focus();
        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(trigger).toBeFocused();
    });

    test('close when focus leaves it with Tab', async ({ page }) => {
        await page.goto('/typography');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const panel = page.locator('#theme-settings-panel');

        await trigger.click();
        await panel.getByRole('button', { name: 'Reset' }).focus();
        // Past the last control of the panel: the panel must not stay open
        // over whatever now has focus, and focus stays where Tab put it.
        await page.keyboard.press('Tab');
        await expect(panel).toBeHidden();
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(trigger).not.toBeFocused();
    });

    test('store Auto as a choice of its own', async ({ page }) => {
        await page.goto('/typography');
        const root = page.locator('html');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const panel = page.locator('#theme-settings-panel');

        await trigger.click();
        await panel.getByRole('radio', { name: 'Dark' }).check();
        await panel.getByRole('radio', { name: 'Auto' }).check();
        await expect(root).not.toHaveAttribute('data-theme', /.*/);
        // Stored, not removed: without a key the next page would fall back to
        // the site default, which need not be "auto".
        expect(await page.evaluate(() => window.localStorage.getItem('theme-appearance'))).toBe('auto');
    });

    test('keep brand, menu and button in one row on a wide screen', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/typography');
        const brandLocator = page.locator('.theme-site-header__brand');
        const brand = await brandLocator.boundingBox();
        const nav = await page.locator('nav.theme-nav-main').boundingBox();
        const cog = await page.getByRole('button', { name: 'Display settings' }).boundingBox();
        if (brand === null || nav === null || cog === null) {
            throw new Error('The brand, the main navigation or the settings button is not rendered.');
        }

        // Side by side, in this order, without overlapping.
        expect(brand.x + brand.width).toBeLessThanOrEqual(nav.x);
        expect(nav.x + nav.width).toBeLessThanOrEqual(cog.x);
        for (const box of [brand, nav]) {
            expect(box.y).toBeLessThan(cog.y + cog.height);
            expect(box.y + box.height).toBeGreaterThan(cog.y);
        }

        // Nothing squeezed: the site title keeps one line - it wrapped onto
        // five beside the old button groups - and the top level of the menu
        // one row.
        const lineHeight = await brandLocator.evaluate((element) => parseFloat(getComputedStyle(element).lineHeight));
        expect(brand.height).toBeLessThan(lineHeight * 1.5);
        const tops = await page.locator('nav.theme-nav-main > .theme-nav-main__list > .theme-nav-main__item').evaluateAll(
            (items) => items.map((item) => item.getBoundingClientRect().top),
        );
        expect(tops.length).toBeGreaterThan(1);
        expect(Math.max(...tops) - Math.min(...tops)).toBeLessThan(8);
    });

    test('keep the chosen options visible in forced colours', async ({ page }) => {
        await page.emulateMedia({ forcedColors: 'active' });
        await page.goto('/typography');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const panel = page.locator('#theme-settings-panel');
        await trigger.click();

        // The checked segment has to stand out from the track it sits on.
        // Left to the browser, both compute to "Canvas".
        const track = await panel.locator('.theme-segmented').evaluate((element) => getComputedStyle(element).backgroundColor);
        const label = (value: string) => panel.locator(`.theme-segmented__option:has(input[value="${value}"]) .theme-segmented__label`);
        const checked = await label('auto').evaluate((element) => getComputedStyle(element).backgroundColor);
        expect(checked).not.toBe(track);
        const foreground = (value: string) => label(value).evaluate((element) => getComputedStyle(element).color);
        expect(await foreground('auto')).not.toBe(await foreground('light'));

        // The switch keeps a thumb, and on and off look different. Left to
        // the browser, the thumb - a gradient - is dropped and the track is
        // "Canvas" in both states.
        const outline = panel.getByRole('switch', { name: 'Element outlines' });
        const look = () => outline.evaluate((element) => {
            const style = getComputedStyle(element);
            return { image: style.backgroundImage, background: style.backgroundColor };
        });
        const on = await look();
        expect(on.image).not.toBe('none');
        await outline.uncheck();
        // Polled: the track colour is transitioned, and read at once it is
        // still on its way from the "on" colour.
        await expect.poll(async () => (await look()).background).not.toBe(on.background);
        expect((await look()).image).not.toBe('none');
    });

    test('close on a click outside', async ({ page }) => {
        await page.goto('/typography');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const panel = page.locator('#theme-settings-panel');

        await trigger.click();
        await expect(panel).toBeVisible();
        await page.locator('main').click({ position: { x: 5, y: 5 } });
        await expect(panel).toBeHidden();
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    });

    test('keep the header in one row and the panel inside a narrow screen', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 740 });
        await page.goto('/typography');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const brand = await page.locator('.theme-site-header__brand').boundingBox();
        const toggle = await page.locator('.theme-nav-main__toggle').boundingBox();
        const cog = await trigger.boundingBox();
        if (brand === null || toggle === null || cog === null) {
            throw new Error('The brand, the menu toggle or the settings button is not rendered.');
        }

        // One row: side by side, in this order, each overlapping the others
        // vertically.
        expect(brand.x + brand.width).toBeLessThanOrEqual(toggle.x);
        expect(toggle.x + toggle.width).toBeLessThanOrEqual(cog.x);
        for (const box of [brand, toggle]) {
            expect(box.y).toBeLessThan(cog.y + cog.height);
            expect(box.y + box.height).toBeGreaterThan(cog.y);
        }

        await trigger.click();
        const panel = await page.locator('#theme-settings-panel').boundingBox();
        if (panel === null) {
            throw new Error('The panel did not open.');
        }
        expect(panel.x).toBeGreaterThanOrEqual(0);
        expect(panel.x + panel.width).toBeLessThanOrEqual(375);
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375);
    });

    test('reset to the defaults of the site', async ({ page }) => {
        await page.goto('/typography');
        const root = page.locator('html');
        const trigger = page.getByRole('button', { name: 'Display settings' });
        const panel = page.locator('#theme-settings-panel');

        await trigger.click();
        await panel.getByRole('radio', { name: 'Light' }).check();
        await panel.getByRole('radio', { name: 'Ocean' }).check();
        await expect(root).toHaveAttribute('data-palette', 'ocean');

        // The constants of this instance are the same values the script falls
        // back to without a server default, so a reset ignoring the defaults
        // rendered next to the control would pass unnoticed. They are moved
        // first: Reset has to read them off the control.
        await page.locator('.theme-settings').evaluate((element) => {
            element.setAttribute('data-theme-default-appearance', 'dark');
            element.setAttribute('data-theme-default-palette', 'moss');
            element.setAttribute('data-theme-default-content-outline', 'off');
        });

        await panel.getByRole('button', { name: 'Reset' }).click();
        await expect(root).toHaveAttribute('data-theme', 'dark');
        await expect(root).toHaveAttribute('data-palette', 'moss');
        await expect(root).toHaveAttribute('data-theme-content-outline', 'off');
        await expect(panel.getByRole('radio', { name: 'Dark' })).toBeChecked();
        await expect(panel.getByRole('radio', { name: 'Moss' })).toBeChecked();
        await expect(panel.getByRole('switch', { name: 'Element outlines' })).not.toBeChecked();

        // Reset forgets the choice rather than storing the defaults, so the
        // next page load follows the constants of the site again - the real
        // ones, "auto", "neutral" and "on".
        const stored = await page.evaluate(() => ['theme-appearance', 'theme-palette', 'theme-content-outline'].map((key) => window.localStorage.getItem(key)));
        expect(stored).toEqual([null, null, null]);
        await page.reload();
        await expect(root).not.toHaveAttribute('data-theme', /.*/);
        await expect(root).toHaveAttribute('data-palette', 'neutral');
        await expect(root).toHaveAttribute('data-theme-content-outline', 'on');
    });
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
