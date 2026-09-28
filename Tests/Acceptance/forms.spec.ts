import { expect, test } from '@playwright/test';

/**
 * The form showcase on "/forms" ("Resources/Private/Templates/Page/Forms.html").
 *
 * The page promises two things a functional test can only read off the markup:
 * that the submit button runs the browser's own validation, and that nothing
 * is sent afterwards. Both are behaviour of the form submission algorithm -
 * validation first, then a "dialog" method outside a dialog ends it - so both
 * are checked where that algorithm runs.
 */
test.describe('the form showcase', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/forms');
    });

    test('validates on submit and marks what fails, without leaving the page', async ({ page }) => {
        const requests: string[] = [];
        page.on('request', (request) => {
            if (request.isNavigationRequest()) {
                requests.push(request.url());
            }
        });

        const form = page.locator('form[aria-labelledby="form-request-title"]');
        await form.getByRole('button', { name: 'Request the instance' }).click();

        // The first invalid control takes focus, and ":user-invalid" now
        // matches it - it did not before the attempt.
        await expect(page.locator('#form-request-name')).toBeFocused();
        expect(await page.locator('#form-request-name').evaluate((element) => element.matches(':user-invalid'))).toBe(true);
        expect(requests).toEqual([]);
        expect(new URL(page.url()).pathname).toBe('/forms');
    });

    test('ends a valid submission in the browser, sending nothing', async ({ page }) => {
        const requests: string[] = [];
        page.on('request', (request) => {
            if (request.method() !== 'GET' || request.isNavigationRequest()) {
                requests.push(`${request.method()} ${request.url()}`);
            }
        });

        await page.locator('#form-request-name').fill('Ada Lovelace');
        await page.locator('#form-request-email').fill('ada@example.org');
        await page.locator('#form-request-base').fill('theme.example.org');
        await page.locator('#form-request-core').selectOption('14');
        await page.locator('#form-request-message').fill('A site package built on this theme.');
        await page.locator('#form-request-password').fill('not-a-real-secret');
        await page.locator('#form-request-terms').check();

        const form = page.locator('form[aria-labelledby="form-request-title"]');
        expect(await form.evaluate((element: HTMLFormElement) => element.checkValidity())).toBe(true);
        await form.getByRole('button', { name: 'Request the instance' }).click();

        // Nothing to wait for, which is the point: give a request the chance
        // to start, then check that none did.
        await page.waitForTimeout(500);
        expect(requests).toEqual([]);
        expect(new URL(page.url()).pathname).toBe('/forms');
        await expect(page.locator('#form-request-name')).toHaveValue('Ada Lovelace');
    });

    test('clears the form with the reset button', async ({ page }) => {
        await page.locator('#form-request-name').fill('Ada Lovelace');
        await page.getByRole('button', { name: 'Clear the form' }).click();
        await expect(page.locator('#form-request-name')).toHaveValue('');
    });

    // A text field narrower than its content scrolls what is typed into it.
    // A date, a time, a colour or a single select does not: it cuts its value
    // off, and the reader sees "16/2026" for a whole date. So each of those is
    // held to its intrinsic width at 305 pixels, 320 less a classic scrollbar,
    // measured on a copy of it with nothing around it to squeeze it. The inline
    // field "Needed from" squeezed its date input to 121 pixels there, 178 in
    // Chromium and 161 in Firefox being what the date needs.
    test('keeps every control that cuts its value off at its own width at 305 pixels', async ({ page }) => {
        await page.setViewportSize({ width: 305, height: 800 });
        await page.goto('/forms');

        const squeezed = await page.evaluate(() => {
            const selector = ['date', 'time', 'datetime-local', 'month', 'week', 'color']
                .map((type) => `.theme-form input[type="${type}"]`)
                .concat('.theme-form select:not([multiple])')
                .join(', ');
            const controls = [...document.querySelectorAll<HTMLElement>(selector)];
            return controls.map((control) => {
                const probe = document.createElement('div');
                probe.style.cssText = 'position: absolute; inset-block-start: 0; inset-inline-start: 0; display: inline-block; visibility: hidden';
                const copy = control.cloneNode(true) as HTMLElement;
                copy.removeAttribute('id');
                probe.append(copy);
                control.closest('form')?.append(probe);
                const own = copy.getBoundingClientRect().width;
                probe.remove();
                const width = control.getBoundingClientRect().width;
                return { name: control.id, width, own };
            }).filter((control) => control.width < control.own - 0.5)
                .map((control) => `#${control.name}: ${control.width.toFixed(1)} of ${control.own.toFixed(1)}`)
                .concat(controls.length === 0 ? ['no control of the kind on the page'] : []);
        });
        expect(squeezed, 'controls narrower than they need to show their value').toEqual([]);
    });

    test('links every error of the returned form to its field', async ({ page }) => {
        const summary = page.locator('.theme-form-summary--error');
        for (const link of await summary.getByRole('link').all()) {
            const target = (await link.getAttribute('href')) ?? '';
            await expect(page.locator(target)).toHaveAttribute('aria-invalid', 'true');
        }
        await summary.getByRole('link', { name: 'Enter at least 20 characters' }).click();
        expect(new URL(page.url()).hash).toBe('#form-returned-message');
    });
});
