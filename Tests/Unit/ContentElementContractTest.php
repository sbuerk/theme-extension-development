<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Every modifier the content element frame can write has a rule.
 *
 * `Layouts/ContentElement.html` and `Partials/ContentElement/Header.html`
 * turn the appearance fields of a record into classes, one `f:variable` per
 * value, `Templates/ContentElements/Bullets.html` its `layout` the same
 * way, `ThemeTextIcon.html` the three axes of its icon, `ThemeFeatures.html`
 * its layout and columns and `ThemeSteps.html` the icon marker. A class
 * written there and matched by no rule is an editor choice that silently
 * does nothing - the defect the component contract exists to
 * rule out, and the one the core RTE preset has, whose `text-center` this
 * theme does not style. `ContentElementAppearanceRenderingTest` holds which
 * value writes which class; this holds every one of those classes to the
 * compiled stylesheet.
 *
 * The two contracts DESIGN.md computed contrast for are asserted as well:
 * the inverse band turns the scheme of its subtree in all three ways the page
 * can get one, and the accent band mixes the tint the contrast table was
 * computed for.
 */
final class ContentElementContractTest extends UnitTestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function stylesheet(): string
    {
        $file = self::root() . '/Resources/Public/Css/theme.css';
        $this->assertFileExists($file, 'The compiled stylesheet is committed - run "runTests.sh -s buildCss".');

        return (string)file_get_contents($file);
    }

    /**
     * @return \Generator<string, array{class: string}>
     */
    public static function writtenClasses(): \Generator
    {
        $seen = [];
        foreach ([
            'Resources/Private/Layouts/ContentElement.html',
            'Resources/Private/Partials/ContentElement/Header.html',
            'Resources/Private/Templates/ContentElements/Bullets.html',
            'Resources/Private/Templates/ContentElements/ThemeTextIcon.html',
            'Resources/Private/Templates/ContentElements/ThemeFeatures.html',
            'Resources/Private/Templates/ContentElements/ThemeSteps.html',
            'Resources/Private/Templates/ContentElements/Text.html',
            'Resources/Private/Templates/ContentElements/Uploads.html',
            'Resources/Private/Partials/ContentElement/Hero.html',
            'Resources/Private/Templates/ContentElements/ThemeTestimonial.html',
            'Resources/Private/Templates/ContentElements/ThemeCta.html',
            'Resources/Private/Templates/ContentElements/ThemeCardGroup.html',
            'Resources/Private/Templates/ContentElements/ThemeCarousel.html',
            'Resources/Private/Templates/ContentElements/ThemeSplitTiles.html',
            'Resources/Private/Templates/ContentElements/ThemeExternalMedia.html',
        ] as $template) {
            $source = (string)file_get_contents(self::root() . '/' . $template);
            preg_match_all('#<f:variable name="\w+" value="([^"]*)"\s*/>#', $source, $values);
            foreach ($values[1] as $value) {
                foreach (preg_split('/\s+/', trim($value)) ?: [] as $class) {
                    // A class written in two cases - the feature layouts 1
                    // and 3 share one, and the hero writes "--centred" for a
                    // screenshot without an image as well - is one data set,
                    // as a data set name is unique.
                    if ($class !== '' && !isset($seen[$class])) {
                        $seen[$class] = true;
                        yield $class => ['class' => $class];
                    }
                }
            }
        }
    }

    #[Test]
    public function theTemplatesWriteTheModifiersOfEveryAppearanceField(): void
    {
        $classes = array_keys(iterator_to_array(self::writtenClasses()));

        // One case per modifier the templates above write: the appearance
        // fields, the lists, the text and icon element, the features, the
        // steps, the card group with its wall, the carousel's caption
        // positions, the split tiles with their tones, the two ratios of the
        // external media element and the layouts and variants - fewer means a
        // value lost its case, and the test below would pass on an empty
        // list.
        $this->assertCount(71, $classes, implode(', ', $classes));
    }

    #[DataProvider('writtenClasses')]
    #[Test]
    public function aModifierTheTemplatesWriteIsStyled(string $class): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('/\.%s(?![\w-])/', preg_quote($class, '/')),
            $this->stylesheet(),
            sprintf('"%s" is written by a template, and no rule of the stylesheet matches it.', $class),
        );
    }

    /**
     * @return \Generator<string, array{needle: string}>
     */
    public static function inverseSchemeRules(): \Generator
    {
        // Compressed: no space after "@media", no quotes around the value of an
        // attribute selector.
        yield 'dark on the default light page' => ['needle' => '/\.theme-content-element--frame-inverse\{[^}]*color-scheme:dark[;}]/'];
        yield 'light on a page forced to dark' => ['needle' => '/:root\[data-theme=dark\] \.theme-content-element--frame-inverse\{color-scheme:light\}/'];
        yield 'light on a page the system makes dark' => ['needle' => '/@media\(prefers-color-scheme: dark\)\{:root:not\(\[data-theme=light\]\) \.theme-content-element--frame-inverse\{color-scheme:light\}\}/'];
        // The chip keeps the page's scheme: the same three cases, inverted.
        yield 'the chip stays light on the default light page' => ['needle' => '/\.theme-content-element--frame-inverse::before\{color-scheme:light\}/'];
        yield 'the chip stays dark on a page forced to dark' => ['needle' => '/:root\[data-theme=dark\] \.theme-content-element--frame-inverse::before\{color-scheme:dark\}/'];
        yield 'the chip stays dark on a page the system makes dark' => ['needle' => '/@media\(prefers-color-scheme: dark\)\{:root:not\(\[data-theme=light\]\) \.theme-content-element--frame-inverse::before\{color-scheme:dark\}\}/'];
        // The inverse tone of the call to action turns its scheme the same
        // three ways, which is what lets it reuse the band's contrast table.
        yield 'the inverse call to action is dark on the default light page' => ['needle' => '/\.theme-cta--inverse\{[^}]*color-scheme:dark[;}]/'];
        yield 'the inverse call to action is light on a page forced to dark' => ['needle' => '/:root\[data-theme=dark\] \.theme-cta--inverse\{color-scheme:light\}/'];
        yield 'the inverse call to action is light on a page the system makes dark' => ['needle' => '/@media\(prefers-color-scheme: dark\)\{:root:not\(\[data-theme=light\]\) \.theme-cta--inverse\{color-scheme:light\}\}/'];
    }

    /**
     * The inverse band relies on no token of its own: every colour inside it
     * turns because the scheme of the band does. Losing one of the three rules
     * leaves the band in the page's own scheme for one way of getting it -
     * a dark band on a dark page, which looks like no band at all.
     */
    #[DataProvider('inverseSchemeRules')]
    #[Test]
    public function theInverseBandTurnsTheSchemeOfItsSubtree(string $needle): void
    {
        $this->assertMatchesRegularExpression($needle, $this->stylesheet());
    }

    /**
     * DESIGN.md computed the contrast of everything on the accent band for
     * this mix. A different percentage is a different table.
     */
    #[Test]
    public function theAccentBandIsTheTintTheContrastTableWasComputedFor(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.theme-content-element--frame-accent\{[^}]*color-mix\(in oklab, var\(--theme-color-primary, #0b57d0\) 5%, var\(--theme-color-background, #ffffff\)\)/',
            $this->stylesheet(),
        );
    }

    /**
     * The accent tone of the call to action is the accent band's tint, so the
     * band's contrast table holds for it. A different mix is a table nobody
     * computed.
     */
    #[Test]
    public function theAccentCallToActionIsTheTintOfTheAccentBand(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.theme-cta--accent\{--theme-cta-background: ?color-mix\(in oklab, var\(--theme-color-primary, #0b57d0\) 5%, var\(--theme-color-background, #ffffff\)\)/',
            $this->stylesheet(),
        );
    }
}
