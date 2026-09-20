<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The language menu, and the state it renders for a page that is not
 * translated.
 *
 * Two things here fail quietly rather than loudly, which is why both are
 * asserted against a real two-language site:
 *
 *   - `LanguageMenuProcessor` derives `current` from the item states
 *     `CUR`/`CURIFSUB`, and the language menu never emits either - it marks
 *     the language being viewed `ACT`. A template built on `current` marks
 *     nothing at all, and the menu looks like a plain list of languages.
 *   - An untranslated language comes back with `available = 0`. Rendered as a
 *     link anyway it promises a translation that does not exist; left out
 *     entirely it tells a reader the site has fewer languages than it has.
 */
final class LanguageMenuRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    private const BASE = 'https://theme.example.com/';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
        'DE' => ['id' => 1, 'title' => 'German', 'locale' => 'de_DE.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteWithTwoLanguages.csv');
        $this->writeThemeSiteConfiguration(german: true);
        $this->setUpThemeRootPage();
    }

    /**
     * Writes the site configuration, with or without the second language.
     *
     * `ThemeSiteTrait::setUpThemeSite()` is not used directly: it writes a
     * single language site, and the second language is the whole subject
     * here. What it *is* used for is the part that differs between the cores,
     * through the same delivery object - a `dependencies` key on v13, nothing
     * on v12, where site sets do not exist.
     */
    private function writeThemeSiteConfiguration(bool $german): void
    {
        $languages = [
            $this->buildDefaultLanguageConfiguration(
                identifier: 'EN',
                base: self::BASE,
            ),
        ];
        if ($german) {
            // "strict": there is no fallback, so a page without a
            // translation is genuinely unavailable in German. A
            // "fallbacks" of the default language would not change the
            // menu either - the overlay chain filters it out.
            $languages[] = $this->buildLanguageConfiguration(
                identifier: 'DE',
                base: self::BASE . 'de/',
                fallbackType: 'strict',
            );
        }

        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: self::BASE,
                websiteTitle: 'Theme',
            ) + $this->themeDelivery()->siteConfiguration(),
            $languages,
        );
    }

    /**
     * The root page, and the `sys_template` record the v12 delivery needs.
     *
     * Separate from the site configuration above because the one language
     * case rewrites only the latter: calling this twice would write a second
     * `sys_template` record for the same page.
     */
    private function setUpThemeRootPage(): void
    {
        $delivery = $this->themeDelivery();

        $this->setUpFrontendRootPage(
            1,
            [],
            $delivery->templateValues(),
            $delivery->createsSysTemplateRecord(),
        );
    }

    private function render(string $path): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest(self::BASE . ltrim($path, '/')),
        )->getBody();
    }

    /**
     * The language menu landmark of the site header.
     */
    private function languageMenu(string $body): string
    {
        $matched = preg_match('#<nav\b[^>]*class="theme-language-menu"[^>]*>(.*?)</nav>#s', $body, $matches);
        $this->assertSame(1, $matched, 'No language menu was rendered.');

        return $matches[0];
    }

    /**
     * A page that has its translation offers both languages as links, and the
     * language being read is marked with `aria-current="true"` - not
     * `"page"`, which would claim the entry is a different page.
     */
    #[Test]
    public function aTranslatedPageOffersEveryLanguageAsALink(): void
    {
        $menu = $this->languageMenu($this->render('/translated'));

        $this->assertSame(2, substr_count($menu, '<a class="theme-language-menu__link"'));
        $this->assertStringNotContainsString('--unavailable', $menu);
        $this->assertMatchesRegularExpression(
            '#<a class="theme-language-menu__link" href="[^"]*" hreflang="en-US" lang="en-US" aria-current="true">English</a>#',
            $menu,
        );
        $this->assertMatchesRegularExpression(
            '#<a class="theme-language-menu__link" href="[^"]*/de/[^"]*" hreflang="de-DE" lang="de-DE">German</a>#',
            $menu,
        );
    }

    /**
     * The honest state: a page with no translation shows the language, and
     * shows that it cannot be reached. It is a span rather than a link - so
     * there is nothing to click and nothing in the tab sequence - and it
     * carries `aria-disabled`, which is what a screen reader announces.
     */
    #[Test]
    public function anUntranslatedPageShowsTheLanguageAsUnavailable(): void
    {
        $menu = $this->languageMenu($this->render('/untranslated'));

        $this->assertSame(1, substr_count($menu, '<a class="theme-language-menu__link"'), 'Only the language being read is a link.');
        $this->assertMatchesRegularExpression(
            '#<span class="theme-language-menu__link theme-language-menu__link--unavailable" lang="de-DE" aria-disabled="true">German</span>#',
            $menu,
            'The untranslated language is not rendered as an unavailable entry.',
        );
    }

    /**
     * Exactly one entry is marked as the language being read, and it is the
     * one the page is in. Reading `current` instead of `active` marks none.
     */
    #[Test]
    public function exactlyTheLanguageBeingReadIsMarked(): void
    {
        foreach (['/translated', '/untranslated'] as $path) {
            $menu = $this->languageMenu($this->render($path));

            $this->assertSame(
                1,
                substr_count($menu, 'aria-current="true"'),
                sprintf('"%s" marks no language, or more than one, as the one being read.', $path),
            );
            $this->assertMatchesRegularExpression('#aria-current="true">English</a>#', $menu);
        }
    }

    /**
     * The menu sits in the header dropdown, and the pairing the script and
     * assistive technology both rely on has to be right: the trigger names a
     * panel that exists, and the panel is a popover, so the browser keeps it
     * closed until the trigger opens it.
     *
     * `popovertarget` is what pairs the two - not `aria-controls`, which the
     * hand-rolled disclosure used before the Popover API replaced it.
     * `aria-expanded` is still on the trigger and still starts `false`; the
     * script mirrors it from the panel's `toggle` event and never sets it
     * here, so the server-rendered value is the one asserted.
     */
    #[Test]
    public function theMenuSitsInADropdownWiredToItsPanel(): void
    {
        $body = $this->render('/translated');

        $matched = preg_match('#<button[^>]*class="theme-dropdown__trigger"[^>]*>#', $body, $button);
        $this->assertSame(1, $matched, 'The header has no dropdown trigger.');
        $this->assertStringContainsString('aria-expanded="false"', $button[0]);
        $this->assertSame(1, preg_match('#popovertarget="([^"]+)"#', $button[0], $controls));

        $this->assertMatchesRegularExpression(
            sprintf('#<div class="theme-dropdown__panel" id="%s" popover>#', preg_quote($controls[1], '#')),
            $body,
            'The trigger points at an id no panel carries, or the panel is not a popover.',
        );
        // The menu is inside that panel, not loose in the header.
        $this->assertMatchesRegularExpression('#<div class="theme-dropdown__panel"[^>]*>\s*<nav class="theme-language-menu"#', $body);
    }

    /**
     * A site with one language gets no dropdown and no menu.
     *
     * This is the case a truthiness guard gets wrong, which is why it is
     * asserted rather than assumed. `LanguageMenuProcessor` defaults
     * `special.value` to `auto` and returns early only when the site has no
     * language at all - one language yields a menu of exactly one item, the
     * language already being read. `{languageMenu}` is therefore *not* empty
     * here, and `Partials/Page/Header.html` has to count to leave the control
     * out.
     *
     * The site configuration written in `setUp()` is replaced rather than
     * added to: `writeSiteConfiguration()` removes the previous directory for
     * the identifier before writing, so the second call is the one that
     * counts.
     */
    #[Test]
    public function aSiteWithOneLanguageGetsNoLanguageDropdown(): void
    {
        $this->writeThemeSiteConfiguration(german: false);

        $body = $this->render('/translated');

        $this->assertStringNotContainsString(
            'theme-language-menu',
            $body,
            'A site with one language renders a language menu of one entry.',
        );
        $this->assertStringNotContainsString(
            'theme-dropdown__trigger',
            $body,
            'A site with one language still offers the dropdown that holds the menu.',
        );
        // The page itself is fine - this is not an empty response.
        $this->assertStringContainsString('theme-site-header__actions', $body);
    }

    /**
     * Every navigation landmark of the page is named, the language menu
     * included - a reader moving by landmark gets a list of them.
     */
    #[Test]
    public function theLanguageMenuIsANamedLandmark(): void
    {
        $this->assertMatchesRegularExpression(
            '#<nav class="theme-language-menu" aria-label="[^"]+">#',
            $this->render('/translated'),
        );
    }
}
