<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The "recently updated" menu lists pages in the same order every time.
 *
 * The core sorts it by "SYS_LASTCHANGED DESC" and nothing else, so pages
 * changed in the same second come back in whatever order the database holds
 * them: ascending uid on SQLite, and on PostgreSQL an order that differed
 * between two identical page trees of one database. Three of the pages here
 * share one timestamp, and the assertion is the complete order: newest first,
 * then the tied pages by descending uid, then the oldest. Without the theme's
 * tie-breaker, SQLite returns the tied pages in ascending uid order and this
 * fails there; what PostgreSQL returns is not specified at all.
 */
final class RecentlyUpdatedMenuRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/RecentlyUpdatedMenuPageTree.csv');
        $this->setUpThemeSite();
    }

    #[Test]
    public function tiedPagesAreOrderedByDescendingUid(): void
    {
        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'))->getBody();

        $matched = preg_match('#<div[^>]*data-ctype="menu_recently_updated"[^>]*>(.*?)</div>\s*</div>#s', $body, $element);
        $this->assertSame(1, $matched, 'No "menu_recently_updated" element was rendered.');

        preg_match_all('#<a[^>]*>\s*([^<]+?)\s*</a>#', $element[1], $links);
        $this->assertSame(
            ['Newest page', 'Tied page four', 'Tied page three', 'Tied page two', 'Theme root'],
            $links[1],
        );
    }
}
