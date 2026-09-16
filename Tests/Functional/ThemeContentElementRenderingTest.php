<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders the content elements the theme registers itself.
 *
 * Everything covered by `CoreContentElementRenderingTest` exists in
 * `EXT:frontend` and only lacked a rendering. These do not exist at all
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

    private const NO_RENDERING_DEFINITION = 'has no rendering definition';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithThemeContentElements.csv');
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
            'theme_media_teaser_grid', 'theme_notice', 'theme_tabs',
            'theme_accordion', 'theme_cta', 'theme_card_group',
            'theme_timeline', 'theme_teaser_list', 'theme_carousel',
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

    /**
     * One element by its uid, for the types the fixture carries more than once.
     */
    private function contentElementByUid(string $body, int $uid): string
    {
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No element c%d was rendered.', $uid));

        return $matches[0];
    }

    /**
     * @return \Generator<string, array{uid: int, kind: string, role: string}>
     */
    public static function noticeKinds(): \Generator
    {
        yield 'note' => ['uid' => 110, 'kind' => 'note', 'role' => 'note'];
        yield 'info' => ['uid' => 120, 'kind' => 'info', 'role' => 'status'];
        yield 'tip' => ['uid' => 130, 'kind' => 'tip', 'role' => 'note'];
        yield 'success' => ['uid' => 140, 'kind' => 'success', 'role' => 'status'];
        yield 'warning' => ['uid' => 150, 'kind' => 'warning', 'role' => 'alert'];
        yield 'danger' => ['uid' => 160, 'kind' => 'danger', 'role' => 'alert'];
    }

    /**
     * The role is not the same for every kind: two severities are polite live
     * regions, two assertive ones, and the two asides are no live region at
     * all. One role copied onto all six announces an aside, or announces a
     * failure politely - neither of which anybody sees.
     */
    #[DataProvider('noticeKinds')]
    #[Test]
    public function aNoticeTakesTheModifierAndTheRoleOfItsKind(int $uid, string $kind, string $role): void
    {
        $this->assertStringContainsString(
            sprintf('<div class="theme-alert theme-alert--%s" role="%s">', $kind, $role),
            $this->contentElementByUid($this->render(), $uid),
        );
    }

    /**
     * A kind the select does not offer - an import, a value from before a kind
     * was removed - is rendered as the default, not as a modifier nothing
     * styles next to a role nobody chose.
     */
    #[Test]
    public function aNoticeOfAnUnknownKindIsANote(): void
    {
        $notice = $this->contentElementByUid($this->render(), 170);

        $this->assertStringContainsString('<div class="theme-alert theme-alert--note" role="note">', $notice);
        $this->assertStringNotContainsString('bogus', $notice);
    }

    /**
     * A notice with neither title nor text would be an empty live region - of
     * the assertive kind in the fixture, which interrupts a reader for nothing.
     * The content element wrapper still renders; the alert inside it does not.
     */
    #[Test]
    public function anEmptyNoticeRendersNoAlert(): void
    {
        $notice = $this->contentElementByUid($this->render(), 220);

        $this->assertStringContainsString('data-ctype="theme_notice"', $notice);
        $this->assertStringNotContainsString('theme-alert', $notice);
        $this->assertStringNotContainsString('role=', $notice);
    }

    #[Test]
    public function aNoticeRendersItsTitleGlyphAndRichText(): void
    {
        $notice = $this->contentElementByUid($this->render(), 110);

        $this->assertMatchesRegularExpression('#<p class="theme-alert__title">\s*A note\s*</p>#', $notice);
        // The note's icon of the shipped set, not a glyph drawn in the template:
        // the markup of the file as shipped, attribution comment included, with
        // the attributes of the ViewHelper. The stand-in it replaced drew with
        // strokes.
        $this->assertMatchesRegularExpression('#<span class="theme-alert__icon" aria-hidden="true">\s*<svg class="theme-icon" aria-hidden="true" focusable="false" #', $notice);
        $this->assertStringContainsString(
            '<svg class="theme-icon" aria-hidden="true" focusable="false"' . substr((new IconSet())->markup('note-sticky'), 4),
            $notice,
        );
        $this->assertStringNotContainsString('stroke=', $notice);
        // Rich text reaches the page as markup, not escaped.
        $this->assertStringContainsString('<strong>rich</strong>', $notice);
        // The title is inside the component, not a content heading as well.
        $this->assertStringNotContainsString('theme-content-element__heading', $notice);
    }

    /**
     * The three rules of the tabs contract the stylesheet and the script
     * depend on: the first tab selected and every other one out of the tab
     * sequence, a panel carrying nothing but its class and its id, and a
     * heading per panel that labels it while there are no tabs.
     */
    #[Test]
    public function tabsKeepTheMarkupContract(): void
    {
        $tabs = $this->contentElementByUid($this->render(), 180);

        $this->assertSame(1, substr_count($tabs, 'role="tablist"'));
        $this->assertStringContainsString(
            '<button class="theme-tabs__tab" type="button" role="tab" id="c180-tab-1" aria-selected="true" aria-controls="c180-tab-1-panel">Alpha tab</button>',
            $tabs,
        );
        $this->assertStringContainsString(
            '<button class="theme-tabs__tab" type="button" role="tab" id="c180-tab-2" aria-selected="false" aria-controls="c180-tab-2-panel" tabindex="-1">Beta tab</button>',
            $tabs,
        );

        preg_match_all('#<div class="theme-tabs__panel"[^>]*>#', $tabs, $panels);
        $this->assertSame(
            ['<div class="theme-tabs__panel" id="c180-tab-1-panel">', '<div class="theme-tabs__panel" id="c180-tab-2-panel">'],
            $panels[0],
            'A panel carries more than its class and its id.',
        );
        $this->assertSame(2, substr_count($tabs, 'class="theme-tabs__heading"'));
        // One level below the element's own h2.
        $this->assertStringContainsString('<h3 class="theme-tabs__heading">Alpha tab</h3>', $tabs);
    }

    /**
     * Two tab groups on one page: an id they shared would point the tabs of
     * the second at the panels of the first, and both would still render.
     */
    #[Test]
    public function tabIdsAreUniquePerElementAndEveryTabControlsAPanel(): void
    {
        $body = $this->render();

        preg_match_all('#\bid="([^"]+)"#', $body, $ids);
        $duplicates = array_values(array_unique(array_diff_assoc($ids[1], array_unique($ids[1]))));
        $this->assertSame([], $duplicates, 'These ids appear more than once: ' . implode(', ', $duplicates));

        // The tabs only - the page chrome carries "aria-controls" of its own.
        preg_match_all('#role="tab"[^>]*aria-controls="([^"]+)"#', $body, $controlled);
        $this->assertCount(4, $controlled[1], 'Two tab groups of two tabs each were expected.');
        $this->assertSame([], array_values(array_diff($controlled[1], $ids[1])), 'A tab controls a panel that does not exist.');
        $this->assertStringContainsString('id="c190-tab-1-panel"', $body);
    }

    #[Test]
    public function aTabPanelRendersItsTextAsRichText(): void
    {
        $this->assertStringContainsString(
            '<strong>rich</strong>',
            $this->contentElementByUid($this->render(), 180),
        );
    }

    /**
     * The shared `name` is what makes an accordion exclusive, and the browser
     * groups by name across the whole document: every item of one element has
     * to share it, and no two elements may.
     */
    #[Test]
    public function theItemsOfAnAccordionShareOneNameOfTheirElement(): void
    {
        $body = $this->render();

        foreach ([200, 210] as $uid) {
            $accordion = $this->contentElementByUid($body, $uid);
            preg_match_all('#<details class="theme-accordion__item"([^>]*)>#', $accordion, $items);

            $this->assertCount(2, $items[1]);
            foreach ($items[1] as $attributes) {
                $this->assertSame(sprintf(' name="c%d-accordion"', $uid), $attributes);
            }
        }
        // The title, then the chevron icon of the set as the marker.
        $this->assertStringContainsString(
            '<summary class="theme-accordion__summary">First question<svg class="theme-icon theme-accordion__marker" aria-hidden="true" focusable="false"'
            . substr((new IconSet())->markup('chevron-down'), 4) . '</summary>',
            $body,
        );
        $this->assertStringContainsString('<em>rich</em>', $this->contentElementByUid($body, 200));
    }

    /**
     * The child's `text` is rich text for tabs and accordion only, through
     * `overrideChildTca`. Set on the column instead, it would turn the plain
     * text of every other relation into rich text - the teaser grid renders
     * that column through `nl2br()`, and would print the markup as text.
     */
    #[Test]
    public function theListItemTextIsRichTextForTabsAndAccordionOnly(): void
    {
        $this->assertArrayNotHasKey(
            'enableRichtext',
            $GLOBALS['TCA']['tx_theme_list_item']['columns']['text']['config'],
            'The column itself is rich text for every relation.',
        );

        $types = $GLOBALS['TCA']['tt_content']['types'];
        foreach ([
            'theme_tabs' => true,
            'theme_accordion' => true,
            'theme_author' => false,
            'theme_linklist' => false,
            'theme_sociallinks' => false,
            'theme_media_teaser_grid' => false,
        ] as $ctype => $richText) {
            $overrides = $types[$ctype]['columnsOverrides']['tx_theme_list_items']['config']['overrideChildTca'] ?? [];
            $this->assertSame(
                $richText,
                (bool)($overrides['columns']['text']['config']['enableRichtext'] ?? false),
                sprintf('"%s" has the wrong rich text setting for the list item text.', $ctype),
            );
        }
    }
}
