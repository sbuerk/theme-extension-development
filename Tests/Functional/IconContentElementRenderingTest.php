<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders the content elements that show icons of the set.
 *
 * Each field of these elements is a modifier of a component, and a modifier
 * that is written for a value nothing styles - or not written at all - leaves
 * a page that looks finished and ignores what the editor chose. So every value
 * is rendered, and one nothing offers, through both delivery paths: the
 * templates are the same files either way, but the path decides whether the
 * theme renders the page at all.
 *
 * The fixture adds its elements to the page of the other theme elements, so
 * the ids of both sets share one page, as they do in an installation.
 */
final class IconContentElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithThemeContentElements.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/IconContentElements.csv');
    }

    private function render(string $path): string
    {
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + ($path === 'set' ? ['dependencies' => ['sbuerk/theme-extension-development']] : []),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        if ($path === 'set') {
            $this->setUpFrontendRootPage(1, [], [], false);
        } else {
            $this->setUpFrontendRootPage(1, [], [
                'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
            ]);
        }

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'))->getBody();
        // The page is the theme's, or none of the assertions below means anything.
        $this->assertStringContainsString('data-theme-page-layout=', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);

        return $body;
    }

    /**
     * One element by its uid, up to the next element or the end of the column.
     */
    private function element(string $body, int $uid): string
    {
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No element c%d was rendered.', $uid));

        return $matches[0];
    }

    /**
     * @return \Generator<string, array{path: string}>
     */
    public static function deliveryPaths(): \Generator
    {
        yield 'site set' => ['path' => 'set'];
        yield 'static include' => ['path' => 'static'];
    }

    /**
     * @param array<string, array<string, int|string>> $cases
     * @return \Generator<string, array<string, int|string>>
     */
    private static function onBothPaths(array $cases): \Generator
    {
        foreach (['set' => 'site set', 'static' => 'static include'] as $path => $label) {
            foreach ($cases as $name => $case) {
                yield $name . ', ' . $label => ['path' => $path] + $case;
            }
        }
    }

    /**
     * @return \Generator<string, array<string, int|string>>
     */
    public static function textIconModifiers(): \Generator
    {
        yield from self::onBothPaths([
            'start, square, medium' => ['uid' => 1010, 'classes' => 'theme-media-object theme-media-object--start theme-media-object--square theme-media-object--md'],
            'end, circle, extra large' => ['uid' => 1020, 'classes' => 'theme-media-object theme-media-object--end theme-media-object--circle theme-media-object--xl'],
            'top, plain, large' => ['uid' => 1030, 'classes' => 'theme-media-object theme-media-object--top theme-media-object--plain theme-media-object--lg'],
            // A value nothing offers is the default of its axis, not a
            // modifier nothing styles.
            'values nothing offers' => ['uid' => 1040, 'classes' => 'theme-media-object theme-media-object--start theme-media-object--plain theme-media-object--md'],
        ]);
    }

    #[DataProvider('textIconModifiers')]
    #[Test]
    public function aTextIconTakesTheModifierOfEveryAxis(string $path, int $uid, string $classes): void
    {
        $this->assertStringContainsString(
            sprintf('<div class="%s">', $classes),
            $this->element($this->render($path), $uid),
        );
    }

    /**
     * The icon is decoration beside the text; the heading, the rich text and
     * the link are the body, in that order, and the link is resolved rather
     * than printed as a link reference.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTextIconRendersItsIconBesideItsHeadingTextAndLink(string $path): void
    {
        $element = $this->element($this->render($path), 1010);

        $this->assertMatchesRegularExpression(
            '#<span class="theme-media-object__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" [^>]*>.*?</svg></span>#s',
            $element,
        );
        $this->assertMatchesRegularExpression(
            '#<div class="theme-media-object__body">\s*<header class="theme-content-element__header">\s*<h2 class="theme-content-element__heading">Beside an icon</h2>.*?<strong>rich</strong>.*?<a [^>]*class="theme-button theme-button--secondary"[^>]*>\s*Read on#s',
            $element,
        );
        $this->assertStringNotContainsString('t3://', $element);
    }

    /**
     * An element without an icon, and one whose icon the set does not have,
     * render their text alone - no empty tile where the icon would be.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTextIconWithoutAUsableIconRendersNoEmptySlot(string $path): void
    {
        $body = $this->render($path);

        foreach ([1040, 1050] as $uid) {
            $element = $this->element($body, $uid);
            $this->assertStringContainsString('<div class="theme-media-object__body">', $element);
            $this->assertStringNotContainsString('theme-media-object__icon', $element, sprintf('c%d renders an icon slot.', $uid));
        }
    }

    /**
     * @return \Generator<string, array<string, int|string|bool>>
     */
    public static function featureLayouts(): \Generator
    {
        foreach (['set' => 'site set', 'static' => 'static include'] as $path => $label) {
            foreach ([
                'columns, three' => ['uid' => 1110, 'grid' => 'theme-feature-grid theme-feature-grid--columns-3', 'item' => 'theme-feature theme-feature--column', 'intro' => false],
                'hanging, two' => ['uid' => 1120, 'grid' => 'theme-feature-grid theme-feature-grid--columns-2', 'item' => 'theme-feature theme-feature--hanging', 'intro' => false],
                'tiles, four' => ['uid' => 1130, 'grid' => 'theme-feature-grid theme-feature-grid--columns-4', 'item' => 'theme-feature theme-feature--tile', 'intro' => false],
                'with an introduction, two' => ['uid' => 1140, 'grid' => 'theme-feature-grid theme-feature-grid--columns-2', 'item' => 'theme-feature theme-feature--hanging', 'intro' => true],
                // Layout 9 and 0 columns - what v13.4 stores for a record
                // written outside the form - are the defaults.
                'values nothing offers' => ['uid' => 1150, 'grid' => 'theme-feature-grid theme-feature-grid--columns-3', 'item' => 'theme-feature theme-feature--column', 'intro' => false],
            ] as $name => $case) {
                yield $name . ', ' . $label => ['path' => $path] + $case;
            }
        }
    }

    /**
     * "layout" picks the item layout, "tx_theme_columns" the most columns of
     * the grid; the fourth layout sets the heading and the text of the element
     * beside the grid instead of above it.
     */
    #[DataProvider('featureLayouts')]
    #[Test]
    public function featuresTakeTheLayoutAndTheColumnsOfTheirFields(string $path, int $uid, string $grid, string $item, bool $intro): void
    {
        $element = $this->element($this->render($path), $uid);

        $this->assertStringContainsString(sprintf('<div class="%s">', $grid), $element);
        $this->assertStringContainsString(sprintf('<div class="%s">', $item), $element);
        if ($intro) {
            $this->assertMatchesRegularExpression(
                '#<div class="theme-feature-intro">\s*<div class="theme-feature-intro__text">\s*<header class="theme-content-element__header">.*?Features with an introduction.*?The introduction beside the grid\..*?</div>\s*</div>\s*<div class="theme-feature-grid#s',
                $element,
            );
        } else {
            $this->assertStringNotContainsString('theme-feature-intro', $element);
        }
    }

    /**
     * A feature: the icon as decoration, the title one level below the
     * element's heading, the plain text, the resolved link; a feature without
     * an icon has no slot for one.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aFeatureRendersItsIconTitleTextAndLink(string $path): void
    {
        $element = $this->element($this->render($path), 1110);

        $this->assertMatchesRegularExpression(
            '#<div class="theme-feature theme-feature--column">\s*<span class="theme-feature__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" [^>]*>.*?</svg></span>\s*<div class="theme-feature__body">\s*<h3 class="theme-feature__title">Install</h3>\s*<p class="theme-feature__text">Require the package\.</p>\s*<a [^>]*class="theme-feature__link"[^>]*>\s*Read on#s',
            $element,
        );
        $this->assertSame(2, substr_count($element, 'class="theme-feature__title"'));
        $this->assertSame(1, substr_count($element, 'theme-feature__icon'), 'The feature without an icon renders a slot.');
        $this->assertStringNotContainsString('t3://', $element);
    }

    /**
     * A figure is a pair of a description list: what it counts is the term,
     * the figure its description, the icon decoration before the figure and
     * the sentence a second description - only where there is one.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aFigureIsATermAndItsDescriptions(string $path): void
    {
        $element = $this->element($this->render($path), 1210);

        $this->assertStringContainsString('<dl class="theme-stats">', $element);
        $this->assertSame(3, substr_count($element, '<div class="theme-stat">'));
        $this->assertStringContainsString('<dt class="theme-stat__label">Icons shipped</dt>', $element);
        $this->assertMatchesRegularExpression(
            '#<dd class="theme-stat__value"><span class="theme-stat__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" [^>]*>.*?</svg></span>2001</dd>#s',
            $element,
        );
        $this->assertStringContainsString('<dd class="theme-stat__text">The whole solid set.</dd>', $element);
        $this->assertStringContainsString('<dt class="theme-stat__label">Palettes</dt>', $element);
        $this->assertStringContainsString('<dd class="theme-stat__value">5</dd>', $element);
        $this->assertSame(1, substr_count($element, 'theme-stat__text'), 'A figure without a sentence renders an empty description.');
    }

    /**
     * A row without what it counts, or without its figure, is no pair of a
     * description list: it renders nothing, rather than an empty term or an
     * empty description. A figure "0" is a figure all the same.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aFigureWithoutItsTermOrItsFigureIsSkipped(string $path): void
    {
        $element = $this->element($this->render($path), 1210);

        $this->assertStringContainsString('<dt class="theme-stat__label">Open issues</dt>', $element);
        $this->assertStringContainsString('<dd class="theme-stat__value">0</dd>', $element);

        $this->assertStringNotContainsString('<dt class="theme-stat__label"></dt>', $element);
        $this->assertStringNotContainsString('<dd class="theme-stat__value"></dd>', $element);
        $this->assertStringNotContainsString('Nothing says what this counts.', $element);
        $this->assertStringNotContainsString('Counted without a figure', $element);
    }

    /**
     * The steps are an ordered list. A marker is empty for the stylesheet to
     * number, or holds the icon of its step and says so with its modifier.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function stepsAreAnOrderedListWithNumberedOrIconMarkers(string $path): void
    {
        $element = $this->element($this->render($path), 1310);

        $this->assertStringContainsString('<ol class="theme-steps">', $element);
        $this->assertSame(2, substr_count($element, '<li class="theme-steps__step">'));
        $this->assertStringContainsString('<span class="theme-steps__marker" aria-hidden="true"></span>', $element);
        $this->assertMatchesRegularExpression(
            '#<span class="theme-steps__marker theme-steps__marker--icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" [^>]*>.*?</svg></span>#s',
            $element,
        );
        $this->assertStringContainsString('<h3 class="theme-steps__title">Clone</h3>', $element);
        $this->assertStringContainsString('<p class="theme-steps__text">Clone the repository.</p>', $element);
    }
}
