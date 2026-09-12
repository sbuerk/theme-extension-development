<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The fixture plugin of `ExtbasePluginRenderingTest`, through the static
 * include rather than the site set.
 *
 * The rendering `configurePlugin()` generates for a plugin CType is added with
 * `addTypoScript(..., 'defaultContentRendering')`. A site using sets always
 * gets it. A `sys_template` site gets it only right after a static include
 * listed in `$GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates']`
 * (`SysTemplateTreeBuilder::addStaticMagicFromGlobals()`), which is what
 * `ext_localconf.php` registers the theme's static include as. Without that
 * entry this page renders every element of the theme and of the core - and
 * the plugin alone as the core's "no rendering definition" notice.
 *
 * The static include is set as `include_static_file`, the way an installation
 * includes it. Importing its two files into the `sys_template` directly
 * renders the same theme with no plugin rendering at all, because the hook is
 * keyed on the include, not on the files.
 */
final class ExtbasePluginStaticIncludeRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'tests/plugin-fixture',
    ];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithExtbasePlugin.csv');
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
        $this->setUpFrontendRootPage(
            1,
            [],
            [
                'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
            ],
        );
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    /**
     * The page is the theme's either way - the assertion below is only worth
     * something once that is established.
     */
    #[Test]
    public function thePageIsRenderedByTheThemeThroughTheStaticInclude(): void
    {
        $this->assertStringContainsString('data-theme-page-layout=', $this->render());
    }

    #[Test]
    public function anExtbasePluginRendersThroughTheStaticInclude(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('Plugin fixture rendered through lib.contentElement', $body);
        $this->assertStringContainsString('data-ctype="testspluginfixture_plugin"', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);
    }
}
