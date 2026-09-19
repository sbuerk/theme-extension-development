import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import AxeBuilder from '@axe-core/playwright';
import { expect, type Locator, type Page, test } from '@playwright/test';

/**
 * Every styleguide section, in every appearance and palette, in a browser.
 *
 * The pages are rendered from "Resources/Private/Partials/Styleguide/*.html" by
 * "Build/Scripts/renderStyleguideFixtures.php" before this suite runs, one
 * document per combination, and "manifest.json" lists them. Nothing here names
 * a section: a partial added to the styleguide is tested by the next run.
 *
 *   - axe runs on every combination, restricted to the WCAG 2.2 AA rules, and
 *     has to report nothing. Colour contrast is the rule that makes the matrix
 *     worth running in full: a palette changes accents in both appearances.
 *   - Screenshots are compared on a reduced matrix, see "targets()".
 *
 * See "docs/testing/visual-tests.md".
 */
type Fixture = {
    section: string;
    appearance: string;
    palette: string;
    path: string;
};

type Manifest = {
    sections: string[];
    appearances: string[];
    palettes: string[];
    defaultPalette: string;
    pages: Fixture[];
};

type Target = {
    name: string;
    locate: (page: Page) => Locator;
};

const manifestPath = resolve(__dirname, '../../../.Build/visual/manifest.json');
if (!existsSync(manifestPath)) {
    throw new Error(`${manifestPath} does not exist. Run "Build/Scripts/runTests.sh -s visual", which renders the fixtures first.`);
}
const manifest: Manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));

/**
 * WCAG 2.0, 2.1 and 2.2, levels A and AA - every tag axe-core 4.13 knows for
 * them. WCAG 2.2 added no level A rule axe implements, so there is no
 * "wcag22a". The "best-practice" rules are not run: several of them judge a
 * whole page ("page-has-heading-one", "region"), and a fixture is one section.
 */
const WCAG_22_AA = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/**
 * The specimen of the token section that shows a given token as a swatch.
 */
const specimen = (page: Page, token: string): Locator => page
    .locator('#tokens .theme-styleguide__specimen')
    .filter({ has: page.locator(`[style*="var(${token})"]`) });

/**
 * What is screenshotted of a section in the palettes other than the default.
 *
 * A palette re-points five tokens and nothing else - the five every block of
 * "abstracts/_palettes.scss" overrides: "--theme-color-primary", "-primary-hover",
 * "-secondary", "-secondary-hover" and "--theme-focus-ring-color". Two
 * specimens of "tokens" show them: the accent swatch group the first four,
 * the focus ring specimen the fifth. The rest of that section is neutral and
 * would add the same pixels to every palette's baseline. The buttons are the
 * component made of accents alone and stay whole. Every other section is
 * compared in the default palette only, in both appearances.
 */
const PALETTE_TARGETS: Record<string, Target[]> = {
    tokens: [
        { name: 'tokens-accents', locate: (page) => specimen(page, '--theme-color-secondary-hover') },
        { name: 'tokens-focus', locate: (page) => specimen(page, '--theme-focus-ring-color') },
    ],
    buttons: [
        { name: 'buttons', locate: (page) => page.locator('#buttons') },
    ],
};

/**
 * The elements a fixture is screenshotted by: the whole section in the
 * default palette, the palette targets above in every other one, nothing for
 * the remaining sections there.
 */
const targets = (fixture: Fixture): Target[] => {
    if (fixture.palette === manifest.defaultPalette) {
        return [{ name: fixture.section, locate: (page) => page.locator(`#${fixture.section}`) }];
    }
    return PALETTE_TARGETS[fixture.section] ?? [];
};

const combination = (fixture: Fixture): string => `${fixture.appearance}-${fixture.palette}`;

const name = (fixture: Fixture): string => `${fixture.section}-${combination(fixture)}`;

/**
 * Opens a fixture and proves the stylesheet was applied, not only linked.
 *
 * The custom property is the first thing lost when the stylesheet reaches the
 * page in a way that changes it - inlined, the BOM the build used to write
 * swallowed the whole ":root" token block - and every colour of every
 * specimen reads it. A page without it would still screenshot and still pass
 * most of axe.
 */
async function open(page: Page, fixture: Fixture): Promise<void> {
    const failures: string[] = [];
    page.on('response', (response) => {
        if (response.status() >= 400) {
            failures.push(`HTTP ${response.status()} ${response.url()}`);
        }
    });
    page.on('requestfailed', (request) => failures.push(`failed ${request.url()}`));
    page.on('pageerror', (error) => failures.push(error.message));

    const response = await page.goto(fixture.path);
    expect(response?.status()).toBe(200);

    const root = page.locator('html');
    await expect(root).toHaveAttribute('data-theme', fixture.appearance);
    await expect(root).toHaveAttribute('data-palette', fixture.palette);

    const surface = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--theme-color-surface').trim());
    expect(surface, 'The design tokens did not resolve - the stylesheet was not applied.').not.toBe('');

    expect(failures).toEqual([]);
}

test('every palette section is part of the styleguide', () => {
    // A section renamed or removed from the styleguide would otherwise silently
    // drop out of the palette screenshots.
    expect(manifest.sections).toEqual(expect.arrayContaining(Object.keys(PALETTE_TARGETS)));
});

test.describe('axe', () => {
    for (const fixture of manifest.pages) {
        test(`${name(fixture)} has no WCAG 2.2 AA violation`, async ({ page }) => {
            await open(page, fixture);

            const results = await new AxeBuilder({ page }).withTags(WCAG_22_AA).analyze();
            const violations = results.violations.map((violation) => ({
                rule: violation.id,
                impact: violation.impact,
                help: violation.help,
                // The summary carries the measurement - for "color-contrast"
                // both colours, the ratio and the one expected.
                nodes: violation.nodes.map((node) => `${node.target.join(' ')}: ${node.failureSummary ?? ''}`),
            }));

            expect(violations).toEqual([]);
        });
    }
});

test.describe('palette', () => {
    // "--theme-color-info" aliases the primary accent, so a palette changes it
    // without overriding it - and neither clipped palette screenshot of
    // "tokens" shows its swatch. It is held to the primary swatch here instead,
    // in every appearance and palette.
    for (const fixture of manifest.pages.filter((page) => page.section === 'tokens')) {
        test(`${name(fixture)} shows the primary accent as info`, async ({ page }) => {
            await open(page, fixture);

            const colour = (token: string) => page
                .locator(`#tokens [style*="var(--theme-color-${token})"]`)
                .first()
                .evaluate((element) => getComputedStyle(element).backgroundColor);

            expect(await colour('info')).toBe(await colour('primary'));
        });
    }
});

test.describe('switch', () => {
    // Where a switch paints its thumb is the whole of its state, and neither
    // other check here can see it. A screenshot proves the switch looks as it
    // did, not that it looks right: the baselines were once captured with the
    // thumb in the middle of the track in both states, and passed because of
    // it. The computed "background-position" flipped from "0% 50%" to
    // "100% 50%" all along, over an image as wide as the track that had
    // nowhere to go. So the switch is screenshotted, the picture decoded on a
    // canvas in the page, and the thumb found as the pixels of the middle row
    // that differ from the track - reported as the horizontal centre of those
    // pixels, a fraction of the width.
    for (const fixture of manifest.pages.filter((page) => page.section === 'forms')) {
        test(`${name(fixture)} draws the thumb at the end its state names`, async ({ page }) => {
            await open(page, fixture);

            const thumb = async (id: string): Promise<number> => {
                const picture = await page.locator(`#${id}`).screenshot();
                return page.evaluate(async (base64) => {
                    const image = new Image();
                    image.src = `data:image/png;base64,${base64}`;
                    await image.decode();
                    const canvas = document.createElement('canvas');
                    canvas.width = image.naturalWidth;
                    canvas.height = image.naturalHeight;
                    const context = canvas.getContext('2d');
                    if (context === null) {
                        throw new Error('The page has no 2D canvas context.');
                    }
                    context.drawImage(image, 0, 0);
                    const { data, width, height } = context.getImageData(0, 0, canvas.width, canvas.height);
                    const pixel = (x: number, y: number) => data.subarray((y * width + x) * 4, (y * width + x) * 4 + 3);

                    // Two rows in: inside the one pixel border, and above the
                    // thumb, which the transparent end of its gradient keeps
                    // clear of the track's edge.
                    const track = pixel(Math.floor(width / 2), 2);
                    const row = Math.floor(height / 2);
                    let sum = 0;
                    let count = 0;
                    // Two columns in from either end: the border is not the thumb.
                    for (let x = 2; x < width - 2; x++) {
                        const [red, green, blue] = pixel(x, row);
                        if (Math.abs(red - track[0]) + Math.abs(green - track[1]) + Math.abs(blue - track[2]) > 96) {
                            sum += x;
                            count++;
                        }
                    }
                    if (count === 0) {
                        throw new Error('There is no thumb on the middle row of the switch.');
                    }
                    return (sum / count + 0.5) / width;
                }, picture.toString('base64'));
            };

            expect(await thumb('sg-form-switch-beta'), 'The thumb of a switch that is off is not at its start.').toBeLessThan(0.4);
            expect(await thumb('sg-form-switch-digest'), 'The thumb of a switch that is on is not at its end.').toBeGreaterThan(0.6);
        });
    }
});

test.describe('screenshot', () => {
    for (const fixture of manifest.pages) {
        for (const target of targets(fixture)) {
            const file = `${target.name}-${combination(fixture)}`;
            test(`${file} looks like its baseline`, async ({ page }) => {
                await open(page, fixture);

                // The media specimens load lazily. A lazily loaded image outside
                // the viewport is not requested before the screenshot scrolls to
                // it, and whether it has arrived by then is timing. Loading every
                // image up front and waiting for it takes the timing out; the
                // attribute has no effect on layout, the images carry width and
                // height.
                await page.evaluate(async () => {
                    const images = Array.from(document.images);
                    for (const image of images) {
                        image.loading = 'eager';
                    }
                    await Promise.all(images.map((image) => image.decode()));
                    await document.fonts.ready;
                });

                const locator = target.locate(page);
                // A markup change that loses or duplicates the clipped element
                // must fail here, not screenshot something else.
                await expect(locator).toHaveCount(1);
                await expect(locator).toHaveScreenshot(`${file}.png`);
            });
        }
    }
});
