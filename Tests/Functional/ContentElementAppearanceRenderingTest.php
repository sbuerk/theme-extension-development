<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The appearance fields of a content element reach the page.
 *
 * `frame_class`, `space_before_class` and `space_after_class` become modifiers
 * of `.theme-content-element` (`Layouts/ContentElement.html`),
 * `header_position` and `tx_theme_header_style` modifiers of its header
 * (`Partials/ContentElement/Header.html`). Before, an editor could set every
 * one of them and nothing changed.
 *
 * Every value is rendered through both delivery paths: the layout and the
 * partial are the same files either way, but the path decides whether the
 * theme renders the page at all, and a field that reaches one of them only is
 * the failure `LegacyDeliveryTest` exists for.
 *
 * The other half of the rule is that a value the theme does not style renders
 * no modifier: a class written for any value is a class no rule matches.
 * `ContentElementContractTest` holds the modifiers that *are* written to the
 * compiled stylesheet.
 */
final class ContentElementAppearanceRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithAppearanceFields.csv');
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

        return $body;
    }

    /**
     * The classes of the wrapper of one element.
     *
     * @return list<string>
     */
    private function wrapperClasses(string $body, int $uid): array
    {
        $this->assertSame(
            1,
            preg_match(sprintf('#<div id="c%d"\s+class="([^"]*)"#', $uid), $body, $matches),
            sprintf('No element c%d was rendered.', $uid),
        );

        return preg_split('/\s+/', trim($matches[1])) ?: [];
    }

    /**
     * The classes of the header and of the heading of one element.
     *
     * @return array{header: list<string>, heading: list<string>}
     */
    private function headerClasses(string $body, int $uid): array
    {
        $this->assertSame(
            1,
            preg_match(
                sprintf('#<div id="c%d"\s[^>]*>.*?<header class="([^"]*)">\s*<h\d class="([^"]*)">#s', $uid),
                $body,
                $matches,
            ),
            sprintf('Element c%d rendered no header.', $uid),
        );

        return [
            'header' => preg_split('/\s+/', trim($matches[1])) ?: [],
            'heading' => preg_split('/\s+/', trim($matches[2])) ?: [],
        ];
    }

    /**
     * The modifiers of a prefix among the given classes.
     *
     * @param list<string> $classes
     * @return list<string>
     */
    private static function modifiers(array $classes, string $prefix): array
    {
        return array_values(array_filter($classes, static fn(string $class): bool => str_starts_with($class, $prefix)));
    }

    /**
     * @param array<string, array{uid: int, expected: list<string>}> $cases
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
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
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
     */
    public static function frames(): \Generator
    {
        yield from self::onBothPaths([
            'surface' => ['uid' => 10, 'expected' => ['theme-content-element--frame-surface']],
            'raised' => ['uid' => 20, 'expected' => ['theme-content-element--frame-raised']],
            'accent' => ['uid' => 30, 'expected' => ['theme-content-element--frame-accent']],
            'inverse' => ['uid' => 40, 'expected' => ['theme-content-element--frame-inverse']],
            'none' => ['uid' => 50, 'expected' => ['theme-content-element--frame-none']],
            'default' => ['uid' => 60, 'expected' => []],
            'a value the page TSconfig removed' => ['uid' => 70, 'expected' => []],
        ]);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('frames')]
    #[Test]
    public function aFrameRendersItsBandModifier(string $path, int $uid, array $expected): void
    {
        $classes = $this->wrapperClasses($this->render($path), $uid);

        $this->assertSame(['theme-content-element', 'theme-content-element--text'], array_slice($classes, 0, 2));
        $this->assertSame($expected, self::modifiers($classes, 'theme-content-element--frame-'));
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
     */
    public static function spaces(): \Generator
    {
        yield from self::onBothPaths([
            'before extra small' => ['uid' => 100, 'expected' => ['theme-content-element--space-before-extra-small']],
            'before small' => ['uid' => 110, 'expected' => ['theme-content-element--space-before-small']],
            'before medium' => ['uid' => 120, 'expected' => ['theme-content-element--space-before-medium']],
            'before large' => ['uid' => 130, 'expected' => ['theme-content-element--space-before-large']],
            'before extra large' => ['uid' => 140, 'expected' => ['theme-content-element--space-before-extra-large']],
            'after extra small' => ['uid' => 150, 'expected' => ['theme-content-element--space-after-extra-small']],
            'after small' => ['uid' => 160, 'expected' => ['theme-content-element--space-after-small']],
            'after medium' => ['uid' => 170, 'expected' => ['theme-content-element--space-after-medium']],
            'after large' => ['uid' => 180, 'expected' => ['theme-content-element--space-after-large']],
            'after extra large' => ['uid' => 190, 'expected' => ['theme-content-element--space-after-extra-large']],
            'an unknown value' => ['uid' => 195, 'expected' => []],
            'none at all' => ['uid' => 60, 'expected' => []],
        ]);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('spaces')]
    #[Test]
    public function aSpacingRendersItsModifier(string $path, int $uid, array $expected): void
    {
        $this->assertSame(
            $expected,
            self::modifiers($this->wrapperClasses($this->render($path), $uid), 'theme-content-element--space-'),
        );
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
     */
    public static function headerPositions(): \Generator
    {
        yield from self::onBothPaths([
            'center' => ['uid' => 200, 'expected' => ['theme-content-element__header--center']],
            'right is the end' => ['uid' => 210, 'expected' => ['theme-content-element__header--end']],
            'left is the start' => ['uid' => 220, 'expected' => ['theme-content-element__header--start']],
            'default' => ['uid' => 230, 'expected' => []],
        ]);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('headerPositions')]
    #[Test]
    public function aHeaderPositionRendersItsModifier(string $path, int $uid, array $expected): void
    {
        $classes = $this->headerClasses($this->render($path), $uid)['header'];

        $this->assertSame('theme-content-element__header', $classes[0]);
        $this->assertSame($expected, self::modifiers($classes, 'theme-content-element__header--'));
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
     */
    public static function headerStyles(): \Generator
    {
        yield from self::onBothPaths([
            'display' => ['uid' => 300, 'expected' => ['theme-display']],
            'like h1' => ['uid' => 310, 'expected' => ['theme-content-element__heading--h1']],
            'like h2' => ['uid' => 320, 'expected' => ['theme-content-element__heading--h2']],
            'like h3' => ['uid' => 330, 'expected' => ['theme-content-element__heading--h3']],
            'like h4' => ['uid' => 340, 'expected' => ['theme-content-element__heading--h4']],
            'like h5' => ['uid' => 350, 'expected' => ['theme-content-element__heading--h5']],
            'an unknown value' => ['uid' => 360, 'expected' => []],
            'none at all' => ['uid' => 230, 'expected' => []],
        ]);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('headerStyles')]
    #[Test]
    public function aHeaderStyleRendersItsLookOnTheHeading(string $path, int $uid, array $expected): void
    {
        $classes = $this->headerClasses($this->render($path), $uid)['heading'];

        $this->assertSame('theme-content-element__heading', $classes[0]);
        $this->assertSame($expected, array_slice($classes, 1));
    }

    /**
     * The look is not the level: an element with `header_layout` 3 set in the
     * look of heading 1 is still an `h3` in the outline.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aHeaderStyleLeavesTheHeadingLevelAlone(string $path): void
    {
        $this->assertMatchesRegularExpression(
            '#<div id="c310"\s[^>]*>.*?<h3 class="theme-content-element__heading theme-content-element__heading--h1">Like h1</h3>#s',
            $this->render($path),
        );
    }

    /**
     * @return \Generator<string, array{path: string}>
     */
    public static function deliveryPaths(): \Generator
    {
        yield 'site set' => ['path' => 'set'];
        yield 'static include' => ['path' => 'static'];
    }
}
