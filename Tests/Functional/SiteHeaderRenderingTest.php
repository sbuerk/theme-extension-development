<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The brand link of the site header leads to the root of the site it is on.
 *
 * The root of a site is its base, and a base may have a path. The link used to
 * be a literal "/", which is the root of the host rather than of the site: on a
 * site below "/sub/" every page linked out of its own site. The site here has
 * exactly such a base, and both the root page and a sub page are asserted, so
 * neither "the current page" nor "the host root" can pass for the site root.
 */
final class SiteHeaderRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
        $this->setUpThemeSite(base: 'https://theme.example.com/sub/');
    }

    #[DataProvider('pagesOfTheSite')]
    #[Test]
    public function theBrandLinksToTheRootOfTheSite(string $url): void
    {
        $body = (string)$this->executeFrontendSubRequest(new InternalRequest($url))->getBody();

        $matched = preg_match('#<a class="theme-site-header__brand" href="([^"]*)"#', $body, $matches);
        $this->assertSame(1, $matched, 'No brand link was rendered.');
        $this->assertSame('/sub/', $matches[1]);
    }

    /**
     * The local rootline of a page ends at the nearest "sys_template" record
     * flagged as root, which is not necessarily the site root. A section
     * carrying one is where "leveluid:0" would have pointed the brand at.
     */
    #[Test]
    public function theBrandLinksToTheSiteRootBelowASectionFlaggedAsTypoScriptRoot(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SectionTypoScriptRoot.csv');

        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/sub/a-page'),
        )->getBody();

        $matched = preg_match('#<a class="theme-site-header__brand" href="([^"]*)"#', $body, $matches);
        $this->assertSame(1, $matched, 'No brand link was rendered.');
        $this->assertSame('/sub/', $matches[1]);
    }

    /**
     * @return \Generator<string, array{url: string}>
     */
    public static function pagesOfTheSite(): \Generator
    {
        yield 'the root page' => ['url' => 'https://theme.example.com/sub/'];
        yield 'a sub page' => ['url' => 'https://theme.example.com/sub/a-page'];
    }
}
