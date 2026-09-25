import { expect, type Page, test } from '@playwright/test';

/**
 * The frontend accounts of the seed, logging in through EXT:felogin rendered by
 * the theme. Credentials: "docs/development/instances.md".
 */
const password = 'Frontend-User-1701D.';

/**
 * Presses "Login" and asserts that the form's POST left the browser.
 *
 * The form is a plain native submit, and nothing in "theme.js" handles it.
 * Once, in Firefox, a click on the button reached no submission at all: no
 * POST was sent, focus stayed in the password field, and the test failed ten
 * seconds later on a success message that could not appear. Playwright's
 * "click action done" does not prove that a mouse event reached the page. So
 * the request is waited for here, and a recurrence fails as what it is - see
 * "docs/testing/acceptance-tests.md#two-engines".
 */
async function submitTheLoginForm(page: Page): Promise<void> {
    const posted = page
        .waitForRequest((request) => request.method() === 'POST' && request.isNavigationRequest(), { timeout: 10_000 })
        .then(() => true, () => false);
    await page.getByRole('button', { name: 'Login' }).click();
    expect(await posted, 'pressing "Login" sent no POST').toBe(true);
}

test('a visitor is refused the members page', async ({ request }) => {
    const response = await request.get('/members');
    expect(response.status()).toBe(403);
});

test('a member logs in and opens the members page', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('jane.doe');
    await page.getByLabel('Password').fill(password);
    await submitTheLoginForm(page);
    await expect(page.getByText("You are now logged in as 'jane.doe'")).toBeVisible();

    const response = await page.goto('/members');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'For members' })).toBeVisible();
});

test('a logged in member sees the logout form and logs out', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('jane.doe');
    await page.getByLabel('Password').fill(password);
    await submitTheLoginForm(page);
    await expect(page.getByText("You are now logged in as 'jane.doe'")).toBeVisible();

    // The login page again, now as a logged in user. On TYPO3 v13 this reads
    // "settings.redirectPageLogout" without a fallback, which a FlexForm that
    // lacks the field turns into an error in place of the form.
    await page.goto('/login');
    await expect(page.getByText('Oops, an error occurred')).toHaveCount(0);
    await page.getByRole('button', { name: 'Logout' }).click();
    await expect(page.getByText('You have logged out.')).toBeVisible();

    const response = await page.goto('/members');
    expect(response?.status()).toBe(403);
});

test('a user without the group logs in and is still refused the members page', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('liam.rhodes');
    await page.getByLabel('Password').fill(password);
    await submitTheLoginForm(page);
    await expect(page.getByText("You are now logged in as 'liam.rhodes'")).toBeVisible();

    const response = await page.goto('/members');
    expect(response?.status()).toBe(403);
});

test('a wrong password is refused', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('jane.doe');
    await page.getByLabel('Password').fill('not the password');
    await submitTheLoginForm(page);
    // Waited for rather than checked for absence: a click does not wait for
    // the navigation it starts, and the page before it has no success message
    // either.
    await expect(page.getByText('Login failure')).toBeVisible();
    await expect(page.getByText("You are now logged in as 'jane.doe'")).toHaveCount(0);
});
