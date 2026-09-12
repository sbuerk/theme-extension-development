<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * The login and the logout form of EXT:felogin, drawn on the form contract of
 * the theme, on both delivery paths.
 *
 * The theme adds a template root path to felogin's plugin
 * (`Configuration/TypoScript/Felogin.typoscript`). Nothing breaks when that
 * path goes missing - felogin renders its own template instead, a perfectly
 * working form with none of the theme's classes - so this asserts the theme's
 * markup, and the absence of felogin's own.
 *
 * The templates are the theme's, the fields are felogin's: every hidden field
 * felogin's own templates send has to reach the page under the same condition
 * - the request token, the redirect fields, "noredirect", the permanent
 * login. A template that dropped one still renders a form that logs in; the
 * redirect or the permanent login is what silently stops working.
 *
 * felogin brings its TypoScript differently on the two paths: through its set
 * `typo3/felogin` for a site using sets, and globally for every `sys_template`
 * site otherwise. The theme's path has to arrive on both, next to either.
 * The first case is the theme delivery of the running core version, as
 * `ThemeSiteTrait` arranges it - the set on v13, the static include on v12,
 * which has no sets - and the second the static include on both.
 * `DevelopmentInstance/LoginPageTest` covers the login and logout of the
 * seeded instance; this covers the markup.
 *
 * Fixture: "/" carries a plain login element, "/redirects" one configured for
 * the redirect modes "getpost", "referer" and "logout" with "/goodbye" as the
 * logout target, "/permalogin" one showing the permanent login.
 */
final class FeloginRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    private const BASE = 'https://theme.example.com';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-felogin',
    ];

    /**
     * felogin offers the permanent login only while the session has a
     * lifetime, and "0" offers it unticked - the TYPO3 defaults are no
     * lifetime at all and so no checkbox.
     *
     * The core answers a request carrying a parameter it cannot verify with a
     * cHash with a 404, and "redirect_url", "referer" and "noredirect" are not
     * among the parameters it excludes by default. An installation linking to
     * its login form with them excludes them the same way.
     */
    protected array $configurationToUseInTestInstance = [
        'FE' => [
            'lifetime' => 3600,
            'permalogin' => 0,
            'cacheHash' => [
                'excludedParameters' => ['redirect_url', 'referer', 'noredirect'],
            ],
        ],
    ];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithLoginForm.csv');
    }

    /**
     * @return \Generator<string, array{delivery: string}>
     */
    public static function deliveries(): \Generator
    {
        yield 'theme delivery' => ['delivery' => 'theme'];
        yield 'static include' => ['delivery' => 'static'];
    }

    /**
     * Renders a page of the fixture through one of the two delivery paths,
     * as a visitor or as the logged in frontend user 1.
     */
    private function renderThrough(string $delivery, string $pathAndQuery = '/', bool $loggedIn = false): string
    {
        $site = $this->buildSiteConfiguration(
            rootPageId: 1,
            base: self::BASE . '/',
            websiteTitle: 'Theme',
        );
        $themeDelivery = $this->themeDelivery();
        if ($delivery === 'theme') {
            $siteConfiguration = $themeDelivery->siteConfiguration();
            // A site that depends on sets gets felogin's TypoScript through its
            // set only - felogin's global registration reaches "sys_template"
            // sites alone. A delivery without sets needs nothing added.
            if (isset($siteConfiguration['dependencies']) && is_array($siteConfiguration['dependencies'])) {
                $siteConfiguration['dependencies'][] = 'typo3/felogin';
            }
            $site += $siteConfiguration;
        }
        $this->writeSiteConfiguration(
            'theme',
            $site,
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: self::BASE . '/',
                ),
            ],
        );

        if ($delivery === 'theme') {
            $this->setUpFrontendRootPage(
                1,
                [],
                $themeDelivery->templateValues(),
                $themeDelivery->createsSysTemplateRecord(),
            );
        } else {
            // "include_static_file", the way an installation includes it, and
            // not the two files imported directly: the rendering of a plugin
            // CType is hooked onto the static include as such, see
            // "ext_localconf.php". Imported as files, the page would render the
            // same theme with no plugin rendering at all.
            $this->setUpFrontendRootPage(
                1,
                [],
                [
                    'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
                ],
            );
        }

        $context = $loggedIn ? (new InternalRequestContext())->withFrontendUserId(1) : null;
        $body = (string)$this->executeFrontendSubRequest(new InternalRequest(self::BASE . $pathAndQuery), $context)->getBody();
        // Not a precondition of the theme, but of every assertion below: the
        // plugin ran at all, and rendered the form it was expected to.
        $this->assertStringContainsString(
            sprintf('name="logintype" value="%s"', $loggedIn ? 'logout' : 'login'),
            $body,
            sprintf('The %s form was not rendered.', $loggedIn ? 'logout' : 'login'),
        );

        return $body;
    }

    /**
     * The one tag of the given element carrying the given attribute.
     */
    private function tag(string $body, string $element, string $attribute): string
    {
        $matched = preg_match(
            sprintf('#<%s\b[^>]*\s%s[^>]*>#', $element, preg_quote($attribute, '#')),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No <%s> with %s was rendered.', $element, $attribute));

        return $matches[0];
    }

    /**
     * The value of the hidden field with the given name, or null.
     */
    private function hiddenValue(string $body, string $name): ?string
    {
        if (preg_match(sprintf('#<input\b[^>]*type="hidden"[^>]*\sname="%s"[^>]*>#', preg_quote($name, '#')), $body, $tag) !== 1) {
            return null;
        }
        preg_match('#\svalue="([^"]*)"#', $tag[0], $value);

        return html_entity_decode($value[1] ?? '');
    }

    #[DataProvider('deliveries')]
    #[Test]
    public function theLoginFormIsDrawnOnTheFormContract(string $delivery): void
    {
        $body = $this->renderThrough($delivery);

        $this->assertStringContainsString('class="theme-form"', $this->tag($body, 'form', 'action="'));
        $this->assertStringContainsString('<legend class="theme-fieldset__legend">', $body);
        $this->assertStringContainsString('<label class="theme-field__label" for="tx-felogin-input-username">', $body);
        $this->assertStringContainsString('class="theme-input"', $this->tag($body, 'input', 'name="user"'));
        $this->assertStringContainsString('class="theme-input"', $this->tag($body, 'input', 'name="pass"'));
        $this->assertStringContainsString('class="theme-button"', $this->tag($body, 'button', 'type="submit"'));
    }

    /**
     * felogin's own template marks its hidden fields with a wrapper of its own.
     * Its presence means felogin's template ran rather than the theme's - which
     * renders the fields the theme asserts on above as well, with other
     * markup around them.
     */
    #[DataProvider('deliveries')]
    #[Test]
    public function feloginsOwnTemplateIsNotTheOneThatRan(string $delivery): void
    {
        $this->assertStringNotContainsString('felogin-hidden', $this->renderThrough($delivery));
    }

    /**
     * The required marker is decoration, and a screen reader announces the
     * control's `required` attribute instead - so both have to be there.
     */
    #[DataProvider('deliveries')]
    #[Test]
    public function theRequiredFieldsAreMarkedForEveryone(string $delivery): void
    {
        $body = $this->renderThrough($delivery);

        $this->assertSame(2, substr_count($body, '<span class="theme-field__required" aria-hidden="true">*</span>'));
        $this->assertStringContainsString('required="required"', $this->tag($body, 'input', 'name="user"'));
        $this->assertStringContainsString('required="required"', $this->tag($body, 'input', 'name="pass"'));
    }

    /**
     * The login form carries the signed request token - the login reads the
     * storage folder back from it - and, for the redirect modes "getpost" and
     * "referer", the "redirect_url" and "referer" it was opened with.
     */
    #[DataProvider('deliveries')]
    #[Test]
    public function theLoginFormSendsTheRequestTokenAndTheRedirectFields(string $delivery): void
    {
        $body = $this->renderThrough(
            $delivery,
            '/redirects?redirect_url=' . rawurlencode(self::BASE . '/goodbye') . '&referer=' . rawurlencode(self::BASE . '/'),
        );

        $this->assertNotEmpty($this->hiddenValue($body, '__RequestToken'), 'The login form carries no request token.');
        $this->assertSame(self::BASE . '/goodbye', $this->hiddenValue($body, 'redirect_url'));
        $this->assertSame(self::BASE . '/', $this->hiddenValue($body, 'referer'));
        $this->assertNull($this->hiddenValue($body, 'noredirect'));
    }

    /**
     * "noredirect" switches the redirect off, and the form has to carry it on
     * to the login request - which then drops "redirect_url" as well.
     */
    #[DataProvider('deliveries')]
    #[Test]
    public function theLoginFormSendsNoredirectWhenRedirectsAreSwitchedOff(string $delivery): void
    {
        $body = $this->renderThrough($delivery, '/redirects?noredirect=1&redirect_url=' . rawurlencode(self::BASE . '/goodbye'));

        $this->assertSame('1', $this->hiddenValue($body, 'noredirect'));
        $this->assertNull($this->hiddenValue($body, 'redirect_url'));
    }

    /**
     * The permanent login is a ".theme-check": the checkbox inside its label.
     * The hidden "0" before it is what the login reads when it is not ticked;
     * "f:form.checkbox" writes an empty hidden field of its own right before
     * the checkbox, as it does in felogin's own template.
     */
    #[DataProvider('deliveries')]
    #[Test]
    public function thePermanentLoginIsACheckboxOnTheFormContract(string $delivery): void
    {
        $body = $this->renderThrough($delivery, '/permalogin');

        $this->assertMatchesRegularExpression(
            '#<label class="theme-check">\s*(?:<input type="hidden"[^>]*>)?<input\b[^>]*type="checkbox"[^>]*name="permalogin"#',
            $body,
        );
        $this->assertSame('0', $this->hiddenValue($body, 'permalogin'));
        $this->assertStringNotContainsString('disabled', $this->tag($body, 'input', 'type="hidden" name="permalogin"'));
    }

    /**
     * A plain login element shows no permanent login at all.
     */
    #[DataProvider('deliveries')]
    #[Test]
    public function thePermanentLoginIsOnlyThereWhenItIsSwitchedOn(string $delivery): void
    {
        $this->assertStringNotContainsString('name="permalogin"', $this->renderThrough($delivery));
    }

    #[DataProvider('deliveries')]
    #[Test]
    public function aLoggedInUserGetsTheLogoutFormOnTheFormContract(string $delivery): void
    {
        $body = $this->renderThrough($delivery, '/', true);

        $this->assertStringContainsString('class="theme-form"', $this->tag($body, 'form', 'action="'));
        $this->assertStringContainsString('<legend class="theme-fieldset__legend">', $body);
        $this->assertStringContainsString('<strong>jane</strong>', $body);
        $button = $this->tag($body, 'button', 'id="tx-felogin-input-logout"');
        $this->assertStringContainsString('class="theme-button theme-button--secondary"', $button);
        $this->assertStringContainsString('type="submit"', $button);
        $this->assertStringNotContainsString('felogin-hidden', $body);
        $this->assertNull($this->hiddenValue($body, 'noredirect'));
    }

    #[DataProvider('deliveries')]
    #[Test]
    public function theLogoutFormSendsNoredirectWhenRedirectsAreSwitchedOff(string $delivery): void
    {
        $this->assertSame('1', $this->hiddenValue($this->renderThrough($delivery, '/redirects?noredirect=1', true), 'noredirect'));
    }

    /**
     * TYPO3 v12 and v13 both assign the logout redirect target as "actionUri",
     * and the form posts there: logging out on "/redirects" lands on
     * "/goodbye".
     */
    #[DataProvider('deliveries')]
    #[Test]
    public function theLogoutFormPostsToTheLogoutRedirectTarget(string $delivery): void
    {
        $form = $this->tag($this->renderThrough($delivery, '/redirects', true), 'form', 'action="');

        $this->assertStringContainsString('/goodbye', $form);
    }
}
