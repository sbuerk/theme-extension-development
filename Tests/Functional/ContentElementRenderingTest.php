<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders the content elements the theme brings itself.
 *
 * The theme does not depend on `fluid_styled_content`, so every content element
 * needs its own rendering definition. Without one the core renders its notice
 * that the element has no rendering definition, which is exactly what the first
 * assertion here guards against - it is the difference between "rendered" and
 * "rendered an error box".
 *
 * Covered here are `header` and `text`, the two elements whose TCA lives in
 * `EXT:frontend/Configuration/TCA/tt_content.php` itself. The `image` element
 * has its own test; the rest of the classic set is registered by EXT:frontend
 * too, in its TCA overrides, and still waits for a template.
 */
final class ContentElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const NO_RENDERING_DEFINITION = 'has no rendering definition';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithContentElements.csv');
        $this->writeSiteConfiguration(
            'theme',
            // See SiteSetRenderingTest for why this is not the "additional"
            // argument: https://github.com/sbuerk/typo3-site-based-test-trait/issues/25
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

    private function renderRootPage(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    #[Test]
    public function noContentElementFallsBackToTheCoreErrorNotice(): void
    {
        $this->assertStringNotContainsString(self::NO_RENDERING_DEFINITION, $this->renderRootPage());
    }

    #[Test]
    public function headerElementRendersItsHeadingAtTheConfiguredLevel(): void
    {
        $body = $this->renderRootPage();

        // "header_layout" 2 in the fixture.
        $this->assertStringContainsString('<h2 class="theme-content-element__heading">A heading element</h2>', $body);
    }

    #[Test]
    public function textElementRendersHeadingAndParsedBodytext(): void
    {
        $body = $this->renderRootPage();

        // "header_layout" 3 in the fixture.
        $this->assertStringContainsString('<h3 class="theme-content-element__heading">A text element</h3>', $body);
        $this->assertStringContainsString('The body text of the element.', $body);
    }

    #[Test]
    public function elementsAreWrappedInTheContentElementFrame(): void
    {
        $body = $this->renderRootPage();

        $this->assertStringContainsString('id="c1"', $body);
        $this->assertStringContainsString('theme-content-element--header', $body);
        $this->assertStringContainsString('theme-content-element--text', $body);
    }

    #[Test]
    public function headerLayout100HidesTheHeading(): void
    {
        $body = $this->renderRootPage();

        // The element itself is rendered, its heading is not: "header_layout"
        // 100 is the "do not display" value of the core TCA.
        $this->assertStringContainsString('id="c3"', $body);
        $this->assertStringNotContainsString('A hidden heading', $body);
    }
}
