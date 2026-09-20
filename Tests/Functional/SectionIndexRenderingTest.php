<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The table of contents of a page, and the link back to the top of it.
 *
 * Both read a core field the theme rendered nothing for until now, and both
 * fail in a way that looks like an editor's doing rather than a defect: a
 * table of contents built with the default `useColPos` silently drops every
 * element that is not in the main column, and one built with the default
 * `sectionIndex.type` silently gains an entry with no text for every element
 * whose header is empty or hidden. Neither breaks a page.
 *
 * The menu is an `HMENU`, not a `MenuProcessor` - that processor rejects
 * `sectionIndex` outright, throwing 1478806566 from `validateConfiguration()`
 * rather than from its constructor - so what is asserted here is the rendered
 * markup of a real page rather than a processed array.
 *
 * --- The order is `sorting`, across every column ---------------------------
 *
 * `AbstractMenuContentObject::sectionIndex()` orders by the menu's
 * `alternativeSortingField`, which defaults to `sorting`, and it does so over
 * the whole result - the column an element sits in is a `WHERE`, never part of
 * the `ORDER BY`. With `useColPos = -1` the columns are therefore interleaved
 * by `sorting` rather than listed one after the other, which is worth knowing
 * before an editor wonders why the sidebar element sits between two elements
 * of the main column.
 *
 * The fixture gives every element a distinct `sorting` for that reason: with
 * two elements sharing one, the order between them is the database's and the
 * assertion below would be flaky rather than wrong.
 *
 * --- The attribute order of the anchor --------------------------------------
 *
 * `TMENU` writes `ATagParams` after the `href`, so an entry is
 * `<a href="#c10" class="theme-content-menu__link">`. The patterns below do not
 * depend on that order, so the assertions stay about the anchor and the class
 * rather than about how the core happens to concatenate them.
 */
final class SectionIndexRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithSectionIndex.csv');
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
     * The first path is the delivery of the running core version - the site
     * set on v13, the `sys_template` record on v12, through
     * `ThemeSiteTrait`. The second is the `include_static_file` path
     * literally, which both versions support and which is what the v12
     * delivery happens to be as well; the two therefore coincide there, and
     * the pair is still worth running because on v13 it is the one place the
     * static include of a set-capable core is exercised for this menu.
     */
    private function render(string $path, string $url = '/'): string
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
            $this->setUpFrontendRootPage(
                1,
                [],
                $delivery->templateValues(),
                $delivery->createsSysTemplateRecord(),
            );
        } else {
            $this->setUpFrontendRootPage(1, [], [
                'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
            ]);
        }

        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/' . ltrim($url, '/')),
        )->getBody();
        $this->assertStringContainsString('data-theme-page-layout=', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);

        return $body;
    }

    /**
     * The table of contents landmark, which is the one navigation carrying
     * the page toc class.
     */
    private function tableOfContents(string $body): string
    {
        $matched = preg_match('#<nav\b[^>]*class="[^"]*theme-page__toc[^"]*"[^>]*>(.*?)</nav>#s', $body, $matches);
        $this->assertSame(1, $matched, 'No table of contents was rendered.');

        return $matches[0];
    }

    /**
     * @return list<string> The text of every entry, in order.
     */
    private static function entries(string $toc): array
    {
        preg_match_all('#<a\b[^>]*class="theme-content-menu__link"[^>]*>(.*?)</a>#s', $toc, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * The index lists the elements that are in it, ordered by `sorting` across
     * every column of the page - and nothing else.
     *
     * The three exclusions are the whole point: an element switched out of
     * the index, one whose header is empty, and one whose header is hidden
     * with `header_layout = 100`. The default `sectionIndex.type` would list
     * the last two as entries with no text at all.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theIndexListsEveryElementWithAHeaderFromEveryColumn(string $path): void
    {
        $toc = $this->tableOfContents($this->render($path));

        $this->assertSame(
            ['First section', 'Second section', 'In the sidebar'],
            self::entries($toc),
            'The table of contents lists the wrong elements, or lists them in the wrong order.',
        );
        $this->assertStringNotContainsString('Out of the index', $toc);
        $this->assertStringNotContainsString('Hidden heading', $toc);
    }

    /**
     * The index is of the page being read, not of the site root.
     *
     * This is the assertion the first version of this test could not make.
     * The fixture had one page, which was the site root, so an index built
     * from the root and an index built from the current page were the same
     * list and a menu with no `entryLevel` passed. `start()` resolves the
     * menu's page from the local rootline at `$this->entryLevel` -
     * `$GLOBALS['TSFE']->config['rootLine']` on v12.4, the
     * `frontend.page.information` request attribute on v13.4 - with
     * `entryLevel` defaulting to 0, the site root, so without
     * `entryLevel = -1` every page of the site lists the root's elements.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theIndexIsOfTheCurrentPageAndNotOfTheSiteRoot(string $path): void
    {
        $toc = $this->tableOfContents($this->render($path, '/below'));

        $this->assertStringContainsString('Only on the page below', $toc, 'The page below the root does not list its own element.');
        $this->assertStringNotContainsString('First section', $toc, 'The page below the root lists the site root\'s elements.');
        $this->assertStringNotContainsString('In the sidebar', $toc);
    }

    /**
     * A header carrying markup is escaped, not rendered.
     *
     * `sectionIndex()` assigns `$row['header']` to the item title verbatim
     * and nothing in `ContentObject/Menu/` ever escapes a title, so the only
     * thing standing between an editor's header and the page is
     * `NO.stdWrap.htmlSpecialChars = 1` - the partial renders this menu with
     * `f:format.raw`. Without it this fixture's header is stored XSS.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aHeaderCarryingMarkupIsEscaped(string $path): void
    {
        $body = $this->render($path, '/below');
        $toc = $this->tableOfContents($body);

        $this->assertStringNotContainsString('<img src=x', $toc, 'The header of an element is rendered as markup.');
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;Markup', $toc, 'The header is not escaped into the entry.');
    }

    /**
     * Every entry points at the anchor of its element - the `c<uid>` id
     * `Layouts/ContentElement.html` writes, which typolink produces from
     * `sectionIndex_uid` by prefixing a numeric fragment with `c`. An entry
     * pointing anywhere else is a link that scrolls nowhere.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function everyEntryPointsAtTheAnchorOfItsElement(string $path): void
    {
        $body = $this->render($path);
        $toc = $this->tableOfContents($body);

        foreach ([10 => 'First section', 20 => 'Second section', 60 => 'In the sidebar'] as $uid => $title) {
            $this->assertMatchesRegularExpression(
                sprintf('#<a\b[^>]*href="[^"]*\#c%d"[^>]*class="theme-content-menu__link"[^>]*>\s*%s\s*</a>#', $uid, preg_quote($title, '#')),
                $toc,
                sprintf('The entry "%s" does not point at "#c%d".', $title, $uid),
            );
            $this->assertStringContainsString(sprintf('<div id="c%d"', $uid), $body, sprintf('No element carries the id "c%d".', $uid));
        }
    }

    /**
     * The landmark is named, like every other navigation of the theme, and it
     * is the content menu component rather than one of its own.
     *
     * It is named by its own visible heading with `aria-labelledby`, not by an
     * `aria-label` repeating the same words: the two carried the same string,
     * so a reader moving by landmark heard "On this page" and then read it
     * again. The assertion is that the id the landmark points at is the id the
     * heading carries - a dangling reference would leave the landmark unnamed
     * while still looking labelled in the markup.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theTableOfContentsIsANamedLandmarkOnTheContentMenu(string $path): void
    {
        $toc = $this->tableOfContents($this->render($path));

        $this->assertStringNotContainsString('aria-label=', $toc, 'The landmark carries a label of its own next to its heading.');
        $this->assertSame(1, preg_match('#<nav[^>]*aria-labelledby="([^"]+)"#', $toc, $labelledBy));
        $this->assertMatchesRegularExpression(
            sprintf('#<p class="theme-page__toc-heading" id="%s">#', preg_quote($labelledBy[1], '#')),
            $toc,
            'The landmark points at an id no heading carries.',
        );
        $this->assertStringContainsString('theme-content-menu', $toc);
        $this->assertStringContainsString('<ul class="theme-content-menu__list">', $toc);
    }

    /**
     * Exactly the element whose `linkToTop` is set gets the link, it points at
     * the id of the main landmark - not at "#top", which moves no focus - and
     * it sits outside the inner box so a band cannot paint it.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function onlyAnElementWithLinkToTopGetsTheLinkBackToTheTop(string $path): void
    {
        $body = $this->render($path);

        $this->assertSame(1, substr_count($body, 'theme-content-element__to-top'), 'Not exactly one element has the link.');
        $this->assertMatchesRegularExpression(
            '#</div>\s*<a class="theme-content-element__to-top" href="\#content">#',
            $body,
            'The link is inside the inner box, or is not where the layout puts it.',
        );
        $this->assertMatchesRegularExpression(
            '#<a class="theme-content-element__to-top" href="\#content">\s*<svg class="theme-icon"[^>]*>.*?</svg>\s*Back to top\s*</a>#s',
            $body,
            'The link does not carry the arrow of the set and its text.',
        );
        // The id it points at is a real element, so following it moves focus.
        $this->assertStringContainsString('id="content"', $body);
    }
}
