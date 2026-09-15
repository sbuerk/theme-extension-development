<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The call to action, `theme_cta`, on `.theme-cta`.
 *
 * `tx_theme_cta_width` and `tx_theme_cta_tone` pick one modifier each in
 * `Templates/ContentElements/ThemeCta.html`, and a value nothing offers picks
 * none. The icon is the picked icon of the set, the heading the element's
 * header at its level, and the two links are the two link palettes, both
 * rendered by the partial of the first.
 */
final class CtaRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithCallsToAction.csv');
    }

    private function render(string $path = 'set'): string
    {
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + ($path === 'set' ? ['dependencies' => ['sbuerk/theme-extension-development']] : []),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        if ($path === 'set') {
            $this->setUpFrontendRootPage(1, [], [], false);
        } else {
            $this->setUpFrontendRootPage(1, [], [
                'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
            ]);
        }

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'))->getBody();
        // The page is the theme's, or none of the assertions below means anything.
        $this->assertStringContainsString('data-theme-page-layout=', $body);

        return $body;
    }

    /**
     * The markup of one element, by its uid: from its wrapper to the wrapper
     * of the next element, or the end of the main column.
     */
    private function element(string $body, int $uid): string
    {
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('The element %d was not rendered.', $uid));

        return $matches[1];
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, class: string}>
     */
    public static function modifiers(): \Generator
    {
        foreach (['set' => 'site set', 'static' => 'static include'] as $path => $label) {
            yield 'boxed on the surface, ' . $label => ['path' => $path, 'uid' => 10, 'class' => 'theme-cta theme-cta--boxed'];
            yield 'a band on the tint, ' . $label => ['path' => $path, 'uid' => 20, 'class' => 'theme-cta theme-cta--band theme-cta--accent'];
            yield 'boxed and inverse, ' . $label => ['path' => $path, 'uid' => 30, 'class' => 'theme-cta theme-cta--boxed theme-cta--inverse'];
            yield 'a band as a placeholder, ' . $label => ['path' => $path, 'uid' => 40, 'class' => 'theme-cta theme-cta--band theme-cta--placeholder'];
            yield 'values nothing offers, ' . $label => ['path' => $path, 'uid' => 50, 'class' => 'theme-cta'];
        }
    }

    #[DataProvider('modifiers')]
    #[Test]
    public function theWidthAndTheTonePickTheModifiers(string $path, int $uid, string $class): void
    {
        $this->assertStringContainsString(
            sprintf('<section class="%s">', $class),
            $this->element($this->render($path), $uid),
        );
    }

    /**
     * Icon, heading, text and both links, in that order: the icon of the set
     * in a slot hidden from assistive technology, the heading at h2 when the
     * editor left the level alone, and the second link in its own style.
     * Each link carries its own icon before its label: the second link hands
     * its icon column to the partial like the other three, and an argument
     * left out of that call renders a button without one.
     */
    #[Test]
    public function aCallToActionRendersItsIconHeadingTextAndBothLinks(): void
    {
        $element = $this->element($this->render(), 10);

        $this->assertMatchesRegularExpression(
            '#<section class="theme-cta theme-cta--boxed">\s*<span class="theme-cta__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true"[^>]*>.*?</svg></span>\s*<h2 class="theme-cta__title">Start a site</h2>\s*<div class="theme-cta__text">\s*<p>With the theme\.</p>\s*</div>\s*<div class="theme-cta__actions">#s',
            $element,
        );
        preg_match('# d="([^"]+)"#', (new IconSet())->markup('rocket'), $icon);
        $this->assertStringContainsString($icon[1] ?? 'no path', $element);

        $links = [
            'theme-button' => ['label' => 'Get started', 'icon' => 'arrow-right'],
            'theme-button theme-button--secondary' => ['label' => 'Read the guide', 'icon' => 'arrow-left'],
        ];
        foreach ($links as $class => $link) {
            $matched = preg_match(sprintf('#<a [^>]*class="%s"[^>]*>(.*?)</a>#s', preg_quote($class, '#')), $element, $button);
            $this->assertSame(1, $matched, sprintf('No "%s" link was rendered.', $class));
            $this->assertMatchesRegularExpression(
                sprintf('#^\s*<svg class="theme-icon" aria-hidden="true"[^>]*>.*?</svg>\s*%s\s*$#s', preg_quote($link['label'], '#')),
                $button[1],
            );
            preg_match('# d="([^"]+)"#', (new IconSet())->markup($link['icon']), $path);
            $this->assertStringContainsString($path[1] ?? 'no path', $button[1], sprintf('The "%s" link shows another icon.', $link['label']));
        }
        $this->assertStringNotContainsString('t3://', $element);
    }

    /**
     * Either link may be missing, and the other still renders in its own
     * style. An element without an icon renders no slot.
     */
    #[Test]
    public function eitherLinkRendersWithoutTheOther(): void
    {
        $body = $this->render();

        $first = $this->element($body, 20);
        $this->assertSame(1, substr_count($first, '<a '));
        $this->assertStringContainsString('Only the first', $first);
        $this->assertStringNotContainsString('theme-cta__icon', $first);

        $second = $this->element($body, 30);
        $this->assertSame(1, substr_count($second, '<a '));
        $this->assertMatchesRegularExpression('#<a [^>]*class="theme-button theme-button--link"[^>]*>\s*Only the second\s*</a>#', $second);
    }

    /**
     * No link, no row of actions; "header_layout" 100 hides the heading; a
     * name the set no longer has costs the icon, not the page.
     */
    #[Test]
    public function whatIsNotThereIsNotRendered(): void
    {
        $body = $this->render();

        $this->assertStringNotContainsString('theme-cta__actions', $this->element($body, 40));

        $hidden = $this->element($body, 60);
        $this->assertStringNotContainsString('theme-cta__title', $hidden);
        $this->assertStringNotContainsString('<svg', $hidden);
        $this->assertStringContainsString('An icon the set does not have.', $hidden);
    }

    /**
     * The style of the second link offers what the first does - the partial
     * that renders both has one case per value - and defaults to the
     * outlined button.
     */
    #[Test]
    public function theSecondLinkOffersTheStylesOfTheFirst(): void
    {
        $columns = $GLOBALS['TCA']['tt_content']['columns'];

        $this->assertSame(
            array_column($columns['tx_theme_link_variant']['config']['items'], 'value'),
            array_column($columns['tx_theme_secondary_link_variant']['config']['items'], 'value'),
        );
        $this->assertSame('secondary', $columns['tx_theme_secondary_link_variant']['config']['default']);
    }
}
