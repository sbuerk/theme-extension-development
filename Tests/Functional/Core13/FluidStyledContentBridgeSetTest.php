<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The bridge to `fluid_styled_content` on the **site set** path.
 *
 * It sits below `Core13/` rather than beside its sibling, and that is a
 * PHPStan requirement rather than a preference: it names `SetRegistry`, a class
 * TYPO3 v12 does not have, and `Build/phpstan/Core12/phpstan.neon` excludes
 * every `Core13` test directory exactly so a v13-only symbol can be named at
 * all.
 *
 * `#[Group('not-core-12')]` for the reason
 * {@see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\SiteSetRenderingTest}
 * carries it: site sets arrived in TYPO3 v13.1 (#103437) and this test's
 * subject *is* the set. `fluid_styled_content` 12.4 ships no `Configuration/Sets/` at all,
 * so on v12 there is neither a `typo3/fluid-styled-content` set to depend on
 * nor a mechanism to depend on it with. What the bridge does there - and what
 * an installation on v12 uses - is the static include, and
 * {@see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\FluidStyledContentBridgeTest}
 * asserts the same byte-for-byte equality on that path, on both versions.
 *
 * The set is shipped on both versions regardless, exactly as the theme's own
 * set is: on v12 `Configuration/Sets/` is read by nothing and is inert rather
 * than an error.
 */
#[Group('not-core-12')]
final class FluidStyledContentBridgeSetTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const BASE = 'https://theme.example.com/';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-fluid-styled-content',
    ];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    /**
     * Renders the fixture page through the sets the site declares, with no
     * `sys_template` record at all - so everything rendered can only come from
     * those sets.
     *
     * @param list<string> $dependencies
     */
    private function renderWith(array $dependencies): string
    {
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: self::BASE,
                websiteTitle: 'Theme',
            ) + ['dependencies' => $dependencies],
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: self::BASE,
                ),
            ],
        );

        $this->setUpFrontendRootPage(1, [], [], false);

        $this->get(CacheManager::class)->flushCaches();

        return (string)$this->executeFrontendSubRequest(new InternalRequest(self::BASE))->getBody();
    }

    /**
     * The same promise the static include test makes, on the set path: the bridged page is byte for byte the
     * page the theme's own set renders alone.
     *
     * The control that keeps this from being vacuous is not a second render
     * here - two sets that do not depend on each other have no specified order,
     * so a *broken* combination cannot be produced deterministically through
     * sets. It is `theBridgeSetActivatesFluidStyledContent()` below, which
     * settles directly that the extension was active at all, plus the control
     * pair of the static include test.
     */
    #[Test]
    public function theBridgeSetRendersExactlyWhatTheThemeSetRendersAlone(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/PageWithCoreContentElements.csv');

        $withoutFsc = $this->renderWith(['sbuerk/theme-extension-development']);
        $withBridge = $this->renderWith(['sbuerk/theme-extension-development-fsc']);

        $this->assertStringContainsString('data-ctype="header"', $withoutFsc);
        $this->assertStringContainsString('data-ctype="text"', $withoutFsc);
        $this->assertStringContainsString('data-ctype="image"', $withoutFsc);
        $this->assertStringContainsString('data-ctype="textmedia"', $withoutFsc);
        $this->assertStringNotContainsString('has no rendering definition', $withBridge);
        $this->assertSame(
            $withoutFsc,
            $withBridge,
            'The bridged page differs from the page the theme renders on its own.',
        );
    }

    /**
     * The bridge set really does activate `fluid_styled_content`.
     *
     * `optionalDependencies` is what does it: `SetRegistry` treats an optional
     * dependency as a dependency once the set it names is installed, so a site
     * declaring only the bridge gets that extension's set as well.
     *
     * Nothing in the rendered markup can show that, and that is the problem
     * this pins. The whole point of the bridge is that the bridged page is byte
     * for byte the page the theme renders alone - so the comparison above would
     * pass just as happily if the optional dependency had never resolved and
     * that extension had never been active at all. On the static include path
     * the control test settles it, because those paths are named literally. On
     * the set path only this does.
     */
    #[Test]
    public function theBridgeSetActivatesFluidStyledContent(): void
    {
        $names = [];
        foreach ($this->get(SetRegistry::class)->getSets('sbuerk/theme-extension-development-fsc') as $set) {
            $names[] = $set->name;
        }

        $this->assertContains('sbuerk/theme-extension-development', $names);
        $this->assertContains(
            'typo3/fluid-styled-content',
            $names,
            'The bridge set did not pull in fluid_styled_content, so the byte-for-byte '
            . 'comparison on the set path would prove nothing.',
        );
    }

    /**
     * The theme's own elements never depended on `lib.contentElement`, and the
     * bridge set must not have changed that.
     */
    #[Test]
    public function theThemesOwnElementsStillRenderThroughTheBridgeSet(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/PageWithThemeContentElements.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/IconContentElements.csv');

        $body = $this->renderWith(['sbuerk/theme-extension-development-fsc']);

        $this->assertStringContainsString('data-ctype="theme_notice"', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);
    }
}
