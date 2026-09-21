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
     * The CTypes left out have no such field: `textmedia` (the orientations
     * are the ones of `textpic`, shown there), `div`, `html` and `shortcut`.
     */
    private const VARIANT_FIELDS = [
        'header' => ['header_layout', 'header_position'],
        'text' => ['layout'],
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

    /**
     * The page the theme elements with a page of their own sit below, as
     * `/elements/theme/<name>`.
     */
    private const THEME_ELEMENTS_PAGE = 8;

    /**
     * The section of composed pages - whole pages built from elements that
     * are demonstrated one at a time elsewhere in the tree.
     */
    private const EXAMPLES_SECTION = '/examples';

    /**
     * The fewest kinds of content element a composed page is made of - see
     * `anExamplePageIsMadeOfSeveralKindsOfElement()` for why it is three.
     */
    private const EXAMPLE_PAGE_MINIMUM_KINDS = 3;

    /**
     * The theme elements that have a page of their own below "Theme
     * elements", by the last segment of its slug.
     */
    private const THEME_ELEMENT_PAGES = [
        'theme_text_icon' => 'text-icon',
        'theme_features' => 'features',
        'theme_stats' => 'stats',
        'theme_steps' => 'steps',
        'theme_hero' => 'hero',
        'theme_hero_small' => 'hero',
        'theme_hero_text_only' => 'hero',
        'theme_cta' => 'cta',
        'theme_testimonial' => 'quote',
        'theme_card_group' => 'card-group',
        'theme_timeline' => 'timeline',
        'theme_teaser_list' => 'teaser-list',
        'theme_carousel' => 'carousel',
        'theme_split_tiles' => 'split-tiles',
        'theme_external_media' => 'external-media',
        'theme_pricing' => 'pricing',
    ];

    /**
     * The fields whose values change how one of those elements looks. The
     * values are read from the form, as for the classic CTypes.
     */
    private const THEME_VARIANT_FIELDS = [
        'theme_text_icon' => ['tx_theme_icon_position', 'tx_theme_icon_shape', 'tx_theme_icon_size'],
        'theme_features' => ['layout', 'tx_theme_columns'],
        'theme_hero' => ['tx_theme_hero_layout'],
        'theme_hero_small' => ['tx_theme_hero_layout'],
        'theme_hero_text_only' => ['tx_theme_hero_layout'],
        'theme_cta' => ['tx_theme_cta_tone', 'tx_theme_cta_width'],
        'theme_testimonial' => ['tx_theme_quote_style'],
        'theme_card_group' => ['layout', 'tx_theme_columns'],
        'theme_timeline' => ['tx_theme_sort_direction'],
        'theme_split_tiles' => ['layout'],
        'theme_external_media' => ['tx_theme_embed_ratio'],
    ];

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
            '/layouts' => 'content',
            '/layouts/two-columns' => 'two_columns',
            '/layouts/two-columns-wide' => 'two_columns_wide',
            '/layouts/three-columns' => 'three_columns',
            '/layouts/article' => 'article',
            '/layouts/cover' => 'cover',
            '/layouts/bands' => 'bands',
            // The composed pages. They are the one part of the tree that
            // picks a layout for what the page is rather than to demonstrate
            // the layout, so which one each of them picked is worth pinning
            // down: a composed page silently falling back to "content" would
            // still render and would stop being what it is for.
            '/examples' => 'content',
            '/examples/album' => 'content',
            '/examples/pricing' => 'content',
            '/examples/journal' => 'content_sidebar',
            '/examples/journal/composing-a-page' => 'article',
            '/examples/product' => 'bands',
            '/examples/campaign' => 'cover',
            '/examples/carousel-landing' => 'bands',
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
     * @return \Generator<string, array{path: string, labels: list<string>}>
     */
    public static function layoutDemoPages(): \Generator
    {
        // The label of every box, which names the slot and the colPos it was
        // seeded into. They are the headers of ordinary content elements, so
        // a label appearing in the rendered page means that element reached
        // the column its layout declares and that the template reads it.
        foreach ([
            '/layouts/two-columns' => ['Stage - colPos 2', 'Main content - colPos 0', 'Second column - colPos 3'],
            '/layouts/two-columns-wide' => ['Stage - colPos 2', 'Main content - colPos 0', 'Second column - colPos 3'],
            '/layouts/three-columns' => ['Stage - colPos 2', 'Main content - colPos 0', 'Second column - colPos 3', 'Third column - colPos 4'],
            '/layouts/article' => ['Stage - colPos 2', 'The article - colPos 0', 'The aside - colPos 1'],
            '/layouts/cover' => ['Cover - colPos 0'],
            '/layouts/bands' => ['First band - colPos 0', 'Second band - colPos 3', 'Third band - colPos 4'],
        ] as $path => $labels) {
            yield $path => ['path' => $path, 'labels' => $labels];
        }
    }

    /**
     * Every column a layout declares is labelled on its demo page.
     *
     * This is what makes the section readable as a wireframe - a look at the
     * page says which colPos is which - and it is the only assertion that
     * covers the seeded records reaching colPos 3 and 4 at all. `colPos` is
     * an ordinary field written as the scenario declares it, so an element
     * that never arrived in its column is a page missing one box, which
     * nothing else here would notice.
     *
     * @param list<string> $labels
     */
    #[DataProvider('layoutDemoPages')]
    #[Test]
    public function aLayoutDemoPageLabelsEveryColumnItDeclares(string $path, array $labels): void
    {
        $body = $this->render($path);

        foreach ($labels as $label) {
            $this->assertStringContainsString(
                $label,
                $body,
                sprintf('The page "%s" does not label the column "%s".', $path, $label),
            );
        }
    }

    /**
     * The footer columns are filled once, on the site root, and every page
     * below it renders them.
     *
     * `lib.content.footer1` to `footer4` and `lib.content.footermeta` are the
     * five content objects of the theme that carry `slide = -1`: a `CONTENT`
     * object that finds nothing in its `colPos` walks up the rootline until a
     * page has something there (`docs/architecture/page-rendering.md`). The
     * whole point of that is that a site edits its footer once.
     *
     * `slide` sits on the object, **not** inside `select` - which is where all
     * five of them carried it until this test was written, and why none of
     * them ever slid. Spelling it `select.slide` anywhere is spelling the
     * defect.
     *
     * Nothing demonstrated it and no test covered it. The seed put nothing
     * into colPos 10 to 14 anywhere, so the `theme-site-footer__inner` of all
     * fifty-eight pages rendered empty - a blank band, and a mechanism the
     * documentation described and the branch did not keep.
     *
     * Two things have to hold together, which is why they are asserted in one
     * test: the columns are declared on **one** page, read from the scenario
     * rather than from a list here; and a page far below that one renders
     * them anyway. Either alone proves nothing - a footer seeded on every
     * page would satisfy the second, and a footer seeded on the root and
     * nowhere else would satisfy the first while rendering on the root only.
     */
    #[Test]
    public function theFooterColumnsAreSeededOnTheSiteRootAndSlideDownToEveryPageBelowIt(): void
    {
        $pagesWithFooterContent = [];
        $walk = static function (array $pages) use (&$walk, &$pagesWithFooterContent): void {
            foreach ($pages as $page) {
                $id = (int)($page['self']['id'] ?? 0);
                foreach ($page['entities']['content'] ?? [] as $element) {
                    if (in_array((int)($element['self']['colPos'] ?? 0), [10, 11, 12, 13, 14], true)) {
                        $pagesWithFooterContent[$id] = true;
                    }
                }
                $walk(is_array($page['children'] ?? null) ? $page['children'] : []);
            }
        };
        $walk(Yaml::parseFile(self::extensionPath(self::SCENARIO))['entities']['page'] ?? []);

        $this->assertSame(
            [1],
            array_keys($pagesWithFooterContent),
            'The footer columns are edited once on the site root. A second page filling colPos 10 to 14 stops the slide there and hides the mechanism.',
        );

        // "/elements/core/header" is four levels below the root, which is as
        // far from it as the tree goes.
        $root = $this->footerOf($this->render('/'));
        $deep = $this->footerOf($this->render('/elements/core/header'));

        foreach (['About', 'Sections', 'Reference', 'Elsewhere'] as $column) {
            $this->assertStringContainsString($column, $root, sprintf('The site root does not render the footer column "%s".', $column));
            $this->assertStringContainsString($column, $deep, sprintf('A page four levels below the root does not inherit the footer column "%s".', $column));
        }

        foreach (['theme-site-footer__columns', 'theme-site-footer__meta'] as $part) {
            $this->assertStringContainsString($part, $deep, sprintf('The footer of a page below the root renders no "%s".', $part));
        }
    }

    /**
     * The content of a rendered page, without the chrome around it.
     *
     * Anything counting the elements of a page has to read this rather than
     * the document: the footer columns of the site root slide into every page
     * below it, so the document of a page with four `text` elements on it
     * carries seven.
     */
    private function mainOf(string $body): string
    {
        $matched = preg_match('#<main\b[^>]*>(.*)</main>#s', $body, $main);
        $this->assertSame(1, $matched, 'The page renders no main region at all.');

        return $main[1];
    }

    /**
     * The site footer of a rendered page, so an assertion about it cannot be
     * satisfied by the same words appearing in the content above it.
     */
    private function footerOf(string $body): string
    {
        $matched = preg_match('#<footer class="theme-site-footer[^"]*"[^>]*>(.*?)</footer>#s', $body, $footer);
        $this->assertSame(1, $matched, 'The page renders no site footer at all.');

        return $footer[1];
    }

    /**
     * The styleguide renders through its own layout, not the 404 page - a
     * page that is `hidden` rather than merely out of a menu would answer
     * 404, which is why the two are asserted apart from the navigation.
     */
    #[Test]
    public function theStyleguidePageIsReachableInTheFrontend(): void
    {
        $body = $this->render('/styleguide');

        $this->assertStringContainsString('data-theme-page-layout="styleguide"', $body);
        $this->assertStringNotContainsString('Page Not Found', $body);
    }

    /**
     * @return \Generator<string, array{path: string}>
     */
    public static function showcaseSections(): \Generator
    {
        // The sections the maintainer chose to have in the navigation: the
        // content elements, the typography, the page layouts, the composed
        // example pages, and the component library with the form showcase.
        foreach (['/elements', '/typography', '/layouts', '/examples', '/styleguide', '/forms'] as $path) {
            yield $path => ['path' => $path];
        }
    }

    /**
     * Every showcase section is a link of the main navigation, looked up in
     * the navigation landmark rather than anywhere on the page - the start
     * page links to several of them from its text as well.
     */
    #[DataProvider('showcaseSections')]
    #[Test]
    public function aShowcaseSectionIsInTheMainNavigation(string $path): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('#<a class="theme-nav-main__link" href="%s"#', preg_quote($path, '#')),
            $this->navigation($this->render('/'), 'theme-nav-main'),
            sprintf('"%s" is not a link of the main navigation.', $path),
        );
    }

    /**
     * The main navigation is two levels deep: the pages of a section are
     * reached from it as well, the pages of a CType one level further through
     * the sub navigation of the section.
     */
    #[Test]
    public function thePagesOfTheSectionsAreInTheNavigation(): void
    {
        $menu = $this->navigation($this->render('/'), 'theme-nav-main');
        foreach (['/typography/text', '/typography/article', '/elements/core', '/elements/frames'] as $path) {
            $this->assertStringContainsString(sprintf('href="%s"', $path), $menu, sprintf('"%s" is not in the main navigation.', $path));
        }
        // The layout fallback fixture is reached by URL, not from the menu,
        // because a reviewer has no reason to open a page that exists to
        // prove a fallback. Room is no longer the spare argument it was: the
        // showcase has seven top level entries since the composed pages
        // joined it, and seven is what the acceptance test of the header row
        // measures at 1280 pixels, so putting "/empty" back would be the
        // eighth and would need measuring again.
        $this->assertStringNotContainsString('href="/empty"', $menu);

        $sub = $this->navigation($this->render('/elements/core/bullets'), 'theme-nav-sub');
        $this->assertStringContainsString('href="/elements/core/textpic"', $sub);
        $this->assertMatchesRegularExpression('#href="/elements/core/bullets"[^>]*aria-current="page"#', $sub);
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
     * Only the link list of "Theme elements" is read. That is the one whose
     * relation deliberately names its children out of uid order, and it is the
     * page the fragment below is taken from; the composed pages of
     * `/examples` use the element as an ordinary archive list, in uid order,
     * which would prove nothing here. Labels are collected from the whole
     * tree, because a child is an ordinary record and nothing but the relation
     * ties it to its parent.
     *
     * @return list<string>
     */
    private function declaredLinkListLabels(): array
    {
        $scenario = Yaml::parseFile(self::extensionPath(self::SCENARIO));
        $relations = [];
        $labels = [];

        $walk = static function (array $items, int $page) use (&$walk, &$relations, &$labels): void {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $self = is_array($item['self'] ?? null) ? $item['self'] : [];
                if (($self['CType'] ?? null) === 'theme_linklist' && $page === self::THEME_ELEMENTS_PAGE) {
                    $relations[] = (string)($self['tx_theme_list_items'] ?? '');
                }
                if (array_key_exists('link', $self) && isset($self['id'])) {
                    $labels[(int)$self['id']] = (string)($self['link_label'] ?? '');
                }
                // A "page" entity carries an id of its own; every other entity
                // belongs to the page it was declared under.
                $below = isset($self['slug']) && isset($self['id']) ? (int)$self['id'] : $page;
                foreach ($item['entities'] ?? [] as $nested) {
                    if (is_array($nested)) {
                        $walk($nested, $below);
                    }
                }
                if (is_array($item['children'] ?? null)) {
                    $walk($item['children'], $below);
                }
            }
        };
        $walk($scenario['entities']['page'] ?? [], 0);

        // One element, because the rendered fragment below is looked up once.
        $this->assertCount(1, $relations, 'The "Theme elements" page has to declare exactly one "theme_linklist" element.');

        $children = array_map('intval', explode(',', $relations[0]));

        // The whole test rests on this relation naming its children out of
        // uid order. Uid order is also creation order and therefore the
        // "sorting" order, so a relation that happens to be ascending is
        // rendered the same way by a template that reads the relation and by
        // one that ignores it - and the assertion below would pass either
        // way, silently. Renumbering the seeded list to "3,4,5,6" is exactly
        // the change that would do that, which is why it fails here instead.
        $ascending = $children;
        sort($ascending);
        $this->assertNotSame(
            $ascending,
            $children,
            'The link list of "Theme elements" names its children in uid order, so the rendered order proves nothing about the relation.',
        );

        $found = [];
        foreach ($children as $uid) {
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
     * @return \Generator<string, array{path: string, markup: string}>
     */
    public static function typographyRichText(): \Generator
    {
        yield 'a lead paragraph' => ['path' => '/typography/text', 'markup' => '<p class="theme-lead">'];
        yield 'a heading inside the text' => ['path' => '/typography/text', 'markup' => '<h3>A heading 3 inside the text</h3>'];
        yield 'keyboard input' => ['path' => '/typography/text', 'markup' => '<kbd>Ctrl</kbd>'];
        yield 'an alignment of the preset' => ['path' => '/typography/text', 'markup' => '<p class="theme-text--center">'];
        yield 'the language of a paragraph' => ['path' => '/typography/text', 'markup' => '<p lang="de">'];
        yield 'a list component' => ['path' => '/typography/lists', 'markup' => '<ul class="theme-list theme-list--check">'];
        yield 'a table component' => ['path' => '/typography/tables', 'markup' => '<table class="theme-table theme-table--striped">'];
        yield 'a block quotation' => ['path' => '/typography/quotes-and-code', 'markup' => '<blockquote>'];
        yield 'code in a pre' => ['path' => '/typography/quotes-and-code', 'markup' => '<pre><code>'];
    }

    /**
     * The typography pages say their rich text went through the processing
     * of the theme's preset on the way into the database, and that the
     * parsing function of the frontend kept it. Each case is one thing that
     * a narrower processing - the core's default tag list, a preset without
     * "class" in its allowed attributes - would drop without a word.
     */
    #[DataProvider('typographyRichText')]
    #[Test]
    public function aTypographyPageKeepsItsRichText(string $path, string $markup): void
    {
        $this->assertStringContainsString($markup, $this->render($path));
    }

    /**
     * @return \Generator<string, array{path: string, minimum: int}>
     */
    public static function plainTextLeads(): \Generator
    {
        yield 'the theme elements' => ['path' => '/elements/theme', 'minimum' => 5];
        yield 'the hero layouts' => ['path' => '/elements/theme/hero', 'minimum' => 10];
    }

    /**
     * The lead of a hero and the text of a teaser are plain text rendered
     * through "f:format.html", which makes every line of the value a
     * paragraph. So a paragraph is a sentence or more, never a line of the
     * source the value was written in, and there is no empty one around it -
     * see "PlainTextSeedTest" for the rule the seeds follow.
     */
    #[DataProvider('plainTextLeads')]
    #[Test]
    public function aPlainTextLeadRendersWholeParagraphs(string $path, int $minimum): void
    {
        $body = $this->render($path);
        preg_match_all('#<div class="theme-(?:hero__lead|teaser__text)">(.*?)</div>#s', $body, $leads);
        $this->assertGreaterThanOrEqual($minimum, count($leads[1]), sprintf('"%s" renders fewer leads than it seeds.', $path));

        foreach ($leads[1] as $lead) {
            preg_match_all('#<p>(.*?)</p>#s', $lead, $paragraphs);
            $this->assertNotSame([], $paragraphs[1], 'A lead without a paragraph: ' . $lead);
            foreach ($paragraphs[1] as $paragraph) {
                $this->assertMatchesRegularExpression('/\S[.!?:"\')]$/', trim($paragraph), 'A lead is split into paragraphs: ' . trim($lead));
            }
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
     *
     * Counted inside `main`, not in the whole document: the footer columns of
     * the site root slide into every page below it, and three of those five
     * elements are `text`. Counted over the document, a page with four `text`
     * elements on it renders seven - the page's own and the site's footer,
     * which is not the page's content and is asserted where it belongs, in
     * `theFooterColumnsAreSeededOnTheSiteRootAndSlideDownToEveryPageBelowIt()`.
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
                substr_count($this->mainOf($this->render($slug)), sprintf('data-ctype="%s"', $type)),
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
     * A theme element with a page of its own has it below "Theme elements",
     * and the page renders every element of that CType seeded on it.
     */
    #[Test]
    public function everyThemeElementWithAPageHasItBelowTheThemeElements(): void
    {
        foreach (self::THEME_ELEMENT_PAGES as $type => $name) {
            $slug = '/elements/theme/' . $name;
            $page = $this->pageBySlug($slug);
            $this->assertNotNull($page, sprintf('"%s" has no page "%s".', $type, $slug));
            $this->assertSame(self::THEME_ELEMENTS_PAGE, $page['pid'], sprintf('"%s" is not below "Theme elements".', $slug));

            $elements = $this->elementsOn($page['uid'], $type);
            $this->assertNotSame([], $elements, sprintf('"%s" shows no "%s" element.', $slug, $type));
            $this->assertSame(
                count($elements),
                substr_count($this->mainOf($this->render($slug)), sprintf('data-ctype="%s"', $type)),
                sprintf('"%s" does not render every "%s" element seeded on it.', $slug, $type),
            );
        }
    }

    /**
     * A features element of four columns is on a page whose main column fits
     * four. Four tracks of the grid's 12rem minimum and three gaps of 1.875rem
     * need 53.625rem inside the element; beside the sub navigation of
     * `content_sidebar` the main column has less, and the grid shows three
     * columns at every viewport. The aside is what takes the width, so the
     * page is asserted to render none.
     */
    #[Test]
    public function aFourColumnFeatureGridIsOnAPageWithoutASidebar(): void
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        /** @var list<array<string, mixed>> $elements */
        $elements = $queryBuilder
            ->select('uid', 'pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('theme_features')),
                $queryBuilder->expr()->eq('tx_theme_columns', $queryBuilder->createNamedParameter(4, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        $this->assertNotSame([], $elements, 'The showcase seeds no features element of four columns.');

        foreach ($elements as $element) {
            $page = BackendUtility::getRecord('pages', (int)$element['pid'], 'slug');
            $this->assertIsArray($page);
            $this->assertStringNotContainsString(
                'theme-page__aside',
                $this->render((string)$page['slug']),
                sprintf('The four-column features element c%d is on "%s", beside a sidebar.', $element['uid'], $page['slug']),
            );
        }
    }

    /**
     * @return \Generator<string, array{type: string, field: string}>
     */
    public static function themeVariantFields(): \Generator
    {
        foreach (self::THEME_VARIANT_FIELDS as $type => $fields) {
            foreach ($fields as $field) {
                yield $type . ', ' . $field => ['type' => $type, 'field' => $field];
            }
        }
    }

    /**
     * The page of a theme element shows every value of the fields that change
     * how it looks - the text and icon element in every position, shape and
     * size of its icon.
     */
    #[DataProvider('themeVariantFields')]
    #[Test]
    public function thePageOfAThemeElementShowsEveryVariant(string $type, string $field): void
    {
        $page = $this->pageBySlug('/elements/theme/' . self::THEME_ELEMENT_PAGES[$type]);
        $this->assertNotNull($page, sprintf('"%s" has no page.', $type));
        $this->assertNotSame([], self::offeredValues($page['uid'], $type, $field), sprintf('The form offers no value for "%s" - the field is wrong.', $field));

        $this->assertSame(
            [],
            self::unshownValues($this->elementsOn($page['uid'], $type), $page['uid'], $type, $field),
            sprintf('No "%s" element on its page shows these values of "%s".', $type, $field),
        );
    }

    /**
     * `/elements/menu` shows every layout the form offers the two page menus -
     * the list, the cards and the thumbnails - and the cards show page media.
     */
    #[Test]
    public function theMenuPageShowsEveryLayoutOfThePageMenus(): void
    {
        $page = $this->pageBySlug('/elements/menu');
        $this->assertNotNull($page, 'The menu page is missing.');

        foreach (['menu_pages', 'menu_subpages'] as $type) {
            $this->assertSame(['0', '1', '2'], self::offeredValues($page['uid'], $type, 'layout'), sprintf('The form offers other layouts for "%s".', $type));
            $this->assertSame(
                [],
                self::unshownValues($this->elementsOn($page['uid'], $type), $page['uid'], $type, 'layout'),
                sprintf('No "%s" element on the menu page shows these layouts.', $type),
            );
        }

        $body = $this->render('/elements/menu');
        $this->assertStringContainsString('<li class="theme-card theme-card--linked">', $body);
        $this->assertStringContainsString('<li class="theme-card theme-card--linked theme-card--compact">', $body);
        $this->assertMatchesRegularExpression('#<div class="theme-card__media">\s*<img #', $body, 'The seeded menu cards show no page media.');
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

    /**
     * The `CType` of every element of the `Examples` section, by the uid of
     * the page it sits on - the pages below `/examples`, at any depth, and
     * not the section index itself. One entry per element, repeats included,
     * because how often one kind occurs is half of what is asserted below.
     *
     * @return array<int, list<string>>
     */
    private function contentTypesOfTheExamples(): array
    {
        $section = $this->pageBySlug(self::EXAMPLES_SECTION);
        $this->assertNotNull($section, 'The "Examples" section is missing.');

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        /** @var list<array<string, mixed>> $pages */
        $pages = $queryBuilder
            ->select('uid', 'pid', 'slug')
            ->from('pages')
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $below = [$section['uid']];
        // The section is two levels deep - the journal entry is a child of the
        // journal - so the walk repeats until it finds nothing new rather than
        // assuming a depth.
        do {
            $found = false;
            foreach ($pages as $page) {
                if (in_array((int)$page['pid'], $below, true) && !in_array((int)$page['uid'], $below, true)) {
                    $below[] = (int)$page['uid'];
                    $found = true;
                }
            }
        } while ($found);
        array_shift($below);
        $this->assertNotSame([], $below, 'The "Examples" section has no pages below it.');

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('pid', 'CType')
            ->from('tt_content')
            ->where($queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($below, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        $types = [];
        foreach ($rows as $row) {
            $types[(int)$row['pid']][] = (string)$row['CType'];
        }

        return $types;
    }

    /**
     * A page of the `Examples` section is a composition, and a composition of
     * one element repeated is a specimen page - of which the tree has a
     * section full already.
     *
     * Two things are asserted, and the count alone is not enough for either.
     *
     * **At least three kinds.** Three, not more, because the campaign page on
     * the `cover` layout is the floor of the section and is meant to be: one
     * hero, one call to action, and the notice that names them. A cover page
     * padded out to pass a higher bound would stop being the thing it
     * demonstrates.
     *
     * **No kind is more than half the page.** On its own the bound above does
     * not say what it sounds like it says: with the notice every page carries
     * and the heading several open with, "three kinds" is satisfied by a hero,
     * five card groups and a notice - which is exactly the specimen page the
     * bound exists to reject. The share is what rejects it, and it needs no
     * list of which types count as scaffolding.
     */
    #[Test]
    public function anExamplePageIsMadeOfSeveralKindsOfElement(): void
    {
        $thin = [];
        $lopsided = [];
        foreach ($this->contentTypesOfTheExamples() as $pid => $types) {
            $kinds = array_count_values($types);
            if (count($kinds) < self::EXAMPLE_PAGE_MINIMUM_KINDS) {
                $thin[] = sprintf('page %d: %s', $pid, implode(', ', array_keys($kinds)));
            }
            arsort($kinds);
            $most = (int)reset($kinds);
            if ($most * 2 > count($types)) {
                $lopsided[] = sprintf('page %d: %d of %d elements are "%s"', $pid, $most, count($types), (string)key($kinds));
            }
        }

        $this->assertSame(
            [],
            $thin,
            sprintf("These pages of \"Examples\" are made of fewer than %d kinds of element:\n  ", self::EXAMPLE_PAGE_MINIMUM_KINDS) . implode("\n  ", $thin),
        );
        $this->assertSame(
            [],
            $lopsided,
            "These pages of \"Examples\" are mostly one kind of element, which is a specimen page:\n  " . implode("\n  ", $lopsided),
        );
    }

    /**
     * The promise of the section: it composes what the theme already has and
     * introduces nothing.
     *
     * An element type that appears only there would mean the composition
     * reached for something the single-element pages never show - which is
     * either a content type nobody demonstrated, or a component added to make
     * one page work. Both are findings rather than features, and this is where
     * they surface.
     */
    #[Test]
    public function theExamplesSectionIntroducesNoContentTypeOfItsOwn(): void
    {
        $inTheSection = [];
        foreach ($this->contentTypesOfTheExamples() as $types) {
            foreach ($types as $type) {
                $inTheSection[$type] = true;
            }
        }
        $this->assertNotSame([], $inTheSection, 'The "Examples" section seeds no content element at all.');

        $section = $this->pageBySlug(self::EXAMPLES_SECTION);
        $this->assertNotNull($section);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('c.CType')
            ->from('tt_content', 'c')
            ->innerJoin('c', 'pages', 'p', 'p.uid = c.pid')
            ->where(
                $queryBuilder->expr()->notLike('p.slug', $queryBuilder->createNamedParameter(self::EXAMPLES_SECTION . '%')),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $elsewhere = array_unique(array_map(static fn(array $row): string => (string)$row['CType'], $rows));
        $only = array_values(array_diff(array_keys($inTheSection), $elsewhere));
        sort($only);

        $this->assertSame(
            [],
            $only,
            'These content types are seeded on the composed pages and nowhere else: ' . implode(', ', $only),
        );
    }
}
