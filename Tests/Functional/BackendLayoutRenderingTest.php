<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Proves that the backend layout of a page decides which page template renders
 * it, including the cases where that is not obvious.
 *
 * The theme resolves the template with `templateName.data = pagelayout` rather
 * than by reading the `backend_layout` field. The difference only shows up on
 * a page that does *not* carry its own layout, which is most of them - so a
 * test that checked only the explicit case would pass against the broken
 * implementation.
 *
 * `PageLayoutResolver::getLayoutIdentifierForPage()` is the behaviour being
 * relied on, and it has two edges worth pinning down:
 *
 *  - it `array_shift()`s the rootline before looking for
 *    `backend_layout_next_level`, so a page's own "next level" setting applies
 *    to its children and never to itself;
 *  - a layout explicitly set to "none" (`-1`) resolves to the identifier
 *    `none`, which would otherwise be looked up as a template called
 *    `Page/None.html` and end the request in a "template not found" error.
 */
final class BackendLayoutRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/BackendLayoutPageTree.csv');
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

    private function render(string $url): string
    {
        return (string)$this->executeFrontendSubRequest(new InternalRequest($url))->getBody();
    }

    /**
     * @return \Generator<string, array{url: string, expectedLayout: string}>
     */
    public static function resolvedLayouts(): \Generator
    {
        yield 'a page renders the layout it declares itself' => [
            'url' => 'https://theme.example.com/own',
            'expectedLayout' => 'content_sidebar',
        ];
        yield 'and another one' => [
            'url' => 'https://theme.example.com/start',
            'expectedLayout' => 'start',
        ];
        yield 'a page without one inherits it from an ancestor' => [
            'url' => 'https://theme.example.com/inherits',
            'expectedLayout' => 'content',
        ];
        yield 'inheritance reaches further than one level' => [
            'url' => 'https://theme.example.com/inherits/deeper',
            'expectedLayout' => 'content',
        ];
        // The rootline is shifted before the search, so the root's own
        // "next level" value is for its children. The root falls back.
        yield 'a page does not inherit its own next level setting' => [
            'url' => 'https://theme.example.com/',
            'expectedLayout' => 'default',
        ];
        // Without the mapping this asks for "Page/None.html" and the request
        // dies - from an editor picking an ordinary option in the page
        // properties.
        yield 'a layout explicitly set to none falls back rather than failing' => [
            'url' => 'https://theme.example.com/none',
            'expectedLayout' => 'default',
        ];
    }

    #[DataProvider('resolvedLayouts')]
    #[Test]
    public function theBackendLayoutDecidesThePageTemplate(string $url, string $expectedLayout): void
    {
        $this->assertStringContainsString(
            sprintf('data-theme-page-layout="%s"', $expectedLayout),
            $this->render($url),
        );
    }

    /**
     * The two column grid in "layout/_page.scss" is switched on with
     * ":has(.theme-page__aside)", so an aside emitted on a layout that has no
     * sidebar column would produce a permanently empty column rather than
     * nothing at all.
     */
    #[Test]
    public function onlyTheSidebarLayoutEmitsAnAside(): void
    {
        $this->assertStringContainsString('theme-page__aside', $this->render('https://theme.example.com/own'));

        foreach (['/', '/inherits', '/start', '/none'] as $path) {
            $this->assertStringNotContainsString(
                'theme-page__aside',
                $this->render('https://theme.example.com' . ltrim($path, '/')),
                sprintf('"%s" must not emit an aside.', $path),
            );
        }
    }

    /**
     * Every layout resolves to a template that exists. A missing one is not a
     * degraded page, it is an exception, and the failure is per layout rather
     * than global - so it survives any check that only opens the front page.
     */
    #[Test]
    public function everyDeclaredLayoutRendersWithoutError(): void
    {
        foreach ([
            'https://theme.example.com/',
            'https://theme.example.com/inherits',
            'https://theme.example.com/inherits/deeper',
            'https://theme.example.com/own',
            'https://theme.example.com/none',
            'https://theme.example.com/start',
        ] as $url) {
            $response = $this->executeFrontendSubRequest(new InternalRequest($url));

            $this->assertSame(200, $response->getStatusCode(), sprintf('"%s" did not render.', $url));
            $this->assertStringContainsString('class="theme-page"', (string)$response->getBody());
        }
    }
}
