<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The condition guarding `Configuration/TypoScript/Static/setup.typoscript`,
 * from the side that only exists once site sets do: a site that uses the set
 * **and** a `sys_template` record selecting the static include.
 *
 * Both mechanisms read the same TypoScript files and neither knows about the
 * other, so without the condition such a site parses the theme twice. The
 * second pass is not visible as duplicated markup - it assigns the same values
 * to the same paths - which is exactly what makes it worth a test: what it
 * destroys is every value the site set up *on top of* the shipped defaults, and
 * the symptom an integrator sees is "my site setting does nothing".
 *
 * The arrangement therefore configures something and then checks it survived:
 * the site's own `constants.typoscript` overrides the palette, and the static
 * include - which comes later in the include tree - would put the shipped
 * default back if the condition let it through.
 *
 * The `sys_template` record is written by hand rather than through
 * `setUpFrontendRootPage()`, because that method hard-codes `'clear' => 3` and
 * a clear-flagged record resets the AST built from the set before any of this
 * can be observed. `clear = 0` is what an integrator adding a template to a set
 * based site ends up with, and on v13 it stays 0: the "nobody set a clear flag"
 * convenience code in `SysTemplateTreeBuilder` treats a TypoScript root site as
 * the row that already cleared.
 *
 * `#[Group('not-core-12')]`: TYPO3 v12 has no site sets (#103437, v13.1), so
 * the condition is trivially true there and there is no second delivery to
 * suppress. The v12 side of the same file - that it renders at all rather than
 * dying in a `TypeError` - is covered by {@see StaticFileIncludeRenderingTest}
 * and {@see StaticTypoScriptFallbackRenderingTest}, neither of which carries a
 * group.
 */
#[Group('not-core-12')]
final class StaticIncludeGuardTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const STATIC_INCLUDE = 'EXT:theme_extension_development/Configuration/TypoScript/Static';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
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
        // After writeSiteConfiguration(), never before: the trait removes the
        // whole site directory before writing config.yaml into it.
        //
        // A site's own "constants.typoscript" is read by SiteConfiguration and
        // becomes the line stream of the site node itself, which the include
        // tree traverser applies after that node's children - so this really is
        // a value set on top of what the set delivers, and not a race.
        GeneralUtility::writeFile(
            $this->instancePath . '/typo3conf/sites/theme/constants.typoscript',
            'theme.appearance.palette = ocean' . LF,
            true,
        );
        // Marks page 1 as site root and clears leftover records; no record of
        // its own, that is the next statement's business.
        $this->setUpFrontendRootPage(1, [], [], false);
        $this->getConnectionPool()->getConnectionForTable('sys_template')->insert(
            'sys_template',
            [
                'pid' => 1,
                'title' => 'Static include beside the set',
                'root' => 1,
                'clear' => 0,
                'include_static_file' => self::STATIC_INCLUDE,
            ],
        );
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    /**
     * The guard is a suppression, so the first thing to rule out is that it
     * suppressed everything: the set still has to deliver a rendered page next
     * to the record.
     */
    #[Test]
    public function theThemeStillRendersBesideTheStaticInclude(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('class="theme-page__main"', $body);
        $this->assertStringContainsString('class="theme-site-header"', $body);
    }

    #[Test]
    public function theThemeIsAppliedExactlyOnce(): void
    {
        $this->assertSame(1, substr_count($this->render(), 'class="theme-site-header"'));
    }

    /**
     * The assertion the guard exists for. Without the condition the static
     * include re-imports `constants.typoscript` of the theme after the site
     * node has been applied, `theme.appearance.palette` falls back to its
     * shipped `neutral`, and the site's own setting is gone without a trace.
     */
    #[Test]
    public function theSiteConfigurationIsNotOverwrittenByTheStaticInclude(): void
    {
        $this->assertStringContainsString(
            'data-palette="ocean"',
            $this->render(),
            'The static include was evaluated although the site set is active, and reset the palette.',
        );
    }
}
