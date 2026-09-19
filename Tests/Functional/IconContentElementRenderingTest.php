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
 * theme renders the page at all. The paths are the theme delivery of the
 * running core (`ThemeSiteTrait`: the site set on v13, the static include on
 * v12) and `include_static_file` as such.
 *
 * The fixture adds its elements to the page of the other theme elements, so
 * the ids of both sets share one page, as they do in an installation.
 */
final class IconContentElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

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
        $delivery = $this->themeDelivery();
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + ($path === 'theme' ? $delivery->siteConfiguration() : []),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        if ($path === 'theme') {
            $this->setUpFrontendRootPage(1, [], $delivery->templateValues(), $delivery->createsSysTemplateRecord());
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
        yield 'theme delivery' => ['path' => 'theme'];
        yield 'static include' => ['path' => 'static'];
    }

    /**
     * @param array<string, array<string, int|string>> $cases
     * @return \Generator<string, array<string, int|string>>
     */
    private static function onBothPaths(array $cases): \Generator
    {
        foreach (['theme' => 'theme delivery', 'static' => 'static include'] as $path => $label) {
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
}
