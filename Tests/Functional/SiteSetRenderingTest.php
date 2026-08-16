<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders a page through the site set of the theme.
 *
 * The subject is the delivery mechanism, not the markup: the site declares the
 * set as a dependency and nothing else, and the root page deliberately has *no*
 * `sys_template` record - the fourth argument of `setUpFrontendRootPage()`. So
 * everything asserted here can only come from
 * "Configuration/Sets/ThemeExtensionDevelopment/config.yaml" and the TypoScript
 * it points at.
 *
 * That is what makes this test worth its runtime: it fails if the set is not
 * found, if its "typoscript" path stops resolving, if the page object is not
 * built, or if the Fluid paths break.
 *
 * This is the one rendering test that carries a core version group. Site sets
 * arrived in TYPO3 v13.1 (#103437) and this test's subject *is* the set, so on
 * v12 there is nothing here to run. The other rendering tests deliberately have
 * no group: they arrange the theme through {@see ThemeSiteTrait}, which
 * delivers it the way the running version can, and grouping them away would
 * give up two thirds of the v12 coverage instead of porting it. The equivalent
 * of this test on the classic delivery path is
 * {@see StaticFileIncludeRenderingTest}, which asserts the same three things
 * and runs on both versions.
 */
#[Group('not-core-12')]
final class SiteSetRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
        $this->writeSiteConfiguration(
            'theme',
            // The set dependency goes into the site array rather than into the
            // "additional" argument. That argument is silently dropped by
            // "sbuerk/typo3-site-based-test-trait": its merge takes $site
            // instead of $additional, and $configuration already is $site.
            // @todo Move this back into "additional:" once
            //       https://github.com/sbuerk/typo3-site-based-test-trait/issues/25
            //       is fixed and the constraint here requires that version.
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + [
                'dependencies' => [
                    'sbuerk/theme-extension-development',
                ],
            ],
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        // No sys_template record: the site set has to deliver the TypoScript on
        // its own, which is the whole point of this test.
        $this->setUpFrontendRootPage(1, [], [], false);
    }

    /**
     * @return \Generator<string, array{url: string, expectedContent: string}>
     */
    public static function renderedPages(): \Generator
    {
        yield 'root page renders its title' => [
            'url' => 'https://theme.example.com/',
            'expectedContent' => 'Theme root',
        ];
        yield 'sub page renders its title' => [
            'url' => 'https://theme.example.com/a-page',
            'expectedContent' => 'A page',
        ];
    }

    #[DataProvider('renderedPages')]
    #[Test]
    public function siteSetRendersThePageTemplate(string $url, string $expectedContent): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest($url));
        $body = (string)$response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        // From "Resources/Private/Templates/Page/Default.html" through the
        // layout, so the whole Fluid chain resolved.
        $this->assertStringContainsString('class="theme-page__main"', $body);
        // The page title, assigned as a TypoScript variable.
        $this->assertStringContainsString($expectedContent, $body);
    }

    #[Test]
    public function siteSetIncludesTheCompiledStylesheet(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));

        $this->assertStringContainsString(
            'theme_extension_development/Resources/Public/Css/theme.css',
            (string)$response->getBody(),
        );
    }

    #[Test]
    public function siteSetRendersTheThemePartials(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));
        $body = (string)$response->getBody();

        $this->assertStringContainsString('class="theme-site-header"', $body);
        $this->assertStringContainsString('data-theme="theme_extension_development"', $body);
    }
}
