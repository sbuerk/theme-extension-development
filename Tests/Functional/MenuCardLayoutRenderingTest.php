<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The two page menus render their pages as cards and as thumbnails.
 *
 * "layout" 1 and 2 of "menu_pages" and "menu_subpages" turn the list of links
 * into a grid of cards, with the first page media of every page - which a
 * second "MenuProcessor" fetches, and only for those two CTypes in those two
 * layouts. What goes wrong there goes wrong quietly: the wrong processor runs
 * and the cards have no image, or the right one runs for a "menu_section"
 * that carries a layout value from an earlier rendering and its own menu is
 * replaced by a flat list. Both are asserted, through both delivery paths.
 *
 * The page media is a real file, indexed from "fileadmin/" in the set up and
 * referenced by the second fixture: "f:image" reads the file.
 */
final class MenuCardLayoutRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithMenuCards.csv');

        $storage = $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        GeneralUtility::writeFile(
            $this->instancePath . '/fileadmin/menu-card.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 270" width="480" height="270"><rect width="480" height="270" fill="#d6dce6"/></svg>',
        );
        // Indexes the file, as sys_file uid 1, which the reference names.
        $this->get(StorageRepository::class)->findByUid($storage)?->getFile('menu-card.svg');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/MenuCardMedia.csv');
    }

    /**
     * @return \Generator<string, array{path: string}>
     */
    public static function deliveryPaths(): \Generator
    {
        yield 'theme delivery' => ['path' => 'theme'];
        yield 'static include' => ['path' => 'static'];
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
        $this->assertStringContainsString('data-theme-page-layout=', $body);

        return $body;
    }

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
     * One card per page: the first page media with the alternative text of
     * its reference, the title as the link of the card one level below the
     * heading of the element, and the abstract. A page without media gets a
     * card without an image, and one without an abstract no empty text.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aPageMenuInTheCardLayoutRendersACardPerPageWithItsMedia(string $path): void
    {
        $menu = $this->element($this->render($path), 10);

        $this->assertStringContainsString('<ul class="theme-card-grid">', $menu);
        $this->assertSame(2, substr_count($menu, '<li class="theme-card theme-card--linked">'));
        $this->assertStringContainsString('<h3 class="theme-card__title"><a class="theme-card__title-link" href="/typography">Typography</a></h3>', $menu);
        $this->assertStringContainsString('<h3 class="theme-card__title"><a class="theme-card__title-link" href="/media">Media</a></h3>', $menu);
        $this->assertSame(1, substr_count($menu, 'class="theme-card__media"'), 'Only the page with media has an image.');
        $this->assertMatchesRegularExpression('#<div class="theme-card__media">\s*<img [^>]*alt="The first page of the typography section"#', $menu);
        $this->assertStringContainsString('<p class="theme-card__text">Headings and running text.</p>', $menu);
        $this->assertSame(1, substr_count($menu, 'theme-card__text'));
        $this->assertStringNotContainsString('theme-content-menu', $menu);
    }

    /**
     * The thumbnails are compact cards in the narrow grid: the image and the
     * title, no abstract.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aPageMenuInTheThumbnailLayoutRendersTheImageAndTheTitleOnly(string $path): void
    {
        $menu = $this->element($this->render($path), 20);

        $this->assertStringContainsString('<ul class="theme-card-grid theme-card-grid--narrow">', $menu);
        $this->assertSame(2, substr_count($menu, '<li class="theme-card theme-card--linked theme-card--compact">'));
        $this->assertMatchesRegularExpression('#<img [^>]*alt="The first page of the typography section"#', $menu);
        $this->assertStringNotContainsString('theme-card__text', $menu);
    }

    /**
     * "menu_subpages" takes the same layouts, with its own selection: the
     * children of the selected page rather than the pages themselves.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aSubpageMenuInTheCardLayoutRendersTheChildrenOfItsPageAsCards(string $path): void
    {
        $menu = $this->element($this->render($path), 30);

        $this->assertSame(2, substr_count($menu, '<li class="theme-card theme-card--linked">'));
        $this->assertStringContainsString('href="/typography">Typography</a>', $menu);
        $this->assertMatchesRegularExpression('#<img [^>]*alt="The first page of the typography section"#', $menu);
        $this->assertStringNotContainsString('>Theme root<', $menu);
    }

    /**
     * The list stays the default, and a value without a rendering - the core
     * layout 3 the form no longer offers - renders the list as well.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aPageMenuInAnyOtherLayoutRendersTheList(string $path): void
    {
        $body = $this->render($path);

        foreach ([40, 60] as $uid) {
            $menu = $this->element($body, $uid);
            $this->assertStringContainsString('<nav class="theme-content-menu"', $menu);
            $this->assertStringNotContainsString('theme-card', $menu);
            $this->assertStringContainsString('>Typography</a>', $menu);
        }
    }

    /**
     * Every other menu type is a reference to "menu_pages" and inherits both
     * processors. One that still carries a layout value from an earlier
     * rendering keeps its own menu - two levels, for "menu_section" - rather
     * than the flat list of pages with media the card layouts fetch.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function anotherMenuTypeWithALayoutValueKeepsItsOwnMenu(string $path): void
    {
        $menu = $this->element($this->render($path), 50);

        $this->assertStringContainsString('<nav class="theme-content-menu"', $menu);
        $this->assertStringContainsString('>Theme root</a>', $menu);
        $this->assertStringContainsString('theme-content-menu__list--sub', $menu);
        $this->assertStringNotContainsString('theme-card', $menu);
    }
}
