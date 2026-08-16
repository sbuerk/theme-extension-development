<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Proves that a third-party Extbase plugin renders in this theme.
 *
 * This is the least visible load-bearing thing in the extension.
 * `ExtensionUtility::configurePlugin()` emits
 * `tt_content.<signature> =< lib.contentElement` for every plugin ever
 * registered, unconditionally, and `lib.contentElement` comes from
 * `fluid_styled_content` - which is not a dependency here and is therefore not
 * installed. In an installation like that, this theme's own definition is the
 * only reason any Extbase plugin renders at all.
 *
 * The fixture extension deliberately ships **no TypoScript of its own**. It
 * registers the plugin and nothing else, so if this passes, it passed because
 * of `lib.contentElement` and `Generic.html` and for no other reason.
 */
final class ExtbasePluginRenderingTest extends AbstractFunctionalTestCase
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
        $this->writeSiteConfiguration(
            'theme',
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
        $this->setUpFrontendRootPage(1, [], [], false);
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    #[Test]
    public function anExtbasePluginRendersItsOwnTemplate(): void
    {
        $this->assertStringContainsString(
            'Plugin fixture rendered through lib.contentElement',
            $this->render(),
            'The plugin did not render. Without a "lib.contentElement" of this theme\'s own, nothing would.',
        );
    }

    /**
     * The plugin has to go through the same wrapper every other element does,
     * or it loses the CType outline, the spacing and the id anchor - and a
     * site package overriding the wrapper would silently not affect plugins.
     */
    #[Test]
    public function thePluginGoesThroughTheSharedContentElementWrapper(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('data-ctype="testspluginfixture_plugin"', $body);
        $this->assertStringContainsString('theme-content-element--testspluginfixture_plugin', $body);
    }

    #[Test]
    public function thePluginDoesNotFallBackToTheCoreNotice(): void
    {
        $this->assertStringNotContainsString('has no rendering definition', $this->render());
    }
}
