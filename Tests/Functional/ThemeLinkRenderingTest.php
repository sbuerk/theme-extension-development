<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
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

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/ThemeLinks.csv');
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
     * The anchor whose text ends with the given label, with its markup.
     */
    private function anchor(string $fragment, string $label): string
    {
        $matched = preg_match(
            sprintf('#<a [^>]*>(?:(?!</a>).)*?%s\s*</a>#s', preg_quote($label, '#')),
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

    #[Test]
    public function aCardLinkRendersItsIconBeforeTheLabel(): void
    {
        $anchor = $this->anchor($this->contentElement($this->render(), 80), 'Open the card');

        $this->assertMatchesRegularExpression('#class="theme-card__link"[^>]*>\s*<svg class="theme-icon" aria-hidden="true"#', $anchor);
        $this->assertStringContainsString($this->pathOf('arrow-right'), $anchor);
    }
}
