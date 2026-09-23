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
        //
        // Both consumers match these on a token boundary, so an entry has to
        // name a class the stylesheet really declares and the styleguide
        // really carries - not one that happens to be the beginning of a
        // longer name. Where a component's root element carries no rule of
        // its own, the entry names the element that does.
        foreach ([
            'accordion' => '.theme-accordion',
            'alert' => '.theme-alert',
            'author' => '.theme-author',
            'avatar' => '.theme-avatar',
            'avatar group' => '.theme-avatar-group',
            'badge' => '.theme-badge',
            'breadcrumb' => '.theme-breadcrumb',
            'button' => '.theme-button',
            'card' => '.theme-card',
            'card scroller' => '.theme-card-scroller',
            'card wall' => '.theme-card-grid--wall',
            'carousel' => '.theme-carousel',
            'close button' => '.theme-close',
            'code block' => '.theme-code',
            'content element' => '.theme-content-element',
            'content menu' => '.theme-content-menu',
            'call to action' => '.theme-cta',
            'description list' => '.theme-dl',
            'divided description list' => '.theme-dl--divided',
            'dialog' => '.theme-dialog',
            'divider' => '.theme-divider',
            'dropdown' => '.theme-dropdown',
            'embed' => '.theme-embed',
            'feature' => '.theme-feature',
            'feature grid' => '.theme-feature-grid',
            'feature introduction' => '.theme-feature-intro',
            'figure' => '.theme-figure',
            'file list' => '.theme-file-list',
            'gallery' => '.theme-gallery',
            'hero' => '.theme-hero',
            'icon' => '.theme-icon',
            'language menu' => '.theme-language-menu',
            'lightbox' => '.theme-lightbox',
            'link decoration' => '.theme-link',
            'list' => '.theme-list',
            'list group' => '.theme-list-group',
            'media' => '.theme-media',
            'media object' => '.theme-media-object',
            'meter' => '.theme-meter',
            'main navigation' => '.theme-nav-main',
            'sub navigation' => '.theme-nav-sub',
            // The list, not the ".theme-pagination" nav around it: that nav
            // is a cluster of individual controls rather than a content
            // panel and deliberately carries no box of its own, so
            // "_pagination.scss" declares no rule for it and there is
            // nothing for an assertion to find. The list is the outermost
            // element the component does style.
            'pagination' => '.theme-pagination__list',
            'panel' => '.theme-panel',
            'pricing' => '.theme-pricing',
            'pricing plan' => '.theme-pricing__plan',
            'progress' => '.theme-progress',
            'quote' => '.theme-quote',
            'display settings' => '.theme-settings',
            'segmented control' => '.theme-segmented',
            'palette swatch' => '.theme-swatch',
            'skip link' => '.theme-skip-link',
            'social links' => '.theme-social-links',
            'split tiles' => '.theme-split-tiles',
            'stat' => '.theme-stat',
            'stats' => '.theme-stats',
            'steps' => '.theme-steps',
            'table' => '.theme-table',
            'tabs' => '.theme-tabs',
            'tag' => '.theme-tag',
            'tag list' => '.theme-tag-list',
            'teaser' => '.theme-teaser',
            'display text role' => '.theme-display',
            'display size one' => '.theme-display--1',
            'display size three' => '.theme-display--3',
            'eyebrow text role' => '.theme-eyebrow',
            'lead text role' => '.theme-lead',
            'timeline' => '.theme-timeline',
            'toggletip' => '.theme-toggletip',
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

    /**
     * The selector has to end where the entry ends.
     *
     * A plain substring check is satisfied by any longer class name
     * containing the entry, and most of the entries above have such a longer
     * sibling: `.theme-card` in `.theme-card-scroller`, `.theme-list` in
     * `.theme-list-group`, `.theme-stat` in `.theme-stats`, `.theme-tag` in
     * `.theme-tag-list`. An entry with one cannot detect a rename of the
     * component it names.
     *
     * A trailing `{` would be the obvious guard and does not work here:
     * dart-sass emits grouped selectors, so `.theme-input` occurs three times
     * as `.theme-input,` and never as `.theme-input{` - and the same provider
     * is matched against markup in `StyleguideRenderingTest`, where there is
     * no brace at all. A lookahead for what may not follow works in both.
     *
     * No lookbehind: the leading `.` is the boundary on this side, and
     * requiring a non-word character before it would reject a compound
     * selector such as `.theme-frame.theme-card`.
     */
    #[DataProvider('shippedComponents')]
    #[Test]
    public function everyComponentIsPartOfTheBundle(string $selector): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('/%s(?![\w-])/', preg_quote($selector, '/')),
            $this->stylesheet(),
            sprintf('"%s" is not a selector of the compiled stylesheet.', $selector),
        );
    }

    /**
     * The structures the multi column, cover and band page layouts are built
     * from, asserted here and deliberately **not** in `shippedComponents()`.
     *
     * That provider is shared with
     * `StyleguideRenderingTest::everyComponentOfTheLibraryIsShownOnTheStyleguide()`,
     * which is the point of it: a component in the bundle that no specimen
     * shows is a component nobody looks at. These are not components. They
     * are page structure, written by `Templates/Page/*.html` and driven by the
     * backend layout of the page, and the styleguide page renders through the
     * `styleguide` layout, which has none of them. Putting them in that list
     * would demand a specimen that fakes a page layout inside a page.
     *
     * What they still need is the guarantee the rest of that provider gives:
     * that the rule reached the compiled bundle at all. Dropping a `@use` from
     * `theme.scss` is otherwise invisible until someone opens a page on one of
     * those layouts. `Tests/Functional/BackendLayoutRenderingTest` covers the
     * other side, that the markup carries these class names.
     *
     * The opening brace is part of every string asserted, and it is not
     * decoration: these names nest - `.theme-page__column` is a prefix of
     * `.theme-page__columns`, which is a prefix of `.theme-page__columns--article` -
     * so a bare substring check passes for a selector that was renamed,
     * misspelled or never written, as long as a longer one containing it
     * exists. It is the trap `everyComponentIsPartOfTheBundle()` keeps out
     * with a token boundary; the brace is available here, because each of
     * these is a rule of its own rather than one of a grouped selector, and
     * it is the stricter of the two - it asserts a rule and not merely an
     * occurrence.
     */
    #[Test]
    public function thePageLayoutStructuresArePartOfTheBundle(): void
    {
        $css = $this->stylesheet();

        foreach ([
            '.theme-page__columns{',
            '.theme-page__columns--halves{',
            '.theme-page__columns--wide-start{',
            '.theme-page__columns--thirds{',
            '.theme-page__columns--article{',
            '.theme-page__column{',
            '.theme-page__column--measure{',
            '.theme-page__cover{',
            '.theme-page__bands{',
            '.theme-page__band{',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css, sprintf('"%s" is not a rule of the compiled stylesheet.', $selector));
        }
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
     * The CType chip straddles the top edge of an element. "--frame-none"
     * drops the inner padding, and without a clearance the lower half of the
     * chip lay over the first line of the content. Where there is no chip - the
     * global switch, "--plain" - there is nothing to clear, and the element is
     * flush with its box again.
     */
    #[Test]
    public function anElementWithoutPaddingKeepsItsContentClearOfTheChip(): void
    {
        $css = (string)preg_replace('/\s+/', '', $this->stylesheet());

        $this->assertStringContainsString(
            '.theme-content-element--frame-none>.theme-content-element__inner{padding-block-start:var(--theme-content-element-chip-clearance)}',
            $css,
            'An element without inner padding has to start its content below the chip.',
        );
        foreach (['[data-theme-content-outline=off].theme-content-element{', '.theme-content-element--plain{'] as $rule) {
            preg_match('/' . preg_quote($rule, '/') . '([^}]*)\}/', $css, $declarations);
            $this->assertArrayHasKey(1, $declarations, sprintf('The compiled stylesheet has no rule "%s".', $rule));
            $this->assertStringContainsString(
                '--theme-content-element-chip-clearance:0',
                $declarations[1],
                sprintf('"%s" removes the chip, so it has to remove the clearance as well.', $rule),
            );
        }
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
     * The carousel's previous and next buttons are not offered while nothing
     * can operate them.
     *
     * The track scrolls and the indicators move the reader with no script at
     * all - the two buttons are the one part that needs one, and a button
     * does nothing without it. They are rendered always and hidden until
     * "theme.js" has bound that carousel and set "data-theme-carousel-bound"
     * on it.
     *
     * Not gated on the root's "data-js", and that is the assertion worth
     * having: that marker only says the inline head script ran, so a page
     * whose "theme.js" failed to load would show two buttons that do nothing.
     * The same mistake the tabs are held to above.
     */
    #[Test]
    public function carouselControlsAreHiddenUntilTheScriptHasBoundThem(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString(
            '.theme-carousel__control{display:none',
            $css,
            'The controls must not be offered until the script has bound the carousel.',
        );
        $this->assertStringContainsString(
            '.theme-carousel[data-theme-carousel-bound] .theme-carousel__control{display:inline-flex}',
            $css,
        );
        $this->assertStringNotContainsString(
            '[data-js] .theme-carousel',
            $css,
            'The carousel must not be gated on the root marker: it does not say that "theme.js" ran.',
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
     * The embed offers no play button until the script has bound it.
     *
     * Gated on the embed's own `data-theme-embed-bound`, which only "theme.js"
     * sets, and not on the root's `data-js` - the distinction the tabs are
     * gated on for the same reason: `data-js` says the inline head script ran,
     * not that "theme.js" did, and a page whose "theme.js" failed to load
     * would show a play button that does nothing over a video nothing else
     * leads to.
     *
     * The link to the source is **not** hidden with it: it is the way to the
     * video when there is no script, which is why the second assertion is
     * there at all. See "components/_embed.scss".
     */
    #[Test]
    public function anEmbedOffersNoPlayButtonUntilTheScriptHasBoundIt(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString(
            '.theme-embed__button{display:none;',
            $css,
            'A play button without the script is a button that does nothing.',
        );
        $this->assertStringContainsString(
            '.theme-embed[data-theme-embed-bound] .theme-embed__button{display:flex}',
            $css,
        );
        $this->assertStringNotContainsString(
            '[data-js] .theme-embed',
            $css,
            'The embed must not be gated on the root marker: it does not say that "theme.js" ran.',
        );
        $this->assertStringNotContainsString(
            '.theme-embed__source{display:none',
            $css,
            'The link to the source is what a page without a script is left with; it may not be hidden.',
        );
    }

    /**
     * The lightbox opener is the gallery's zoom link, and it is deliberately
     * not a `data-theme-dialog-open` opener: that attribute is hidden without
     * the script marker, and this link still enlarges the image without one.
     * A rule hiding it would take the fallback away with the enhancement.
     */
    #[Test]
    public function theLightboxOpenerIsNotHiddenWithoutTheScriptMarker(): void
    {
        $this->assertStringNotContainsString(
            ':root:not([data-js]) [data-theme-lightbox]',
            $this->stylesheet(),
            'The zoom link is the fallback, not an enhancement - hiding it removes the only way to the image.',
        );
    }

    /**
     * Text is aligned to the start and the end of a line, never to its left
     * and right.
     *
     * In a right-to-left page the start of a line is its right edge. Every
     * spacing and border of the library is written in logical properties, and a
     * physical `text-align` is the one that still slips in - the element
     * baseline carried two, on the caption and on the table cells, which
     * aligned the text of a right-to-left table against the wrong edge while
     * every left-to-right check looked right.
     */
    #[Test]
    public function textIsAlignedToTheStartOrTheEndOfTheLine(): void
    {
        preg_match_all('/text-align:(left|right)\b/', $this->stylesheet(), $physical);

        $this->assertSame([], $physical[0], 'Use "start" or "end" - a physical alignment does not follow the direction of the text.');
    }

    /**
     * Every table class an editor can pick has a modifier in the bundle.
     *
     * `Templates/ContentElements/Table.html` turns the `table_class` of the
     * element into `.theme-table--<value>` without a list, so a value that
     * has no rule renders a table that looks exactly like the default one -
     * the editor picked "striped" and nothing tells anyone it did nothing.
     *
     * The values are the core's two TCA items, which are the same on v12.4
     * and v13.4 and cannot be read here without TYPO3, and the `addItems` of
     * the theme's page TSconfig, read from the file.
     * `Tests/Functional/TableClassRenderingTest` holds the same list against
     * the TCA and the TSconfig a running instance actually loads.
     */
    #[Test]
    public function everyTableClassAnEditorCanPickIsStyled(): void
    {
        $tsConfig = (string)file_get_contents(
            dirname(__DIR__, 2) . '/Configuration/PageTsConfig/TCEFORM/TableClass.tsconfig',
        );
        preg_match('/addItems\s*\{(.*?)\}/s', $tsConfig, $block);
        $this->assertArrayHasKey(1, $block, 'The "addItems" block of the table class TSconfig was not found.');
        preg_match_all('/^\s*([a-z0-9-]+)\s*=/m', $block[1], $added);
        $this->assertNotEmpty($added[1], 'The table class TSconfig adds no item.');

        $css = $this->stylesheet();
        $unstyled = [];
        foreach (['striped', 'bordered', ...$added[1]] as $value) {
            // Anchored at the end: "striped" is a prefix of "striped-columns",
            // and a plain substring search would find the one in the other.
            if (preg_match('/\.theme-table--' . preg_quote($value, '/') . '(?![\w-])/', $css) !== 1) {
                $unstyled[] = $value;
            }
        }

        $this->assertSame([], $unstyled, 'These table classes can be picked but match no rule: ' . implode(', ', $unstyled));
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
        // "Partials/ContentElement/ItemHeading.html" puts these one level
        // below the element's heading, h2 to h6.
        yield 'feature title' => ['selector' => '.theme-feature__title'];
        yield 'step title' => ['selector' => '.theme-steps__title'];
        // "Templates/ContentElements/ThemeCta.html" puts it on h1 to h5.
        yield 'call to action title' => ['selector' => '.theme-cta__title'];
        yield 'card title' => ['selector' => '.theme-card__title'];
        yield 'timeline title' => ['selector' => '.theme-timeline__title'];
        yield 'list group title' => ['selector' => '.theme-list-group__title'];
        yield 'pricing plan name' => ['selector' => '.theme-pricing__name'];
    }

    /**
     * A row of the list group is the target of its link, so the link gives up
     * the ring around its title and the row carries it. It is drawn on the
     * link's own `::after`, the hit area covering the row, and not through
     * `:has()` on the row. That began as a browser floor decision - the floor
     * was Firefox 120 and `:has()` arrived in 121 - and the floor has since
     * moved to 125, so the original reason no longer holds. The rule is kept
     * because it depends on nothing but the link having `:focus-visible`,
     * which is the simpler dependency for an indicator WCAG 2.4.7 requires.
     */
    #[Test]
    public function aListGroupRowShowsItsFocusRingWithoutHas(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/\.theme-list-group__link:focus-visible::after\{[^}]*outline:var\(--theme-border-width-strong\) solid var\(--theme-color-primary\)[^}]*box-shadow:var\(--theme-focus-ring\)/',
            $css,
        );
        $this->assertStringNotContainsString(':has(.theme-list-group__link:focus-visible)', $css);
    }

    /**
     * A social link whose whole visible content is a platform logo still has
     * an accessible name: the platform's name is in the markup and only
     * hidden visually. That hiding is conditional on an icon actually having
     * been rendered - the logo comes from a record and is rendered
     * "optional", so a name a later Font Awesome version dropped renders
     * nothing, and a modifier class written by the template would then hide
     * the label of an entry that has nothing left to show.
     *
     * Asserted on the compiled stylesheet rather than on the SCSS source:
     * what reaches a page is the compiled rule, and a `:has()` lost in a
     * refactoring of the source would still leave a plausible looking SCSS
     * file behind.
     */
    #[Test]
    public function aSocialLinkHidesItsLabelOnlyWhereALogoWasRendered(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/\.theme-social-links__link:has\(\.theme-icon\) \.theme-social-links__label[^{]*\{[^}]*clip-path:inset\(50%\)/',
            $css,
            'The label of a social link is not hidden inside ":has(.theme-icon)".',
        );
        $this->assertStringNotContainsString(
            '.theme-social-links__label{position:absolute',
            $css,
            'The label of a social link is hidden unconditionally, so an entry without a logo has nothing to show.',
        );
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

    /**
     * @return \Generator<string, array{file: string, token: string}>
     */
    public static function controlBoundaryTokens(): \Generator
    {
        // The component tokens that draw the resting edge of a control - the
        // one visual cue that identifies an empty field or an unset switch.
        yield 'text input, textarea and select' => ['file' => 'forms/_controls.scss', 'token' => '--theme-input-border-color'];
        yield 'input group addon' => ['file' => 'forms/_input-group.scss', 'token' => '--theme-input-group-addon-border-color'];
        yield 'switch track' => ['file' => 'forms/_controls.scss', 'token' => '--theme-switch-track-border-color'];
        // Not controls, but graphical objects under the same criterion: the
        // edge of the track is the only thing that shows the whole range.
        yield 'progress track' => ['file' => 'components/_progress.scss', 'token' => '--theme-progress-track-border-color'];
        yield 'meter track' => ['file' => 'components/_meter.scss', 'token' => '--theme-meter-track-border-color'];
    }

    /**
     * A control boundary uses `--theme-color-border-strong`; the decorative
     * `--theme-color-border` reaches about 1.4:1, and WCAG 1.4.11 needs 3:1
     * for the edge that identifies a control. `StylesheetTest` holds the
     * token itself to 3:1 - this holds the controls to the token.
     *
     * The fallback literal is asserted as well: it is what a control shows
     * where the token file is absent, and it has to be the light value of the
     * same token rather than a leftover of the decorative one.
     *
     * The default is the first declaration in the file - the token layer
     * opens the component's block. It is not the only one: the switch
     * re-points its track border to `CanvasText` under `forced-colors`, which
     * is a system colour and no default.
     */
    #[DataProvider('controlBoundaryTokens')]
    #[Test]
    public function aControlDrawsItsBoundaryInTheStrongBorderColour(string $file, string $token): void
    {
        $scss = dirname(__DIR__, 2) . '/Resources/Private/Scss';

        preg_match(
            '/--theme-color-border-strong\s*:\s*light-dark\(\s*(#[0-9a-f]{6})\s*,/i',
            $this->withoutComments((string)file_get_contents($scss . '/abstracts/_tokens.scss')),
            $strong,
        );
        $this->assertArrayHasKey(1, $strong, '"--theme-color-border-strong" was not found in the token file.');

        preg_match_all(
            '/(?:^|[;{\s])' . preg_quote($token, '/') . '\s*:\s*([^;]+);/',
            $this->withoutComments((string)file_get_contents($scss . '/' . $file)),
            $values,
        );
        $this->assertNotEmpty($values[1], sprintf('"%s" is not declared in "%s".', $token, $file));

        $this->assertSame(
            sprintf('var(--theme-color-border-strong,%s)', strtolower($strong[1])),
            strtolower((string)preg_replace('/\s+/', '', $values[1][0])),
            sprintf('"%s" has to default to "--theme-color-border-strong" - the decorative border is not a control boundary.', $token),
        );
    }

    /**
     * Hover has to stay visible once the resting border is the strong one:
     * a hover colour equal to the default is no hover at all.
     */
    #[Test]
    public function aHoveredTextInputChangesItsBorder(): void
    {
        $scss = $this->withoutComments(
            (string)file_get_contents(dirname(__DIR__, 2) . '/Resources/Private/Scss/forms/_controls.scss'),
        );

        $declared = [];
        foreach (['--theme-input-border-color', '--theme-input-border-color-hover'] as $token) {
            preg_match_all('/(?:^|[;{\s])' . preg_quote($token, '/') . '\s*:\s*([^;]+);/', $scss, $values);
            $this->assertCount(1, $values[1], sprintf('"%s" is not declared exactly once.', $token));
            $declared[$token] = (string)preg_replace('/\s+/', '', $values[1][0]);
        }

        $this->assertNotSame($declared['--theme-input-border-color'], $declared['--theme-input-border-color-hover']);
        // A hover that falls back to the decorative border would differ from
        // the resting colour and still drop the boundary below 3:1 again.
        $this->assertDoesNotMatchRegularExpression(
            '/var\(--theme-color-border[,)]/',
            $declared['--theme-input-border-color-hover'],
            'The hover border of a text input must not be the decorative "--theme-color-border".',
        );
    }

    /**
     * @return \Generator<string, array{selector: string}>
     */
    public static function blockComponents(): \Generator
    {
        // The blocks that sit in a column of content between other blocks.
        // The first ones set the rhythm; the button group and the table
        // wrapper ended without a margin, and the component after them
        // touched them.
        yield 'list' => ['selector' => '.theme-list'];
        yield 'description list' => ['selector' => '.theme-dl'];
        yield 'code block' => ['selector' => '.theme-code'];
        yield 'figure' => ['selector' => '.theme-figure'];
        yield 'button group' => ['selector' => '.theme-button-group'];
        yield 'table wrapper' => ['selector' => '.theme-table-wrapper'];
    }

    /**
     * Every block ends on the same bottom margin, so whatever follows keeps
     * the same distance from it. Read off the base rule of the compiled
     * stylesheet - the rule whose selector is the class alone.
     */
    #[DataProvider('blockComponents')]
    #[Test]
    public function aBlockKeepsItsDistanceToWhatFollows(string $selector): void
    {
        preg_match(
            '/(?:^|[{};])' . preg_quote($selector, '/') . '\{([^}]*)\}/',
            $this->stylesheet(),
            $rule,
        );
        $this->assertArrayHasKey(1, $rule, sprintf('The compiled stylesheet has no base rule for "%s".', $selector));

        $declarations = (string)preg_replace('/\s+/', '', $rule[1]);
        $this->assertStringContainsString(
            'margin:00var(--theme-space-4,1.25rem)',
            $declarations,
            sprintf('"%s" has to end on the bottom margin of every other block.', $selector),
        );
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
     * The array is written out rather than filled in a loop: PHPStan 1.x, the
     * line the v12 dependency set resolves, infers a loop-filled array as
     * `non-empty-array<'primary'|'secondary', string>` and not as the shape.
     *
     * @return array{primary: string, secondary: string}
     */
    private function colourPair(string $declarations, string $primary, string $secondary, string $palette): array
    {
        return [
            'primary' => $this->declaredValue($declarations, $primary, $palette),
            'secondary' => $this->declaredValue($declarations, $secondary, $palette),
        ];
    }

    private function declaredValue(string $declarations, string $property, string $palette): string
    {
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

        return strtolower((string)preg_replace('/\s+/', '', $values[1][0]));
    }

    /**
     * @return \Generator<string, array{component: string}>
     */
    public static function floatingComponents(): \Generator
    {
        yield 'figure' => ['component' => 'theme-figure'];
        yield 'gallery' => ['component' => 'theme-gallery'];
    }

    /**
     * Whatever holds a floated figure or gallery becomes a block formatting
     * context, so the float cannot hang out of it. For a list item that rule
     * alone would replace `display: list-item` and silently drop the marker -
     * the item still renders, one bullet short. The list item therefore gets
     * `flow-root list-item`, which keeps both.
     */
    #[DataProvider('floatingComponents')]
    #[Test]
    public function aListItemHoldingAFloatKeepsItsMarker(string $component): void
    {
        $this->assertStringContainsString(
            sprintf(':where(li:has(>.%1$s--float-start,>.%1$s--float-end)){display:flow-root list-item}', $component),
            $this->stylesheet(),
            'A list item containing a float must stay a list item.',
        );
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
