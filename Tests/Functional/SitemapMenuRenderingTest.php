<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The sitemap lists the whole site, from its root, on whatever page it sits.
 *
 * `tt_content.menu_sitemap` is built `=< tt_content.menu_subpages`, which sets
 * `special = directory`. The sitemap has to get rid of that again, and a
 * `special >` in the referencing block does not: `mergeTSRef()` overlays the
 * local block onto the referenced one with `array_replace_recursive()`, so a
 * property removed locally is merely absent from the overlay and the
 * referenced value survives. The sitemap then became a directory menu of
 * `pages`, a field its type does not offer, which falls back to the current
 * page - an empty menu on any page without children.
 *
 * The element therefore sits on a leaf page away from the root. On the root
 * page the directory fallback lists the root's children as well, and the
 * defect would not show.
 */
final class SitemapMenuRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithSitemapMenu.csv');
        $this->setUpThemeSite();
    }

    /**
     * Every page of the site below the root, each at its depth in the tree.
     *
     * The list is read as a sequence of list openings, list closings and link
     * titles, so the assertion covers the nesting as well as the pages: a
     * sitemap flattened to one level, or rooted one level too deep, lists the
     * same titles at the wrong depth.
     */
    #[Test]
    public function aSitemapListsTheWholeSiteFromItsRoot(): void
    {
        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/second/sitemap'),
        )->getBody();

        $matched = preg_match('#<div[^>]*data-ctype="menu_sitemap"[^>]*>(.*?)</main>#s', $body, $element);
        $this->assertSame(1, $matched, 'No "menu_sitemap" element was rendered.');

        preg_match_all(
            '#(?<open><ul\b)|(?<close></ul>)|<a[^>]*class="theme-content-menu__link"[^>]*>\s*(?<title>[^<]+?)\s*</a>#',
            $element[1],
            $tokens,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );
        $depth = 0;
        $depthByTitle = [];
        foreach ($tokens as $token) {
            if ($token['open'] !== null) {
                $depth++;
            } elseif ($token['close'] !== null) {
                $depth--;
            } else {
                $depthByTitle[$token['title']] = $depth;
            }
        }

        $this->assertSame(
            [
                'First section' => 1,
                'A page in the first section' => 2,
                'Three levels down' => 3,
                'Four levels down' => 4,
                'Second section' => 1,
                'The page carrying the sitemap' => 2,
            ],
            $depthByTitle,
        );
    }
}
