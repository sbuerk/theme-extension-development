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
            'appearance switcher' => '.theme-appearance-switcher',
            'badge' => '.theme-badge',
            'breadcrumb' => '.theme-breadcrumb',
            'button' => '.theme-button',
            'card' => '.theme-card',
            'content element' => '.theme-content-element',
            'content menu' => '.theme-content-menu',
            'gallery' => '.theme-gallery',
            'hero' => '.theme-hero',
            'main navigation' => '.theme-nav-main',
            'sub navigation' => '.theme-nav-sub',
            'pagination' => '.theme-pagination',
            'quote' => '.theme-quote',
            'skip link' => '.theme-skip-link',
            'table' => '.theme-table',
            'teaser' => '.theme-teaser',
            'form field' => '.theme-field',
            'form input' => '.theme-input',
            'form validation summary' => '.theme-form-summary',
            'page' => '.theme-page',
            'site header' => '.theme-site-header',
            'site footer' => '.theme-site-footer',
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
     * Every palette can be chosen, and every choice shows the right colour.
     *
     * The switcher's swatches cannot read a palette they are not inside -
     * a custom property only ever holds the value of the selector that
     * currently matches, and CSS cannot ask what it *would* resolve to under a
     * different attribute. So each swatch carries its palette's primary colour
     * as a literal, copied from "abstracts/_palettes.scss".
     *
     * That copy is the only duplicated colour in the stylesheet, and nothing in
     * the language links the two: adding a palette leaves its swatch missing,
     * and the button then renders with no colour at all rather than failing.
     * This test is that link.
     */
    #[Test]
    public function everyPaletteHasASwatchInTheSwitcher(): void
    {
        $scss = dirname(__DIR__, 2) . '/Resources/Private/Scss';

        preg_match_all(
            "/:root\[data-palette='([a-z]+)'\]/",
            (string)file_get_contents($scss . '/abstracts/_palettes.scss'),
            $palettes,
        );
        $this->assertNotEmpty($palettes[1], 'No alternate palette was found at all.');

        // "neutral" is the default and lives in the token file rather than
        // behind a selector, so it is never in the match above.
        $expected = [...$palettes[1], 'neutral'];
        sort($expected);

        $switcher = (string)file_get_contents($scss . '/components/_appearance-switcher.scss');
        preg_match_all('/\.theme-appearance__swatch--([a-z]+)/', $switcher, $swatches);

        $actual = array_values(array_unique($swatches[1]));
        sort($actual);

        $this->assertSame($expected, $actual, 'A palette and its swatch have drifted apart.');
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
