<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The long form article of the showcase, `/typography/article`.
 *
 * The page is the one demo of the tree that is read rather than looked at,
 * and three of the things it demonstrates only exist while they survive the
 * way into the database and out of it again:
 *
 * - The **byline** and the **footnotes** are markup of `html` elements. The
 *   theme has no content element for either, so the page is also the proof
 *   that the two components can be produced from a record at all.
 * - A **footnote reference** is a `sup` with an id, around a link to a
 *   fragment, inside a *rich text* field. That path is the fragile one:
 *   `f:format.html` runs the value through `lib.parseFunc_RTE`, whose
 *   `tags.a` turns every anchor into a `typolink` and whose `htmlSanitize`
 *   drops every tag and attribute `typo3/html-sanitizer`'s common behaviour
 *   does not list. Both keep this markup today
 *   (`CommonBuilder::createBasicTags()` lists `sup`,
 *   `createGlobalAttrs()` lists `id`, and the `href` value builder allows a
 *   local target), and a core release that narrowed either would take the
 *   references off the page without a word.
 * - The **table of contents** is the `sectionIndex` menu of the theme, which
 *   only the `content_sidebar` layout renders. The article is the page that
 *   asks for it, and the two elements that are not sections of the
 *   article - the title and the notes - are out of it through the core field
 *   an editor sets.
 *
 * The seeded values bypass the rich text editor, so this page shows more than
 * an editor could produce through the toolbar of the theme's preset; what is
 * asserted here is the *frontend* path, which is the same for both.
 */
final class ArticleRenderingTest extends AbstractFunctionalTestCase
{
    use DataFactoryImportTrait;
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    /**
     * As in `ShowcaseTreeTest`: the instances require rte_ckeditor, so the
     * rich text of the showcase is written through the processing of the
     * theme's preset rather than through the core's shorter default list.
     */
    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'sbuerk/data-factory',
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

        $this->setUpThemeSite(identifier: 'demo', websiteTitle: 'Theme demo');
    }

    private function article(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/typography/article'),
        )->getBody();
    }

    /**
     * @return \Generator<string, array{markup: string}>
     */
    public static function articleMarkup(): \Generator
    {
        yield 'the byline' => ['markup' => '<p class="theme-byline">'];
        yield 'the author of the byline' => ['markup' => '<span class="theme-byline__author">The theme team</span>'];
        yield 'the machine readable date' => ['markup' => '<time class="theme-byline__item" datetime="2026-09-20">'];
        yield 'the notes' => ['markup' => '<aside class="theme-footnotes"'];
        yield 'a note' => ['markup' => '<li class="theme-footnotes__item" id="fn1">'];
        yield 'the link back to a reference' => ['markup' => '<a class="theme-footnotes__backlink" href="#fnref1"'];
    }

    #[DataProvider('articleMarkup')]
    #[Test]
    public function theArticleRendersTheMarkupOfItsHtmlElements(string $markup): void
    {
        $this->assertStringContainsString($markup, $this->article());
    }

    /**
     * @return \Generator<string, array{number: int}>
     */
    public static function footnoteReferences(): \Generator
    {
        yield 'the first' => ['number' => 1];
        yield 'the second' => ['number' => 2];
    }

    /**
     * The reference pair, both halves of it, in the rendered page: the `sup`
     * keeps its class and its id through `parseFunc`, its link still points
     * at the note, and the note points back at the `sup`.
     *
     * The assertion is on the attributes rather than on a literal string of
     * markup: `typolink` decides the attribute order of an anchor it rebuilds,
     * and that order is not what this is about.
     */
    #[DataProvider('footnoteReferences')]
    #[Test]
    public function aFootnoteReferenceSurvivesTheRichTextPath(int $number): void
    {
        $body = $this->article();

        $this->assertMatchesRegularExpression(
            sprintf('~<sup class="theme-footnote-ref" id="fnref%1$d">\s*<a[^>]*href="#fn%1$d"[^>]*>%1$d</a>\s*</sup>~', $number),
            $body,
            sprintf('The reference of note %d did not survive "lib.parseFunc_RTE".', $number),
        );
        $this->assertStringContainsString(
            sprintf('<li class="theme-footnotes__item" id="fn%d">', $number),
            $body,
            sprintf('The reference of note %d points at a note that is not on the page.', $number),
        );
    }

    /**
     * The image of the `textpic` is on the `textpic`.
     *
     * A file reference names its record by a literal uid in `config.yml`,
     * because that is the one relation the scenario format cannot express -
     * and a literal does not move when the records around it do. Inserting
     * the byline renumbered this page, and the reference stayed on the uid
     * that had become a `text` element, which renders no image: the element
     * whose prose describes an image beside the text had none.
     *
     * Asserted on the element rather than on the page, because the page has
     * no other image and a bare "is there an `img`" would have passed on the
     * broken tree too - the file was still written, it just hung on the wrong
     * record. `SeedFileReferenceTest` asks the general question of every
     * reference of the set; this one holds the page whose text makes a claim
     * about its image.
     */
    #[Test]
    public function theImageOfTheArticleIsOnTheElementWhoseTextDescribesIt(): void
    {
        preg_match('#<div[^>]*\bid="c5605".*?</div>\s*</div>#s', $this->article(), $element);

        $this->assertArrayHasKey(0, $element, 'The "textpic" of the article is not on the page.');
        $this->assertMatchesRegularExpression(
            '#<img[^>]+alt="A placeholder graphic in portrait format"#',
            $element[0],
            'The image the text of the article flows around is not on the element that renders it.',
        );
    }

    /**
     * The article is the one page of the typography section with a sidebar,
     * and the table of contents is why.
     */
    #[Test]
    public function theArticleRendersATableOfContentsInItsAside(): void
    {
        $body = $this->article();

        $this->assertStringContainsString('data-theme-page-layout="content_sidebar"', $body);
        $this->assertStringContainsString('theme-page__aside', $body);
        $this->assertStringContainsString('<nav class="theme-content-menu theme-page__toc"', $body);
    }

    /**
     * Every entry of the table of contents, and only those: an element of the
     * page that is a section of the article is in it, and the four that are
     * not carry `sectionIndex = 0` and are out - the title, the byline, the
     * notes, and the code figure, whose `html` element renders no header at
     * all, so an entry for it would name a heading nobody can see.
     *
     * Read off the anchors of the menu rather than off its text, because the
     * anchor is the part that says *which element* an entry is.
     */
    #[Test]
    public function onlyTheSectionsOfTheArticleAreInItsTableOfContents(): void
    {
        preg_match(
            '#<nav class="theme-content-menu theme-page__toc".*?</nav>#s',
            $this->article(),
            $menu,
        );
        $this->assertArrayHasKey(0, $menu, 'The article renders no table of contents.');

        preg_match_all('/href="[^"]*#c(\d+)"/', $menu[0], $entries);
        $uids = array_map(intval(...), $entries[1]);
        sort($uids);

        $this->assertSame(
            // "The record", "The frame", "What the frame reads", "The
            // template", "The fields of a table element" and "What is left
            // to the editor" - every element whose header the page actually
            // renders.
            [5604, 5605, 5606, 5607, 5608, 5610],
            $uids,
            'The table of contents of the article is not the sections of the article.',
        );
    }

    /**
     * The notes end on the link back to the top of the page, which is the
     * core `linkToTop` field rather than markup of the element - the second
     * Appearance field this page seeds to demonstrate it.
     */
    #[Test]
    public function theNotesEndOnTheLinkBackToTheTopOfThePage(): void
    {
        $this->assertMatchesRegularExpression(
            '#id="c5611".*?theme-content-element__to-top#s',
            $this->article(),
            'The notes do not carry the "linkToTop" link.',
        );
    }
}
