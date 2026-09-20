<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The site settings that pick a header and a footer partial.
 *
 * `theme.header.variant` picks one of four header partials.
 *
 * The interesting part of this feature is not that four arrangements exist -
 * it is that the value of an editable setting decides which template renders,
 * and that a value nobody planned for cannot do anything worse than fall back
 * to the default. Both are asserted here, on the rendered page, with the
 * setting written into the site configuration the way an integrator writes
 * it.
 *
 * The **widths** of the variants are not asserted here and cannot be: a
 * functional test renders markup, not a layout. The single row of the default
 * header is measured in a browser by `Tests/Acceptance/frontend.spec.ts`, and
 * the other three variants are built so that they never put a third thing in
 * that row - see `Partials/Page/Header.html`.
 */
final class ChromeVariantRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
    }

    /**
     * Writes the site with the given settings and renders its root page.
     *
     * The settings go into the site configuration as a **map** of the full
     * setting keys, which is the format the core stores and advises since
     * TYPO3 v13.4 (#106894, "Site settings.yaml is now stored as a map"); the
     * older tree form is still read, and is deliberately not used here so
     * both cores take the same path through `SiteSettingsFactory`.
     *
     * @param array<string, string|int> $settings
     */
    private function renderWithSettings(array $settings): string
    {
        $site = $this->buildSiteConfiguration(
            rootPageId: 1,
            base: 'https://theme.example.com/',
            websiteTitle: 'Theme',
        ) + [
            'dependencies' => [
                'sbuerk/theme-extension-development',
            ],
        ];
        if ($settings !== []) {
            $site['settings'] = $settings;
        }
        // See SiteSetRenderingTest for why the dependency is not the
        // "additional" argument: https://github.com/sbuerk/typo3-site-based-test-trait/issues/25
        $this->writeSiteConfiguration(
            'theme',
            $site,
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        $this->setUpFrontendRootPage(1, [], [], false);
        // Each test of this class writes the site again with other settings,
        // and both the resolved site and the TypoScript built from its
        // settings are cached - so without this every test after the first
        // renders the first one's header. A test that writes the site once,
        // in "setUp()", needs none of this.
        $this->get(CacheManager::class)->flushCaches();

        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    /**
     * @return \Generator<string, array{variant: string, class: string}>
     */
    public static function variantsAndTheirModifiers(): \Generator
    {
        yield 'centred' => ['variant' => 'centred', 'class' => 'theme-site-header--centred'];
        yield 'actions' => ['variant' => 'actions', 'class' => 'theme-site-header--actions'];
        yield 'two tier' => ['variant' => 'two-tier', 'class' => 'theme-site-header--two-tier'];
    }

    #[DataProvider('variantsAndTheirModifiers')]
    #[Test]
    public function theSettingSelectsTheHeaderVariant(string $variant, string $class): void
    {
        $body = $this->renderWithSettings(['theme.header.variant' => $variant]);

        $this->assertStringContainsString('<header class="theme-site-header ' . $class . '">', $body);
    }

    /**
     * A site that never touches the setting keeps the header it had, with no
     * modifier on it at all. That is what lets the four acceptance tests that
     * measure the single row go on measuring the header they were written
     * for.
     */
    #[Test]
    public function aSiteWithoutTheSettingGetsTheSimpleHeader(): void
    {
        $body = $this->renderWithSettings([]);

        $this->assertStringContainsString('<header class="theme-site-header">', $body);
        $this->assertStringNotContainsString('theme-site-header--', $body);
    }

    /**
     * The value is not a file name. `f:render partial="Page/Header/{variant}"`
     * would be the short way to write the switch and would turn a setting an
     * integrator edits into a file system lookup; the switch names its four
     * partials as literals instead, so anything else renders the default.
     */
    #[Test]
    public function aValueTheSwitchDoesNotKnowRendersTheDefaultHeader(): void
    {
        $body = $this->renderWithSettings(['theme.header.variant' => '../ContentElement/Header']);

        $this->assertStringContainsString('<header class="theme-site-header">', $body);
        $this->assertStringNotContainsString('theme-site-header--', $body);
    }

    /**
     * Both halves of the call to action are settings, and one of them alone
     * is not a call to action: a button with no destination and a button with
     * no name are each worse than no button.
     */
    /**
     * @return \Generator<string, array{settings: array<string, string|int>}>
     */
    public static function incompleteCallToActionSettings(): \Generator
    {
        yield 'a page without a label' => ['settings' => [
            'theme.header.variant' => 'actions',
            'theme.header.actionPage' => 10,
        ]];
        yield 'a label without a page' => ['settings' => [
            'theme.header.variant' => 'actions',
            'theme.header.actionLabel' => 'Get in touch',
        ]];
    }

    /**
     * @param array<string, string|int> $settings
     */
    #[DataProvider('incompleteCallToActionSettings')]
    #[Test]
    public function theCallToActionNeedsBothOfItsSettings(array $settings): void
    {
        $body = $this->renderWithSettings($settings);

        $this->assertStringContainsString('theme-site-header--actions', $body);
        // Not "theme-site-header__action": the controls slot is
        // "theme-site-header__actions", which contains it.
        $this->assertStringNotContainsString('theme-button--primary theme-site-header__action', $body);
    }

    /**
     * The footer takes its own setting, and it works exactly like the
     * header's - same switch, same default with no modifier on it, same
     * fallback for a value nothing matches.
     */
    #[Test]
    public function theSettingSelectsTheFooterVariant(): void
    {
        $body = $this->renderWithSettings(['theme.footer.variant' => 'newsletter']);

        $this->assertStringContainsString('<footer class="theme-site-footer theme-site-footer--newsletter"', $body);
    }

    #[Test]
    public function aSiteWithoutTheSettingGetsTheColumnsFooter(): void
    {
        $body = $this->renderWithSettings([]);

        $this->assertStringContainsString('<footer class="theme-site-footer"', $body);
        $this->assertStringNotContainsString('theme-site-footer--', $body);
    }

    /**
     * The newsletter band needs a heading, a page and a label. One of the
     * three missing leaves a band with nothing to say, or a control that
     * points nowhere or has no name, so the band is not rendered at all.
     *
     * @return \Generator<string, array{settings: array<string, string|int>}>
     */
    public static function incompleteNewsletterSettings(): \Generator
    {
        $complete = [
            'theme.footer.variant' => 'newsletter',
            'theme.footer.newsletterHeading' => 'Read along',
            'theme.footer.newsletterPage' => 10,
            'theme.footer.newsletterLabel' => 'Subscribe',
        ];
        foreach (['theme.footer.newsletterHeading', 'theme.footer.newsletterPage', 'theme.footer.newsletterLabel'] as $missing) {
            $settings = $complete;
            $settings[$missing] = $missing === 'theme.footer.newsletterPage' ? 0 : '';
            yield 'without ' . $missing => ['settings' => $settings];
        }
    }

    /**
     * @param array<string, string|int> $settings
     */
    #[DataProvider('incompleteNewsletterSettings')]
    #[Test]
    public function theNewsletterBandNeedsAllThreeOfItsSettings(array $settings): void
    {
        $body = $this->renderWithSettings($settings);

        $this->assertStringContainsString('theme-site-footer--newsletter', $body);
        $this->assertStringNotContainsString('theme-site-footer__newsletter', $body);
    }

    #[Test]
    public function theNewsletterBandLinksToTheConfiguredPage(): void
    {
        $body = $this->renderWithSettings([
            'theme.footer.variant' => 'newsletter',
            'theme.footer.newsletterHeading' => 'Read along',
            'theme.footer.newsletterText' => 'One mail per release.',
            'theme.footer.newsletterPage' => 10,
            'theme.footer.newsletterLabel' => 'Subscribe',
        ]);

        $this->assertStringContainsString('<h2 class="theme-site-footer__newsletter-heading">Read along</h2>', $body);
        $this->assertStringContainsString('<p>One mail per release.</p>', $body);

        $matched = preg_match(
            '#<a href="([^"]*)" class="theme-button theme-button--primary theme-site-footer__newsletter-action">Subscribe</a>#',
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, 'The newsletter button was not rendered.');
        $this->assertStringStartsWith('/', $matches[1], 'The newsletter button does not lead to a page of the site.');
    }

    #[Test]
    public function theCallToActionLinksToTheConfiguredPage(): void
    {
        $body = $this->renderWithSettings([
            'theme.header.variant' => 'actions',
            'theme.header.actionPage' => 10,
            'theme.header.actionLabel' => 'Get in touch',
        ]);

        $matched = preg_match(
            '#<a href="([^"]*)" class="theme-button theme-button--primary theme-site-header__action">Get in touch</a>#',
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, 'The call to action was not rendered.');
        $this->assertStringStartsWith('/', $matches[1], 'The call to action does not lead to a page of the site.');
        $this->assertStringNotContainsString('t3://', $matches[1]);
    }
}
