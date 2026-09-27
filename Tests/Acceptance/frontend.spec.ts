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

/**
 * The width from which each arrangement of the site header holds its main
 * navigation in one row, in pixels at the default root size: the
 * "bp.$header-*" breakpoints of "abstracts/_breakpoints.scss". One pixel below,
 * the menu is behind its toggle. Written down here rather than read off the
 * page, because what is under test is that the stylesheet switches where the
 * budget says it does - "layout/_site-header.scss" has the measurements.
 */
const headerBreakpoints = {
    'simple': 74 * 16,
    'centred': 55 * 16,
    'actions': 55 * 16,
    'two-tier': 65 * 16,
};
const headerArrangements = ['simple', 'centred', 'actions', 'two-tier'] as const;

/**
 * The page's own header, rearranged into one of the three other variants.
 *
 * The instance renders one variant per site, and the specimens of the
 * styleguide section `chrome` - the only other headers a browser sees - sit
 * in the content column with three entries and no second level, so they
 * measure neither the width a real header has nor the menu it holds. So the
 * specimen of the variant lends its rows, and the page's own header lends
 * what goes into them: its title, its main navigation with the seven top
 * level entries of the showcase and their second levels, and its controls
 * slot. The elements are moved, not copied, so the menu toggle, the language
 * dropdown and the display settings stay bound by the script of the page.
 *
 * The rows are the specimen's, not written out here: the specimens are held
 * to the classes of the variant partials by
 * `ComponentLibraryTest::theChromeSpecimensCarryEveryClassTheirPartialsWrite`,
 * and a skeleton typed into this file would be held to nothing. `actions`
 * keeps the call to action of its specimen, since the instance configures
 * none. `simple` is the page's header as it is.
 */
const arrangeHeader = async (page: Page, variant: string) => {
    await page.evaluate((variant) => {
        if (variant === 'simple') {
            return;
        }
        const pageHeader = document.querySelector('.theme-page > .theme-site-header');
        const specimen = [...document.querySelectorAll('#chrome .theme-site-header')]
            .find((header) => header.classList.contains(`theme-site-header--${variant}`));
        if (pageHeader === null || specimen === undefined) {
            throw new Error(`There is no page header, or no "${variant}" specimen to take the rows from - is this "/styleguide"?`);
        }
        const header = specimen.cloneNode(true) as HTMLElement;
        for (const selector of ['.theme-site-header__brand', 'nav.theme-nav-main', '.theme-site-header__actions']) {
            const slot = header.querySelector(selector);
            const content = pageHeader.querySelector(selector);
            if (slot === null || content === null) {
                throw new Error(`"${selector}" is missing from the "${variant}" specimen or from the page header.`);
            }
            slot.replaceWith(content);
        }
        pageHeader.replaceWith(header);
    }, variant);
};

/**
 * The page's header holds its main navigation as one row of the seven top
 * level entries of the showcase: the toggle is not offered, nothing of any
 * row of the header reaches out of the row's content box - a menu that does
 * not wrap and does not fit spills instead, which is what this looks for -
 * and the title is not squeezed below its longest word.
 *
 * `width` also holds the page to no sideways scroll; `null` leaves that out,
 * on a page whose own content is not what is under test.
 */
const expectTheMenuInOneRow = async (page: Page, width: number | null) => {
    const header = page.locator('.theme-page > .theme-site-header');
    await expect(header.locator('.theme-nav-main__toggle')).toBeHidden();

    const measured = await header.evaluate((element) => {
        const spills: string[] = [];
        element.querySelectorAll('.theme-site-header__inner').forEach((row) => {
            const style = getComputedStyle(row);
            const box = row.getBoundingClientRect();
            const start = box.left + parseFloat(style.paddingLeft);
            const end = box.right - parseFloat(style.paddingRight);
            [...row.children].forEach((child) => {
                const other = child.getBoundingClientRect();
                if (other.width > 0 && (other.left < start - 0.5 || other.right > end + 0.5)) {
                    spills.push(`${child.className} (${other.left}..${other.right}) outside ${start}..${end}`);
                }
            });
        });
        const links = [...element.querySelectorAll('nav.theme-nav-main > .theme-nav-main__list > .theme-nav-main__item > .theme-nav-main__link')]
            .map((link) => link.getBoundingClientRect());
        let overlapping = 0;
        links.forEach((link, index) => links.slice(index + 1).forEach((other) => {
            if (!(link.right <= other.left || other.right <= link.left || link.bottom <= other.top || other.bottom <= link.top)) {
                overlapping++;
            }
        }));
        const brand = element.querySelector('.theme-site-header__brand');
        return {
            spills,
            entries: links.length,
            rows: new Set(links.map((link) => Math.round(link.top))).size,
            overlapping,
            squeezed: brand === null ? 1 : brand.scrollWidth - brand.clientWidth,
        };
    });

    expect.soft(measured.spills, 'what reaches out of a row of the header').toEqual([]);
    // The budget the breakpoints are sized for. A showcase with more entries
    // needs the breakpoints measured again, and this says so first.
    expect.soft(measured.entries, 'top level entries').toBe(7);
    expect.soft(measured.rows, 'rows of top level entries').toBe(1);
    expect.soft(measured.overlapping, 'pairs of top level entries on top of each other').toBe(0);
    expect.soft(measured.squeezed, 'pixels of the title outside its own box').toBeLessThanOrEqual(0);
    if (width !== null) {
        expect.soft(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
};

/**
 * The page's header has its main navigation behind the toggle, and the
 * toggle opens it as a band under the whole header.
 */
const expectTheMenuBehindItsToggle = async (page: Page, width: number | null) => {
    const header = page.locator('.theme-page > .theme-site-header');
    const toggle = header.locator('.theme-nav-main__toggle');
    const list = header.locator('nav.theme-nav-main > .theme-nav-main__list');

    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(list).toBeHidden();
    if (width !== null) {
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(list).toBeVisible();
    const band = await list.boundingBox();
    const box = await header.boundingBox();
    if (band === null || box === null) {
        throw new Error('The header or its expanded menu is not rendered.');
    }
    // Under the header, across its whole width - not squeezed into the slot
    // of the toggle. The band starts at the header's padding edge, above
    // its bottom hairline.
    expect(band.y).toBeGreaterThanOrEqual(box.y + box.height - 1.5);
    expect(band.width).toBeCloseTo(box.width, 0);
};

/**
 * Every second level of the header's main navigation, opened by the pointer,
 * against every top level entry and every control of the header: a list of
 * what each one covers, empty when it covers nothing.
 */
const secondLevelsCoveringTheHeader = async (page: Page) => {
    const header = page.locator('.theme-page > .theme-site-header');
    const items = header.locator('nav.theme-nav-main > .theme-nav-main__list > .theme-nav-main__item:has(> .theme-nav-main__list--sub)');
    const count = await items.count();
    expect(count, 'entries with a second level').toBeGreaterThan(0);

    const covered: string[] = [];
    for (let index = 0; index < count; index++) {
        const item = items.nth(index);
        await item.hover();
        await expect(item.locator(':scope > .theme-nav-main__list--sub')).toBeVisible();
        covered.push(...await item.evaluate((element) => {
            const header = element.closest('.theme-site-header');
            const secondLevel = element.querySelector(':scope > .theme-nav-main__list--sub');
            const link = element.querySelector(':scope > .theme-nav-main__link');
            if (header === null || secondLevel === null || link === null) {
                return ['the entry lost its header, its link or its second level'];
            }
            const box = secondLevel.getBoundingClientRect();
            const hits: string[] = [];
            header.querySelectorAll([
                'nav.theme-nav-main > .theme-nav-main__list > .theme-nav-main__item > .theme-nav-main__link',
                '.theme-site-header__brand',
                '.theme-site-header__action',
                '.theme-nav-main__toggle',
                '.theme-dropdown__trigger',
                '.theme-settings__trigger',
            ].join(',')).forEach((control) => {
                if (control === link) {
                    return;
                }
                const other = control.getBoundingClientRect();
                if (other.width === 0 && other.height === 0) {
                    return;
                }
                if (!(box.right <= other.left || other.right <= box.left || box.bottom <= other.top || other.bottom <= box.top)) {
                    hits.push(`"${link.textContent?.trim()}" covers "${control.textContent?.trim() || control.getAttribute('aria-label') || control.className}"`);
                }
            });
            return hits;
        }));
    }
    return covered;
};

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

        // The header of both trees is the default arrangement, "simple": the
        // title, the seven top level entries of the showcase and both
        // controls in one row. From its breakpoint up the menu holds its
        // entries in one row and the title gives way beside it; one pixel
        // below, the menu is behind its toggle. 1024 used to be the width at
        // which the menu started to wrap instead, and is well inside the
        // collapsed range now.
        const headerScenarios = [
            { width: 1280, expanded: true },
            { width: headerBreakpoints.simple, expanded: true },
            { width: headerBreakpoints.simple - 1, expanded: false },
            { width: 1024, expanded: false },
        ];
        for (const scenario of headerScenarios) {
            const state = scenario.expanded ? 'holds the seven top level entries in one row' : 'puts the menu behind its toggle';
            test(`${tree.prefix}/ ${state} at ${scenario.width} pixels`, async ({ page }) => {
                await page.setViewportSize({ width: scenario.width, height: 800 });
                await page.goto(`${tree.prefix}/typography`);

                if (scenario.expanded) {
                    await expectTheMenuInOneRow(page, scenario.width);
                } else {
                    await expectTheMenuBehindItsToggle(page, scenario.width);
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

        // The title is what gives way: with the seven entries of the
        // showcase it takes several lines at this width, and that is the
        // contract rather than a squeeze. What it may not do is get narrower
        // than its longest word - a word that runs out of its box runs under
        // the menu beside it. It wrapped onto five lines once beside the old
        // button groups, and it kept one line while the menu wrapped instead.
        expect(await brandLocator.evaluate((element) => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(0);

        // And the menu does not give way at all: one row. This allowed two
        // rows while the menu wrapped, and the second level of an entry of
        // the first row opened over the entries of the second.
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
            }
        }
        expect(new Set(boxes.map((box) => Math.round(box.top))).size).toBe(1);
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

/**
 * A `theme.js` that does not do its job leaves the menu usable.
 *
 * The inline head script sets `data-js` before first paint, so a page that
 * works paints its menu collapsed from the first frame. The toggles are bound
 * by `theme.js`, though, and a module that is not found, or that throws before
 * it gets to them, used to leave the menu hidden behind a toggle that opens
 * nothing - below the breakpoint of the header's arrangement, which for the
 * default one is every width under 1184 pixels. The head script now takes
 * `data-js` back at `DOMContentLoaded` unless the module set `data-js-bound`
 * once the controls gated on `data-js` were bound, and the page falls back to
 * its layout without a script. A throw in a binder after that - a carousel, a
 * lightbox - is isolated and leaves the header as it is, and a module that
 * runs late sets `data-js` again with its confirmation.
 * See "The marker is a promise" in `Configuration/TypoScript/Appearance.typoscript`.
 */
test.describe('the main navigation when theme.js does not do its job', () => {
    const header = (page: Page) => page.locator('.theme-page > .theme-site-header');
    const failures = {
        'is not found': async (page: Page) => page.route(/\/JavaScript\/theme\.js/, (route) => route.fulfill({ status: 404, body: '' })),
        'throws before the toggles are bound': async (page: Page) => page.route(/\/JavaScript\/theme\.js/, async (route) => {
            const response = await route.fetch();
            const body = (await response.text()).replace('\nbindMainMenuToggle();\n', "\nthrow new Error('theme.js broke before the toggles');\n");
            await route.fulfill({ response, body });
        }),
    };

    for (const [failure, arrange] of Object.entries(failures)) {
        for (const width of [1000, 375]) {
            test(`stays open when the module ${failure}, at ${width} pixels`, async ({ page }) => {
                await page.setViewportSize({ width, height: 800 });
                await arrange(page);
                await page.goto('/typography');

                await expect(page.locator('html')).not.toHaveAttribute('data-js-bound', /.*/);
                await expect(page.locator('html')).not.toHaveAttribute('data-js', /.*/);
                // The menu is shown and the dead toggle is not, and neither is
                // the settings cog, which would open nothing either.
                await expect(header(page).locator('.theme-nav-main__toggle')).toBeHidden();
                await expect(header(page).locator('nav.theme-nav-main > .theme-nav-main__list')).toBeVisible();
                await expect(header(page).getByRole('link', { name: 'Typography', exact: true })).toBeVisible();
                await expect(header(page).locator('.theme-settings')).toBeHidden();
                expect(await header(page).evaluate((element) => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(0);
            });
        }
    }

    // A binder after the confirmation that throws - here the carousels - is
    // reported and isolated: the header keeps its collapsed menu and a
    // toggle that works, and the binders after it still run. Confirming as
    // the last statement of the module took the whole page back to its
    // no-script layout for a broken carousel.
    test('keeps the header collapsed and working when a later binder throws', async ({ page }) => {
        await page.setViewportSize({ width: 1000, height: 800 });
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route(/\/JavaScript\/theme\.js/, async (route) => {
            const response = await route.fetch();
            const body = (await response.text()).replace('\nfunction bindCarousels() {\n', "\nfunction bindCarousels() {\n    throw new Error('a carousel broke');\n");
            await route.fulfill({ response, body });
        });
        await page.goto('/typography');

        await expect(page.locator('html')).toHaveAttribute('data-js-bound', '');
        await expect(page.locator('html')).toHaveAttribute('data-js', '');
        await expect(header(page).locator('nav.theme-nav-main > .theme-nav-main__list')).toBeHidden();
        await header(page).locator('.theme-nav-main__toggle').click();
        await expect(header(page).locator('nav.theme-nav-main > .theme-nav-main__list')).toBeVisible();
        await expect(header(page).locator('.theme-settings')).toBeVisible();
        // Reported, not swallowed - and it was the one that was meant to throw.
        expect(errors).toEqual(['a carousel broke']);
    });

    // A module that runs after "DOMContentLoaded" - an optimiser loads it
    // "async", or inserts it from a script - finds "data-js" taken back
    // already. It sets it again with its confirmation, so the page gets its
    // collapsed menu and its settings back, with one layout shift, instead
    // of keeping the no-script layout it fell back to. The module of the page
    // is held back, and the same file inserted once the page has loaded,
    // under an address of its own - a module that failed once stays failed
    // for its address - which is what "async" and a slow network do at their
    // worst, without depending on either. (Rewriting the document instead is
    // no option: Chromium treats a fulfilled document as public and refuses
    // it the module of the local instance.)
    test('recovers when the module runs after the document is parsed', async ({ page }) => {
        await page.setViewportSize({ width: 1000, height: 800 });
        await page.route(/\/JavaScript\/theme\.js\?\d+$/, (route) => route.abort());
        await page.goto('/typography');

        // No module yet: the head script took the marker back.
        await expect(page.locator('html')).not.toHaveAttribute('data-js', /.*/);
        await expect(header(page).locator('nav.theme-nav-main > .theme-nav-main__list')).toBeVisible();

        const moduleUrl = await page.locator('script[type="module"][src*="/JavaScript/theme.js"]').getAttribute('src');
        expect(moduleUrl, 'the module tag of the page').not.toBeNull();
        await page.addScriptTag({ url: `${moduleUrl}&late`, type: 'module' });
        await expect(page.locator('html')).toHaveAttribute('data-js-bound', '');
        await expect(page.locator('html')).toHaveAttribute('data-js', '');
        await expect(header(page).locator('.theme-nav-main__toggle')).toBeVisible();
        await header(page).locator('.theme-nav-main__toggle').click();
        await expect(header(page).locator('nav.theme-nav-main > .theme-nav-main__list')).toBeVisible();
        await expect(header(page).locator('.theme-settings')).toBeVisible();
    });

    // And a page that works does not pay for it: the marker is there from
    // the first frame the header is rendered in, and the menu is never shown
    // before it collapses - not even with the module 600 ms late, which is
    // where gating the collapse on a marker the module sets painted 1733
    // pixels of expanded menu for 36 frames. 1000 pixels: "simple" is
    // collapsed there.
    test('keeps a working page collapsed from its first frame, with the module late', async ({ page }) => {
        await page.setViewportSize({ width: 1000, height: 800 });
        await page.addInitScript(() => {
            const frames: { dataJs: boolean, listShown: boolean, height: number }[] = [];
            (window as unknown as { themeFrames: typeof frames }).themeFrames = frames;
            const sample = () => {
                const list = document.querySelector('.theme-page > .theme-site-header nav.theme-nav-main > .theme-nav-main__list');
                const siteHeader = document.querySelector('.theme-page > .theme-site-header');
                if (list !== null && siteHeader !== null) {
                    frames.push({
                        dataJs: document.documentElement.hasAttribute('data-js'),
                        listShown: getComputedStyle(list).display !== 'none',
                        height: Math.round(siteHeader.getBoundingClientRect().height),
                    });
                }
                if (!document.documentElement.hasAttribute('data-js-bound') || frames.length < 5) {
                    requestAnimationFrame(sample);
                }
            };
            requestAnimationFrame(sample);
        });
        await page.route(/\/JavaScript\/theme\.js/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 600));
            await route.continue();
        });
        await page.goto('/typography');
        await expect(page.locator('html')).toHaveAttribute('data-js-bound', '');
        await expect(page.locator('html')).toHaveAttribute('data-js', '');

        const frames = await page.evaluate(() => (window as unknown as { themeFrames: { dataJs: boolean, listShown: boolean, height: number }[] }).themeFrames);
        expect(frames.length).toBeGreaterThan(0);
        expect(frames.filter((frame) => !frame.dataJs || frame.listShown)).toEqual([]);
        expect(new Set(frames.map((frame) => frame.height)).size).toBe(1);

        await header(page).locator('.theme-nav-main__toggle').click();
        await expect(header(page).locator('nav.theme-nav-main > .theme-nav-main__list')).toBeVisible();
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
            // viewport. Under the *trigger* is not enough - at 1280 the title
            // takes several lines beside the menu, and the header reaches below
            // the trigger.
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
 * row that is as tall as its tallest child. Measured at 1280 pixels before
 * this change, while the menu still wrapped onto two rows, the panel ran
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

    // 1280 has the menu beside a title on several lines; 375 has it behind its
    // toggle. The header is a different height in each, which is why the
    // block offset has to be derived from the header rather than chosen.
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
            // and is not enough: at 1280 the title takes several lines, and the
            // header reaches below the cog.
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

/**
 * Both panels of the controls slot drop under the whole header in every header
 * variant, not only in the default one the page itself renders.
 *
 * The blocks above measure the page's own header, which is `simple`. The
 * other three arrangements are one site setting away and nothing opened a
 * panel in them - and `centred` put both panels inside the header, over its
 * navigation row: its title row was `position: relative` and its controls
 * `position: absolute`, so the row, not the header, was their containing
 * block. The stylesheet gate in `ComponentLibraryTest` names the positioned
 * boxes; this measures what they did.
 *
 * The instance renders one variant per site, and a page choosing another
 * would need a per-page override of a site-wide setting that no site writes
 * (see the note on the "Examples" section of the showcase scenario). So the
 * four headers measured are the specimens of the styleguide section `chrome`,
 * which carry the markup of the variant partials and are held to it by
 * `ComponentLibraryTest::theChromeSpecimensCarryEveryClassTheirPartialsWrite`.
 * A specimen stands one icon button in for the controls; the real slot of the
 * page's own header - the language dropdown and the display settings, as the
 * instance renders them - is copied into each one first, with its ids made
 * unique, and both panels are opened.
 *
 * The same measurement holds two more promises of the header rows: no title
 * box reaches under a trigger in any variant, and the title of `centred` sits
 * on the centre of the content container from `bp.$md` up. Below it the
 * variant centres the title together with the controls, so 375 checks only
 * the first. 768 is `bp.$md` itself.
 *
 * The title row is laid out at `bp.$md`, and the menu at a breakpoint per
 * arrangement - so at 1024 `simple` is collapsed while the other three are
 * not, and at 768 all four are. The specimens carry the menu toggle of the
 * real partial, so a collapsed one keeps its toggle in the header.
 */
test.describe('the header panels in every header variant', () => {
    for (const width of [1280, 1024, 768, 375]) {
        test(`drop under the whole header and end at its container at ${width} pixels`, async ({ page }) => {
            await page.setViewportSize({ width, height: 800 });
            await page.goto('/styleguide');

            const measured = await page.evaluate(() => {
                const slot = document.querySelector('.theme-page > .theme-site-header .theme-site-header__actions');
                if (slot === null) {
                    throw new Error('The page header has no controls slot to copy.');
                }
                const variants: Record<string, Record<string, number>> = {};
                document.querySelectorAll<HTMLElement>('#chrome .theme-site-header').forEach((header, index) => {
                    const modifier = [...header.classList].find((name) => name.startsWith('theme-site-header--'));
                    const variant = modifier === undefined ? 'simple' : modifier.slice('theme-site-header--'.length);
                    const target = header.querySelector('.theme-site-header__actions');
                    if (target === null) {
                        throw new Error(`The "${variant}" specimen has no controls slot.`);
                    }
                    target.innerHTML = slot.innerHTML.replaceAll('theme-language-panel', `sg-chrome-${index}-language-panel`)
                        .replaceAll('theme-settings-', `sg-chrome-${index}-settings-`);
                    const dropdown = target.querySelector<HTMLDetailsElement>('details.theme-dropdown');
                    const dropdownPanel = target.querySelector<HTMLElement>('.theme-dropdown__panel');
                    const settingsPanel = target.querySelector<HTMLElement>('.theme-settings__panel');
                    if (dropdown === null || dropdownPanel === null || settingsPanel === null) {
                        throw new Error('The copied controls slot lacks the language dropdown or the display settings.');
                    }
                    dropdown.open = true;
                    settingsPanel.hidden = false;

                    // The content container of the last row: the first row of
                    // a variant may carry spacing of its own.
                    const rows = header.querySelectorAll<HTMLElement>('.theme-site-header__inner');
                    const row = rows[rows.length - 1];
                    const dropdownBox = dropdownPanel.getBoundingClientRect();
                    const settingsBox = settingsPanel.getBoundingClientRect();
                    const rowBox = row.getBoundingClientRect();
                    const rowStyle = getComputedStyle(row);
                    const containerStart = rowBox.left + parseFloat(rowStyle.paddingInlineStart);
                    const containerEnd = rowBox.right - parseFloat(rowStyle.paddingInlineEnd);

                    const brand = header.querySelector('.theme-site-header__brand');
                    if (brand === null) {
                        throw new Error(`The "${variant}" specimen has no title.`);
                    }
                    const brandBox = brand.getBoundingClientRect();
                    // The title's box against every trigger of the copied
                    // slot, as a count of intersecting pairs.
                    let brandOverTriggers = 0;
                    target.querySelectorAll('.theme-dropdown__trigger, .theme-settings__trigger').forEach((trigger) => {
                        const box = trigger.getBoundingClientRect();
                        if (!(brandBox.right <= box.left || box.right <= brandBox.left || brandBox.bottom <= box.top || box.bottom <= brandBox.top)) {
                            brandOverTriggers++;
                        }
                    });

                    variants[variant] = {
                        headerBottom: header.getBoundingClientRect().bottom,
                        containerEnd,
                        containerCentre: (containerStart + containerEnd) / 2,
                        brandCentre: (brandBox.left + brandBox.right) / 2,
                        brandOverTriggers,
                        dropdownTop: dropdownBox.top,
                        dropdownEnd: dropdownBox.right,
                        settingsTop: settingsBox.top,
                        settingsEnd: settingsBox.right,
                    };
                });
                return variants;
            });

            expect(Object.keys(measured).sort()).toEqual(['actions', 'centred', 'simple', 'two-tier']);
            for (const [variant, box] of Object.entries(measured)) {
                // Under all of the header, which in the three variants with
                // two rows is under the second row as well.
                expect.soft(box.dropdownTop, `the top of the language panel of "${variant}"`).toBeGreaterThanOrEqual(box.headerBottom);
                expect.soft(box.settingsTop, `the top of the display settings panel of "${variant}"`).toBeGreaterThanOrEqual(box.headerBottom);
                // And at the end of the content container, the edge the header
                // anchor derives - not the end of a row that captured it.
                expect.soft(box.dropdownEnd, `the end of the language panel of "${variant}"`).toBeCloseTo(box.containerEnd, 0);
                expect.soft(box.settingsEnd, `the end of the display settings panel of "${variant}"`).toBeCloseTo(box.containerEnd, 0);
                // The title's box stays clear of the controls.
                expect.soft(box.brandOverTriggers, `triggers under the title of "${variant}"`).toBe(0);
            }

            // The title of "centred" on the centre of the whole row, not of
            // the space the controls leave.
            if (width >= 768) {
                expect.soft(Math.abs(measured.centred.brandCentre - measured.centred.containerCentre), 'the title of "centred" off the centre of its row').toBeLessThanOrEqual(1);
            }
        });
    }
});

/**
 * The main navigation of every header arrangement, just above and just below
 * the breakpoint of that arrangement.
 *
 * The menu never wraps: from its arrangement's breakpoint up it is one row of
 * entries and the title gives way beside it, one pixel below it is behind its
 * toggle. Each arrangement has a breakpoint of its own because each shares
 * the menu's row with something else - see "layout/_site-header.scss". At the
 * breakpoint itself the row is as tight as it ever gets, so that is where a
 * row that spills, a title squeezed below its longest word, or a second level
 * over an entry or a control would show first; it is checked in both reading
 * directions. 1280 is where every arrangement is expanded.
 *
 * The second level used to open over the entries that had wrapped under it:
 * in the default header at 1280 pixels on the showcase, "Elements" over
 * "Styleguide" and "Forms", and in the three variants, whose menu has a row
 * of its own, between 768 and 1023 pixels, where a 50% cap meant for the
 * default reached them too.
 */
test.describe('the main navigation in every header arrangement', () => {
    for (const arrangement of headerArrangements) {
        const breakpoint = headerBreakpoints[arrangement];

        for (const direction of ['ltr', 'rtl']) {
            test(`is one row covered by no second level in "${arrangement}" at ${breakpoint} pixels, ${direction}`, async ({ page }) => {
                await page.setViewportSize({ width: breakpoint, height: 800 });
                await page.goto('/styleguide');
                await arrangeHeader(page, arrangement);
                await page.locator('html').evaluate((element, direction) => element.setAttribute('dir', direction), direction);

                await expectTheMenuInOneRow(page, null);
                expect(await secondLevelsCoveringTheHeader(page)).toEqual([]);
            });
        }

        test(`is one row covered by no second level in "${arrangement}" at 1280 pixels`, async ({ page }) => {
            await page.setViewportSize({ width: 1280, height: 800 });
            await page.goto('/styleguide');
            await arrangeHeader(page, arrangement);

            await expectTheMenuInOneRow(page, null);
            expect(await secondLevelsCoveringTheHeader(page)).toEqual([]);
        });

        test(`is behind its toggle in "${arrangement}" at ${breakpoint - 1} pixels`, async ({ page }) => {
            await page.setViewportSize({ width: breakpoint - 1, height: 800 });
            await page.goto('/styleguide');
            await arrangeHeader(page, arrangement);

            await expectTheMenuBehindItsToggle(page, null);
        });
    }

    // Without a script nothing collapses: below its breakpoint an
    // arrangement shows the list stacked in the flow, every second level
    // inline, and that has to fit the screen as it did when the stacked
    // layout only began at 768 pixels. "flex: none" on the navigation,
    // written for the row, once applied here too and kept "simple" as wide
    // as its widest entry: 456 pixels of content in a 360 pixel wide page
    // at 375. 800 is below every breakpoint and above "bp.$md".
    for (const width of [375, 800]) {
        test(`fits the screen without JavaScript in every arrangement at ${width} pixels`, async ({ browser }) => {
            const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width, height: 800 } });
            const page = await context.newPage();
            const overflowing: string[] = [];

            // And the page as a reader gets it, for the one arrangement the
            // instance renders.
            await page.goto('/typography');
            const [pageScrollWidth, pageClientWidth] = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth]);
            if (pageScrollWidth > pageClientWidth) {
                overflowing.push(`"/typography": ${pageScrollWidth} in ${pageClientWidth}`);
            }

            for (const arrangement of headerArrangements) {
                await page.goto('/styleguide');
                await arrangeHeader(page, arrangement);
                // The header's own scroll width: it spans the page, so what
                // spills out of one of its rows widens it, and the rest of
                // "/styleguide" - a table, a code block - is left out of it.
                const [scrollWidth, clientWidth] = await page.locator('.theme-page > .theme-site-header')
                    .evaluate((header) => [header.scrollWidth, header.clientWidth]);
                if (scrollWidth > clientWidth) {
                    overflowing.push(`"${arrangement}": ${scrollWidth} in ${clientWidth}`);
                }
                // What is measured is the no-script layout, not a page that
                // ran its script after all.
                await expect(page.locator('html')).not.toHaveAttribute('data-js', /.*/);
                await expect(page.locator('.theme-page > .theme-site-header nav.theme-nav-main > .theme-nav-main__list')).toBeVisible();
            }
            expect(overflowing).toEqual([]);
            await context.close();
        });
    }

    // Without a script the header lists its sections and no second level,
    // at every width its menu is stacked - below the breakpoint of its
    // arrangement, down to a phone. The stacked menu with every second level
    // open was 1733 to 1788 pixels of header between 768 and the breakpoint,
    // and 2523 in "simple" at 375; with the sections alone it is 511 to 566
    // there, and 621 to 805 at 375 (Chromium, the showcase - "simple" is the
    // tallest, its narrow column wraps the entries). The bound is 640 from
    // 768 up, a little over seven entries, a title and the controls, and 900
    // below it. Each section page links its own pages, which
    // "SectionPagesListTheirSubpagesTest" holds the seed to.
    const withoutAScript: [string, number, number][] = [
        ['simple', 800, 640],
        ['simple', headerBreakpoints.simple - 1, 640],
        ['centred', headerBreakpoints.centred - 1, 640],
        ['actions', headerBreakpoints.actions - 1, 640],
        ['two-tier', headerBreakpoints['two-tier'] - 1, 640],
        ...headerArrangements.flatMap((arrangement): [string, number, number][] => [[arrangement, 767, 640], [arrangement, 375, 900]]),
    ];
    for (const [arrangement, width, bound] of withoutAScript) {
        test(`lists the sections only without JavaScript in "${arrangement}" at ${width} pixels`, async ({ browser }) => {
            const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width, height: 800 } });
            const page = await context.newPage();
            await page.goto('/styleguide');
            await arrangeHeader(page, arrangement);
            await expect(page.locator('html')).not.toHaveAttribute('data-js', /.*/);

            const siteHeader = page.locator('.theme-page > .theme-site-header');
            const topLevel = siteHeader.locator('nav.theme-nav-main > .theme-nav-main__list > .theme-nav-main__item > .theme-nav-main__link');
            await expect(topLevel).toHaveCount(7);
            for (const link of await topLevel.all()) {
                await expect(link).toBeVisible();
            }
            // Out of the layout, the tab order and the accessibility tree:
            // "display: none", not a clip.
            const secondLevel = siteHeader.locator('.theme-nav-main__list--sub');
            expect(await secondLevel.count()).toBeGreaterThan(0);
            for (const list of await secondLevel.all()) {
                await expect(list).toHaveCSS('display', 'none');
            }
            await expect(siteHeader.getByRole('link', { name: 'Core elements', exact: true })).toHaveCount(0);

            const [height, overflow] = await siteHeader.evaluate((element) => [element.getBoundingClientRect().height, element.scrollWidth - element.clientWidth]);
            expect(height).toBeLessThanOrEqual(bound);
            expect(overflow).toBeLessThanOrEqual(0);

            // The header's menu only: the main navigation specimen of the
            // styleguide, outside the header, keeps its second level open
            // below "bp.$md" without a script, as it always has.
            if (width < 768) {
                await expect(page.locator('#navigation nav.theme-nav-main .theme-nav-main__list--sub').first()).toBeVisible();
            }
            await context.close();
        });
    }

    // The header collapses its menu at a width of its own; a menu anywhere
    // else still collapses at "bp.$md". At 1024 the page's header is behind
    // its toggle and the specimen of the navigation section is a row; below
    // 768 the specimen is behind a toggle of its own, which opens it.
    test('leaves a main navigation outside the header to collapse at bp.$md', async ({ page }) => {
        await page.setViewportSize({ width: 1024, height: 800 });
        await page.goto('/styleguide');
        const specimen = page.locator('#navigation nav.theme-nav-main');
        const toggle = specimen.locator('.theme-nav-main__toggle');
        const list = specimen.locator(':scope > .theme-nav-main__list');

        await expect(page.locator('.theme-page > .theme-site-header .theme-nav-main__toggle')).toBeVisible();
        await expect(toggle).toBeHidden();
        await expect(list).toBeVisible();

        await page.setViewportSize({ width: 767, height: 800 });
        await expect(toggle).toBeVisible();
        await expect(list).toBeHidden();
        await toggle.click();
        await expect(list).toBeVisible();
    });
});

/**
 * The showcase under an enforced Content Security Policy.
 *
 * `-s acceptance` enforces TYPO3's frontend policy in its own copy of the
 * instance configuration (`runTests.sh`; the committed instances and DDEV stay
 * as they are). A browser blocks what the policy does not allow without an
 * error the page can see - a script that does not run, a source that does not
 * load - and reports it as a `securitypolicyviolation` event, which is what
 * is collected here, from the first byte of each page, with every
 * click-to-load embed opened, since that is where a frame source is decided.
 *
 * Two things were blocked before the theme dealt with them: the inline
 * no-flash script of the head, allowed by its hash since
 * `Configuration/ContentSecurityPolicies.php`, and the `data:` sources and
 * caption tracks of the media specimen of the styleguide, which "media-src"
 * does not allow and which are files now. The markers are asserted as well:
 * the no-flash script set `data-js`, and `theme.js` confirmed it.
 */
test.describe('the showcase under an enforced Content Security Policy', () => {
    const pages = [
        '/',
        '/typography',
        '/media',
        '/styleguide',
        '/forms',
        '/elements/cheatsheet',
        '/elements/theme/external-media',
        '/examples/carousel-landing',
        '/legacy/styleguide',
    ];

    for (const path of pages) {
        test(`${path} violates nothing`, async ({ page }) => {
            await page.addInitScript(() => {
                const violations: string[] = [];
                (window as unknown as { themeViolations: string[] }).themeViolations = violations;
                document.addEventListener('securitypolicyviolation', (event) => {
                    violations.push(`${event.effectiveDirective} blocked ${event.blockedURI || '(inline)'} at ${event.sourceFile}:${event.lineNumber}`);
                });
            });
            const response = await page.goto(path);
            expect(response?.headers()['content-security-policy'], 'the instance does not enforce a policy').toBeTruthy();
            await page.waitForLoadState('networkidle');

            // Clicked by the element rather than by the pointer: a specimen of
            // the styleguide lies over one of them, and what matters here is
            // the frame the click puts in, not where the pointer can reach.
            // Each button once, as it was when the page had loaded - an opened
            // embed replaces its button with the frame.
            await page.evaluate(() => document.querySelectorAll<HTMLButtonElement>('.theme-embed[data-theme-embed-bound] .theme-embed__button').forEach((button) => button.click()));
            // Every media element of the page, in view, so a player that
            // fetches a caption track or a poster does it now.
            await page.evaluate(() => document.querySelectorAll('video, audio').forEach((media) => media.scrollIntoView()));
            // A frame of a provider never finishes loading in the network of
            // the suite, so "networkidle" is no end to wait for here. A
            // violation is reported when the request is refused, which is
            // before it leaves the browser; half a second covers the task that
            // dispatches the event.
            await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 500)));

            expect.soft(await page.evaluate(() => (window as unknown as { themeViolations: string[] }).themeViolations)).toEqual([]);
            await expect(page.locator('html')).toHaveAttribute('data-js', '');
            await expect(page.locator('html')).toHaveAttribute('data-js-bound', '');
        });
    }
});

/**
 * The footer fits a narrow screen.
 *
 * The meta row of the footer is a wrapping flex row, and a flex item is never
 * narrower than its longest word unless it is told so. The showcase puts a
 * path into it as inline code - `Configuration/DataFactory/theme-demo/`, one
 * word of 294 pixels - and the footer, and with it the page, scrolled sideways
 * to 354 pixels at 320 and 360 on the development machine. Whether that path
 * overflows depends on the monospace font - it has no break opportunity, and
 * in the fonts of the pinned image the whole word is narrow enough to fit -
 * so the test does not rely on it: it puts one longer word, without a single
 * break opportunity, into that code first. What is measured is the footer's
 * own scroll width, against its own client width - the header has a minimum
 * width of its own, measured elsewhere - and that every line box of the code
 * stays inside the meta row: wrapped, by the body's "overflow-wrap:
 * break-word", not cut off.
 */
test.describe('the site footer on a narrow screen', () => {
    for (const path of ['/typography', '/legacy/typography']) {
        for (const width of [320, 360]) {
            test(`${path} keeps the footer inside the screen at ${width} pixels`, async ({ page }) => {
                await page.setViewportSize({ width, height: 800 });
                await page.goto(path);

                const measured = await page.locator('.theme-page > .theme-site-footer').evaluate((footer) => {
                    const meta = footer.querySelector('.theme-site-footer__meta');
                    const code = meta === null ? null : meta.querySelector('code');
                    if (meta === null || code === null) {
                        return { spill: -1, outside: -1, lines: 0 };
                    }
                    code.textContent = 'Configuration_DataFactory_theme_demo_ScenarioLegacy_yaml';
                    const row = meta.getBoundingClientRect();
                    const lines = [...code.getClientRects()];
                    return {
                        spill: footer.scrollWidth - footer.clientWidth,
                        outside: lines.filter((line) => line.left < row.left - 0.5 || line.right > row.right + 0.5).length,
                        lines: lines.length,
                    };
                });
                expect(measured.lines, 'the inline code of the meta row').toBeGreaterThan(0);
                expect(measured.spill).toBeLessThanOrEqual(0);
                expect(measured.outside).toBe(0);
            });
        }
    }
});
