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
    use DeliveredMarkupTrait;
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/BackendLayoutPageTree.csv');
        $this->setUpThemeSite();
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
        yield 'the two column layout' => [
            'url' => 'https://theme.example.com/two-columns',
            'expectedLayout' => 'two_columns',
        ];
        yield 'the two column layout with a wide first column' => [
            'url' => 'https://theme.example.com/two-columns-wide',
            'expectedLayout' => 'two_columns_wide',
        ];
        yield 'the three column layout' => [
            'url' => 'https://theme.example.com/three-columns',
            'expectedLayout' => 'three_columns',
        ];
        yield 'the article layout' => [
            'url' => 'https://theme.example.com/article',
            'expectedLayout' => 'article',
        ];
        yield 'the cover layout' => [
            'url' => 'https://theme.example.com/cover',
            'expectedLayout' => 'cover',
        ];
        yield 'the band layout' => [
            'url' => 'https://theme.example.com/bands',
            'expectedLayout' => 'bands',
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
     *
     * `/article` is in the list although it *does* render an `aside` element:
     * that one is a column of the article grid, not the page shell's
     * navigation column, and the two must not be the same element - see
     * `theArticleAsideIsAColumnAndNotTheShellAside()`.
     */
    #[Test]
    public function onlyTheSidebarLayoutEmitsAnAside(): void
    {
        $this->assertStringContainsString('theme-page__aside', $this->render('https://theme.example.com/own'));

        foreach (['/', '/inherits', '/start', '/none', '/article', '/cover', '/bands'] as $path) {
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
            'https://theme.example.com/two-columns',
            'https://theme.example.com/two-columns-wide',
            'https://theme.example.com/three-columns',
            'https://theme.example.com/article',
            'https://theme.example.com/cover',
            'https://theme.example.com/bands',
        ] as $url) {
            $response = $this->executeFrontendSubRequest(new InternalRequest($url));

            $this->assertSame(200, $response->getStatusCode(), sprintf('"%s" did not render.', $url));
            $this->assertStringContainsString('class="theme-page"', (string)$response->getBody());
        }
    }

    /**
     * The headings of every `.theme-page__column` of a page, column by
     * column, so an assertion can say which slot rendered *where* rather than
     * only that its content is somewhere on the page.
     *
     * Read from the DOM rather than with a regular expression over the
     * source: the columns are sibling elements holding further elements, and
     * "everything between this delimiter and the next one" stops being true
     * for the last of them.
     *
     * @param string $container The class of the element holding the slots.
     * @param string $slot      The class each slot element carries.
     *
     * @return list<list<string>>
     */
    private function slotHeadings(string $body, string $container, string $slot): array
    {
        $xpath = $this->deliveredDocument($body);
        $slots = $this->elementsMatching(
            $xpath,
            // "*", not "div": the aside of the article layout is a column
            // like any other and an "aside" element at the same time.
            sprintf('//*[%s]/*[%s]', $this->hasClass($container), $this->hasClass($slot)),
        );

        $headings = [];
        foreach ($slots as $element) {
            $headings[] = array_map(
                static fn(\DOMElement $heading): string => trim($heading->textContent),
                $this->elementsMatching($xpath, sprintf('.//*[%s]', $this->hasClass('theme-content-element__heading')), $element),
            );
        }

        return $headings;
    }

    /**
     * @return list<list<string>>
     */
    private function columnHeadings(string $body): array
    {
        return $this->slotHeadings($body, 'theme-page__columns', 'theme-page__column');
    }

    /**
     * @return \Generator<string, array{url: string, modifiers: list<string>, expected: list<list<string>>}>
     */
    public static function columnLayouts(): \Generator
    {
        yield 'two equal columns' => [
            'url' => 'https://theme.example.com/two-columns',
            'modifiers' => ['theme-page__columns--halves'],
            'expected' => [['In the main column'], ['In the second column']],
        ];
        yield 'two columns with a wide first one' => [
            'url' => 'https://theme.example.com/two-columns-wide',
            'modifiers' => ['theme-page__columns--wide-start'],
            'expected' => [['In the wide column'], ['In the narrow column']],
        ];
        yield 'three columns' => [
            'url' => 'https://theme.example.com/three-columns',
            'modifiers' => ['theme-page__columns--thirds'],
            'expected' => [['In the first of three'], ['In the second of three'], ['In the third of three']],
        ];
        yield 'an article and its notes' => [
            'url' => 'https://theme.example.com/article',
            // The reading measure is a modifier on the column, not on the
            // grid, so nothing about the grid or the content says whether it
            // was written. Dropping it from "Page/Article.html" leaves every
            // other assertion of this suite green while the article runs the
            // full width of its track.
            'modifiers' => ['theme-page__columns--article', 'theme-page__column--measure'],
            'expected' => [['In the article'], ['In the notes beside it']],
        ];
    }

    /**
     * A column layout renders each of its slots into its own column, in the
     * order the template declares them, and reads no slot the layout does not
     * declare.
     *
     * The modifiers are asserted beside the content because what they carry
     * lives in the stylesheet only - `--halves` and `--wide-start` produce the
     * same markup otherwise, so the content check alone would pass with the
     * two templates swapped.
     *
     * @param list<string>       $modifiers
     * @param list<list<string>> $expected
     */
    #[DataProvider('columnLayouts')]
    #[Test]
    public function aColumnLayoutRendersEachSlotInItsOwnColumn(string $url, array $modifiers, array $expected): void
    {
        $body = $this->render($url);

        foreach ($modifiers as $modifier) {
            $this->assertStringContainsString($modifier, $body, sprintf('"%s" is not in the rendered page.', $modifier));
        }
        $this->assertSame($expected, $this->columnHeadings($body));
    }

    /**
     * A layout reads the slots its template names and no others.
     *
     * The fixture puts one element in colPos 4 on the two column page, which
     * declares colPos 0 and 3 only. The element it sits beside is asserted
     * present in the same breath: an absence is only evidence when the thing
     * it is contrasted with is there, and a page that failed to render at all
     * would otherwise satisfy the second assertion by itself.
     */
    #[Test]
    public function aLayoutRendersNoSlotItDoesNotDeclare(): void
    {
        $body = $this->render('https://theme.example.com/two-columns');

        $this->assertStringContainsString('In the second column', $body);
        $this->assertStringNotContainsString('In a column this layout has not got', $body);
    }

    /**
     * The article layout reads colPos 1 like the sidebar layout does, and
     * renders it somewhere else: as a column of the article grid that follows
     * the article, rather than as the page shell's navigation column, which
     * precedes the content and carries the sub navigation.
     *
     * Asserted on the element itself rather than on the absence of a class,
     * which `onlyTheSidebarLayoutEmitsAnAside()` already covers: what matters
     * here is that the notes *are* rendered, and rendered as an `aside`.
     */
    #[Test]
    public function theArticleAsideIsAColumnAndNotTheShellAside(): void
    {
        $body = $this->render('https://theme.example.com/article');
        $xpath = $this->deliveredDocument($body);

        $asides = $this->elementsMatching($xpath, sprintf('//aside[%s]', $this->hasClass('theme-page__column')));

        $this->assertCount(1, $asides);
        $this->assertStringContainsString('In the notes beside it', $asides[0]->textContent);
        $this->assertStringNotContainsString('theme-nav-sub', $body, 'The article aside must not carry the sub navigation.');
    }

    /**
     * The cover layout has one slot and renders nothing above it: no
     * breadcrumb, no stage. Both are partials the other content layouts call
     * explicitly, so leaving them out is a decision of this template and not
     * a consequence of the layout having no such column.
     */
    #[Test]
    public function theCoverLayoutRendersItsOneSlotAndNoChrome(): void
    {
        $body = $this->render('https://theme.example.com/cover');

        $this->assertStringContainsString('theme-page__cover', $body);
        $this->assertStringContainsString('On the cover', $body);
        $this->assertStringNotContainsString('theme-breadcrumb', $body);
    }

    /**
     * The band layout stacks the three *column* slots of `three_columns`
     * instead of putting them side by side, so content moved between the two
     * stays in the slot it was in. Not a swap without consequence:
     * `three_columns` also declares `stage` (colPos 2), which `bands` has
     * not got.
     */
    #[Test]
    public function theBandLayoutStacksItsThreeSlotsInOrder(): void
    {
        $this->assertSame(
            [['In the first band'], ['In the second band'], ['In the third band']],
            $this->slotHeadings(
                $this->render('https://theme.example.com/bands'),
                'theme-page__bands',
                'theme-page__band',
            ),
        );
    }
}
