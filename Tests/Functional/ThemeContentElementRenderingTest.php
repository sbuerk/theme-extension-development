<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders the content elements the theme registers itself.
 *
 * Everything covered by `CoreContentElementRenderingTest` exists in
 * `EXT:frontend` and only lacked a rendering. These ten do not exist at all
 * without this extension: they bring their own TCA, their own columns and an
 * inline child table.
 *
 * That difference is what this test is really about. A missing template shows
 * the core's notice and is obvious; a mistake in the TCA is not. A column that
 * never made it into the schema, an inline relation that resolves to nothing,
 * a `link` field read as though it were a plain URL - each of those renders a
 * page that looks finished and is missing its content.
 */
final class ThemeContentElementRenderingTest extends AbstractFunctionalTestCase
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithThemeContentElements.csv');
        $this->setUpThemeSite();
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    private function contentElement(string $body, string $ctype): string
    {
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
    public static function themeContentTypes(): \Generator
    {
        foreach ([
            'theme_hero', 'theme_hero_small', 'theme_hero_text_only',
            'theme_teaser', 'theme_media_teaser', 'theme_testimonial',
            'theme_author', 'theme_linklist', 'theme_sociallinks',
            'theme_media_teaser_grid',
        ] as $ctype) {
            yield $ctype => ['ctype' => $ctype];
        }
    }

    #[DataProvider('themeContentTypes')]
    #[Test]
    public function everyThemeTypeRendersThroughTheContentElementWrapper(string $ctype): void
    {
        $this->assertStringContainsString(
            sprintf('data-ctype="%s"', $ctype),
            $this->render(),
            sprintf('The "%s" element did not render.', $ctype),
        );
    }

    #[Test]
    public function noThemeElementFallsBackToTheCoreNotice(): void
    {
        $this->assertStringNotContainsString(self::NO_RENDERING_DEFINITION, $this->render());
    }

    #[Test]
    public function aHeroRendersItsHeadingLeadAndAction(): void
    {
        $hero = $this->contentElement($this->render(), 'theme_hero');

        $this->assertStringContainsString('theme-hero', $hero);
        $this->assertStringContainsString('A hero', $hero);
        $this->assertStringContainsString('The hero lead.', $hero);
        $this->assertStringContainsString('Read on', $hero);
    }

    /**
     * `tx_theme_link` is a `link` type field, so its stored value is a TYPO3
     * link reference and not a URL. Rendered as though it were one, the anchor
     * carries `t3://page?uid=1` and the page still looks correct until someone
     * clicks it.
     */
    #[Test]
    public function aLinkFieldIsResolvedToARealUrl(): void
    {
        $hero = $this->contentElement($this->render(), 'theme_hero');

        $this->assertMatchesRegularExpression('#<a[^>]*href="[^"]+"[^>]*>\s*Read on#s', $hero);
        $this->assertStringNotContainsString('t3://', $hero);
    }

    /**
     * The variant selects the button modifier. Getting this wrong renders
     * three identical buttons, which looks deliberate.
     */
    #[Test]
    public function theLinkVariantSelectsTheButtonModifier(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('theme-button--secondary', $this->contentElement($body, 'theme_hero_small'));
        $this->assertStringContainsString('theme-button--ghost', $this->contentElement($body, 'theme_hero_text_only'));
    }

    #[Test]
    public function aTestimonialRendersAsAQuote(): void
    {
        $testimonial = $this->contentElement($this->render(), 'theme_testimonial');

        $this->assertStringContainsString('theme-quote', $testimonial);
        $this->assertStringContainsString('The Analytical Engine', $testimonial);
        $this->assertStringContainsString('Ada Lovelace', $testimonial);
    }

    /**
     * The four list based elements read the inline child table. An inline
     * relation that resolves to nothing renders a correct, empty wrapper -
     * which is indistinguishable from "the editor added no entries".
     */
    #[Test]
    public function aLinkListRendersItsInlineChildren(): void
    {
        $list = $this->contentElement($this->render(), 'theme_linklist');

        $this->assertStringContainsString('Documentation', $list);
        $this->assertStringContainsString('Changelog', $list);

        // Its own children only - the other elements' children share the table.
        $this->assertStringNotContainsString('Homepage', $list);
        $this->assertStringNotContainsString('Somewhere social', $list);
    }

    #[Test]
    public function anAuthorRendersItsPersonAndLinks(): void
    {
        $author = $this->contentElement($this->render(), 'theme_author');

        $this->assertStringContainsString('theme-author', $author);
        $this->assertStringContainsString('Grace Hopper', $author);
        $this->assertStringContainsString('Profile', $author);
        $this->assertStringContainsString('Homepage', $author);
    }

    #[Test]
    public function aTeaserGridRendersOneCardPerChild(): void
    {
        $grid = $this->contentElement($this->render(), 'theme_media_teaser_grid');

        $this->assertStringContainsString('theme-card-grid', $grid);
        $this->assertSame(2, substr_count($grid, 'theme-card__title'));
        $this->assertStringContainsString('First teaser', $grid);
        $this->assertStringContainsString('Second teaser', $grid);
    }

    /**
     * The inline children are ordered by `sorting_foreign` on the parent side,
     * not by the child's own `sorting`. Reading the wrong one puts an editor's
     * carefully ordered list in creation order instead.
     */
    #[Test]
    public function inlineChildrenKeepTheOrderTheEditorGaveThem(): void
    {
        $list = $this->contentElement($this->render(), 'theme_linklist');

        $this->assertLessThan(
            strpos($list, 'Changelog'),
            strpos($list, 'Documentation'),
            'The inline children are not in their declared order.',
        );
    }
}
