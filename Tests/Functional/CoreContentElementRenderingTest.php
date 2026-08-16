<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders the classic content types `EXT:frontend` registers but supplies no
 * rendering for.
 *
 * `fluid_styled_content` is not a dependency of this theme, so it is not
 * installed here. What it would have supplied is the *rendering*, never
 * the TCA: every one of these types can be created in the backend of an
 * installation using this theme whether or not anything renders it. Without a
 * definition the core prints its own notice instead, so a type nobody covered
 * looks broken to an editor rather than absent.
 *
 * The sweep below is therefore the important assertion: it fails for any type
 * that is creatable but unrendered, which is the state the whole set was in
 * before this change.
 */
final class CoreContentElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    private const NO_RENDERING_DEFINITION = 'has no rendering definition';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithCoreContentElements.csv');
        $this->setUpThemeSite();
    }

    private function render(string $path = '/'): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/' . ltrim($path, '/')),
        )->getBody();
    }

    /**
     * `menu_categorized_content` selects through raw SQL, and the category list
     * it interpolates can legitimately be empty.
     *
     * `selected_categories` declares `minitems = 1`, but that is enforced by the
     * backend form rather than by DataHandler: an element saved before a
     * category was picked holds an empty value, and interpolating it produced
     * `IN ()`. SQLite accepts that. MariaDB, MySQL and PostgreSQL reject it as
     * a syntax error, and because the query throws rather than returning
     * nothing, the **whole page** returned a 500 - not just this element.
     *
     * The element is on a page of its own so the sweep above keeps rendering
     * the fully configured one; both shapes have to work, and only one of them
     * did.
     *
     * This test passes on SQLite whether or not the fix is in place. Run it
     * against MariaDB to see it fail without the guard:
     * `runTests.sh -t 13 -s functional -d mariadb -i 10.6 -- --filter aCategorizedContentMenu`
     */
    #[Test]
    public function aCategorizedContentMenuWithNoCategorySelectedRendersEmptyRatherThanFailing(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithUnselectedCategoryMenu.csv');

        $body = $this->render('/unselected');

        // The page rendered at all, which is the half that used to throw.
        $this->assertStringContainsString('A categorized content menu with nothing selected', $body);
        $this->assertStringNotContainsString(self::NO_RENDERING_DEFINITION, $body);

        // And it selected nothing rather than everything: the guard replaces an
        // empty list with a uid no category has, so the menu is empty.
        $menu = $this->contentElement($body, 'menu_categorized_content');
        $this->assertStringNotContainsString('theme-content-menu__link', $menu);
    }

    #[Test]
    public function noRenderedElementFallsBackToTheCoreNotice(): void
    {
        $this->assertStringNotContainsString(self::NO_RENDERING_DEFINITION, $this->render());
    }

    /**
     * A menu that lists pages has to actually list them.
     *
     * The sweep above only proves a template ran. A menu configured with the
     * wrong `special` renders its wrapper perfectly and lists nothing, which
     * looks like "no pages match" rather than like a defect.
     */
    #[Test]
    public function aPageMenuListsTheSubPages(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('A listed sub page', $body);
        $this->assertStringContainsString('Another listed sub page', $body);
    }

    /**
     * The categorised menus select by category rather than by rootline
     * position, which `MenuProcessor` cannot express at all.
     *
     * The two are built differently on purpose. The pages variant uses
     * `RECORDS`, which can select by category. The content variant uses
     * `DatabaseQueryProcessor` with an explicit join on
     * `sys_category_record_mm`, because `RECORDS` would render each match as a
     * whole content element nested inside this one - the wrong shape for a
     * menu, and it would inherit the recursion exposure the shortcut element
     * documents and has to close for itself.
     *
     * The fixture puts one page and one content element in a category and
     * points both elements at it. Without that, the pair render a correct
     * empty wrapper and nothing proves the selection was ever wired up - which
     * is exactly the state this test was added in, and it caught two real
     * defects before it passed.
     */
    #[Test]
    public function aCategorisedMenuSelectsWhatShareItsCategory(): void
    {
        $body = $this->render();

        $pages = $this->contentElement($body, 'menu_categorized_pages');
        $this->assertStringContainsString('A listed sub page', $pages);
        $this->assertStringNotContainsString('Another listed sub page', $pages);

        // The content variant lists what shares the category rather than
        // re-rendering it, so it links the element by its header and anchor.
        $content = $this->contentElement($body, 'menu_categorized_content');
        $this->assertStringContainsString('A bullet list', $content);
        $this->assertStringContainsString('#c10', $content);
        $this->assertStringNotContainsString('menu_categorized_pages', $content);
    }

    /**
     * The markup of one content element, by its CType.
     *
     * The page renders nineteen elements, so asserting against the whole
     * response cannot tell "this menu selected it" from "some other element on
     * the page happens to contain it".
     */
    private function contentElement(string $body, string $ctype): string
    {
        // The lookahead has to name the *wrapper* class specifically. Matching
        // "theme-content-element" alone also matches the element's own
        // "__inner" child, which ends the fragment on its first line and makes
        // every assertion against it fail for a reason that has nothing to do
        // with what was rendered.
        $matched = preg_match(
            sprintf(
                '#<div[^>]*data-ctype="%s"[^>]*>(.*?)(?=<div[^>]*class="theme-content-element theme-content-element--|</main>)#s',
                preg_quote($ctype, '#'),
            ),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No "%s" element was rendered.', $ctype));

        return $matches[0];
    }

    /**
     * @return \Generator<string, array{ctype: string}>
     */
    public static function coveredContentTypes(): \Generator
    {
        foreach ([
            'bullets', 'table', 'div', 'html', 'textmedia', 'textpic', 'uploads', 'shortcut',
            'menu_pages', 'menu_subpages', 'menu_section', 'menu_section_pages',
            'menu_sitemap', 'menu_sitemap_pages', 'menu_abstract',
            'menu_recently_updated', 'menu_related_pages',
            'menu_categorized_pages', 'menu_categorized_content',
        ] as $ctype) {
            yield $ctype => ['ctype' => $ctype];
        }
    }

    /**
     * The wrapper carries the CType, so this also proves each element actually
     * went through `lib.contentElement` rather than being emitted some other
     * way.
     */
    #[DataProvider('coveredContentTypes')]
    #[Test]
    public function everyCoveredTypeIsRenderedThroughTheContentElementWrapper(string $ctype): void
    {
        $this->assertStringContainsString(
            sprintf('data-ctype="%s"', $ctype),
            $this->render(),
            sprintf('The "%s" element did not render through the shared wrapper.', $ctype),
        );
    }

    #[Test]
    public function aBulletListBecomesAList(): void
    {
        $body = $this->render();

        $this->assertMatchesRegularExpression('#<ul[^>]*>.*?<li[^>]*>\s*First item\s*</li>#s', $body);
        $this->assertStringContainsString('Third item', $body);
    }

    #[Test]
    public function aTableBecomesATableWithItsCellsSplit(): void
    {
        $body = $this->render();

        $this->assertMatchesRegularExpression('#<table\b#', $body);
        foreach (['Name', 'Role', 'Ada', 'Analyst', 'Grace', 'Compiler'] as $cell) {
            $this->assertStringContainsString($cell, $body, sprintf('The cell "%s" is missing.', $cell));
        }

        // Split into cells, not printed as one delimited string.
        $this->assertStringNotContainsString('Ada|Analyst', $body);
    }

    #[Test]
    public function aDividerBecomesARule(): void
    {
        $this->assertMatchesRegularExpression('#<hr\b[^>]*>#', $this->render());
    }

    /**
     * The one element that deliberately does not escape. The core restricts it
     * to admin users for exactly that reason, and running it through
     * `f:format.html` instead would hand it to the RTE parser and rewrite it.
     */
    #[Test]
    public function anHtmlElementIsEmittedUntouched(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('<em data-raw="yes">Unescaped on purpose</em>', $body);
        $this->assertStringNotContainsString('&lt;em data-raw=', $body);
    }

    /**
     * A shortcut renders another record. The reference here points at the
     * bullet list, so its content has to appear twice on the page - once in
     * its own place and once through the shortcut.
     */
    #[Test]
    public function aShortcutRendersTheRecordItPointsAt(): void
    {
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($this->render(), 'First item'),
            'The shortcut did not render the record it references.',
        );
    }

    /**
     * The theme's own cycle break, pinned by what only it can produce.
     *
     * Inside a shortcut, the `shortcut` branch of the content element `CASE`
     * is overridden to render nothing. The fixture therefore holds a chain
     * rather than a cycle: uid 84 references uid 80, which references the
     * bullet list. Nothing about that chain is circular, so a core register
     * tracking already-rendered records would happily render all three levels
     * and the bullet list would appear a third time.
     *
     * It appears twice - in its own place and through uid 80 - because the
     * second level renders nothing at all.
     *
     * This is the distinguishing observation, and it is why the test exists
     * beside `aCircularShortcutDoesNotTakeTheRequestDown`: that one passes
     * whether or not this theme breaks the cycle, because the core's own
     * `RecordsContentObject` register (`$recordRegister`, v13.4) already keeps
     * a *cycle* from taking the request down. Verified by removing
     * `conf.tt_content.shortcut` from the rendering definition and watching it
     * stay green. This test goes red there instead.
     */
    #[Test]
    public function aShortcutInsideAShortcutRendersNothing(): void
    {
        $this->assertSame(
            2,
            substr_count($this->render(), 'First item'),
            'A shortcut nested in a shortcut rendered its target instead of nothing.',
        );
    }

    /**
     * The fixture contains a shortcut pointing at itself and a pair pointing
     * at each other. Rendering the page at all is the assertion.
     *
     * The break is structural: inside a shortcut, the shortcut branch renders
     * nothing, so no chain of references can return to its start. That needs
     * no per-request state, and it is a property of this theme's own rendering
     * definition rather than of whatever the `RECORDS` cObject underneath
     * keeps track of - see the long comment above `tt_content.shortcut` in
     * `Configuration/TypoScript/ContentElements.typoscript`.
     *
     * What this test pins is the outcome rather than the mechanism: a cycle an
     * editor can author in the backend leaves the request standing, and it
     * leaves no "no rendering definition" notice on the page. This is the only
     * place that statement is checked against a real request.
     */
    #[Test]
    public function aCircularShortcutDoesNotTakeTheRequestDown(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        );

        $this->assertSame(200, $response->getStatusCode());

        $body = (string)$response->getBody();

        // The page still rendered everything else around the cycle.
        $this->assertStringContainsString('data-ctype="bullets"', $body);
        $this->assertStringNotContainsString(self::NO_RENDERING_DEFINITION, $body);
    }
}
