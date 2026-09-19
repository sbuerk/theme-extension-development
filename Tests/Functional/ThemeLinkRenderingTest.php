<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The links the theme renders from its own fields: the "theme_link" palette of
 * its content elements and the link of an inline list item.
 *
 * An editor chooses how such a link looks - a style and an icon - and neither
 * choice fails loudly when it is lost: a style that does not reach the markup
 * renders the default button, an icon that does not renders the label alone.
 * Both look deliberate on a page, which is why they are asserted here.
 */
final class ThemeLinkRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/ThemeLinks.csv');

        // The file a "t3://file" link of the fixture names. A test instance has
        // a "fileadmin/" folder and no storage record.
        $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        GeneralUtility::writeFile($this->instancePath . '/fileadmin/theme-link.pdf', '%PDF-1.4');
        $this->setUpThemeSite();

        // A second site of the same installation. A link to it is not a link
        // "elsewhere", although its host is not the host of the request.
        $this->writeSiteConfiguration(
            'other',
            $this->buildSiteConfiguration(
                rootPageId: 2,
                base: 'https://other.example.com/',
                websiteTitle: 'Other',
            ),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://other.example.com/',
                ),
            ],
        );
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    private function contentElement(string $body, int $uid): string
    {
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('Content element %d was not rendered.', $uid));

        return $matches[0];
    }

    /**
     * The anchor whose text ends with the given label - followed only by the
     * spans a decorated link carries - with its markup.
     */
    private function anchor(string $fragment, string $label): string
    {
        $matched = preg_match(
            sprintf('#<a [^>]*>(?:(?!</a>).)*?%s\s*(?:<span [^>]*>[^<]*</span>)*</a>#s', preg_quote($label, '#')),
            $fragment,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No link labelled "%s" was rendered.', $label));

        return $matches[0];
    }

    /**
     * The path of an icon of the set, which tells two icons apart where the
     * attribution comment and the attributes do not.
     */
    private function pathOf(string $icon): string
    {
        preg_match('# d="([^"]+)"#', (new IconSet())->markup($icon), $matches);
        $this->assertNotSame('', $matches[1] ?? '', sprintf('The icon "%s" has no path.', $icon));

        return $matches[1];
    }

    /**
     * @return \Generator<string, array{uid: int, label: string, class: string}>
     */
    public static function linkStyles(): \Generator
    {
        yield 'button, the default' => ['uid' => 10, 'label' => 'Primary link', 'class' => 'theme-button'];
        yield 'secondary' => ['uid' => 20, 'label' => 'Secondary link', 'class' => 'theme-button theme-button--secondary'];
        yield 'ghost' => ['uid' => 30, 'label' => 'Ghost link', 'class' => 'theme-button theme-button--ghost'];
        yield 'link' => ['uid' => 40, 'label' => 'Link link', 'class' => 'theme-button theme-button--link'];
    }

    #[DataProvider('linkStyles')]
    #[Test]
    public function eachLinkStyleRendersItsButtonModifier(int $uid, string $label, string $class): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), $uid), $label);

        $this->assertMatchesRegularExpression(sprintf('#\bclass="%s"#', preg_quote($class, '#')), $anchor);
    }

    /**
     * Every style the form offers is one the template renders, and the other
     * way round: a value added to the select without a case in the partial
     * renders the default button.
     */
    #[Test]
    public function theFormOffersExactlyTheStylesTheTemplateRenders(): void
    {
        $items = $GLOBALS['TCA']['tt_content']['columns']['tx_theme_link_variant']['config']['items'] ?? [];

        $this->assertSame(['', 'secondary', 'ghost', 'link'], array_column($items, 'value'));
    }

    /**
     * The link of a card of the card group takes the same styles, through the
     * same partial: the column of the child carries the configuration of the
     * element's, so the two lists cannot drift apart.
     */
    #[Test]
    public function aCardLinkOffersTheStylesOfTheLinkOfAnElement(): void
    {
        $this->assertSame(
            $GLOBALS['TCA']['tt_content']['columns']['tx_theme_link_variant']['config'],
            $GLOBALS['TCA']['tx_theme_list_item']['columns']['link_variant']['config'] ?? null,
        );
    }

    #[Test]
    public function aLinkIconRendersBeforeTheLabelThroughTheIconViewHelper(): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 50), 'Next step');

        $this->assertMatchesRegularExpression(
            '#^<a [^>]*class="theme-button"[^>]*>\s*<svg class="theme-icon" aria-hidden="true" focusable="false" [^>]*><!--! Font Awesome Free .*?</svg>\s*Next step\s*</a>$#s',
            $anchor,
        );
        $this->assertStringContainsString($this->pathOf('arrow-right'), $anchor);
    }

    /**
     * A stored name the set no longer has - Font Awesome renamed it in a
     * version the theme updated to - loses the icon, not the page.
     */
    #[Test]
    public function aLinkIconTheSetNoLongerHasRendersTheLinkWithoutIt(): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 60), 'Still a link');

        $this->assertStringNotContainsString('<svg', $anchor);
    }

    #[Test]
    public function aListItemLinkRendersItsIconBeforeTheLabel(): void
    {
        $list = $this->contentElement($this->render(), 70);

        $withIcon = $this->anchor($list, 'Documentation');
        $this->assertMatchesRegularExpression('#class="theme-content-menu__link"[^>]*>\s*<svg class="theme-icon" aria-hidden="true"#', $withIcon);
        $this->assertStringContainsString($this->pathOf('book-open'), $withIcon);

        $this->assertStringNotContainsString('<svg', $this->anchor($list, 'Plain'));
    }

    private const MARKER = '<span class="theme-link__marker" aria-hidden="true"></span>';

    /**
     * @return \Generator<string, array{label: string, kind: string}>
     */
    public static function richTextLinksByKind(): \Generator
    {
        yield 'another site' => ['label' => 'External site', 'kind' => 'external'];
        yield 'an email address' => ['label' => 'Write to us', 'kind' => 'mail'];
        yield 'a phone number' => ['label' => 'Call us', 'kind' => 'tel'];
    }

    /**
     * A link in rich text reaches "typolink" through "lib.parseFunc_RTE" and
     * is marked by the type TYPO3 resolved it to, with the marker last and no
     * hint: neither opens a window nor starts a download.
     */
    #[DataProvider('richTextLinksByKind')]
    #[Test]
    public function aLinkInRichTextIsMarkedByItsKind(string $label, string $kind): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 90), $label);

        $this->assertMatchesRegularExpression(sprintf('#\bclass="theme-link theme-link--%s"#', $kind), $anchor);
        $this->assertStringEndsWith($label . self::MARKER . '</a>', $anchor);
        $this->assertStringNotContainsString('theme-link__hint', $anchor);
    }

    /**
     * A page of the site is not marked, and neither is an absolute URL on the
     * host of the site: "external" is the host, not the shape of the "href".
     */
    #[Test]
    public function aLinkToThisSiteIsNotMarked(): void
    {
        $element = $this->contentElement($this->render(), 90);

        $this->assertStringNotContainsString('theme-link', $this->anchor($element, 'Start page'));
        $this->assertStringNotContainsString('theme-link', $this->anchor($element, 'Own host'));
    }

    /**
     * A link that opens a window says so before it is followed, in words a
     * screen reader reads - the marker alone is decoration.
     */
    #[Test]
    public function aLinkOpeningANewWindowSaysSo(): void
    {
        $body = $this->render();
        $hint = '<span class="theme-link__hint"> (opens in a new window)</span>';

        $inText = $this->anchor($this->contentElement($body, 90), 'New window');
        $this->assertMatchesRegularExpression('#\bclass="theme-link theme-link--external"#', $inText);
        $this->assertStringEndsWith('New window' . self::MARKER . $hint . '</a>', $inText);

        $button = $this->anchor($this->contentElement($body, 110), 'Read elsewhere');
        $this->assertMatchesRegularExpression('#\bclass="theme-button theme-button--secondary theme-link theme-link--external"#', $button);
        $this->assertMatchesRegularExpression('#\btarget="_blank"#', $button);
        $this->assertStringEndsWith(self::MARKER . $hint . '</a>', $button);
    }

    #[Test]
    public function aLinkToAFileIsADownloadAndSaysSo(): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 100), 'The report');

        $this->assertMatchesRegularExpression('#\bhref="[^"]*fileadmin/theme-link\.pdf"#', $anchor);
        $this->assertMatchesRegularExpression('#\bclass="theme-button theme-link theme-link--download"#', $anchor);
        $this->assertStringEndsWith(self::MARKER . '<span class="theme-link__hint"> (download)</span></a>', $anchor);
    }

    /**
     * A page of the site is no kind a marker names, and a page that opens a
     * new window still says so before it is followed.
     */
    #[Test]
    public function aPageLinkOpeningANewWindowSaysSoWithoutAMarker(): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 120), 'Start in a new window');

        $this->assertStringNotContainsString('theme-link--', $anchor);
        $this->assertStringEndsWith('Start in a new window<span class="theme-link__hint"> (opens in a new window)</span></a>', $anchor);
    }

    /**
     * Another site of the same installation is not "elsewhere": its host is
     * the base of a site, though not the host of the request.
     */
    #[Test]
    public function aLinkToAnotherSiteOfTheInstallationIsNotMarked(): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 120), 'Other site');

        $this->assertStringContainsString('other.example.com', $anchor);
        $this->assertStringNotContainsString('theme-link', $anchor);
    }

    #[Test]
    public function aCardLinkRendersItsIconBeforeTheLabel(): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 80), 'Open the card');

        $this->assertMatchesRegularExpression('#class="theme-card__link"[^>]*>\s*<svg class="theme-icon" aria-hidden="true"#', $anchor);
        $this->assertStringContainsString($this->pathOf('arrow-right'), $anchor);
    }
}
