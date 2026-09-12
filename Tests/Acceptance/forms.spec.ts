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
        await page.locator('#form-request-core').selectOption('13');
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
