<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Covers the three navigations: the main menu, the left hand sub navigation
 * and the breadcrumb.
 *
 * The page tree is deliberately three levels deep. Almost every sub navigation
 * bug only appears below the second level - a menu configured relative to the
 * current page looks perfectly correct on a second level page and empties out
 * on its children, which is precisely where a reader needs it.
 *
 * The accessible state is asserted rather than the visual one. The stylesheet
 * renders the current item from `[aria-current='page']`, so asserting a
 * modifier class instead would let the two drift apart without any test
 * noticing.
 */
final class NavigationRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NavigationPageTree.csv');
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

    private function render(string $path): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/' . ltrim($path, '/')),
        )->getBody();
    }

    /**
     * The markup of one navigation landmark, by its class.
     *
     * Asserting against the whole response is not good enough for anything
     * about the *sub* navigation: the main menu carries two levels, so every
     * page's title in the tree legitimately appears somewhere in the body.
     * A "this section only" assertion made against the full document passes
     * whatever the sub navigation contains, including nothing.
     *
     * The navigations are siblings and never nested, so a non-greedy match to
     * the next closing tag is exact rather than approximate.
     */
    private function navigation(string $body, string $class): string
    {
        $matched = preg_match(
            sprintf('#<nav\b[^>]*class="[^"]*%s[^"]*"[^>]*>(.*?)</nav>#s', preg_quote($class, '#')),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No "%s" navigation was rendered.', $class));

        return $matches[0];
    }

    #[Test]
    public function theMainMenuListsTheTopLevelOfTheSite(): void
    {
        $body = $this->render('/first');

        $this->assertStringContainsString('theme-nav-main', $body);
        $this->assertStringContainsString('First section', $body);
        $this->assertStringContainsString('Second section', $body);
    }

    /**
     * `nav_hide` is what the styleguide page will rely on in a later step, so
     * a menu that ignores it would quietly publish a page meant to be
     * unlisted.
     */
    #[Test]
    public function theMainMenuLeavesOutAPageHiddenFromNavigation(): void
    {
        $this->assertStringNotContainsString('Hidden from the menu', $this->render('/first'));
    }

    #[Test]
    public function theMainMenuCarriesASecondLevel(): void
    {
        $body = $this->render('/first');

        $this->assertStringContainsString('theme-nav-main__list--sub', $body);
        $this->assertStringContainsString('A page in the first section', $body);
    }

    /**
     * The whole point of the sub navigation: it shows the *section*, not the
     * children of the current page. On a third level page a menu built
     * relative to the current page renders nothing at all, and a menu built
     * relative to its parent renders the wrong section.
     */
    #[Test]
    public function theSubNavigationShowsTheSectionOnEveryLevelOfIt(): void
    {
        foreach (['/first', '/first/a', '/first/a/deep'] as $path) {
            $subNavigation = $this->navigation($this->render($path), 'theme-nav-sub');

            $this->assertStringContainsString(
                'A page in the first section',
                $subNavigation,
                sprintf('"%s" lost its own section from the sub navigation.', $path),
            );
            $this->assertStringContainsString(
                'Another page in the first section',
                $subNavigation,
                sprintf('"%s" shows an incomplete section.', $path),
            );
            $this->assertStringNotContainsString(
                'A page in the second section',
                $subNavigation,
                sprintf('"%s" shows a foreign section in its sub navigation.', $path),
            );
        }
    }

    #[Test]
    public function theCurrentPageIsMarkedForAssistiveTechnology(): void
    {
        $this->assertStringContainsString('aria-current="page"', $this->render('/first/a'));
    }

    /**
     * The trail marker has to mark one branch, not all of them.
     *
     * This is asserted because the condition behind it is the kind that fails
     * open: written with an escaped ampersand - which reads as correct and is
     * even valid XML - Fluid does not parse it as a conjunction at all and the
     * whole expression evaluates true, so every top level item is marked and
     * the menu answers "where am I" with "everywhere".
     */
    #[Test]
    public function onlyTheBranchLeadingToTheCurrentPageIsMarkedActive(): void
    {
        $mainMenu = $this->navigation($this->render('/first/a'), 'theme-nav-main');

        $this->assertSame(
            1,
            substr_count($mainMenu, 'theme-nav-main__item--active'),
            'Exactly one top level item leads to the current page.',
        );

        $matched = preg_match(
            '#<li[^>]*theme-nav-main__item--active[^>]*>\s*<a[^>]*>\s*([^<]+?)\s*</a>#s',
            $mainMenu,
            $active,
        );
        $this->assertSame(1, $matched, 'No active item was rendered at all.');
        $this->assertSame('First section', trim($active[1]));
    }

    #[Test]
    public function theBreadcrumbShowsTheTrailAndDoesNotLinkTheCurrentPage(): void
    {
        $body = $this->render('/first/a/deep');

        $this->assertStringContainsString('theme-breadcrumb', $body);
        $this->assertStringContainsString('First section', $body);
        $this->assertStringContainsString('A page in the first section', $body);

        // The last item is the current page: marked, and not a link.
        $this->assertMatchesRegularExpression(
            '#<li[^>]*class="theme-breadcrumb__item"[^>]*aria-current="page"[^>]*>\s*Three levels down\s*</li>#',
            $body,
            'The current page must be the last breadcrumb item and must not be a link.',
        );
    }

    /**
     * Three navigations on one page are indistinguishable to a screen reader
     * without one, and the landmark list is exactly how that reader moves
     * around the page.
     */
    #[Test]
    public function everyNavigationLandmarkIsLabelled(): void
    {
        $body = $this->render('/first/a');

        preg_match_all('#<nav\b[^>]*>#', $body, $matches);
        $this->assertNotEmpty($matches[0], 'No navigation landmark was rendered at all.');

        foreach ($matches[0] as $nav) {
            $this->assertMatchesRegularExpression(
                '#aria-label="[^"]+"#',
                $nav,
                sprintf('A navigation landmark has no accessible name: %s', $nav),
            );
        }
    }

    /**
     * The toggle only does anything once step 4's script flips its attribute,
     * and the stylesheet hides it until then. What has to be right *now* is
     * the pairing the script and assistive technology both rely on.
     */
    #[Test]
    public function theMenuToggleIsWiredToTheListItControls(): void
    {
        $body = $this->render('/first');

        $matched = preg_match('#<button[^>]*class="theme-nav-main__toggle"[^>]*>#', $body, $button);
        $this->assertSame(1, $matched, 'The main menu has no toggle button.');

        $this->assertMatchesRegularExpression('#aria-expanded="false"#', $button[0]);
        $this->assertSame(1, preg_match('#aria-controls="([^"]+)"#', $button[0], $controls));

        $this->assertMatchesRegularExpression(
            sprintf('#<ul[^>]*id="%s"#', preg_quote($controls[1], '#')),
            $body,
            'The toggle points at an id that no list carries.',
        );
    }

    /**
     * Placement follows the backend layout: the left column is what the
     * sidebar layout has, so it is the layout that gets the left navigation.
     */
    #[Test]
    public function onlyTheSidebarLayoutCarriesTheSubNavigation(): void
    {
        $withSidebar = $this->render('/first');
        $this->assertStringContainsString('theme-nav-sub', $withSidebar);

        // The root inherits nothing - its own next-level setting is for its
        // children - so it renders the bare "default" layout.
        $this->assertStringNotContainsString('theme-nav-sub', $this->render('/'));
    }
}
