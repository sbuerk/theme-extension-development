import { expect, test } from '@playwright/test';

/**
 * The components of the library that need the theme's script, driven on the
 * styleguide ("Resources/Private/Partials/Styleguide/Interactive.html").
 *
 * The functional tests prove that the markup is on the page. What only a
 * browser shows is the behaviour "Resources/Public/JavaScript/theme.js" adds
 * to it - and what the stylesheet leaves a visitor with when that script
 * never runs: with JavaScript switched off, and with JavaScript on but
 * "theme.js" failing to load, which are two different pages.
 */
test.describe('the styleguide with JavaScript', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/styleguide');
    });

    test('tabs follow the arrow keys and show one panel at a time', async ({ page }) => {
        const group = page.locator('#interactive .theme-tabs');
        const tab = (name: string) => group.getByRole('tab', { name, exact: true });
        const panel = (id: string) => page.locator(`#${id}`);

        await expect(group).toHaveAttribute('data-theme-tabs-bound', '');
        await expect(tab('Site set')).toHaveAttribute('aria-selected', 'true');
        await expect(panel('sg-tabs-set-panel')).toBeVisible();
        await expect(panel('sg-tabs-static-panel')).toBeHidden();
        await expect(panel('sg-tabs-legacy-panel')).toBeHidden();
        // The heading labels a panel only while there are no tabs.
        await expect(panel('sg-tabs-set-panel').locator('.theme-tabs__heading')).toBeHidden();

        // Bound, every panel is a tab panel, labelled by its tab, with a tab
        // stop - none of which the markup carries.
        for (const [panelId, tabId] of [['sg-tabs-set-panel', 'sg-tabs-set'], ['sg-tabs-static-panel', 'sg-tabs-static'], ['sg-tabs-legacy-panel', 'sg-tabs-legacy']]) {
            await expect(panel(panelId)).toHaveAttribute('role', 'tabpanel');
            await expect(panel(panelId)).toHaveAttribute('aria-labelledby', tabId);
            await expect(panel(panelId)).toHaveAttribute('tabindex', '0');
        }

        await tab('Site set').focus();
        await page.keyboard.press('ArrowRight');
        await expect(tab('Static include')).toBeFocused();
        await expect(tab('Static include')).toHaveAttribute('aria-selected', 'true');
        await expect(tab('Static include')).toHaveAttribute('tabindex', '0');
        await expect(tab('Site set')).toHaveAttribute('aria-selected', 'false');
        await expect(tab('Site set')).toHaveAttribute('tabindex', '-1');
        await expect(panel('sg-tabs-static-panel')).toBeVisible();
        await expect(panel('sg-tabs-set-panel')).toBeHidden();

        await page.keyboard.press('End');
        await expect(tab('Legacy tree')).toBeFocused();
        await expect(panel('sg-tabs-legacy-panel')).toBeVisible();

        // Past the last tab the arrow key wraps round to the first, and back.
        await page.keyboard.press('ArrowRight');
        await expect(tab('Site set')).toBeFocused();
        await page.keyboard.press('ArrowLeft');
        await expect(tab('Legacy tree')).toBeFocused();
        await page.keyboard.press('Home');
        await expect(tab('Site set')).toBeFocused();
        await expect(panel('sg-tabs-set-panel')).toBeVisible();

        // Only the selected tab is a tab stop, so Tab leaves the list for the panel.
        await page.keyboard.press('Tab');
        await expect(panel('sg-tabs-set-panel')).toBeFocused();

        await tab('Legacy tree').click();
        await expect(tab('Legacy tree')).toHaveAttribute('aria-selected', 'true');
        await expect(panel('sg-tabs-legacy-panel')).toBeVisible();
        await expect(panel('sg-tabs-set-panel')).toBeHidden();
    });

    test('a dialog opens as a modal and gives focus back however it closes', async ({ page }) => {
        const opener = page.locator('[data-theme-dialog-open="sg-dialog-reseed"]');
        const dialog = page.locator('#sg-dialog-reseed');
        const cancel = dialog.getByRole('button', { name: 'Cancel' });

        await expect(dialog).toBeHidden();

        await opener.click();
        await expect(dialog).toBeVisible();
        expect(await dialog.evaluate((element) => element.matches(':modal'))).toBe(true);
        // A confirmation opens on the action that does no harm.
        await expect(cancel).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(opener).toBeFocused();

        await opener.click();
        await cancel.click();
        await expect(dialog).toBeHidden();
        await expect(opener).toBeFocused();
        expect(await dialog.evaluate((element: HTMLDialogElement) => element.returnValue)).toBe('cancel');

        await opener.click();
        await dialog.getByRole('button', { name: 'Close' }).click();
        await expect(dialog).toBeHidden();
        await expect(opener).toBeFocused();

        // A press on the text, dragged out and released over the scrim, is a
        // text selection and arrives as a click on the dialog outside its box:
        // the dialog stays open.
        await opener.click();
        const text = await dialog.locator('.theme-dialog__body').boundingBox();
        expect(text).not.toBeNull();
        await page.mouse.move(text!.x + 10, text!.y + 10);
        await page.mouse.down();
        await page.mouse.move(5, 5, { steps: 5 });
        await page.mouse.up();
        await expect(dialog).toBeVisible();

        // The top left corner of the viewport is scrim, not dialog.
        await page.mouse.click(5, 5);
        await expect(dialog).toBeHidden();
        await expect(opener).toBeFocused();
    });

    test('a tooltip shows on focus, and Escape hides it without moving focus', async ({ page }) => {
        const trigger = page.getByRole('button', { name: 'Element outlines', exact: true });
        const bubble = page.locator('#sg-tooltip-outline');

        await expect(trigger).toHaveAccessibleDescription('Draws a dashed frame and the CType around every content element.');
        await expect(bubble).toBeHidden();

        await trigger.focus();
        await expect(bubble).toBeVisible();
        // WCAG 2.4.11: the bubble goes when focus leaves it, with nothing pressed.
        await page.keyboard.press('Tab');
        await expect(bubble).toBeHidden();

        await trigger.focus();
        await expect(bubble).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(bubble).toBeHidden();
        await expect(trigger).toBeFocused();

        // Dismissed until focus has left - then the next focus shows it again.
        await page.keyboard.press('Tab');
        await trigger.focus();
        await expect(bubble).toBeVisible();
    });

    test('one Escape closes the display settings and a hovered tooltip, each on its own terms', async ({ page }) => {
        const cog = page.locator('.theme-site-header .theme-settings__trigger');
        const panel = page.locator('#theme-settings-panel');
        const trigger = page.getByRole('button', { name: 'Element outlines', exact: true });
        const bubble = page.locator('#sg-tooltip-outline');

        await cog.click();
        await expect(panel).toBeVisible();
        // Hovering moves neither focus nor the panel: focus stays on the cog.
        await trigger.hover();
        await expect(bubble).toBeVisible();
        await expect(panel).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
        await expect(bubble).toBeHidden();
        await expect(cog).toBeFocused();
    });

    test('Reset in the display settings is a link-style button with a full target', async ({ page }) => {
        // Opened and walked with the keyboard only: ":focus-visible" follows
        // the way focus arrived, and a focus moved by script after a mouse
        // click would not show the ring a keyboard user sees.
        const cog = page.locator('.theme-site-header .theme-settings__trigger');
        await cog.focus();
        await page.keyboard.press('Enter');
        const reset = page.locator('#theme-settings-panel [data-theme-settings-reset]');
        await expect(reset).toBeVisible();
        await expect(reset).toHaveClass(/theme-button--link/);

        const box = await reset.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.height).toBeGreaterThanOrEqual(44);
        expect(box!.width).toBeGreaterThanOrEqual(44);

        // Tab through the panel until Reset has focus; it carries the global ring.
        for (let step = 0; step < 10 && !(await reset.evaluate((element) => element === document.activeElement)); step++) {
            await page.keyboard.press('Tab');
        }
        await expect(reset).toBeFocused();
        expect(await reset.evaluate((element) => element.matches(':focus-visible') && getComputedStyle(element).outlineStyle !== 'none')).toBe(true);
    });

    test('a tooltip stays inside the viewport of a phone', async ({ page }) => {
        // WCAG 1.4.10: at 400px nothing may need scrolling in two directions.
        await page.setViewportSize({ width: 400, height: 800 });
        const pairs: [string, string][] = [['Element outlines', '#sg-tooltip-outline'], ['Back to the section', '#sg-tooltip-section']];

        for (const [name, bubbleSelector] of pairs) {
            await page.getByRole(name === 'Element outlines' ? 'button' : 'link', { name, exact: true }).focus();
            const bubble = page.locator(bubbleSelector);
            await expect(bubble).toBeVisible();
            const box = await bubble.boundingBox();
            expect(box).not.toBeNull();
            expect(box!.x, `${bubbleSelector} starts at ${box!.x}`).toBeGreaterThanOrEqual(0);
            expect(box!.x + box!.width, `${bubbleSelector} ends at ${box!.x + box!.width}`).toBeLessThanOrEqual(400);
        }
    });

    test('an icon button is square in every size', async ({ page }) => {
        const buttons = page.locator('#buttons .theme-button--icon');
        expect(await buttons.count()).toBeGreaterThan(3);
        for (const button of await buttons.all()) {
            const box = await button.boundingBox();
            expect(box).not.toBeNull();
            expect(Math.round(box!.height), await button.getAttribute('class') ?? '').toBe(Math.round(box!.width));
        }
    });

    test('the tip is mixed from the secondary accent of every palette in both appearances', async ({ page }) => {
        // The tints DESIGN.md records for the tip, computed from the formula in
        // "components/_alert.scss". A pixel painted with the computed colour is
        // compared, which is what the browser actually draws; one step per
        // channel covers rounding between the two conversions.
        const expected: Record<string, Record<string, number[]>> = {
            neutral: { light: [0xe4, 0xee, 0xed], dark: [0x18, 0x26, 0x2a] },
            ember: { light: [0xef, 0xe9, 0xe2], dark: [0x24, 0x25, 0x24] },
            ocean: { light: [0xe4, 0xed, 0xee], dark: [0x19, 0x26, 0x2c] },
            moss: { light: [0xea, 0xeb, 0xe3], dark: [0x21, 0x26, 0x24] },
            violet: { light: [0xf6, 0xe6, 0xed], dark: [0x26, 0x20, 0x2a] },
        };
        const tip = page.locator('#boxes .theme-alert--tip');

        for (const [palette, appearances] of Object.entries(expected)) {
            for (const [appearance, colour] of Object.entries(appearances)) {
                await page.evaluate(([name, scheme]) => {
                    document.documentElement.setAttribute('data-palette', name);
                    document.documentElement.setAttribute('data-theme', scheme);
                }, [palette, appearance]);

                const painted = await tip.evaluate((element) => {
                    const canvas = document.createElement('canvas');
                    canvas.width = 1;
                    canvas.height = 1;
                    const context = canvas.getContext('2d') as CanvasRenderingContext2D;
                    context.fillStyle = getComputedStyle(element).backgroundColor;
                    context.fillRect(0, 0, 1, 1);
                    return Array.from(context.getImageData(0, 0, 1, 1).data.slice(0, 3));
                });

                for (let channel = 0; channel < 3; channel++) {
                    expect(
                        Math.abs(painted[channel] - colour[channel]),
                        `${palette}, ${appearance}: painted ${painted.join(',')}, recorded ${colour.join(',')}`,
                    ).toBeLessThanOrEqual(1);
                }
            }
        }
    });
});

test.describe('the styleguide in forced colours', () => {
    test.use({ forcedColors: 'active' });

    test('a state drawn in colour survives forced colours', async ({ page }) => {
        await page.goto('/styleguide');

        // The system highlight colour, as this browser resolves it under the
        // emulated scheme - read off a probe that opts out of the repaint.
        const highlight = await page.evaluate(() => {
            const probe = document.createElement('div');
            probe.style.cssText = 'forced-color-adjust: none; background-color: Highlight';
            document.body.append(probe);
            const colour = getComputedStyle(probe).backgroundColor;
            probe.remove();
            return colour;
        });
        const style = (selector: string, property: string, pseudo: string | null = null) =>
            page.locator(selector).first().evaluate(
                (element, [name, pseudoElement]) => getComputedStyle(element, pseudoElement).getPropertyValue(name as string),
                [property, pseudo],
            );

        // A selected tab and a pressed toggle take the highlight pair: as a
        // fill of their own they would be repainted to the canvas colour.
        expect(await style('#sg-tabs-set', 'background-color')).toBe(highlight);
        expect(await style('#sg-tabs-static', 'background-color')).not.toBe(highlight);
        expect(await style('#buttons .theme-button-group--attached [aria-pressed="true"]', 'background-color')).toBe(highlight);
        expect(await style('#buttons .theme-button-group--attached [aria-pressed="false"]', 'background-color')).not.toBe(highlight);

        // Forced colours paint a transparent border side solid: the spinner's
        // gap, and with it the spinner, would disappear into a full ring.
        expect(await style('#buttons [aria-busy="true"]', 'border-right-color', '::before')).toBe('rgba(0, 0, 0, 0)');

        // The inverted tooltip keeps an edge, and drops the arrow.
        expect(await style('#sg-tooltip-outline', 'border-top-width')).toBe('1px');
        expect(await style('#sg-tooltip-outline', 'content', '::after')).toBe('none');

        // An alert keeps an edge all round once its tint is gone, the dialog a strong one.
        expect(await style('#boxes .theme-alert--tip', 'border-top-width')).toBe('1px');
        expect(await style('.theme-styleguide__stage .theme-dialog', 'border-top-width')).toBe('2px');
    });
});

/**
 * Every panel readable, no tab semantics without tabs, and no opener that
 * could open nothing - asserted for both ways a page can end up without the
 * script's behaviour.
 */
async function expectTheUndecoratedTabs(page: import('@playwright/test').Page): Promise<void> {
    const group = page.locator('#interactive .theme-tabs');
    await expect(group).not.toHaveAttribute('data-theme-tabs-bound', /.*/);
    await expect(group.locator('.theme-tabs__list')).toBeHidden();
    const panels = group.locator('.theme-tabs__panel');
    await expect(panels).toHaveCount(3);
    for (const panel of await panels.all()) {
        await expect(panel).toBeVisible();
        await expect(panel.locator('.theme-tabs__heading')).toBeVisible();
        await expect(panel).not.toHaveAttribute('role', /.*/);
        await expect(panel).not.toHaveAttribute('tabindex', /.*/);
        await expect(panel).not.toHaveAttribute('aria-labelledby', /.*/);
    }
}

test.describe('the styleguide without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('every tab panel is readable and no dialog opener is offered', async ({ page }) => {
        await page.goto('/styleguide');
        // Not even the inline head script ran: this is the undecorated page.
        await expect(page.locator('html')).not.toHaveAttribute('data-js', /.*/);

        await expectTheUndecoratedTabs(page);
        await expect(page.locator('[data-theme-dialog-open]')).toBeHidden();
        await expect(page.locator('#sg-dialog-reseed')).toBeHidden();
    });
});

test.describe('the styleguide when theme.js does not load', () => {
    test('every tab panel is still readable', async ({ page }) => {
        await page.route(/\/JavaScript\/theme\.js/, (route) => route.abort());
        await page.goto('/styleguide');
        // The inline head script ran and announced a script - the one that
        // would have switched the tabs never arrived.
        await expect(page.locator('html')).toHaveAttribute('data-js', '');

        await expectTheUndecoratedTabs(page);
    });
});
