import { defineConfig, devices } from '@playwright/test';

/**
 * Acceptance tests against a development instance that was built from nothing.
 *
 * "Build/Scripts/runTests.sh -s acceptance" builds the instance below
 * ".Build/acceptance/" with the same "composer system:setup" a DDEV instance
 * runs, serves it with the PHP built-in server and runs this suite in the
 * Playwright container against it, with BASE_URL pointing at that server.
 *
 * BASE_URL may point anywhere else that serves a seeded instance - a DDEV
 * instance, for example - so the same specs can be run against one by hand:
 *
 *   cd Tests/Acceptance && BASE_URL=https://core13-theme-v2.ddev.site npx playwright test
 *
 * The results and the report go below ".Build/acceptance/", which is
 * git-ignored and removed by "composerUpdate" like the rest of ".Build/"; the
 * dependency is installed into "node_modules/" next to this file.
 */
const baseURL = process.env.BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.ts',
    globalSetup: './global-setup.ts',
    outputDir: '../../.Build/acceptance/test-results',
    // One browser at a time, and one PHP worker serving it (runTests.sh). The
    // instance is SQLite, which allows one writer: two browsers rendering
    // uncached pages at once made one of them fail with "database is locked"
    // (HTTP 500), and not on every run.
    workers: 1,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [
        ['list'],
        ['html', { outputFolder: '../../.Build/acceptance/report', open: 'never' }],
    ],
    use: {
        baseURL,
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
