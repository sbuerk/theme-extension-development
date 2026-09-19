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

/**
 * The lightbox of an enlargeable gallery, on the seeded page of the image
 * element. The functional tests hold the markup - the dialog, one item per
 * image, the zoom link that keeps its "href". What only a browser shows is
 * that the link stops navigating once the script has bound it, that the dialog
 * is a real modal, and that the arrows move within the gallery.
 */
test.describe('the gallery lightbox', () => {
    test('opens the image that was pressed, moves between them and gives focus back', async ({ page }) => {
        await page.goto('/elements/core/image');
        const zoom = page.locator('#c3406 .theme-gallery__zoom');
        const dialog = page.locator('#c3406-lightbox');

        await expect(zoom).toHaveCount(3);
        await expect(dialog).toBeHidden();
        // The fallback is intact: the link still points at the file.
        await expect(zoom.first()).toHaveAttribute('href', /fileadmin/);

        await zoom.first().click();
        await expect(dialog).toBeVisible();
        expect(await dialog.evaluate((element) => element.matches(':modal'))).toBe(true);
        // The link was not followed - the page is still the gallery's.
        await expect(page).toHaveURL(/\/elements\/core\/image$/);

        // The item that was pressed, and only that one.
        const shown = dialog.locator('.theme-lightbox__item:visible');
        await expect(shown).toHaveCount(1);

        const first = await shown.getAttribute('id');
        await dialog.getByRole('button', { name: 'Next image' }).click();
        await expect(dialog.locator('.theme-lightbox__item:visible')).toHaveCount(1);
        expect(await dialog.locator('.theme-lightbox__item:visible').getAttribute('id')).not.toBe(first);

        // The arrow keys do the same, and wrap: back twice from the second
        // item is the last one.
        await page.keyboard.press('ArrowLeft');
        expect(await dialog.locator('.theme-lightbox__item:visible').getAttribute('id')).toBe(first);
        await page.keyboard.press('ArrowLeft');
        expect(await dialog.locator('.theme-lightbox__item:visible').getAttribute('id')).not.toBe(first);

        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(zoom.first()).toBeFocused();

        // A second thumbnail opens the same dialog on **its own** image, and
        // focus goes back to the one that was pressed. The id the link names
        // is what the dialog has to show - asserting only that some item is
        // visible would pass whichever one it was.
        const second = await zoom.nth(1).getAttribute('data-theme-lightbox-item');
        expect(second).not.toBeNull();
        await zoom.nth(1).click();
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('.theme-lightbox__item:visible')).toHaveCount(1);
        expect(await dialog.locator('.theme-lightbox__item:visible').getAttribute('id')).toBe(second);
        await dialog.getByRole('button', { name: 'Close' }).click();
        await expect(zoom.nth(1)).toBeFocused();
    });

    test('the zoom link leads to the file without JavaScript', async ({ browser }) => {
        const context = await browser.newContext({ javaScriptEnabled: false });
        const page = await context.newPage();
        await page.goto('/elements/core/image');

        const zoom = page.locator('#c3406 .theme-gallery__zoom');
        await expect(zoom.first()).toBeVisible();
        await expect(zoom.first()).toHaveAttribute('href', /fileadmin/);
        // A closed dialog renders nothing, so the enhancement is simply absent.
        await expect(page.locator('#c3406-lightbox')).toBeHidden();
        await context.close();
    });
});

/**
 * The external media element: nothing of the other site is requested until the
 * button is pressed, and then exactly one iframe appears.
 *
 * The provider is blocked at the route rather than actually contacted - what
 * is under test is which address the page asks for, not the other site.
 */
test.describe('the external media content element', () => {
    test('loads no iframe until the button is pressed', async ({ page }) => {
        const requested: string[] = [];
        await page.route(/youtube-nocookie\.com|player\.vimeo\.com/, (route) => {
            requested.push(route.request().url());
            return route.abort();
        });

        await page.goto('/elements/theme/external-media');
        const embed = page.locator('#c13002 .theme-embed');
        await expect(embed).toHaveAttribute('data-theme-embed-bound', '');
        await expect(embed.locator('.theme-embed__poster')).toBeVisible();
        await expect(page.locator('iframe')).toHaveCount(0);
        expect(requested, 'the page contacted the provider before the button was pressed').toEqual([]);

        await embed.getByRole('button').click();
        const iframe = embed.locator('iframe');
        await expect(iframe).toHaveCount(1);
        await expect(iframe).toHaveAttribute('src', /^https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?autoplay=1$/);
        // An iframe is a document of its own and needs a name of its own.
        await expect(iframe).toHaveAttribute('title', /\S/);
        // The poster it replaced is gone, not merely covered.
        await expect(embed.locator('.theme-embed__poster')).toHaveCount(0);
    });

    test('a host that is not recognised offers no button at all', async ({ page }) => {
        await page.goto('/elements/theme/external-media');
        const embed = page.locator('#c13005 .theme-embed');

        await expect(embed.locator('.theme-embed__button')).toHaveCount(0);
        await expect(embed.locator('.theme-embed__source')).toBeVisible();
        await expect(embed.locator('.theme-embed__source')).toHaveAttribute('href', 'https://media.example.org/videos/the-launch');
    });

    test('without JavaScript there is no button, and the link is the way to the video', async ({ browser }) => {
        const context = await browser.newContext({ javaScriptEnabled: false });
        const page = await context.newPage();
        await page.goto('/elements/theme/external-media');

        const embed = page.locator('#c13002 .theme-embed');
        await expect(embed).not.toHaveAttribute('data-theme-embed-bound', /.*/);
        await expect(embed.locator('.theme-embed__button')).toBeHidden();
        await expect(embed.locator('.theme-embed__source')).toBeVisible();
        await expect(page.locator('iframe')).toHaveCount(0);
        await context.close();
    });

    test('a page whose theme.js never loads offers no button either', async ({ page }) => {
        await page.route(/\/JavaScript\/theme\.js/, (route) => route.abort());
        await page.goto('/elements/theme/external-media');

        // The inline head script ran and announced a script; the one that
        // would have bound the button never arrived.
        await expect(page.locator('html')).toHaveAttribute('data-js', '');
        await expect(page.locator('#c13002 .theme-embed__button')).toBeHidden();
        await expect(page.locator('#c13002 .theme-embed__source')).toBeVisible();
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
