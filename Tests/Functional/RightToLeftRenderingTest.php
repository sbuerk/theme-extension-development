<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The right-to-left page of the showcase, `/typography/right-to-left`.
 *
 * The page exists because a physical edge in the stylesheet is invisible in a
 * left-to-right page, which is the only kind of page every other demo is. Its
 * direction comes from a `dir="rtl"` wrapper per block rather than from a site
 * language: a language is a property of the installation - a site
 * configuration and a translation of every record - and none of that travels
 * with a seed set, while a wrapper is something an editor has as well and puts
 * both directions on one page.
 *
 * That wrapper is the part worth asserting, and **which element it sits in
 * decides what asserting it proves**. The page has four of them, and they are
 * not the same case:
 *
 * - `15203`, `15204` and `15206` are `html` elements.
 *   `Templates/ContentElements/Html.html` renders `bodytext` through
 *   `f:format.raw` - no parsing function, no sanitizer - so a wrapper there
 *   reaches the page whatever the core does with markup. Each of them carries
 *   something the theme's rich text preset cannot write: a check list and a
 *   table component, a footnote block, and a `bdi`.
 * - `15205` is rich text. It goes through `lib.parseFunc_RTE`, where `div` is
 *   an `externalBlocks` entry, and through `htmlSanitize`, which keeps only
 *   what `typo3/html-sanitizer`'s common behaviour lists - `dir` and `lang` in
 *   `CommonBuilder::createGlobalAttrs()`. It also went through the **save**
 *   transformation of the preset on the way in, whose `allowAttributes` lists
 *   both (`EXT:rte_ckeditor/Configuration/RTE/Processing.yaml`). A release
 *   dropping either would leave this page looking exactly as it does now, in
 *   the wrong direction, and nothing else in the suite would notice.
 *
 * So the count below covers all four, and the assertion that names the rich
 * text path names the one element that takes it.
 *
 * The stylesheet side is `ComponentLibraryTest`: that no box is placed by a
 * physical edge, and that every direction-aware icon is mirrored under
 * `:dir(rtl)`. That the mirroring resolves in a browser at all is the visual
 * suite's `direction` section.
 */
final class RightToLeftRenderingTest extends AbstractFunctionalTestCase
{
    use DataFactoryImportTrait;
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

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

    private function page(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/typography/right-to-left'),
        )->getBody();
    }

    /**
     * Four of the elements wrap their content, and every one of those wrappers
     * has to reach the page. Counted rather than merely found: a page that
     * lost one of them - because an element was retyped, or because the
     * sanitizer stopped keeping the attribute on a wrapper it parsed - still
     * satisfies a plain search for one.
     */
    #[Test]
    public function everyBlockOfThePageKeepsItsDirectionAndItsLanguage(): void
    {
        $body = $this->page();

        $this->assertSame(
            4,
            substr_count($body, '<div dir="rtl" lang="ar">'),
            'A "dir" wrapper of the page is missing.',
        );
    }

    /**
     * The one wrapper that is rich text keeps `dir` and `lang`, through the
     * save transformation of the preset and through `lib.parseFunc_RTE`.
     *
     * Named per element, because the count above cannot tell a wrapper that
     * survived that path from one that was never put through it: three of the
     * four are `html` elements rendered raw, and they would hold the count at
     * four on their own while the rich text path lost its only one.
     */
    #[Test]
    public function theDirectionWrapperSurvivesTheRichTextPath(): void
    {
        $this->assertStringContainsString(
            '<div dir="rtl" lang="ar">',
            $this->element(15205),
            'The wrapper of the rich text element c15205 did not survive the way in and out again.',
        );
    }

    /**
     * The rendered content element with the given uid, from its wrapper to the
     * start of the next one.
     */
    private function element(int $uid): string
    {
        $body = $this->page();
        $start = strpos($body, sprintf('id="c%d"', $uid));
        $this->assertNotFalse($start, sprintf('The page renders no element c%d.', $uid));

        $next = preg_match('/\bid="c\d+"/', $body, $ignored, PREG_OFFSET_CAPTURE, $start + 1) === 1
            ? $ignored[0][1]
            : strlen($body);

        return substr($body, $start, $next - $start);
    }

    /**
     * The page itself is an English page, and says so. The wrapper is what is
     * right to left, not the document: the site header, the navigation and
     * the footer are rendered once per page and stay as they are, which the
     * last element of the page states and this holds it to.
     *
     * The value of `lang` differs between the two core versions and is not
     * the theme's: TYPO3 v12.4 writes the language code of the site
     * language's locale, `en`, and v13.4 its hreflang, `en-US`
     * (`EXT:frontend/Classes/Http/RequestHandler.php`, `$htmlTagAttributes`,
     * on both). Either says the document is English, which is what this is
     * about.
     */
    #[Test]
    public function theDocumentItselfStaysLeftToRight(): void
    {
        $body = $this->page();

        $this->assertMatchesRegularExpression('#<html[^>]*\blang="en(?:-US)?"#', $body);
        $this->assertDoesNotMatchRegularExpression('#<html[^>]*\bdir="rtl"#', $body);
    }

    /**
     * The link that leaves the site is the one specimen of the page that is
     * not written out in the record: `LinkDecoration` adds the marker span and
     * the kind class to a link TYPO3 built, and the stylesheet mirrors the
     * marker. Inside a `dir` wrapper that decoration still has to happen - the
     * listener sees a link, not a direction - so the wrapper must not have
     * taken the anchor with it into raw output.
     */
    #[Test]
    public function theLinkThatLeavesTheSiteIsDecoratedInsideTheWrapper(): void
    {
        // Every wrapper up to its first closing tag. Several of them hold an
        // anchor - a footnote reference does too - so the assertion is that
        // *one* of them holds the decorated one, not that the first does.
        preg_match_all('#<div dir="rtl" lang="ar">(?:(?!</div>).)*#s', $this->page(), $blocks);
        $this->assertNotSame([], $blocks[0], 'The page renders no right-to-left block at all.');

        $decorated = array_values(array_filter(
            $blocks[0],
            static fn(string $block): bool => str_contains($block, 'class="theme-link theme-link--external"'),
        ));

        $this->assertCount(1, $decorated, 'No right-to-left block holds a link decorated as leaving the site.');
        $this->assertStringContainsString('<span class="theme-link__marker" aria-hidden="true"></span>', $decorated[0]);
    }

    /**
     * `bdi` says where a run of user data begins, so a value starting with a
     * strong right-to-left character cannot reorder the sentence around it.
     *
     * It is on the page, and it is on the page as **raw markup**, which is the
     * only way it can be: the frontend sanitizer allows the tag
     * (`CommonBuilder::createBasicTags()`), but the save transformation of the
     * theme's preset does not - `allowTags` of the imported
     * `EXT:rte_ckeditor/Configuration/RTE/Processing.yaml` has no `bdi` and no
     * `bdo` - so a rich text field never gets to store one.
     *
     * This asserts the specimen is rendered. The reason it cannot be rich text
     * is pinned by `RichTextPresetTest::thePresetCannotWriteAnIsolatedRun`,
     * which goes red if a core release adds the tag - at which point this
     * specimen could move into a `text` element and prove rather more.
     */
    #[Test]
    public function theIsolatedRunIsRenderedAsRawMarkup(): void
    {
        $this->assertStringContainsString('<bdi>', $this->element(15206));
    }
}
