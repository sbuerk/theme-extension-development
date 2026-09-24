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
 * `tt_content.<signature> =< lib.contentElement` for every plugin registered
 * as its own CType. Of the TYPO3 system extensions, only
 * `fluid_styled_content` defines `lib.contentElement`, on v12.4 and v13.4
 * alike, with a `Generic` template of its own. It is not a dependency here,
 * and this test does not load it. So on a site that does not include its
 * TypoScript, the theme's own definition is the only reason any Extbase
 * plugin renders.
 *
 * The fixture extension deliberately ships **no TypoScript of its own**. It
 * registers the plugin and nothing else, so if this passes, it passed because
 * of `lib.contentElement` and `Generic.html` and for no other reason.
 */
final class ExtbasePluginRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

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
        $this->setUpThemeSite();
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
            'The plugin did not render. Without a "lib.contentElement" of this theme\'s own, nothing in this instance would.',
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

    /**
     * A plugin is rendered with its content element: the FlexForm settings it
     * was configured with reach it, and so does the record itself. Without
     * the record, "CObjectViewHelper" starts a content object renderer with an
     * empty one, and the plugin runs as if nobody had configured it.
     *
     * Asserted for a cached and for a non-cacheable plugin: the second is
     * rendered after the page from a serialised content object renderer,
     * which is a second place for the record to get lost.
     */
    #[Test]
    public function aPluginIsRenderedWithItsContentElement(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('<div class="plugin-fixture__greeting">Hello from the FlexForm</div>', $body);
        $this->assertStringContainsString('<div class="plugin-fixture__content-element">10</div>', $body);
    }

    #[Test]
    public function aNonCacheablePluginIsRenderedWithItsContentElement(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('<div class="plugin-fixture__greeting">Hello from the uncached FlexForm</div>', $body);
        $this->assertStringContainsString('<div class="plugin-fixture__content-element">20</div>', $body);
    }

    #[Test]
    public function thePluginDoesNotFallBackToTheCoreNotice(): void
    {
        $this->assertStringNotContainsString('has no rendering definition', $this->render());
    }
}
