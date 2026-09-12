<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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

    private const SCENARIO = 'Configuration/DataFactory/theme-demo/Scenario.yaml';

    /**
     * The instances require rte_ckeditor, so the rich text of the showcase is
     * written through the processing of the theme's preset there. Loaded
     * here for the same reason as in `AbstractInstanceSeedTestCase`: the
     * seed goes through the same processing it goes through in an instance,
     * rather than through the core's short default list of tags.
     */
    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'sbuerk/data-factory',
    ];

    /**
     * The page every classic CType has below it, as `/elements/core/<CType>`.
     */
    private const CORE_ELEMENTS_PAGE = 6;

    /**
     * The fields whose values change how a classic CType looks, per CType.
     * The values themselves are read from the form - see `offeredValues()` -
     * so a value added to the TCA or the page TSconfig without an element
     * showing it fails here.
     *
     * The CTypes left out have no such field: `text`, `textmedia` (the
     * orientations are the ones of `textpic`, shown there), `div`, `html`
     * and `shortcut`.
     */
    private const VARIANT_FIELDS = [
        'header' => ['header_layout', 'header_position'],
        'textpic' => ['imageorient'],
        'image' => ['imagecols'],
        'bullets' => ['layout', 'bullets_type'],
        'table' => ['table_class'],
        'uploads' => ['uploads_type'],
    ];

    /**
     * `imagecols` offers one to eight columns. At the gallery width of the
     * theme a fifth column is a row of thumbnails, so the page shows the
     * counts up to four - a stated limit, not a value that was forgotten.
     */
    private const VARIANT_MAXIMUM = ['imagecols' => 4];

    /**
     * The appearance fields every CType carries, all shown on `/elements/frames`.
     */
    private const APPEARANCE_FIELDS = [
        'frame_class',
        'space_before_class',
        'space_after_class',
        'header_position',
        'tx_theme_header_style',
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

        $this->writeSiteConfiguration(
            'demo',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme demo',
            ) + [
                'dependencies' => ['sbuerk/theme-extension-development'],
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
     * TypoScript renders instead of failing - and every one of them renders
     * through the theme at all.
     */
    #[DataProvider('showcasePages')]
    #[Test]
    public function aShowcasePageRendersEveryElementOnIt(string $path): void
    {
        $body = $this->render($path);

        $this->assertStringContainsString('data-theme-page-layout=', $body, sprintf('"%s" did not render through the theme.', $path));
        $this->assertStringNotContainsString('has no rendering definition', $body);
    }

    /**
     * Every page the scenario declares, by its slug - read from the scenario,
     * so a page added to it is covered without being listed here.
     *
     * @return \Generator<string, array{path: string}>
     */
    public static function showcasePages(): \Generator
    {
        $scenario = Yaml::parseFile(self::extensionPath(self::SCENARIO));
        $slugs = [];
        $walk = static function (array $pages) use (&$walk, &$slugs): void {
            foreach ($pages as $page) {
                $slugs[] = (string)($page['self']['slug'] ?? '');
                $walk(is_array($page['children'] ?? null) ? $page['children'] : []);
            }
        };
        $walk($scenario['entities']['page'] ?? []);

        foreach ($slugs as $slug) {
            yield $slug => ['path' => $slug];
        }
    }

    /**
     * The classic CTypes: every type the TypoScript renders that is neither a
     * menu nor one of the theme's own - the set `/elements/core` shows.
     *
     * @return list<string>
     */
    private static function classicContentTypes(): array
    {
        return array_values(array_filter(
            self::renderedContentTypes(),
            static fn(string $type): bool => !str_starts_with($type, 'menu_') && !str_starts_with($type, 'theme_'),
        ));
    }

    /**
     * @return array{uid: int, pid: int}|null The page with that slug.
     */
    private function pageBySlug(string $slug): ?array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', 'pid')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? ['uid' => (int)$row['uid'], 'pid' => (int)$row['pid']] : null;
    }

    /**
     * @return list<array<string, mixed>> The content elements of one CType on one page.
     */
    private function elementsOn(int $pageUid, string $type): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($type)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    /**
     * The values the form offers for a field of a CType on a page: the items
     * of the TCA, with the `removeItems` and `addItems` of the page TSconfig
     * applied - the type specific part merged over the field, the way
     * `PageTsConfigMerged` does it for the form.
     *
     * @return list<string>
     */
    private static function offeredValues(int $pageUid, string $type, string $field): array
    {
        $values = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns'][$field]['config']['items'] ?? [] as $item) {
            $value = (string)($item['value'] ?? '');
            if ($value !== '--div--') {
                $values[] = $value;
            }
        }

        $configuration = BackendUtility::getPagesTSconfig($pageUid)['TCEFORM.']['tt_content.'][$field . '.'] ?? [];
        $typeSpecific = $configuration['types.'][$type . '.'] ?? [];
        unset($configuration['types.']);
        if (is_array($typeSpecific)) {
            ArrayUtility::mergeRecursiveWithOverrule($configuration, $typeSpecific);
        }

        $removed = GeneralUtility::trimExplode(',', (string)($configuration['removeItems'] ?? ''), true);
        $added = array_map('strval', array_keys(is_array($configuration['addItems.'] ?? null) ? $configuration['addItems.'] : []));
        $values = array_values(array_unique(array_merge(array_diff($values, $removed), $added)));

        $maximum = self::VARIANT_MAXIMUM[$field] ?? null;
        if ($maximum !== null) {
            $values = array_values(array_filter($values, static fn(string $value): bool => (int)$value <= $maximum));
        }
        sort($values);

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @return list<string> The values the form offers that no element shows.
     */
    private static function unshownValues(array $elements, int $pageUid, string $type, string $field): array
    {
        $shown = array_map(static fn(array $element): string => (string)($element[$field] ?? ''), $elements);

        return array_values(array_diff(self::offeredValues($pageUid, $type, $field), $shown));
    }

    /**
     * Every classic CType has a page of its own below "Core elements", named
     * after it, which renders every element of that CType seeded on it.
     *
     * The CTypes are read from the TypoScript, like the completeness check
     * above: a classic type the theme starts rendering fails here until it
     * has its page.
     */
    #[Test]
    public function everyClassicContentTypeHasAPageOfItsOwn(): void
    {
        $types = self::classicContentTypes();
        $this->assertContains('textpic', $types, 'The classic CTypes were not found - the derivation is wrong.');

        $missing = [];
        foreach ($types as $type) {
            $slug = '/elements/core/' . $type;
            $page = $this->pageBySlug($slug);
            if ($page === null || $page['pid'] !== self::CORE_ELEMENTS_PAGE) {
                $missing[] = $slug;
                continue;
            }
            $elements = $this->elementsOn($page['uid'], $type);
            $this->assertNotSame([], $elements, sprintf('"%s" shows no "%s" element.', $slug, $type));
            $this->assertSame(
                count($elements),
                substr_count($this->render($slug), sprintf('data-ctype="%s"', $type)),
                sprintf('"%s" does not render every "%s" element seeded on it.', $slug, $type),
            );
        }

        $this->assertSame([], $missing, 'These classic CTypes have no page below "Core elements": ' . implode(', ', $missing));
    }

    /**
     * @return \Generator<string, array{type: string, field: string}>
     */
    public static function classicVariantFields(): \Generator
    {
        foreach (self::VARIANT_FIELDS as $type => $fields) {
            foreach ($fields as $field) {
                yield $type . ', ' . $field => ['type' => $type, 'field' => $field];
            }
        }
    }

    /**
     * The page of a CType shows every value of the fields that change how it
     * looks - textpic in every orientation, the table in every class the
     * form offers, including those the page TSconfig adds.
     */
    #[DataProvider('classicVariantFields')]
    #[Test]
    public function thePageOfAClassicContentTypeShowsEveryVariant(string $type, string $field): void
    {
        $page = $this->pageBySlug('/elements/core/' . $type);
        $this->assertNotNull($page, sprintf('"%s" has no page.', $type));
        $this->assertNotSame([], self::offeredValues($page['uid'], $type, $field), sprintf('The form offers no value for "%s" - the field is wrong.', $field));

        $elements = $this->elementsOn($page['uid'], $type);
        if ($type === 'bullets' && $field === 'layout') {
            // The layout applies to the two lists; the definition list ignores it.
            $elements = array_values(array_filter($elements, static fn(array $element): bool => (int)$element['bullets_type'] !== 2));
        }

        $this->assertSame(
            [],
            self::unshownValues($elements, $page['uid'], $type, $field),
            sprintf('No "%s" element on its page shows these values of "%s".', $type, $field),
        );
    }

    /**
     * The layout "Icons" is only an icon list with an icon picked - without
     * one it renders the markers of layout 0 - so the seeded element picks
     * one, and the page shows the component rather than a plain list.
     */
    #[Test]
    public function theSeededIconListShowsThePickedIcon(): void
    {
        $body = $this->render('/elements/core/bullets');

        $this->assertStringContainsString('<ul class="theme-list theme-list--icon">', $body);
        $this->assertMatchesRegularExpression(
            '#<li><span class="theme-list__icon" aria-hidden="true"><svg\b[^>]*\bclass="theme-icon"#',
            $body,
            'The seeded icon list renders no icon in its slot.',
        );
    }

    /**
     * @return \Generator<string, array{field: string}>
     */
    public static function appearanceFields(): \Generator
    {
        foreach (self::APPEARANCE_FIELDS as $field) {
            yield $field => ['field' => $field];
        }
    }

    /**
     * `/elements/frames` shows every value of the appearance fields the form
     * offers - the bands `ContentElementAppearance.tsconfig` adds to
     * `frame_class` included, the frames it removes excluded.
     */
    #[DataProvider('appearanceFields')]
    #[Test]
    public function theFramesPageShowsEveryAppearanceValue(string $field): void
    {
        $page = $this->pageBySlug('/elements/frames');
        $this->assertNotNull($page, 'The Frames page is missing.');

        $this->assertSame(
            [],
            self::unshownValues($this->elementsOn($page['uid'], 'text'), $page['uid'], 'text', $field),
            sprintf('No text element on the Frames page shows these values of "%s".', $field),
        );
    }
}
