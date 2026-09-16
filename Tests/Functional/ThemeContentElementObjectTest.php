<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The theme's own content elements do not depend on `lib.contentElement`.
 *
 * `fluid_styled_content` starts its `lib.contentElement` with
 * `lib.contentElement >` on v13.4 and v14.3, so an installation that loads it
 * after the theme loses every root path the theme put there. The `theme_*`
 * elements are therefore built on `lib.themeContentElement`, and
 * this renders them with `lib.contentElement` cleared after the theme - the
 * line of `Fixtures/TypoScript/ClearContentElement.typoscript`, which is the
 * first line of fluid_styled_content's own definition - on both delivery
 * paths.
 *
 * The page is compared as a whole, cleared against not cleared: an element
 * that still reached `lib.contentElement` somewhere would render differently
 * or not at all. The comparison proves nothing unless the clearing takes
 * effect, so a core element on a second page is rendered with the same
 * TypoScript and has to lose its rendering.
 */
final class ThemeContentElementObjectTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const CLEAR_CONTENT_ELEMENT = 'EXT:theme_extension_development/Tests/Functional/Fixtures/TypoScript/ClearContentElement.typoscript';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithThemeContentElements.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/IconContentElements.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/CoreHeaderSubpage.csv');
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
     * The site of the given delivery path, with `lib.contentElement` cleared
     * after the theme or not.
     *
     * The set path gets a `sys_template` record of its own for the clearing
     * line, without a `clear` flag: a record with one starts the TypoScript
     * from nothing, the set included (`IncludeTreeAstBuilderVisitor`), and
     * `setUpFrontendRootPage()` always writes one. The static path includes
     * the static template the way an installation does, as
     * `include_static_file`, followed by the clearing line in the same record.
     */
    private function setUpSite(string $path, bool $clearContentElement): void
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

        $setup = $clearContentElement ? '@import \'' . self::CLEAR_CONTENT_ELEMENT . '\'' . LF : '';
        if ($path === 'set') {
            $this->setUpFrontendRootPage(1, [], [], false);
            if ($clearContentElement) {
                $this->getConnectionPool()->getConnectionForTable('sys_template')->insert('sys_template', [
                    'pid' => 1,
                    'title' => 'Clears lib.contentElement',
                    'root' => 0,
                    'clear' => 0,
                    'config' => $setup,
                ]);
            }
        } else {
            $this->setUpFrontendRootPage(1, [], [
                'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
                'config' => $setup,
            ]);
        }

        $this->get(CacheManager::class)->flushCaches();
    }

    private function render(string $uri): string
    {
        return (string)$this->executeFrontendSubRequest(new InternalRequest($uri))->getBody();
    }

    #[DataProvider('deliveryPaths')]
    #[Test]
    public function everyThemeElementRendersTheSameWithLibContentElementCleared(string $path): void
    {
        $this->setUpSite($path, false);
        $intact = $this->render('https://theme.example.com/');

        $this->setUpSite($path, true);
        $cleared = $this->render('https://theme.example.com/');

        foreach ([
            'theme_hero', 'theme_hero_small', 'theme_hero_text_only',
            'theme_teaser', 'theme_media_teaser', 'theme_testimonial',
            'theme_author', 'theme_linklist', 'theme_sociallinks',
            'theme_media_teaser_grid', 'theme_notice', 'theme_tabs',
            'theme_accordion', 'theme_cta', 'theme_card_group',
            'theme_timeline', 'theme_teaser_list', 'theme_carousel',
            'theme_split_tiles',
        ] as $ctype) {
            $this->assertStringContainsString(sprintf('data-ctype="%s"', $ctype), $cleared, sprintf('"%s" did not render.', $ctype));
        }
        $this->assertStringNotContainsString('has no rendering definition', $cleared);
        $this->assertSame($intact, $cleared, 'A theme element renders differently once "lib.contentElement" is cleared.');
    }

    /**
     * The control of the comparison above: the same TypoScript takes the
     * rendering of a core element away, so the clearing line did reach the
     * TypoScript of the page.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function clearingLibContentElementTakesTheCoreRenderingAway(string $path): void
    {
        $this->setUpSite($path, false);
        $this->assertStringContainsString('data-ctype="header"', $this->render('https://theme.example.com/core'));

        $this->setUpSite($path, true);
        $this->assertStringNotContainsString('data-ctype="header"', $this->render('https://theme.example.com/core'));
    }
}
