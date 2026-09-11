<?php

declare(strict_types=1);

/**
 * Renders the styleguide partials into the static pages of the visual suite.
 *
 *   php Build/Scripts/renderStyleguideFixtures.php              # writes .Build/visual/
 *   php Build/Scripts/renderStyleguideFixtures.php --sections   # prints JSON, writes nothing
 *
 * "Build/Scripts/runTests.sh -s visual" runs it before the Playwright specs of
 * "Tests/Acceptance/Visual/". Every "Resources/Private/Partials/Styleguide/*.html"
 * is rendered with the standalone "typo3fluid/fluid" of the root dependency set
 * - no TYPO3 bootstrap, no instance, no database - and written once per
 * appearance and palette as a page of its own below ".Build/visual/fixtures/",
 * next to the "manifest.json" the specs iterate. The partials are discovered,
 * not listed: a new one is covered by the next run.
 *
 * Nothing it writes is committed, so a fixture cannot drift from its partial.
 * What can drift is standalone Fluid from the Fluid TYPO3 renders the same
 * partial with; "StyleguideRenderingTest" compares the two through
 * "--sections".
 *
 * WHY ONE PAGE PER COMBINATION
 *
 * Appearance and palette are both selected on the root element -
 * ":root[data-theme]" and ":root[data-palette]" - so a combination is a
 * document, not a region of one. The attributes are written into the markup
 * the way TYPO3 renders them ("Appearance.typoscript"), so the stylesheet
 * resolves each combination on the first paint and no script has to run. No
 * script runs at all: "data-js" is not set, which only the collapsing main
 * navigation below its breakpoint and the display settings of the header react
 * to - the former is not reached at the viewport of the suite, the latter is
 * page chrome rather than a specimen.
 *
 * WHY THE STYLESHEET IS LINKED
 *
 * Each page links the committed "Resources/Public/Css/theme.css", unchanged.
 * Both obvious alternatives test a different stylesheet, and both look
 * plausible while doing it:
 *
 *   - Inlined into "<style>", the text is no longer decoded as a stylesheet
 *     file. The build used to write a UTF-8 BOM, which a linked file loses in
 *     the decoder; inlined, it became part of the first selector, and the
 *     first rule - the whole ":root" token block - was dropped. The dark
 *     appearance then reported contrast violations that did not exist. The
 *     build writes no BOM any more, and linking is right either way.
 *   - Imported through a bundler, Vite rewrote every "light-dark()" into
 *     fallback custom properties.
 *
 * The link is relative, so a page works from the static server of the suite
 * as well as opened from disk in any browser.
 *
 * It needs the composer install of the repository root for Fluid, so run
 * "Build/Scripts/runTests.sh -s composerUpdate" first if ".Build/" is empty.
 * Either core version will do: the v12 set brings Fluid 2, the v13 set Fluid
 * 4, and the pages they produce are identical.
 */
$autoload = __DIR__ . '/../../.Build/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "The composer install is missing. Run \"Build/Scripts/runTests.sh -s composerUpdate\" first.\n");
    exit(1);
}
require $autoload;

use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * The repository root. "__DIR__" has symlinks resolved, so this is never a
 * path through the self referencing "theme" symlink, even if the script was
 * called through one.
 */
const ROOT_PATH = __DIR__ . '/../..';
const PARTIAL_ROOT_PATH = ROOT_PATH . '/Resources/Private/Partials/';
const PALETTE_SOURCE = ROOT_PATH . '/Resources/Private/Scss/abstracts/_palettes.scss';
const TARGET_PATH = ROOT_PATH . '/.Build/visual';

/**
 * The stylesheet, relative to a page in ".Build/visual/fixtures/".
 */
const STYLESHEET_HREF = '../../../Resources/Public/Css/theme.css';

/**
 * The two explicit appearances. The third, "auto", is the absence of the
 * attribute and resolves to one of these two through "color-scheme".
 */
const APPEARANCES = ['light', 'dark'];

/**
 * The palette "_tokens.scss" itself declares, and which therefore has no
 * "[data-palette]" rule in "_palettes.scss". TYPO3 renders it onto the root
 * element all the same ("theme.appearance.palette" in "constants.typoscript"),
 * and so does a fixture.
 */
const DEFAULT_PALETTE = 'neutral';

exit(main($argv));

/**
 * @param string[] $argv
 */
function main(array $argv): int
{
    $arguments = array_slice($argv, 1);
    if ($arguments !== [] && $arguments !== ['--sections']) {
        fwrite(STDERR, "Usage: renderStyleguideFixtures.php [--sections]\n");
        return 1;
    }

    // A notice or a deprecation raised while rendering is a partial that does
    // not render the way it is meant to. The run fails on it, like every test
    // suite of this repository does.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        $sections = renderSections();
        $palettes = palettes();
    } catch (\Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        return 1;
    }

    if ($arguments === ['--sections']) {
        echo json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        return 0;
    }

    writeFixtures($sections, $palettes);
    printf(
        "Rendered %d sections × %d appearances × %d palettes = %d pages into %s\n",
        count($sections),
        count(APPEARANCES),
        count($palettes),
        count($sections) * count(APPEARANCES) * count($palettes),
        realpath(TARGET_PATH) ?: TARGET_PATH,
    );

    return 0;
}

/**
 * Every styleguide partial, rendered, by the id of its section.
 *
 * @return array<string, string>
 */
function renderSections(): array
{
    $partials = glob(PARTIAL_ROOT_PATH . 'Styleguide/*.html') ?: [];
    if ($partials === []) {
        throw new \RuntimeException('No partial found below "Resources/Private/Partials/Styleguide/".');
    }
    sort($partials);

    $sections = [];
    foreach ($partials as $partial) {
        $name = basename($partial, '.html');
        $html = renderPartial('Styleguide/' . $name);
        $id = sectionId($name, $html);
        if (isset($sections[$id])) {
            throw new \RuntimeException(sprintf('The partials "%s" and "%s" render the same section id "%s".', $sections[$id], $name, $id));
        }
        $sections[$id] = $html;
    }

    return $sections;
}

function renderPartial(string $partial): string
{
    $view = new TemplateView();
    $templatePaths = $view->getRenderingContext()->getTemplatePaths();
    $templatePaths->setPartialRootPaths([PARTIAL_ROOT_PATH]);
    $templatePaths->setTemplateSource(sprintf('<f:render partial="%s" />', $partial));

    try {
        $html = trim((string)$view->render());
    } catch (\Throwable $exception) {
        throw new \RuntimeException(sprintf('"%s" does not render with standalone Fluid: %s', $partial, $exception->getMessage()), 0, $exception);
    }

    // The styleguide partials are literal markup by contract
    // ("docs/development/styleguide.md"), and a fixture built from a partial
    // that needs TYPO3 would test broken markup. Most such partials fail above
    // already: a ViewHelper of a TYPO3 namespace - "f:translate",
    // "f:uri.resource", "f:image" - resolves to a class that throws without
    // TYPO3 around it, and an unknown ViewHelper or an undeclared namespace is a
    // parse error. A namespace declared with a URL that is not a PHP namespace
    // is ignored instead, and its tags reach the output verbatim; that is
    // caught here.
    //
    // A variable cannot be caught at all: Fluid renders one that is not set,
    // and a property path on one, as an empty string rather than as "{name}".
    // The only guard against a partial that starts to expect a variable is
    // "StyleguideRenderingTest::everySectionRendersWithoutTypo3AsItDoesOnThePage",
    // which compares this rendering with the one TYPO3 produces.
    if (preg_match('#</?[a-z][a-z0-9]*:[a-z]#i', $html, $match) === 1) {
        throw new \RuntimeException(sprintf('"%s" leaves "%s" unrendered, which needs more than standalone Fluid to render.', $partial, $match[0]));
    }

    return $html;
}

/**
 * The id of the one section a styleguide partial consists of.
 */
function sectionId(string $name, string $html): string
{
    if (preg_match('#^<section\b[^>]*\bclass="theme-styleguide__section"[^>]*\bid="([a-z0-9-]+)"#', $html, $match) !== 1) {
        throw new \RuntimeException(sprintf(
            'The partial "Styleguide/%s" does not render a single <section class="theme-styleguide__section" id="…"> first,'
            . ' which every styleguide partial has to be.',
            $name,
        ));
    }

    return $match[1];
}

/**
 * The default palette, then every alternate "_palettes.scss" declares.
 *
 * Read from the source rather than listed here, so a palette added there is
 * covered without touching this script.
 *
 * @return string[]
 */
function palettes(): array
{
    $source = (string)file_get_contents(PALETTE_SOURCE);
    // Comments first: the file shows its own selector in an example.
    $source = (string)preg_replace('#//.*$#m', '', $source);
    preg_match_all("#:root\\[data-palette='([a-z0-9-]+)'\\]#", $source, $matches);
    if ($matches[1] === []) {
        throw new \RuntimeException('No palette was found in "abstracts/_palettes.scss" - the path or the selector is wrong.');
    }

    return array_values(array_unique([DEFAULT_PALETTE, ...$matches[1]]));
}

/**
 * @param array<string, string> $sections
 * @param string[] $palettes
 */
function writeFixtures(array $sections, array $palettes): void
{
    $fixturePath = TARGET_PATH . '/fixtures';
    if (!is_dir($fixturePath)) {
        mkdir($fixturePath, 0777, true);
    }
    // Only the pages of an earlier run, never anything else: a partial that was
    // renamed or removed must not leave a page behind that looks current.
    foreach (glob($fixturePath . '/*.html') ?: [] as $stale) {
        unlink($stale);
    }

    $pages = [];
    foreach ($sections as $id => $html) {
        foreach (APPEARANCES as $appearance) {
            foreach ($palettes as $palette) {
                $path = sprintf('fixtures/%s-%s-%s.html', $id, $appearance, $palette);
                file_put_contents(TARGET_PATH . '/' . $path, page($id, $html, $appearance, $palette));
                $pages[] = ['section' => $id, 'appearance' => $appearance, 'palette' => $palette, 'path' => $path];
            }
        }
    }

    $manifest = [
        'sections' => array_keys($sections),
        'appearances' => APPEARANCES,
        'palettes' => $palettes,
        'defaultPalette' => DEFAULT_PALETTE,
        'pages' => $pages,
    ];
    file_put_contents(
        TARGET_PATH . '/manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
    );
    file_put_contents(TARGET_PATH . '/index.html', index($pages));
}

/**
 * One section in one appearance and palette.
 *
 * The wrapper is the structure "Layouts/Default.html" and
 * "Templates/Page/Styleguide.html" place a section in, so it takes the width,
 * the padding and the styleguide furniture of the real page. The "<h1>" gives
 * the section headings the outline they have there. The empty icon keeps the
 * browser from requesting "/favicon.ico".
 */
function page(string $id, string $section, string $appearance, string $palette): string
{
    $title = sprintf('Styleguide fixture: %s, %s, %s', $id, $appearance, $palette);
    $stylesheet = STYLESHEET_HREF;

    return <<<HTML
        <!DOCTYPE html>
        <html lang="en" data-theme="{$appearance}" data-palette="{$palette}">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title}</title>
        <link rel="icon" href="data:,">
        <link rel="stylesheet" href="{$stylesheet}">
        </head>
        <body>
        <div class="theme-page">
        <div class="theme-page__body">
        <main class="theme-page__main" id="content">
        <div class="theme-styleguide">
        <h1>Styleguide: {$id}</h1>
        {$section}
        </div>
        </main>
        </div>
        </div>
        </body>
        </html>

        HTML;
}

/**
 * A page listing every fixture, for opening them from disk.
 *
 * @param list<array{section: string, appearance: string, palette: string, path: string}> $pages
 */
function index(array $pages): string
{
    $items = '';
    foreach ($pages as $page) {
        $items .= sprintf(
            "<li><a href=\"%s\">%s, %s, %s</a></li>\n",
            htmlspecialchars($page['path']),
            htmlspecialchars($page['section']),
            htmlspecialchars($page['appearance']),
            htmlspecialchars($page['palette']),
        );
    }

    return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <title>Styleguide fixtures</title>
        <link rel="icon" href="data:,">
        </head>
        <body>
        <main>
        <h1>Styleguide fixtures</h1>
        <p>Generated by "Build/Scripts/renderStyleguideFixtures.php" - never edit, never commit.</p>
        <ul>
        {$items}</ul>
        </main>
        </body>
        </html>

        HTML;
}
