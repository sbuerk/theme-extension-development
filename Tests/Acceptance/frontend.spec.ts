import { expect, type Page, test } from '@playwright/test';

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
    '/typography/text',
    '/typography/lists',
    '/typography/tables',
    '/typography/quotes-and-code',
    '/typography/article',
    '/elements/core/header',
    '/elements/core/text',
    '/elements/core/textpic',
    '/elements/core/textmedia',
    '/elements/core/image',
    '/elements/core/bullets',
    '/elements/core/table',
    '/elements/core/uploads',
    '/elements/core/div',
    '/elements/core/html',
    '/elements/core/shortcut',
    '/elements/frames',
    '/layouts',
    '/layouts/two-columns',
    '/layouts/two-columns-wide',
    '/layouts/three-columns',
    '/layouts/article',
    '/layouts/cover',
    '/layouts/bands',
    '/examples',
    '/examples/album',
    '/examples/pricing',
    '/examples/journal',
    '/examples/journal/composing-a-page',
    '/examples/product',
    '/examples/campaign',
    '/examples/carousel-landing',
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
                // The page's own header, not any header on it: the styleguide
                // section "chrome" renders four specimen headers inside
                // "main", with the same classes, because that is what a
                // specimen of the real markup is. The real one is the direct
                // child of ".theme-page".
                await expect(page.locator('.theme-page > .theme-site-header .theme-site-header__brand')).toHaveAttribute('href', `${tree.prefix}/`);
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

        // The band layout is the one that deliberately leaves the content
        // container, and the reason the stylesheet widens the body instead of
        // letting a band break out with "width: 100vw" and a negative margin
        // is that "100vw" includes the scrollbar ("layout/_page.scss"). That
        // reasoning names this measurement, so the measurement has to be
        // pointed at a band page: the other two "scrollWidth" assertions in
        // this file are both on "/typography", which renders through
        // "content" and never leaves the container at all.
        //
        // All three band pages are measured, not only the wireframe one.
        // "/layouts/bands" holds nothing but text elements; the composed
        // pages put a hero with a cropped image and a carousel - a track
        // that scrolls sideways by design - into a band, and a component
        // that overflows its band is exactly what this measurement is for.
        for (const path of ['/layouts/bands', '/examples/product', '/examples/carousel-landing']) {
            test(`${tree.prefix}${path} spans the row without spilling sideways`, async ({ page }) => {
                await page.setViewportSize({ width: 1280, height: 800 });
                await page.goto(`${tree.prefix}${path}`);

                // The bands really are out of the container - otherwise the
                // overflow check below would hold for a page that never went wide.
                const bands = page.locator('.theme-page__bands');
                await expect(bands).toHaveCount(1);
                const available = await page.evaluate(() => document.documentElement.clientWidth);
                expect(await bands.evaluate((element) => element.getBoundingClientRect().width)).toBeCloseTo(available, 0);

                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(available);
            });
        }

        test(`${tree.prefix}/ shows the showcase sections in the main navigation and nothing else`, async ({ page }) => {
            await page.goto(`${tree.prefix}/`);
            const navigation = page.locator('nav.theme-nav-main');
            for (const section of ['Elements', 'Typography', 'Layouts', 'Examples', 'Styleguide', 'Forms']) {
                await expect(navigation.getByRole('link', { name: section, exact: true })).toHaveCount(1);
            }
            // The layout fallback fixture and the account pages stay out.
            for (const hidden of ['Empty page', 'Login', 'Members', 'Frontend users']) {
                await expect(navigation.getByRole('link', { name: hidden, exact: true })).toHaveCount(0);
            }
        });

        // The showcase has seven top level entries; a site has fewer or more.
        // From the breakpoint up the header holds them in one row with the
        // title of this tree and never spills sideways. On a wide row the
        // title keeps its line and the menu wraps; on a narrow one the menu
        // keeps its row as long as it can and the title wraps. Entries are
        // added as copies of the last one, with the markup the menu renders,
        // or removed from the end.
        const headerScenarios = [
            { name: 'holds seven top level entries beside a one line title at 1280 pixels', width: 1280, entries: 7, titleOneLine: true, menuOneRow: false },
            { name: 'keeps three top level entries in one row at 768 pixels', width: 768, entries: 3, titleOneLine: false, menuOneRow: true },
            { name: 'holds seven top level entries without spilling sideways at 768 pixels', width: 768, entries: 7, titleOneLine: false, menuOneRow: false },
            { name: 'holds seven top level entries without spilling sideways at 900 pixels', width: 900, entries: 7, titleOneLine: false, menuOneRow: false },
        ];
        for (const scenario of headerScenarios) {
            test(`${tree.prefix}/ ${scenario.name}`, async ({ page }) => {
                await page.setViewportSize({ width: scenario.width, height: 800 });
                await page.goto(`${tree.prefix}/typography`);
                const count = await page.evaluate((entries) => {
                    const list = document.querySelector('nav.theme-nav-main > .theme-nav-main__list');
                    if (list === null) {
                        return 0;
                    }
                    while (list.children.length > entries && list.lastElementChild !== null) {
                        list.lastElementChild.remove();
                    }
                    // Names no section of the showcase carries, so a synthetic
                    // entry can never be mistaken for a real one in a failure
                    // message. "Examples" was in this list until the composed
                    // pages made it a real section.
                    const labels = ['Documentation', 'Downloads', 'Support'];
                    const template = list.lastElementChild;
                    while (template !== null && list.children.length < entries) {
                        const item = template.cloneNode(true) as HTMLElement;
                        item.classList.remove('theme-nav-main__item--active');
                        item.querySelectorAll('.theme-nav-main__list--sub').forEach((sub) => sub.remove());
                        const link = item.querySelector('a');
                        if (link !== null) {
                            link.removeAttribute('aria-current');
                            link.textContent = labels.shift() ?? 'More';
                        }
                        list.append(item);
                    }
                    return list.children.length;
                }, scenario.entries);
                expect(count).toBe(scenario.entries);

                const brandLocator = page.locator('.theme-site-header__brand');
                const brand = await brandLocator.boundingBox();
                const nav = await page.locator('nav.theme-nav-main').boundingBox();
                const cog = await page.getByRole('button', { name: 'Display settings' }).boundingBox();
                if (brand === null || nav === null || cog === null) {
                    throw new Error('The brand, the main navigation or the settings button is not rendered.');
                }

                if (scenario.titleOneLine) {
                    const lineHeight = await brandLocator.evaluate((element) => parseFloat(getComputedStyle(element).lineHeight));
                    expect(brand.height).toBeLessThan(lineHeight * 1.5);
                }

                // Brand, navigation and cog side by side, nothing overlapping and
                // nothing outside the screen.
                expect(brand.x + brand.width).toBeLessThanOrEqual(nav.x);
                expect(nav.x + nav.width).toBeLessThanOrEqual(cog.x);
                expect(cog.x + cog.width).toBeLessThanOrEqual(scenario.width);
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(scenario.width);

                // Every entry inside the navigation, and no two on top of each other.
                const items = await page.locator('nav.theme-nav-main > .theme-nav-main__list > .theme-nav-main__item > .theme-nav-main__link').evaluateAll(
                    (links) => links.map((link) => {
                        const box = link.getBoundingClientRect();
                        return { left: box.left, right: box.right, top: box.top, bottom: box.bottom };
                    }),
                );
                expect(items).toHaveLength(scenario.entries);
                for (const [index, item] of items.entries()) {
                    expect(item.left).toBeGreaterThanOrEqual(nav.x);
                    expect(item.right).toBeLessThanOrEqual(nav.x + nav.width);
                    for (const other of items.slice(index + 1)) {
                        const apart = item.right <= other.left || other.right <= item.left || item.bottom <= other.top || other.bottom <= item.top;
                        expect(apart).toBe(true);
                        // Entries of one row share their top. The last entry of
                        // the list used to sit half a list item margin lower.
                        const sameRow = item.top < other.bottom && other.top < item.bottom;
                        if (sameRow) {
                            expect(Math.abs(item.top - other.top)).toBeLessThan(0.5);
                        }
                    }
                }
                if (scenario.menuOneRow) {
                    const tops = items.map((item) => item.top);
                    expect(Math.max(...tops) - Math.min(...tops)).toBeLessThan(0.5);
                }
            });
        }
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
        // five beside the old button groups.
        const lineHeight = await brandLocator.evaluate((element) => parseFloat(getComputedStyle(element).lineHeight));
        expect(brand.height).toBeLessThan(lineHeight * 1.5);

        // The menu itself may wrap, and above "bp.$lg" that is the contract:
        // on a wide row the title keeps its line and the menu gives way, on a
        // narrow one the other way round ("abstracts/_breakpoints.scss"). This
        // used to assert one row, which held only while the showcase had five
        // sections; it has six since the page layouts were added, and the
        // header scenarios above cover both row counts deliberately, at three
        // and at seven entries. What must still hold here is that nothing
        // overlaps, that the entries of one row share it, and that the menu
        // stays within the two rows the contract allows it.
        const boxes = await page.locator('nav.theme-nav-main > .theme-nav-main__list > .theme-nav-main__item').evaluateAll(
            (items) => items.map((item) => {
                const box = item.getBoundingClientRect();
                return { left: box.left, right: box.right, top: box.top, bottom: box.bottom };
            }),
        );
        expect(boxes.length).toBeGreaterThan(1);
        for (const [index, box] of boxes.entries()) {
            for (const other of boxes.slice(index + 1)) {
                const apart = box.right <= other.left || other.right <= box.left || box.bottom <= other.top || other.bottom <= box.top;
                expect(apart).toBe(true);
                if (box.top < other.bottom && other.top < box.bottom) {
                    expect(Math.abs(box.top - other.top)).toBeLessThan(0.5);
                }
            }
        }

        // Without a bound on the number of rows the two checks above are
        // satisfied by seven entries on seven rows: no pair then shares a row,
        // so the shared top never fires, and "apart" is all but tautological
        // for flex items. So the rows are counted. The seven top level entries
        // of the showcase occupy two at 1280 pixels - measured, not
        // assumed, and the number "docs/development/component-library.md"
        // documents. The bound is an upper one because a menu that gets back
        // into one row is not a regression; three rows beside a one line title
        // is.
        const rows = new Set(boxes.map((box) => Math.round(box.top)));
        expect(rows.size).toBeLessThanOrEqual(2);
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

/**
 * The sub navigation tree, on a page that has one.
 *
 * `/elements/core` is a second-level page of the "Elements" section, so its
 * sidebar lists that section: the pages below it, two of which have children of
 * their own and are therefore branches. "Core elements" is the branch the
 * reader is in, "Theme elements" is the other one.
 *
 * What a browser shows and the markup cannot: that a branch really opens and
 * closes, that it does so with JavaScript disabled - the tree is built on
 * `details`, and nothing about it waits for a script - and that the branch page
 * is still reachable beside its toggle.
 */
test.describe('the sub navigation tree', () => {
    const branch = (page: Page, title: string) => page
        .locator('.theme-nav-sub__item--branch')
        .filter({ has: page.getByRole('link', { name: title, exact: true }) });

    test('opens the branch the reader is in and leaves the other folded', async ({ page }) => {
        await page.goto('/elements/core');

        await expect(branch(page, 'Core elements').locator('details')).toHaveAttribute('open', '');
        await expect(branch(page, 'Theme elements').locator('details')).not.toHaveAttribute('open', '');
        await expect(branch(page, 'Theme elements').getByRole('link', { name: 'Text and icon', exact: true })).toBeHidden();
    });

    test('folds and unfolds a branch with JavaScript disabled', async ({ browser }) => {
        const context = await browser.newContext({ javaScriptEnabled: false });
        const page = await context.newPage();
        await page.goto('/elements/core');

        const themeElements = branch(page, 'Theme elements');
        const toggle = themeElements.locator('summary.theme-nav-sub__toggle');
        const child = themeElements.getByRole('link', { name: 'Text and icon', exact: true });

        await expect(child).toBeHidden();
        await toggle.click();
        await expect(child).toBeVisible();
        await toggle.click();
        await expect(child).toBeHidden();

        await context.close();
    });

    // The toggle is a "summary", so Enter and Space are the element's own
    // behaviour rather than anything the theme wires up - which is exactly
    // why it is worth one assertion: the design was chosen for that, and a
    // future toggle built out of a div would pass every other test here.
    test('opens a branch from the keyboard', async ({ page }) => {
        await page.goto('/elements/core');

        const themeElements = branch(page, 'Theme elements');
        const child = themeElements.getByRole('link', { name: 'Text and icon', exact: true });

        await expect(child).toBeHidden();
        await themeElements.locator('summary.theme-nav-sub__toggle').focus();
        await page.keyboard.press('Enter');
        await expect(child).toBeVisible();
        await page.keyboard.press('Space');
        await expect(child).toBeHidden();
    });

    test('keeps the branch page reachable beside its toggle', async ({ page }) => {
        await page.goto('/elements/core');

        await branch(page, 'Theme elements').getByRole('link', { name: 'Theme elements', exact: true }).click();
        await expect(page).toHaveURL(/\/elements\/theme$/);
    });
});

/**
 * The language dropdown at the end of the header row.
 *
 * Nothing opened it before: the markup was asserted by
 * `Tests/Functional/LanguageMenuRenderingTest`, and the browser checks of the
 * instances confirmed that the trigger *renders*. A panel that opens over the
 * control that opened it, over the settings cog and over the end of the main
 * navigation passed every one of those, on both cores and at both widths.
 *
 * So what is measured here is where the panel lands, against the boxes it must
 * not land on. The header is the page's own, not a specimen of one - the
 * styleguide renders four of those inside `main`.
 */
test.describe('the header language dropdown', () => {
    const header = (page: Page) => page.locator('.theme-page > .theme-site-header');
    const trigger = (page: Page) => header(page).locator('.theme-dropdown__trigger');
    const panel = (page: Page) => header(page).locator('#theme-language-panel');

    const boxOf = async (locator: ReturnType<Page['locator']>, what: string) => {
        const box = await locator.boundingBox();
        if (box === null) {
            throw new Error(`${what} is not rendered.`);
        }
        return box;
    };

    // The inline end of the header's content container - its right edge in a
    // left-to-right page and its left one in a right-to-left page, inside the
    // gutter either way. That is the edge the panel is pinned to, and reading
    // it off the element rather than writing the number down here is what
    // makes the same assertion hold at both widths and in both directions.
    const inlineEndOfTheRow = (page: Page) => header(page).locator('.theme-site-header__inner').evaluate((element) => {
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        const padding = parseFloat(style.paddingInlineEnd);

        return style.direction === 'rtl' ? box.left + padding : box.right - padding;
    });

    // 1280 has the menu beside the title, 375 has it behind its toggle, and
    // the header is a different height in each - which is the whole reason a
    // panel in the top layer cannot be pinned under it by a constant.
    for (const width of [1280, 375]) {
        test(`opens under the header row and covers no control of it at ${width} pixels`, async ({ page }) => {
            await page.setViewportSize({ width, height: 800 });
            await page.goto('/typography');

            await expect(panel(page)).toBeHidden();
            await trigger(page).click();
            await expect(panel(page)).toBeVisible();

            const panelBox = await boxOf(panel(page), 'The language panel');
            const triggerBox = await boxOf(trigger(page), 'The language trigger');
            const headerBox = await boxOf(header(page), 'The site header');

            // Under the row, not over it. This is the measurement that was
            // missing: the panel used to start at the top edge of the
            // viewport. Under the *trigger* is not enough - at 1280 the menu
            // takes a second row inside the header that reaches below it.
            expect(panelBox.y).toBeGreaterThanOrEqual(triggerBox.y + triggerBox.height);
            expect(panelBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height);

            // And over nothing else in the header either - the brand, every
            // entry of the main navigation, the menu toggle and the settings
            // cog. Each is looked up as it exists at this width: the toggle
            // only exists below the breakpoint, the entries only above it.
            const covered = await header(page).evaluate((element, panelSelector) => {
                const panelElement = element.querySelector(panelSelector);
                if (panelElement === null) {
                    return ['the panel disappeared'];
                }
                const box = panelElement.getBoundingClientRect();
                const selectors = [
                    '.theme-site-header__brand',
                    '.theme-nav-main__toggle',
                    '.theme-nav-main__link',
                    '.theme-settings__trigger',
                    '.theme-dropdown__trigger',
                ];
                const hits: string[] = [];
                for (const selector of selectors) {
                    element.querySelectorAll(selector).forEach((control) => {
                        const other = control.getBoundingClientRect();
                        if (other.width === 0 && other.height === 0) {
                            return;
                        }
                        const apart = box.right <= other.left || other.right <= box.left
                            || box.bottom <= other.top || other.bottom <= box.top;
                        if (!apart) {
                            hits.push(`${selector} (${other.left}..${other.right} x ${other.top}..${other.bottom})`);
                        }
                    });
                }
                return hits;
            }, '#theme-language-panel');
            expect(covered).toEqual([]);

            // At the end of the row: the panel's end edge is the end of the
            // content container of the header, not the edge of the viewport -
            // 60 pixels in from it at 1280, the page gutter at 375. At 1280
            // that is also where the last control of the row ends; at 375 the
            // row has no slack left and the cog reaches a few pixels past its
            // own container, which is a squeeze of the header row rather than
            // a placement of this panel.
            expect(panelBox.x + panelBox.width).toBeCloseTo(await inlineEndOfTheRow(page), 0);
            expect(panelBox.x).toBeGreaterThanOrEqual(0);
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        });
    }

    // The trigger moves to the left of the row in a right-to-left document and
    // the panel has to go with it. It used to stay on the right, pinned there
    // by the second value of an "inset" shorthand.
    test('follows its trigger in a right-to-left document', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/typography');
        await page.locator('html').evaluate((element) => element.setAttribute('dir', 'rtl'));

        await trigger(page).click();
        await expect(panel(page)).toBeVisible();

        const panelBox = await boxOf(panel(page), 'The language panel');
        const triggerBox = await boxOf(trigger(page), 'The language trigger');
        const headerBox = await boxOf(header(page), 'The site header');

        // The row is mirrored, so the trigger is now near the left edge.
        expect(triggerBox.x).toBeLessThan(1280 / 2);
        // And the end of the row is its left edge, which is where the panel
        // ends too. It used to stay on the right, pinned there by the second
        // value of an "inset" shorthand.
        expect(panelBox.x).toBeCloseTo(await inlineEndOfTheRow(page), 0);
        expect(panelBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height);
    });

    test('closes on Escape, on a click outside and when focus leaves it', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/typography');

        await trigger(page).click();
        await expect(panel(page)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(panel(page)).toBeHidden();
        await expect(trigger(page)).toBeFocused();

        await trigger(page).click();
        await expect(panel(page)).toBeVisible();
        await page.locator('main').click({ position: { x: 5, y: 5 } });
        await expect(panel(page)).toBeHidden();

        await trigger(page).click();
        await expect(panel(page)).toBeVisible();
        // Past the last link of the panel: the panel must not stay open over
        // whatever now has focus (WCAG 2.2, 2.4.11).
        await panel(page).getByRole('link').last().focus();
        await page.keyboard.press('Tab');
        await expect(panel(page)).toBeHidden();
    });

    // The whole argument for taking this panel out of the top layer is that
    // the element opens it without a script. That claim needs the test the
    // sub navigation tree already has for its own "details" branches, and for
    // the same reason: every other test in this block runs with the script,
    // and a dropdown rebuilt out of a div and a click handler would pass all
    // of them.
    test('opens and closes with JavaScript disabled, under the header row', async ({ browser }) => {
        const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 740 } });
        const page = await context.newPage();
        await page.goto('/typography');

        await expect(panel(page)).toBeHidden();
        await trigger(page).click();
        await expect(panel(page)).toBeVisible();

        // Without "data-js" the main navigation stays in the flow, so the
        // header is as tall as the whole menu and the panel drops under all
        // of it - far from its trigger, and over nothing. That distance is
        // the trade this anchor makes and it is written out in
        // "components/_dropdown.scss"; what has to hold is the contract.
        const panelBox = await boxOf(panel(page), 'The language panel');
        const headerBox = await boxOf(header(page), 'The site header');
        const triggerBox = await boxOf(trigger(page), 'The language trigger');
        expect(panelBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height);
        expect(panelBox.x).toBeGreaterThanOrEqual(0);
        expect(panelBox.x + panelBox.width).toBeCloseTo(await inlineEndOfTheRow(page), 0);
        expect(triggerBox.y).toBeLessThan(panelBox.y);

        await trigger(page).click();
        await expect(panel(page)).toBeHidden();

        await context.close();
    });

    // The trigger is a "summary", so Enter and Space are the element's own
    // behaviour rather than anything this theme wires up - which is exactly
    // why it is worth one assertion, the same one the sub navigation tree
    // carries: the design was chosen for that, and a trigger built out of a
    // div would pass every other test here.
    test('opens and closes from the keyboard', async ({ page }) => {
        await page.goto('/typography');

        await expect(panel(page)).toBeHidden();
        await trigger(page).focus();
        await page.keyboard.press('Enter');
        await expect(panel(page)).toBeVisible();
        await page.keyboard.press('Space');
        await expect(panel(page)).toBeHidden();
        // Focus stays on the trigger throughout: a summary is the focused
        // element, not a wrapper that hands focus somewhere else.
        await expect(trigger(page)).toBeFocused();
    });
});

/**
 * The display settings panel, measured against the header row it drops out of.
 *
 * `the display settings` above exercises the control thoroughly - what a choice
 * applies, what a reset forgets, what survives a reload, how the panel is
 * dismissed - and never asks where the panel lands. So the cog kept the defect
 * the language dropdown beside it was fixed for: anchored on its own component,
 * the panel starts just under the 44 pixel trigger, which is *inside* a header
 * row that is as tall as its tallest child and may hold two rows of menu
 * entries. Measured at 1280 pixels before this change, the panel ran
 * x 916..1220 by y 104.5..587 in a 146 pixel header, over two links of the
 * second navigation row.
 *
 * The assertions mirror `the header language dropdown` above deliberately: the
 * two controls share the slot, so what holds for one holds for the other, and
 * `drop to the same edge as the language dropdown` says that in one test rather
 * than leaving it to two sets of numbers that happen to agree.
 */
test.describe('the display settings panel', () => {
    const header = (page: Page) => page.locator('.theme-page > .theme-site-header');
    const trigger = (page: Page) => header(page).locator('.theme-settings__trigger');
    const panel = (page: Page) => header(page).locator('#theme-settings-panel');

    const boxOf = async (locator: ReturnType<Page['locator']>, what: string) => {
        const box = await locator.boundingBox();
        if (box === null) {
            throw new Error(`${what} is not rendered.`);
        }
        return box;
    };

    // The inline end of the header's content container, read off the element
    // rather than written down here, so the same assertion holds at both widths
    // and in both reading directions. The same helper the dropdown block
    // carries - the two panels are pinned to the same edge, which is the point.
    const inlineEndOfTheRow = (page: Page) => header(page).locator('.theme-site-header__inner').evaluate((element) => {
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        const padding = parseFloat(style.paddingInlineEnd);

        return style.direction === 'rtl' ? box.left + padding : box.right - padding;
    });

    // 1280 has the menu beside the title and wrapped onto a second row inside
    // the header; 375 has it behind its toggle. The header is a different
    // height in each, which is why the block offset has to be derived from the
    // header rather than chosen.
    for (const width of [1280, 375]) {
        test(`opens under the header row and covers no control of it at ${width} pixels`, async ({ page }) => {
            await page.setViewportSize({ width, height: 800 });
            await page.goto('/typography');

            await expect(panel(page)).toBeHidden();
            await trigger(page).click();
            await expect(panel(page)).toBeVisible();

            const panelBox = await boxOf(panel(page), 'The settings panel');
            const triggerBox = await boxOf(trigger(page), 'The settings cog');
            const headerBox = await boxOf(header(page), 'The site header');

            // Under the row, not into it. Under the *trigger* is what shipped
            // and is not enough: at 1280 the menu takes a second row inside the
            // header that reaches below the cog.
            expect(panelBox.y).toBeGreaterThanOrEqual(triggerBox.y + triggerBox.height);
            expect(panelBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height);

            // And over nothing else in the header either - the brand, every
            // entry of the main navigation, the menu toggle, the language
            // trigger and the cog itself. Each is looked up as it exists at
            // this width: the toggle only below the breakpoint, the entries
            // only above it.
            const covered = await header(page).evaluate((element, panelSelector) => {
                const panelElement = element.querySelector(panelSelector);
                if (panelElement === null) {
                    return ['the panel disappeared'];
                }
                const box = panelElement.getBoundingClientRect();
                const selectors = [
                    '.theme-site-header__brand',
                    '.theme-nav-main__toggle',
                    '.theme-nav-main__link',
                    '.theme-settings__trigger',
                    '.theme-dropdown__trigger',
                ];
                const hits: string[] = [];
                for (const selector of selectors) {
                    element.querySelectorAll(selector).forEach((control) => {
                        const other = control.getBoundingClientRect();
                        if (other.width === 0 && other.height === 0) {
                            return;
                        }
                        const apart = box.right <= other.left || other.right <= box.left
                            || box.bottom <= other.top || other.bottom <= box.top;
                        if (!apart) {
                            hits.push(`${selector} (${other.left}..${other.right} x ${other.top}..${other.bottom})`);
                        }
                    });
                }
                return hits;
            }, '#theme-settings-panel');
            expect(covered).toEqual([]);

            // At the end of the row: the panel's end edge is the end of the
            // header's content container, not the edge of the viewport and not
            // the edge of the cog. At 1280 the last two coincide; at 375 the
            // row has no slack left and the cog reaches a few pixels past its
            // own container, which is the row being squeezed rather than this
            // panel being misplaced - see "layout/_site-header.scss".
            expect(panelBox.x + panelBox.width).toBeCloseTo(await inlineEndOfTheRow(page), 0);
            expect(panelBox.x).toBeGreaterThanOrEqual(0);
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        });
    }

    // The cog moves to the left of the row in a right-to-left document. The
    // panel followed it before this change - "inset-inline-end: 0" on the
    // component is direction aware - and has to keep doing so now that it is
    // pinned to the container edge instead.
    test('follows its trigger in a right-to-left document', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/typography');
        await page.locator('html').evaluate((element) => element.setAttribute('dir', 'rtl'));

        await trigger(page).click();
        await expect(panel(page)).toBeVisible();

        const panelBox = await boxOf(panel(page), 'The settings panel');
        const triggerBox = await boxOf(trigger(page), 'The settings cog');
        const headerBox = await boxOf(header(page), 'The site header');

        expect(triggerBox.x).toBeLessThan(1280 / 2);
        expect(panelBox.x).toBeCloseTo(await inlineEndOfTheRow(page), 0);
        expect(panelBox.y).toBeGreaterThanOrEqual(headerBox.y + headerBox.height);
    });

    // Why this defect is a commit rather than a note: the two controls sit in
    // the same slot and dropped to visibly different heights, 104.5 against
    // 155 at 1280. Neither number belongs in a test - what has to hold is that
    // they agree, whatever the row does to its own height.
    test('drops to the same edge as the language dropdown', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/typography');

        const languagePanel = header(page).locator('#theme-language-panel');
        await header(page).locator('.theme-dropdown__trigger').click();
        const dropdownBox = await boxOf(languagePanel, 'The language panel');

        // Opening the cog closes the dropdown by itself: a click on the cog is
        // a click outside the dropdown, which is one of its three dismissal
        // rules. The two panels are never open at once, which is what lets
        // them share an edge without sharing a place.
        await trigger(page).click();
        await expect(languagePanel).toBeHidden();
        const settingsBox = await boxOf(panel(page), 'The settings panel');

        expect(settingsBox.y).toBeCloseTo(dropdownBox.y, 0);
        expect(settingsBox.x + settingsBox.width).toBeCloseTo(dropdownBox.x + dropdownBox.width, 0);
    });

    // The trigger is a plain button, so Enter and Space are the element's own
    // behaviour - worth the single assertion the dropdown's summary and the sub
    // navigation's branches carry, for their reason: every other test in this
    // block clicks, and a trigger rebuilt out of a div with a click handler
    // would pass all of them.
    test('opens and closes from the keyboard', async ({ page }) => {
        await page.goto('/typography');

        await expect(panel(page)).toBeHidden();
        await trigger(page).focus();
        await page.keyboard.press('Enter');
        await expect(panel(page)).toBeVisible();
        await page.keyboard.press('Space');
        await expect(panel(page)).toBeHidden();
        await expect(trigger(page)).toBeFocused();
    });

    // The counterpart of the dropdown's "opens and closes with JavaScript
    // disabled", and deliberately the opposite assertion. This control has no
    // no-script state: "components/_settings.scss" hides the whole of it until
    // "data-js" is on the root, because the cog discloses nothing and no choice
    // applies without the module script. So the header anchor costs this panel
    // nothing - the degraded case the dropdown had to trade against, where an
    // unscripted menu stays in the flow and the header grows to the height of
    // the whole site tree, cannot arise here: there is no cog in it to click.
    test('is not rendered at all with JavaScript disabled', async ({ browser }) => {
        const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 740 } });
        const page = await context.newPage();
        await page.goto('/typography');

        await expect(header(page).locator('.theme-settings')).toBeHidden();
        await expect(trigger(page)).toBeHidden();
        await expect(panel(page)).toBeHidden();

        await context.close();
    });
});
