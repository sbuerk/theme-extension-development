<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Holds the shipped showcase tree to being a complete showcase.
 *
 * The seeding *mechanism* is `sbuerk/data-factory`'s and is tested there. This
 * covers the *result*: that the tree a developer gets from
 * `data-factory:import theme-demo` actually demonstrates the theme, rather than
 * demonstrating whichever parts someone remembered.
 *
 * The two assertions that matter most read the repository rather than a list
 * written here - the registered backend layouts, and the content types the
 * TypoScript renders. A list maintained in a test is a list that goes stale the
 * first time someone adds a layout, and it goes stale silently, because a demo
 * page nobody seeded is a page nobody misses. Deriving the expectation from the
 * source makes the omission fail here instead.
 */
final class ShowcaseTreeTest extends AbstractFunctionalTestCase
{
    use DataFactoryImportTrait;
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    private const SCENARIO = 'Configuration/DataFactory/theme-demo/Scenario.yaml';

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'sbuerk/data-factory',
    ];

    /**
     * `list` is the Extbase plugin container, not a type an editor picks from
     * the "new content element" wizard - it is covered by
     * `ExtbasePluginRenderingTest` and is not seeded without a plugin to put in
     * it.
     *
     * The two categorized menus select through `sys_category` and an MM table,
     * and the showcase seeds no categories. They are seeded with no category
     * selected, which renders an empty menu - the correct rendering of "nothing
     * chosen" - so they are present in the tree but cannot be asserted on by
     * their output. It is a named gap, not an oversight.
     */
    private const NOT_SEEDED = ['list', 'menu_categorized_pages', 'menu_categorized_content'];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->createDefaultFileStorage();
        $this->importSeedSet('theme-demo');

        $this->setUpThemeSite(identifier: 'demo', websiteTitle: 'Theme demo');
    }

    private function render(string $path): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/' . ltrim($path, '/')),
        )->getBody();
    }

    private static function extensionPath(string $relative): string
    {
        return dirname(__DIR__, 2) . '/' . ltrim($relative, '/');
    }

    /**
     * The navigation landmark with the given class, so a "this page is not in
     * the menu" assertion cannot be satisfied by the page merely being absent
     * from somewhere else in the document.
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

    /**
     * @return list<string> The `backend_layout` values of every seeded page,
     *         with a page that declares none reported as `default` - which is
     *         the identifier `PageLayoutResolver` falls back to.
     */
    private function seededLayouts(): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('uid', 'backend_layout')
            ->from('pages')
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values(array_unique(array_map(
            static fn(array $row): string => ($row['backend_layout'] ?? '') === '' ? 'default' : (string)$row['backend_layout'],
            $rows,
        )));
    }

    /**
     * Every backend layout the extension registers, read from the TsConfig it
     * registers them in.
     *
     * @return list<string>
     */
    private static function registeredLayouts(): array
    {
        $files = glob(self::extensionPath('Configuration/PageTsConfig/BackendLayouts') . '/*.tsconfig');
        $identifiers = [];

        foreach ($files ?: [] as $file) {
            if (preg_match('/^mod\.web_layout\.BackendLayouts\.([a-z_0-9]+)\s*\{/m', (string)file_get_contents($file), $matched) === 1) {
                $identifiers[] = $matched[1];
            }
        }

        sort($identifiers);

        return $identifiers;
    }

    /**
     * Every content type the theme's TypoScript defines a rendering for.
     *
     * `tt_content.<type> =<` is how each one is opened, whether it copies
     * `lib.contentElement` or another type, so this finds the ones derived from
     * a sibling as well.
     *
     * @return list<string>
     */
    private static function renderedContentTypes(): array
    {
        preg_match_all(
            '/^tt_content\.([a-z_0-9]+)\s*=</m',
            (string)file_get_contents(self::extensionPath('Configuration/TypoScript/ContentElements.typoscript')),
            $matched,
        );

        $types = array_values(array_unique(array_diff($matched[1], self::NOT_SEEDED)));
        sort($types);

        return $types;
    }

    /**
     * @return list<string> The `CType` of every seeded content element.
     */
    private function seededContentTypes(): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('CType')
            ->from('tt_content')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values(array_unique(array_map(
            static fn(array $row): string => (string)$row['CType'],
            $rows,
        )));
    }

    /**
     * A layout that is registered but never seeded is a layout nobody has ever
     * looked at. It has a page module grid, a template and a colPos contract,
     * and the first person to select it finds out whether any of that works.
     */
    #[Test]
    public function everyRegisteredBackendLayoutIsUsedByASeededPage(): void
    {
        $registered = self::registeredLayouts();
        $this->assertNotEmpty($registered, 'No backend layout was found at all - the path is wrong.');

        $unused = array_values(array_diff($registered, $this->seededLayouts()));
        sort($unused);

        $this->assertSame(
            [],
            $unused,
            'These backend layouts are registered but no seeded page uses them: ' . implode(', ', $unused),
        );
    }

    /**
     * The counterpart: every type the theme renders is on a page somebody can
     * open. A rendering definition with nothing to render is how a broken
     * template survives a release.
     */
    #[Test]
    public function everyRenderedContentTypeAppearsInTheSeededTree(): void
    {
        $rendered = self::renderedContentTypes();
        $this->assertNotEmpty($rendered, 'No content type was found at all - the path is wrong.');

        $missing = array_values(array_diff($rendered, $this->seededContentTypes()));
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            'These content types are rendered but never seeded: ' . implode(', ', $missing),
        );
    }

    /**
     * `backend_layout` is a plain field of the record, written as the scenario
     * declares it, and the page is only right if the frontend agrees.
     *
     * Asserted through the frontend rather than through the row, because the
     * row being right and the page rendering through another template is
     * exactly the failure this is for: `data = pagelayout` resolves through
     * `PageLayoutResolver`, which honours an ancestor's
     * `backend_layout_next_level` and can disagree with the field.
     *
     * @param string $path   The slug of the seeded page.
     * @param string $layout The layout identifier it has to resolve to.
     */
    #[DataProvider('seededPageLayouts')]
    #[Test]
    public function aSeededPageRendersThroughTheLayoutItDeclares(string $path, string $layout): void
    {
        $this->assertStringContainsString(
            sprintf('data-theme-page-layout="%s"', $layout),
            $this->render($path),
            sprintf('The page "%s" did not render through the "%s" layout.', $path, $layout),
        );
    }

    /**
     * @return \Generator<string, array{path: string, layout: string}>
     */
    public static function seededPageLayouts(): \Generator
    {
        foreach ([
            '/' => 'start',
            '/typography' => 'content',
            '/media' => 'content',
            // Declares no "backend_layout" at all, so it is the only page that
            // reaches the hard-coded fallback in "PageLayoutResolver".
            '/empty' => 'default',
            '/elements' => 'content',
            '/elements/core' => 'content_sidebar',
            '/elements/menu' => 'content_sidebar',
            '/elements/theme' => 'content',
            '/styleguide' => 'styleguide',
            '/forms' => 'forms',
        ] as $path => $layout) {
            yield $path => ['path' => $path, 'layout' => $layout];
        }
    }

    /**
     * The layout attribute and the template that actually ran are two different
     * things - the attribute comes from the same TypoScript chain as the
     * template name, so both are wrong together only if the chain is wrong, and
     * both are right together only if the template exists.
     *
     * `content_sidebar` is the one layout with an independent tell:
     * `Templates/Page/ContentSidebar.html` is the only template that defines
     * the `Aside` section, so it is the only one that puts
     * `.theme-page__aside` on the page at all.
     */
    #[Test]
    public function theSidebarLayoutIsTheOnlyOneThatRendersAnAside(): void
    {
        $this->assertStringContainsString('theme-page__aside', $this->render('/elements/core'));
        $this->assertStringNotContainsString('theme-page__aside', $this->render('/elements/theme'));
    }

    /**
     * Siblings under one parent resolving to different layouts is what proves
     * the layout is read per page rather than inherited down the branch. Both
     * are children of "Elements", and one has a sidebar and the other does not.
     */
    #[Test]
    public function siblingPagesResolveTheirOwnLayoutRatherThanTheBranchOne(): void
    {
        $this->assertStringContainsString('data-theme-page-layout="content_sidebar"', $this->render('/elements/menu'));
        $this->assertStringContainsString('data-theme-page-layout="content"', $this->render('/elements/theme'));
    }

    /**
     * `nav_hide`, not `hidden`: the page has to be reachable by URL and absent
     * from the menu. A `hidden` page returns 404 and needs a backend preview
     * link carrying a valid hash, which defeats seeding it in the first place.
     *
     * The two halves are asserted separately because each passes on its own for
     * the wrong reason - a 404 page is also absent from the menu.
     */
    #[Test]
    public function theStyleguidePageIsReachableInTheFrontend(): void
    {
        $body = $this->render('/styleguide');

        $this->assertStringContainsString('data-theme-page-layout="styleguide"', $body);
        $this->assertStringNotContainsString('Page Not Found', $body);
    }

    #[Test]
    public function theStyleguidePageIsNotInTheMainNavigation(): void
    {
        $menu = $this->navigation($this->render('/'), 'theme-nav-main');

        $this->assertStringNotContainsString('/styleguide', $menu);
        // The form showcase is kept out of the menu the same way.
        $this->assertStringNotContainsString('/forms', $menu);
        // The other new pages are in it, so this is not passing because the
        // menu came back empty.
        $this->assertStringContainsString('/elements', $menu);
    }

    /**
     * The inline children of a content element are ordered by the parent's
     * relation, not by their own `sorting`: the parent declares the ids of its
     * children as a list, and that list is the order they have to render in.
     *
     * Read through the rendered page rather than through `sorting_foreign`,
     * because the column being right while the template reads the relation the
     * other way round is a real and silent failure - and the rendering is what
     * a person actually sees.
     */
    #[Test]
    public function inlineChildrenKeepTheirDeclarationOrderInTheFrontend(): void
    {
        $declared = $this->declaredLinkListLabels();
        $this->assertGreaterThanOrEqual(
            2,
            count($declared),
            'The definition declares fewer than two link list children, so nothing about order can be proven.',
        );

        $body = $this->render('/elements/theme');
        $matched = preg_match(
            '#<div[^>]*data-ctype="theme_linklist"[^>]*>(.*?)(?=<div[^>]*class="theme-content-element theme-content-element--|</main>)#s',
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, 'No "theme_linklist" element was rendered.');

        $fragment = $matches[0];
        $positions = [];

        foreach ($declared as $label) {
            $at = strpos($fragment, $label);
            $this->assertNotFalse(
                $at,
                sprintf('The declared link list entry "%s" was not rendered at all.', $label),
            );
            $positions[] = $at;
        }

        $ordered = $positions;
        sort($ordered);
        $this->assertSame(
            $ordered,
            $positions,
            'The inline children rendered in a different order than the definition declares them.',
        );
    }

    /**
     * The `link_label` of the link list's inline children, in the order the
     * scenario lists them in the parent's relation field.
     *
     * Read from the scenario rather than written out here, so the test states
     * the promise - the declared order survives - instead of restating today's
     * demo copy and having to be edited whenever that copy changes.
     *
     * A child may deliberately carry no `link_label`, to demonstrate the
     * fallback to the resolved URL. Those are skipped: what is rendered for
     * them is a URL rather than the declared value, so they say nothing about
     * order without hard coding the resolved link.
     *
     * @return list<string>
     */
    private function declaredLinkListLabels(): array
    {
        $scenario = Yaml::parseFile(self::extensionPath(self::SCENARIO));
        $relations = [];
        $labels = [];

        $walk = static function (array $items) use (&$walk, &$relations, &$labels): void {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $self = is_array($item['self'] ?? null) ? $item['self'] : [];
                if (($self['CType'] ?? null) === 'theme_linklist') {
                    $relations[] = (string)($self['tx_theme_list_items'] ?? '');
                }
                if (array_key_exists('link', $self) && isset($self['id'])) {
                    $labels[(int)$self['id']] = (string)($self['link_label'] ?? '');
                }
                foreach ($item['entities'] ?? [] as $nested) {
                    if (is_array($nested)) {
                        $walk($nested);
                    }
                }
                if (is_array($item['children'] ?? null)) {
                    $walk($item['children']);
                }
            }
        };
        $walk($scenario['entities']['page'] ?? []);

        // One element, because the rendered fragment below is looked up once.
        $this->assertCount(1, $relations, 'The scenario has to declare exactly one "theme_linklist" element.');

        $found = [];
        foreach (array_map('intval', explode(',', $relations[0])) as $uid) {
            // A relation naming a child the scenario does not declare would
            // otherwise shorten the list instead of failing.
            $this->assertArrayHasKey($uid, $labels, sprintf('The link list names list item %d, which is not declared.', $uid));
            if ($labels[$uid] !== '') {
                $found[] = $labels[$uid];
            }
        }

        return $found;
    }

    /**
     * The showcase pages exist to be looked at, so none of them may carry the
     * core's "no rendering definition" notice - which is what a `CType` with no
     * TypoScript renders instead of failing.
     */
    #[DataProvider('showcasePages')]
    #[Test]
    public function aShowcasePageRendersEveryElementOnIt(string $path): void
    {
        $this->assertStringNotContainsString('has no rendering definition', $this->render($path));
    }

    /**
     * @return \Generator<string, array{path: string}>
     */
    public static function showcasePages(): \Generator
    {
        foreach (['/elements/core', '/elements/menu', '/elements/theme'] as $path) {
            yield $path => ['path' => $path];
        }
    }
}
