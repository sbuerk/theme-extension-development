import { defineConfig, devices } from '@playwright/test';

/**
 * Visual and accessibility tests of the styleguide partials.
 *
 * "Build/Scripts/runTests.sh -s visual" renders every styleguide partial into
 * static pages below ".Build/visual/" ("Build/Scripts/renderStyleguideFixtures.php"),
 * serves the repository root with the PHP built-in server through
 * "router.php", and runs this suite in the Playwright container with BASE_URL
 * pointing at ".Build/visual/" on that server. No TYPO3 instance is involved.
 *
 * It shares "package.json", the lockfile and therefore the one Playwright pin
 * with the acceptance suite; "../playwright.config.ts" ignores this directory.
 *
 * Every setting that changes pixels is fixed here rather than left to a
 * default, because the committed baselines are only valid for exactly this
 * rendering: the viewport, the scale factor, reduced motion, no animation, no
 * caret. The fonts are whatever the pinned Playwright image ships - the theme
 * sets "system-ui" - which is why baselines are written by this suite in that
 * image and nowhere else. See "docs/testing/visual-tests.md".
 */
const baseURL = process.env.BASE_URL ?? 'http://127.0.0.1:8000/.Build/visual/';

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.ts',
    globalSetup: './global-setup.ts',
    outputDir: '../../../.Build/visual/test-results',
    // One flat directory of committed PNGs, named after section, appearance
    // and palette. No platform or project suffix: there is exactly one
    // rendering these are valid for, and it is the pinned image.
    snapshotPathTemplate: '{testDir}/Baselines/{arg}{ext}',
    // A missing or changed baseline fails and nothing is written. Writing one
    // takes "-- --update-snapshots", which is a decision somebody makes after
    // looking at the diff - never a side effect of a run.
    updateSnapshots: 'none',
    // Static pages from a static server - no shared state between tests.
    workers: 4,
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: 0,
    timeout: 30_000,
    expect: {
        timeout: 10_000,
        toHaveScreenshot: {
            animations: 'disabled',
            caret: 'hide',
            scale: 'css',
            // No tolerance at all. The default per pixel threshold of 0.2
            // let a colour token move by four steps per channel - the dark
            // muted text, "#848fa1" back to "#808b9d" - without a single
            // differing pixel, and a token that changed a colour is what the
            // screenshots are for. Two runs in the same image, under podman
            // and under docker, are identical to the pixel, so nothing is
            // bought by tolerating a difference.
            threshold: 0,
            maxDiffPixels: 0,
        },
    },
    reporter: [
        ['list'],
        ['html', { outputFolder: '../../../.Build/visual/report', open: 'never' }],
    ],
    use: {
        baseURL,
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
                deviceScaleFactor: 1,
                // The fixtures set "data-theme" explicitly, so the emulated
                // preference decides nothing; it is fixed so it cannot start to.
                colorScheme: 'light',
                reducedMotion: 'reduce',
                locale: 'en-US',
                timezoneId: 'UTC',
            },
        },
    ],
});
