<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The "Bullet list" element renders the list components of the theme.
 *
 * `layout` picks the modifier of `.theme-list` for the unordered and the
 * ordered list, and the definition list is a `.theme-dl--horizontal` of
 * `term|description` lines, split the way fluid_styled_content splits them.
 * See `Templates/ContentElements/Bullets.html` and the `bullets` section of
 * `docs/architecture/content-elements.md`.
 *
 * The list tags are asserted with their whole `class` attribute: a modifier
 * that is written next to the one expected - the check marks on an inline
 * list - is as wrong as a missing one.
 */
final class BulletListRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithBulletLists.csv');
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
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

    /**
     * The markup of one element, by its uid: from its wrapper to the wrapper
     * of the next element, or the end of the main column.
     */
    private function element(int $uid): string
    {
        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'))->getBody();
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('The element %d was not rendered.', $uid));

        return $matches[1];
    }

    /**
     * @return \Generator<string, array{uid: int, tag: string}>
     */
    public static function listLayouts(): \Generator
    {
        yield 'layout 0, bullets or numbers' => ['uid' => 10, 'tag' => '<ul class="theme-list">'];
        yield 'layout 1, check marks' => ['uid' => 20, 'tag' => '<ul class="theme-list theme-list--check">'];
        yield 'layout 2, icons' => ['uid' => 30, 'tag' => '<ul class="theme-list theme-list--icon">'];
        yield 'layout 3, inline' => ['uid' => 40, 'tag' => '<ul class="theme-list theme-list--inline">'];
        yield 'an ordered list takes the modifier as well' => ['uid' => 50, 'tag' => '<ol class="theme-list theme-list--check">'];
        yield 'a layout the theme does not know renders no modifier' => ['uid' => 70, 'tag' => '<ul class="theme-list">'];
    }

    #[DataProvider('listLayouts')]
    #[Test]
    public function theLayoutPicksTheModifierOfTheList(int $uid, string $tag): void
    {
        $element = $this->element($uid);

        $this->assertStringContainsString($tag, $element);
        $this->assertSame(1, preg_match_all('#<(ul|ol|dl)\b#', $element), 'The element renders more or less than one list.');
    }

    /**
     * The icon the editor picked, in front of every item, in the slot the
     * component positions, hidden from assistive technology - and only in the
     * icon layout.
     *
     * The picked icon is told from any other by its path data, read from the
     * shipped file through `IconSet` rather than written out here.
     */
    #[Test]
    public function theIconLayoutPutsThePickedIconInFrontOfEveryItem(): void
    {
        $element = $this->element(30);

        $this->assertSame(3, substr_count($element, '<li>'));
        $this->assertSame(
            3,
            preg_match_all('#<li><span class="theme-list__icon" aria-hidden="true"><svg\b[^>]*\bclass="theme-icon"[^>]*>#', $element),
            'Not every item starts with the icon slot holding an icon of the set.',
        );
        $this->assertSame(1, preg_match('#\bd="([^"]+)"#', (new IconSet())->markup('folder'), $path), 'The shipped "folder" icon has no path.');
        $this->assertSame(3, substr_count($element, $path[0]), 'The slots do not hold the picked icon.');
        $this->assertStringNotContainsString('theme-list__icon', $this->element(20));
    }

    /**
     * The layout "Icons" with no icon picked has nothing to put in the slot,
     * so it is a list with markers rather than a column of empty slots. A
     * name the set does not have - an icon a later version of the set
     * dropped - costs the icon, not the page.
     */
    #[Test]
    public function theIconLayoutWithoutAnIconIsAListWithMarkers(): void
    {
        $element = $this->element(35);

        $this->assertStringContainsString('<ul class="theme-list">', $element);
        $this->assertStringNotContainsString('theme-list__icon', $element);

        $unknown = $this->element(36);
        $this->assertStringContainsString('First item', $unknown);
        $this->assertStringContainsString('Second item', $unknown);
        $this->assertStringNotContainsString('<svg', $unknown);
    }

    /**
     * A line is text, never markup, in every layout.
     */
    #[Test]
    public function anItemIsEscaped(): void
    {
        $element = $this->element(10);

        $this->assertStringContainsString('<li>&lt;b&gt;typed&lt;/b&gt;</li>', $element);
        // The trailing line break of the field is no item.
        $this->assertSame(3, substr_count($element, '<li>'));
    }

    /**
     * `term|description` per line: the first cell is the term, every further
     * non-empty cell a description. A line without "|" is a term without a
     * description, an empty cell adds no empty `<dd>`, and an empty line adds
     * nothing.
     *
     * A line with an empty term - `|Orphan description` - is skipped whole,
     * description included. fluid_styled_content renders an empty `<dt>` and
     * the description there; a description without its term is not a
     * definition, and a list with an empty term is one a screen reader
     * announces as such, so this output deliberately differs.
     */
    #[Test]
    public function aDefinitionListSplitsEveryLineIntoATermAndItsDescriptions(): void
    {
        $element = $this->element(60);

        $this->assertStringContainsString('<dl class="theme-dl theme-dl--horizontal">', $element);
        preg_match_all('#<(dt|dd)>(.*?)</\1>#s', $element, $matches, PREG_SET_ORDER);
        $this->assertSame(
            [
                ['dt', 'Term one'],
                ['dd', 'First description'],
                ['dt', 'Term two'],
                ['dt', 'Term three'],
                ['dd', 'Third a'],
                ['dd', 'Third b'],
                ['dt', 'Term four'],
            ],
            array_map(static fn(array $match): array => [$match[1], trim($match[2])], $matches),
        );
        $this->assertStringNotContainsString('Orphan description', $element);
        $this->assertStringNotContainsString('<dt></dt>', $element);
    }

    #[Test]
    public function aDefinitionListIgnoresTheLayout(): void
    {
        $element = $this->element(80);

        $this->assertStringContainsString('<dl class="theme-dl theme-dl--horizontal">', $element);
        $this->assertStringContainsString('<dt>Term</dt>', $element);
        $this->assertStringContainsString('<dd>Description</dd>', $element);
        $this->assertStringNotContainsString('theme-list', $element);
    }
}
