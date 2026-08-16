<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders a page through the classic static include instead of the site set.
 *
 * This is the other branch of the condition guarding
 * "Configuration/TypoScript/Static/setup.typoscript": the site declares **no**
 * set, so `site('sets')` is empty, the condition is true and the include
 * happens. The companion {@see SiteSetRenderingTest} covers the set itself, and
 * together they prove the theme is reachable both ways.
 */
final class StaticTypoScriptFallbackRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
        // Deliberately no "dependencies": this site does not use the site set.
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        // A sys_template record pulling in the static include of the theme,
        // which is what an installation without site sets does.
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => [
                    'EXT:theme_extension_development/Configuration/TypoScript/Static/constants.typoscript',
                ],
                'setup' => [
                    'EXT:theme_extension_development/Configuration/TypoScript/Static/setup.typoscript',
                ],
            ],
        );
    }

    #[Test]
    public function staticIncludeRendersThePageTemplate(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));
        $body = (string)$response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('class="theme-page__main"', $body);
        $this->assertStringContainsString('Theme root', $body);
    }

    #[Test]
    public function staticIncludeIncludesTheCompiledStylesheet(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));

        $this->assertStringContainsString(
            'theme_extension_development/Resources/Public/Css/theme.css',
            (string)$response->getBody(),
        );
    }
}
