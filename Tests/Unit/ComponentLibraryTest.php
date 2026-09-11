<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Guards the component library's structural promises.
 *
 * `StylesheetTest` covers the appearance contract - what a colour token is and
 * how light and dark are selected. This covers the other half: that every
 * component the templates render against is actually in the bundle, and that
 * the two behaviours which degrade *silently* when broken still hold.
 *
 * Both of those were deliberate decisions rather than accidents of authoring,
 * which is why they are asserted rather than left to review.
 */
final class ComponentLibraryTest extends UnitTestCase
{
    private function stylesheet(): string
    {
        $file = dirname(__DIR__, 2) . '/Resources/Public/Css/theme.css';
        $this->assertFileExists($file, 'The compiled stylesheet is committed - run "runTests.sh -s buildCss".');

        return (string)file_get_contents($file);
    }

    /**
     * @return \Generator<string, array{selector: string}>
     */
    public static function shippedComponents(): \Generator
    {
        // The markup contract each component file documents in its header. A
        // template rendering one of these expects the rule to exist; dropping
        // a "@use" from "theme.scss" is otherwise invisible until someone
        // looks at the page.
        foreach ([
            'accordion' => '.theme-accordion',
            'alert' => '.theme-alert',
            'author' => '.theme-author',
            'badge' => '.theme-badge',
            'breadcrumb' => '.theme-breadcrumb',
            'button' => '.theme-button',
            'card' => '.theme-card',
            'close button' => '.theme-close',
            'content element' => '.theme-content-element',
            'content menu' => '.theme-content-menu',
            'dialog' => '.theme-dialog',
            'gallery' => '.theme-gallery',
            'hero' => '.theme-hero',
            'main navigation' => '.theme-nav-main',
            'sub navigation' => '.theme-nav-sub',
            'pagination' => '.theme-pagination',
            'panel' => '.theme-panel',
            'quote' => '.theme-quote',
            'display settings' => '.theme-settings',
            'segmented control' => '.theme-segmented',
            'palette swatch' => '.theme-swatch',
            'skip link' => '.theme-skip-link',
            'table' => '.theme-table',
            'tabs' => '.theme-tabs',
            'teaser' => '.theme-teaser',
            'display text role' => '.theme-display',
            'eyebrow text role' => '.theme-eyebrow',
            'lead text role' => '.theme-lead',
            'tooltip' => '.theme-tooltip',
            'form field' => '.theme-field',
            'form input' => '.theme-input',
            'form switch' => '.theme-switch',
            'form choice group' => '.theme-choice-group',
            'form input group' => '.theme-input-group',
            'form validation summary' => '.theme-form-summary',
            'page' => '.theme-page',
            'site header' => '.theme-site-header',
            'site footer' => '.theme-site-footer',
            'styleguide' => '.theme-styleguide',
        ] as $name => $selector) {
            yield $name => ['selector' => $selector];
        }
    }

    #[DataProvider('shippedComponents')]
    #[Test]
    public function everyComponentIsPartOfTheBundle(string $selector): void
    {
        $this->assertStringContainsString($selector, $this->stylesheet());
    }

    /**
     * The main navigation must be usable with no JavaScript at all.
     *
     * The toggle is a plain button, so it only does anything once a script
     * flips its `aria-expanded`. Collapsing by default would therefore hide
     * the whole menu on a narrow viewport whenever that script has not run,
     * and the page would look fine in every desktop check.
     *
     * So the open layout is the default and the collapse is gated behind the
     * `data-js` marker the script sets on the root. Inverting that back is a
     * one-character change with no visible symptom during development.
     */
    #[Test]
    public function collapsingTheMainNavigationRequiresTheScriptMarker(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString(
            '[data-js] .theme-nav-main:not(:has(.theme-nav-main__toggle[aria-expanded=true]))>.theme-nav-main__list{display:none}',
            $css,
            'The collapse rule must be gated behind the script marker, or the menu disappears without JavaScript.',
        );

        // And the toggle itself must not be offered when nothing can operate it.
        $this->assertStringContainsString('[data-js] .theme-nav-main__toggle', $css);
    }

    /**
     * The CType outline is a development affordance and has to leave without
     * a trace on a production site - the label included.
     *
     * It was implemented with a container style query first. That works, and a
     * pseudo element really can query its originating element, but Firefox only
     * shipped style queries in 151 (19 May 2026): on anything older the block
     * is dropped, the outline goes and the label stays, leaving a stray chip on
     * a production page. The attribute selector asserted here has no such
     * floor.
     */
    #[Test]
    public function theContentElementOutlineSwitchesOffCompletely(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString(
            '[data-theme-content-outline=off] .theme-content-element::before{content:none}',
            $css,
            'Switching the outline off has to remove the CType label as well.',
        );
        $this->assertStringContainsString('[data-theme-content-outline=off] .theme-content-element{', $css);
    }

    /**
     * Tabs must leave every panel readable until the script has bound them.
     *
     * A tab is a button, and a button does nothing without a script, so tabs
     * shown without one would strand every panel but the first. The list is
     * therefore hidden by default, and only shown - and the per-panel headings
     * only hidden - on the group's own `data-theme-tabs-bound`, which only
     * "theme.js" sets. Not on `data-js`: that says the inline head script ran,
     * not that "theme.js" did, and a page whose "theme.js" failed to load would
     * show tabs that do nothing over panels nobody can reach. Both mistakes
     * are invisible in every check made with a working script.
     */
    #[Test]
    public function tabsShowEveryPanelUntilTheScriptHasBoundThem(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString(
            '.theme-tabs__list{display:none;',
            $css,
            'Until the script has bound the group the tab list must not be rendered, or every panel but the first is out of reach.',
        );
        $this->assertStringContainsString('.theme-tabs[data-theme-tabs-bound]>.theme-tabs__list{display:flex}', $css);
        $this->assertStringContainsString(
            '.theme-tabs[data-theme-tabs-bound]>.theme-tabs__panel>.theme-tabs__heading{display:none}',
            $css,
        );
        $this->assertStringNotContainsString(
            '[data-js] .theme-tabs',
            $css,
            'The tabs must not be gated on the root marker: it does not say that "theme.js" ran.',
        );
    }

    /**
     * Nothing can open a dialog without a script, so no opener is offered.
     *
     * Written as a negation on purpose - an opener is any element, and a rule
     * showing it again would have to know its `display` - so the assertion is
     * on the negated selector itself. See "components/_dialog.scss".
     */
    #[Test]
    public function aDialogOpenerIsHiddenWithoutTheScriptMarker(): void
    {
        $this->assertStringContainsString(
            ':root:not([data-js]) [data-theme-dialog-open]{display:none}',
            $this->stylesheet(),
            'An opener without the script marker is a button that does nothing.',
        );
    }

    /**
     * @return \Generator<string, array{selector: string}>
     */
    public static function titlesRenderedOnAnyHeadingLevel(): \Generator
    {
        // "Partials/ContentElement/Hero.html" and "Teaser.html" put these on
        // h1 to h5, whichever "header_layout" asks for.
        yield 'hero title' => ['selector' => '.theme-hero__title'];
        yield 'teaser title' => ['selector' => '.theme-teaser__title'];
    }

    /**
     * `h5` and `h6` are set in capitals by the element baseline. A title that
     * a component selects by class, on whatever level the editor picked, must
     * not pick that up: "Hero.html" promises that the level changes nothing
     * about how the title looks, and `text-transform` is a property the class
     * would otherwise leave to the element.
     */
    #[DataProvider('titlesRenderedOnAnyHeadingLevel')]
    #[Test]
    public function aTitleOnAnyHeadingLevelKeepsItsOwnCase(string $selector): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('/%s\{[^}]*text-transform:none/', preg_quote($selector, '/')),
            $this->stylesheet(),
        );
    }

    /**
     * Every palette can be chosen, and every choice shows its own colours.
     *
     * The swatches of the display settings cannot read a palette they are not
     * inside - a custom property only ever holds the value of the selector
     * that currently matches, and CSS cannot ask what it *would* resolve to
     * under a different attribute. So each swatch carries its palette's
     * primary and secondary colour as literals, copied from
     * "abstracts/_palettes.scss" and, for "neutral", "abstracts/_tokens.scss".
     *
     * That copy is the only duplicated colour in the stylesheet, and nothing in
     * the language links the two: adding a palette leaves its swatch missing,
     * and changing one leaves the swatch showing the old colours - both
     * without an error. This test is that link, for the names and for every
     * value.
     */
    #[Test]
    public function everyPaletteHasASwatchWithItsOwnColours(): void
    {
        $scss = dirname(__DIR__, 2) . '/Resources/Private/Scss';

        $palettes = [];
        preg_match_all(
            "/:root\[data-palette='([a-z]+)'\]\s*\{(.*?)\}/s",
            $this->withoutComments((string)file_get_contents($scss . '/abstracts/_palettes.scss')),
            $blocks,
            PREG_SET_ORDER,
        );
        $this->assertNotEmpty($blocks, 'No alternate palette was found at all.');
        foreach ($blocks as [, $name, $declarations]) {
            $palettes[$name] = $this->colourPair($declarations, '--theme-color-primary', '--theme-color-secondary', $name);
        }

        // "neutral" is the default and lives in the token file rather than
        // behind a selector, so it is never in the match above.
        $palettes['neutral'] = $this->colourPair(
            $this->withoutComments((string)file_get_contents($scss . '/abstracts/_tokens.scss')),
            '--theme-color-primary',
            '--theme-color-secondary',
            'neutral',
        );

        $swatches = [];
        preg_match_all(
            '/\.theme-swatch--([a-z]+)\s*\{(.*?)\}/s',
            $this->withoutComments((string)file_get_contents($scss . '/components/_settings.scss')),
            $blocks,
            PREG_SET_ORDER,
        );
        foreach ($blocks as [, $name, $declarations]) {
            $swatches[$name] = $this->colourPair($declarations, '--theme-swatch-primary', '--theme-swatch-secondary', $name);
        }

        ksort($palettes);
        ksort($swatches);

        $this->assertSame(
            array_keys($palettes),
            array_keys($swatches),
            'A palette has no swatch, or a swatch has no palette.',
        );
        $this->assertSame($palettes, $swatches, 'A swatch shows other colours than its palette.');
    }

    private function withoutComments(string $scss): string
    {
        $scss = (string)preg_replace('#/\*.*?\*/#s', '', $scss);

        return (string)preg_replace('#//.*$#m', '', $scss);
    }

    /**
     * The values of two declarations in a block, whitespace normalised and
     * lower-cased, so only a different colour counts as a difference.
     *
     * Each property has to be declared exactly once in the block: a second
     * declaration would make "the value" ambiguous, and picking either one
     * would let the other drift unnoticed.
     *
     * @return array{primary: string, secondary: string}
     */
    private function colourPair(string $declarations, string $primary, string $secondary, string $palette): array
    {
        $pair = [];
        foreach (['primary' => $primary, 'secondary' => $secondary] as $key => $property) {
            preg_match_all(
                '/(?:^|[;{\s])' . preg_quote($property, '/') . '\s*:\s*([^;]+);/',
                $declarations,
                $values,
            );
            $this->assertCount(
                1,
                $values[1],
                sprintf('"%s" is not declared exactly once for the "%s" palette.', $property, $palette),
            );
            $pair[$key] = strtolower((string)preg_replace('/\s+/', '', $values[1][0]));
        }

        return $pair;
    }

    /**
     * A component reaching for a token that was never declared resolves to its
     * fallback, or to nothing at all, and neither produces a build error.
     *
     * This walks the sources rather than the compiled file, because that is
     * where the mistake gets made and where the name is still readable.
     */
    #[Test]
    public function noComponentReferencesAnUndeclaredToken(): void
    {
        $root = dirname(__DIR__, 2) . '/Resources/Private/Scss';
        $sources = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)),
            '/\.scss$/',
        );

        $declared = [];
        $referenced = [];

        foreach ($sources as $file) {
            $contents = (string)file_get_contents((string)$file);

            // Comments are stripped first, and that is not a detail: the file
            // documenting why a breakpoint *cannot* be a custom property spells
            // out "var(--theme-breakpoint-md)" in prose, and scanning it as
            // code reports the token this codebase deliberately does not have.
            $contents = (string)preg_replace('#/\*.*?\*/#s', '', $contents);
            $contents = (string)preg_replace('#//.*$#m', '', $contents);

            preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/m', $contents, $declarations);
            $declared = [...$declared, ...$declarations[1]];

            preg_match_all('/var\(\s*(--[a-z0-9-]+)/', $contents, $references);
            $referenced = [...$referenced, ...$references[1]];
        }

        $this->assertNotEmpty($declared, 'No sources were found - the path is wrong.');

        $undeclared = array_values(array_unique(array_diff($referenced, $declared)));
        sort($undeclared);

        $this->assertSame([], $undeclared, 'These tokens are used but never declared: ' . implode(', ', $undeclared));
    }
}
