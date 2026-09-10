import { expect, test } from '@playwright/test';

/**
 * The frontend accounts of the seed, logging in through EXT:felogin rendered by
 * the theme. Credentials: "docs/development/instances.md".
 */
const password = 'Frontend-User-1701D.';

test('a visitor is refused the members page', async ({ request }) => {
    const response = await request.get('/members');
    expect(response.status()).toBe(403);
});

test('a member logs in and opens the members page', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('jane.doe');
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page.getByText("You are now logged in as 'jane.doe'")).toBeVisible();

    const response = await page.goto('/members');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'For members' })).toBeVisible();
});

test('a logged in member sees the logout form and logs out', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('jane.doe');
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Login' }).click();
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
    await page.getByRole('button', { name: 'Login' }).click();
    await expect(page.getByText("You are now logged in as 'liam.rhodes'")).toBeVisible();

    const response = await page.goto('/members');
    expect(response?.status()).toBe(403);
});

test('a wrong password is refused', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('jane.doe');
    await page.getByLabel('Password').fill('not the password');
    await page.getByRole('button', { name: 'Login' }).click();
    // Waited for rather than checked for absence: a click does not wait for
    // the navigation it starts, and the page before it has no success message
    // either.
    await expect(page.getByText('Login failure')).toBeVisible();
    await expect(page.getByText("You are now logged in as 'jane.doe'")).toHaveCount(0);
});
