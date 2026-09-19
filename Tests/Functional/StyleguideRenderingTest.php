<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Tests\Unit\ComponentLibraryTest;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Holds the styleguide to being complete, and to ignoring the page it sits on.
 *
 * The page renders the component library straight from Fluid rather than from
 * content elements, which is what the brief asked for and what the `styleguide`
 * backend layout is shaped for - its only column is an inert `colPos 999` that
 * no `lib.content.*` object reads.
 *
 * That makes two things worth asserting, and they pull in opposite directions:
 * everything the library ships has to be *on* the page, and everything an
 * editor puts on the page has to stay *off* it.
 */
final class StyleguideRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/StyleguidePage.csv');
        $this->setUpThemeSite();
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/styleguide'),
        )->getBody();
    }

    /**
     * The component list is **not** restated here.
     *
     * `ComponentLibraryTest` already keeps the list of every component in the
     * bundle, and two lists of the same thing drift - silently, because a
     * component missing from the styleguide looks exactly like a styleguide
     * that is simply shorter than it used to be. Reusing that provider is the
     * link between the two.
     *
     * Some of the entries are satisfied by the page frame rather than by a
     * specimen: `.theme-page`, `.theme-site-header`, `.theme-site-footer`,
     * `.theme-skip-link`, and `.theme-settings` with the `.theme-segmented`
     * and `.theme-swatch` controls inside it, are chrome this page carries
     * like any other. That is not a loophole - they *are* demonstrated on the
     * page, which is what this asserts.
     *
     * @return \Generator<string, array{selector: string}>
     */
    public static function everyShippedComponent(): \Generator
    {
        yield from ComponentLibraryTest::shippedComponents();
    }

    #[DataProvider('everyShippedComponent')]
    #[Test]
    public function everyComponentOfTheLibraryIsShownOnTheStyleguide(string $selector): void
    {
        $this->assertStringContainsString(
            // The provider yields CSS selectors; on the page they are class
            // attribute values.
            ltrim($selector, '.'),
            $this->render(),
            sprintf('The styleguide does not demonstrate "%s".', $selector),
        );
    }

    /**
     * Every section the page template claims to render actually renders.
     *
     * The index at the top links to each by id, so a partial that failed to
     * render leaves a link pointing at nothing - a dead anchor, which no other
     * assertion here would notice.
     *
     * @return \Generator<string, array{id: string}>
     */
    public static function everySection(): \Generator
    {
        foreach (['tokens', 'typography', 'lists', 'tables', 'buttons', 'icons', 'boxes', 'data-display', 'features', 'collections', 'pricing', 'interactive', 'forms', 'navigation', 'media', 'variants'] as $id) {
            yield $id => ['id' => $id];
        }
    }

    /**
     * The link is looked for in the index, and only there. A section may link
     * to itself - the band specimens of "media" do - and a search of the whole
     * page found those links and passed with the index link gone.
     */
    #[DataProvider('everySection')]
    #[Test]
    public function everySectionOfTheStyleguideRendersAndIsLinkedFromTheIndex(string $id): void
    {
        $body = $this->render();

        $this->assertStringContainsString(sprintf('id="%s"', $id), $body);
        preg_match('#<nav class="theme-content-menu theme-styleguide__toc"[^>]*>(.*?)</nav>#s', $body, $index);
        $this->assertArrayHasKey(1, $index, 'The styleguide renders no section index.');
        $this->assertStringContainsString(
            sprintf('href="#%s"', $id),
            $index[1],
            sprintf('The section index does not link the section "%s".', $id),
        );
    }

    /**
     * The page ignores what an editor put on it.
     *
     * The fixture places a content element in `colPos 999` - the one column the
     * `styleguide` backend layout offers - and another in `colPos 0`, which the
     * layout does not offer at all but which a page switched to this layout
     * from another one would still carry. Neither may reach the frontend.
     */
    #[Test]
    public function contentPlacedOnTheStyleguidePageIsNotRendered(): void
    {
        $body = $this->render();

        $this->assertStringNotContainsString('Editor content in the unused column', $body);
        $this->assertStringNotContainsString('Editor content left over in the main column', $body);
    }

    /**
     * The brief called out form error handling specifically, and it is the one
     * part of the library no content element exercises: no content element renders a
     * form, so without this section `forms/_validation.scss` would ship
     * unrendered.
     */
    #[Test]
    public function theFormsSectionShowsTheInvalidState(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('theme-field--invalid', $body);
        $this->assertStringContainsString('aria-invalid="true"', $body);
        $this->assertStringContainsString('theme-field__error', $body);
        $this->assertStringContainsString('theme-form-summary', $body);
    }

    /**
     * Every colour token has a swatch.
     *
     * Derived from the stylesheet source rather than from a list, for the same
     * reason as the component sweep above: a token added without a swatch is
     * invisible, and the token section is the only place the palette can be
     * looked at at all.
     */
    #[Test]
    public function everyColourTokenHasASwatch(): void
    {
        $tokens = (string)file_get_contents(
            dirname(__DIR__, 2) . '/Resources/Private/Scss/abstracts/_tokens.scss',
        );
        // Comments first: the file explains its own decisions in prose and
        // names tokens while doing so.
        $tokens = (string)preg_replace('#//.*$#m', '', $tokens);

        preg_match_all('/^\s*(--theme-color-[a-z0-9-]+)\s*:/m', $tokens, $declared);
        $this->assertNotEmpty($declared[1], 'No colour token was found - the path is wrong.');

        $body = $this->render();
        $missing = [];
        foreach (array_unique($declared[1]) as $token) {
            if (!str_contains($body, $token)) {
                $missing[] = $token;
            }
        }
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            'These colour tokens have no swatch on the styleguide: ' . implode(', ', $missing),
        );
    }

    /**
     * A specimen navigation landmark must not be labelled like the real one.
     *
     * The page carries a real main navigation, sub navigation and breadcrumb as
     * chrome, and the navigation section shows a second instance of each. Two
     * landmarks with the same accessible name is a defect that is invisible in
     * every visual check, so it is asserted rather than reviewed.
     */
    #[Test]
    public function noSpecimenLandmarkSharesItsNameWithThePageChrome(): void
    {
        preg_match_all('#<nav\b[^>]*aria-label="([^"]+)"#', $this->render(), $labels);

        $this->assertNotEmpty($labels[1], 'No labelled navigation landmark was rendered at all.');

        $duplicates = array_values(array_diff_assoc($labels[1], array_unique($labels[1])));
        sort($duplicates);

        $this->assertSame(
            [],
            $duplicates,
            'These navigation landmarks share an accessible name: ' . implode(', ', $duplicates),
        );
    }

    /**
     * The visual suite tests the markup TYPO3 renders.
     *
     * `-s visual` renders every styleguide partial with standalone Fluid,
     * without TYPO3 (`Build/Scripts/renderStyleguideFixtures.php`), and takes
     * its screenshots of that. They are only worth something while it is the
     * markup this page carries for the same partial - which stops being true
     * the day a partial starts to depend on a ViewHelper, a variable or a
     * setting only TYPO3 provides. The script refuses a ViewHelper it cannot
     * render, but a variable it cannot see at all: Fluid renders one that is
     * not set as an empty string. This test is the only guard against a
     * partial that starts to expect one.
     *
     * The script is run rather than included, like in
     * `GeneratedLegacyScenarioTest`, so the rendering compared is exactly the
     * one the fixtures are made of. Whitespace runs are collapsed on both
     * sides, as the page places each partial at an indentation of its own.
     */
    #[Test]
    public function everySectionRendersWithoutTypo3AsItDoesOnThePage(): void
    {
        $script = dirname(__DIR__, 2) . '/Build/Scripts/renderStyleguideFixtures.php';
        $output = [];
        $status = 0;
        exec(
            sprintf('%s %s --sections 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script)),
            $output,
            $status,
        );
        $this->assertSame(0, $status, 'The styleguide partials do not render without TYPO3: ' . implode("\n", $output));

        $sections = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections, 'The script rendered no section at all.');

        $collapse = static fn(string $html): string => (string)preg_replace('/\s+/', ' ', trim($html));
        $page = $collapse($this->render());
        $different = [];
        foreach ($sections as $id => $html) {
            if (!str_contains($page, $collapse((string)$html))) {
                $different[] = $id;
            }
        }

        $this->assertSame(
            [],
            $different,
            'These sections render differently without TYPO3 than on the page, so the visual suite'
            . ' does not test what the page shows: ' . implode(', ', $different),
        );
    }

    /**
     * No element on the page may claim an id twice.
     *
     * The specimens duplicate markup that already exists as page chrome, and
     * the form section alone brings a dozen ids of its own. A repeated id
     * breaks every `for`/`aria-describedby`/`aria-controls` reference pointing
     * at it, and the page still looks entirely correct.
     */
    #[Test]
    public function everyIdOnThePageIsUnique(): void
    {
        preg_match_all('#\bid="([^"]+)"#', $this->render(), $ids);

        $duplicates = array_values(array_unique(array_diff_assoc($ids[1], array_unique($ids[1]))));
        sort($duplicates);

        $this->assertSame([], $duplicates, 'These ids appear more than once: ' . implode(', ', $duplicates));
    }

    /**
     * Every id reference on the page points at an id that exists.
     *
     * The uniqueness test above catches an id used twice; this catches the
     * other half - a `for`, `aria-controls`, `aria-labelledby`,
     * `aria-describedby` or `data-theme-dialog-open` naming an id nothing
     * carries. The interactive specimens are wired together by nothing else,
     * and none of the three breaks visibly: a tab pointing at a misspelt panel
     * id still renders, a dialog opener naming the wrong id is still a button,
     * and a tooltip described by a missing id is simply never announced.
     */
    #[Test]
    public function everyIdReferenceOnThePageResolves(): void
    {
        $body = $this->render();

        preg_match_all('#\sid="([^"]+)"#', $body, $ids);
        preg_match_all(
            '#\s(?:for|aria-controls|aria-labelledby|aria-describedby|data-theme-dialog-open)="([^"]+)"#',
            $body,
            $references,
        );
        $this->assertNotEmpty($references[1], 'No id reference was rendered at all.');

        // "aria-labelledby" and "aria-describedby" take a list of ids.
        $referenced = [];
        foreach ($references[1] as $value) {
            $referenced = [...$referenced, ...(preg_split('/\s+/', trim($value)) ?: [])];
        }

        $missing = array_values(array_unique(array_diff($referenced, $ids[1])));
        sort($missing);

        $this->assertSame([], $missing, 'These ids are referenced, but no element carries them: ' . implode(', ', $missing));
    }
}
