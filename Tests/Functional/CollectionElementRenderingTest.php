<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The card group, the timeline and the teaser list render their items on the
 * components they are built on.
 *
 * All three read the shared inline child table, and each reads a field of the
 * element that decides how: the card group its arrangement and its column
 * count, the timeline its order. Getting one of those wrong renders a correct
 * looking element - a grid instead of the scroller, three columns instead of
 * two, the entries in the order of the relation instead of by date - which is
 * what is asserted here, through both delivery paths: the templates are the
 * same files either way, but the path decides whether the theme renders the
 * page at all.
 */
final class CollectionElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithCollections.csv');

        // The image of the first teaser list row: a real file, indexed as
        // sys_file uid 1, which the reference names - "f:uri.image" reads it.
        $storage = $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        GeneralUtility::writeFile(
            $this->instancePath . '/fileadmin/portrait.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="80" height="80"><rect width="80" height="80" fill="#d6dce6"/></svg>',
        );
        $this->get(StorageRepository::class)->findByUid($storage)?->getFile('portrait.svg');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/CollectionMedia.csv');
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
        // The page is the theme's, or none of the assertions below means anything.
        $this->assertStringContainsString('data-theme-page-layout=', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);

        return $body;
    }

    /**
     * One element by its uid, from its wrapper to the wrapper of the next.
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
     * The titles of the items of an element, in the order they are rendered.
     *
     * @return list<string>
     */
    private static function titles(string $element, string $class): array
    {
        preg_match_all(sprintf('#<h\d class="%s">(?:<a [^>]*>)?([^<]+)#', preg_quote($class, '#')), $element, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * The grid caps its columns at the count the element asks for, and the
     * cards are the items of a list, with a subtitle and a button of the
     * style the editor picked. A card link without a label gets the fallback
     * text of the core - the title of the page it leads to, or the URL of a
     * link to another site - and no icon, never the stored link reference.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aCardGroupRendersItsCardsInTheColumnsItAsksFor(string $path): void
    {
        $group = $this->element($this->render($path), 10);

        $this->assertStringContainsString('<ul class="theme-card-grid theme-card-grid--columns-2">', $group);
        $this->assertStringNotContainsString('theme-card-scroller', $group);
        $this->assertSame(3, substr_count($group, '<li class="theme-card">'));
        $this->assertSame(['Workshops', 'Office hours', 'Elsewhere'], self::titles($group, 'theme-card__title'));
        // One level below the h2 of the element.
        $this->assertStringContainsString('<h3 class="theme-card__title">Workshops</h3>', $group);
        $this->assertStringContainsString('<p class="theme-card__subtitle">Every second Thursday</p>', $group);

        $this->assertMatchesRegularExpression('#<div class="theme-card__actions">\s*<a [^>]*class="theme-button"[^>]*>\s*Book a seat\s*</a>#', $group);
        $this->assertMatchesRegularExpression('#<a [^>]*class="theme-button theme-button--secondary"[^>]*>Theme root</a>#', $group);
        $this->assertStringNotContainsString('<svg', $group, 'A card link without a label keeps its icon, which would leave it without a name.');
        // A link to another site without a label: the core's fallback text for
        // a URL is the URL itself.
        $this->assertMatchesRegularExpression('#<a [^>]*href="https://example\.org/"[^>]*>https://example\.org/#', $group);
        $this->assertStringNotContainsString('t3://', $group);
    }

    /**
     * The scroller is one row in a region that carries the tab stop and a
     * name - the heading of the element - and keeps the column count as the
     * number of cards in view.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aCardGroupInTheScrollerLayoutIsANamedRegionWithATabStop(string $path): void
    {
        $body = $this->render($path);
        $group = $this->element($body, 20);

        $this->assertStringContainsString('<div class="theme-card-scroller" role="region" tabindex="0" aria-label="Upcoming releases">', $group);
        $this->assertStringContainsString('<ul class="theme-card-grid theme-card-grid--scroller theme-card-grid--columns-4">', $group);

        // Without a heading the region is still named.
        $this->assertStringContainsString('<div class="theme-card-scroller" role="region" tabindex="0" aria-label="Cards">', $this->element($body, 70));
    }

    /**
     * The wall is the same list of cards with one more modifier: no wrapper
     * and no region, because it does not scroll, and the column count it is
     * given is kept.
     *
     * The order of the cards in the markup is asserted, and that is the point
     * of the test rather than a detail of it: a wall fills column by column,
     * so what the eye reads across the top is not the order of the relation.
     * The DOM order is what a screen reader and the tab sequence follow, and
     * it has to stay the order the editor put the cards in - see the component
     * for why the visual order is acceptable and the source order is not
     * negotiable.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aCardGroupInTheWallLayoutKeepsTheOrderOfTheRelation(string $path): void
    {
        $group = $this->element($this->render($path), 80);

        $this->assertStringContainsString(
            '<ul class="theme-card-grid theme-card-grid--wall theme-card-grid--columns-2">',
            $group,
        );
        // It does not scroll, so it is not a region and has no tab stop.
        $this->assertStringNotContainsString('theme-card-scroller', $group);
        $this->assertStringNotContainsString('role="region"', $group);
        $this->assertStringNotContainsString('tabindex', $group);

        $this->assertSame(
            ['The first card of the wall', 'The second card of the wall', 'The third card of the wall'],
            self::titles($group, 'theme-card__title'),
            'The wall renders its cards in an order other than the relation.',
        );
    }

    /**
     * A layout and a column count the form does not offer - core layouts the
     * page TSconfig removes, a value from an import - render the grid in
     * three columns, the defaults, not a modifier nothing styles.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aCardGroupWithValuesTheFormDoesNotOfferRendersTheDefaultGrid(string $path): void
    {
        $group = $this->element($this->render($path), 30);

        $this->assertStringContainsString('<ul class="theme-card-grid theme-card-grid--columns-3">', $group);
        $this->assertStringNotContainsString('theme-card-scroller', $group);
        $this->assertStringNotContainsString('--columns-9', $group);
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
     */
    public static function timelineOrders(): \Generator
    {
        foreach (self::deliveryPaths() as $label => $case) {
            // The relation lists "Beta release", "First sketch", "Beta
            // feedback"; the two betas share a date, and the relation decides
            // between them in both directions.
            yield 'oldest first, ' . $label => $case + ['uid' => 40, 'expected' => ['First sketch', 'Beta release', 'Beta feedback']];
            yield 'newest first, ' . $label => $case + ['uid' => 50, 'expected' => ['Beta release', 'Beta feedback', 'First sketch']];
        }
    }

    /**
     * The entries are sorted by their date, in the direction of the element,
     * and the relation breaks a tie. Rendering them in the order of the
     * relation would put "Beta release" first in both.
     *
     * @param list<string> $expected
     */
    #[DataProvider('timelineOrders')]
    #[Test]
    public function aTimelineSortsItsEntriesByDate(string $path, int $uid, array $expected): void
    {
        $timeline = $this->element($this->render($path), $uid);

        $this->assertSame(1, substr_count($timeline, '<ol class="theme-timeline">'));
        $this->assertSame($expected, self::titles($timeline, 'theme-timeline__title'));
    }

    /**
     * The date is machine readable in "datetime" and written out in the
     * locale of the site language.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTimelineEntryCarriesItsDateAsTimeElement(string $path): void
    {
        $timeline = $this->element($this->render($path), 40);

        $this->assertStringContainsString('<time class="theme-timeline__date" datetime="2018-01-15">15 January 2018</time>', $timeline);
        $this->assertStringContainsString('<h3 class="theme-timeline__title">First sketch</h3>', $timeline);
        $this->assertStringContainsString('<p class="theme-timeline__text">Drawn on a napkin.</p>', $timeline);
    }

    /**
     * An entry with an icon of the set shows it on the line, in the slot the
     * stylesheet puts in place of the ring, before the date. An entry whose
     * icon the set no longer has keeps the ring: no empty slot.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTimelineEntryWithAnIconShowsItOnTheLine(string $path): void
    {
        $timeline = $this->element($this->render($path), 40);

        $this->assertSame(1, substr_count($timeline, 'theme-timeline__icon'), 'Not exactly the entry with a usable icon has the slot.');
        $this->assertMatchesRegularExpression(
            '#<li class="theme-timeline__item">\s*<span class="theme-timeline__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true"[^>]*>.*?</svg></span>\s*<time class="theme-timeline__date" datetime="2018-01-15">#s',
            $timeline,
        );
        $this->assertSame(1, preg_match('#\bd="([^"]+)"#', (new IconSet())->markup('pen'), $matches), 'The shipped "pen" icon has no path.');
        $this->assertStringContainsString($matches[0], $timeline, 'The slot does not hold the icon of the entry.');
    }

    /**
     * The image of a row is an avatar in its decorative form: the title beside
     * it names the row, so its "alt" is empty rather than the alternative text
     * of the file reference, which a screen reader would read out next to the
     * title. A row without an image has no avatar at all.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTeaserListRowShowsItsImageAsADecorativeAvatar(string $path): void
    {
        $list = $this->element($this->render($path), 60);

        $this->assertMatchesRegularExpression(
            '#<li class="theme-list-group__item">\s*<span class="theme-avatar"><img class="theme-avatar__image" src="[^"]+" alt="" width="40" height="40" loading="lazy"></span>#',
            $list,
        );
        $this->assertSame(1, substr_count($list, 'class="theme-avatar"'), 'The row without an image has an avatar.');
        $this->assertStringNotContainsString('A portrait of the author', $list);
        $this->assertStringNotContainsString('theme-list-group__media', $list);
    }

    /**
     * The title of a row is its one link, resolved from the link reference,
     * and a row without a link has a title of plain text. The date and the
     * meta data are the end of the row, and a row with neither has no meta
     * block at all.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTeaserListRowIsOneLinkWithItsDateAndMetaData(string $path): void
    {
        $list = $this->element($this->render($path), 60);

        $this->assertSame(1, substr_count($list, '<ul class="theme-list-group">'));
        $this->assertSame(2, substr_count($list, '<li class="theme-list-group__item">'));
        $this->assertMatchesRegularExpression('#<h3 class="theme-list-group__title"><a href="/" class="theme-list-group__link">Moving to site sets</a></h3>#', $list);
        $this->assertSame(1, substr_count($list, '<a '), 'A row carries more than one link, or the row without a link got one.');
        $this->assertStringContainsString('<h3 class="theme-list-group__title">Announced</h3>', $list);
        $this->assertStringNotContainsString('t3://', $list);

        $this->assertMatchesRegularExpression('#<p class="theme-list-group__meta">\s*<time datetime="2026-03-02">2 March 2026</time>\s*<span>8 min read</span>\s*</p>#', $list);
        $this->assertSame(1, substr_count($list, 'theme-list-group__meta'));
    }
}
