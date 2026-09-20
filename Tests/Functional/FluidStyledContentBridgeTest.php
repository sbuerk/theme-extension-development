<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The theme next to `fluid_styled_content`, through the bridge, on the classic
 * static include path - the one delivery both supported core versions have.
 *
 * That extension renders the same classic content elements this theme renders,
 * and beside it the two are not merely redundant but order dependent. Its
 * `Configuration/TypoScript/Helper/ContentElement.typoscript` starts with
 * `lib.contentElement >` and rebuilds the object from its own root paths, on
 * 12.4 and 13.4 alike, so:
 *
 * - loaded **after** the theme it takes the theme's Fluid paths with it, and
 *   the theme's own branches then look for `ContentElements/...` among that
 *   extension's templates;
 * - loaded **before** the theme it leaves its own per-element data processing
 *   underneath the theme's branches, because `=<` and a plain assignment keep
 *   whatever the theme does not overwrite key by key.
 *
 * The second is the quiet one and it is not quiet at all in practice: both
 * extensions wire `menu_categorized_content` through `DatabaseQueryProcessor`
 * and configure it differently - that extension with `join`/`where.wrap`, this
 * theme with a portable `where.cObject` subquery - so the merged keys compose
 * one query out of two, and the page dies on a SQL syntax error rather than
 * rendering something slightly wrong.
 *
 * The bridge - the static include `Configuration/TypoScript/Fsc` here, and the
 * site set `sbuerk/theme-extension-development-fsc` that
 * `FluidStyledContentBridgeSetTest` covers on v13 - clears the classic branches
 * and declares them again from the theme's own file, last. So the promise is
 * not "it mostly works": it is that the page is **byte for byte** the page the
 * theme renders on its own, whichever of the two extensions was loaded first,
 * and that is what the comparison below asserts.
 *
 * `withoutTheBridge...` is the control. The comparison would pass for a
 * trivial reason if that extension were not actually doing anything, so the
 * same fixture is rendered with it and without the bridge, in **both** load
 * orders, and each has to come out different from the theme's own page.
 *
 * Those orders are driven through `include_static_file` because that is the one
 * place the order is specified: two sets that do not depend on each other are
 * ordered by `DependencyOrderingService` and nothing promises which of them
 * wins. The bridge does not care - it depends on both and is therefore last
 * either way - but a test asserting a *broken* combination has to be able to
 * produce it deterministically. It is also the only path v12 has.
 *
 * Each test imports its own page fixture rather than sharing one from
 * `setUp()`: the two fixtures both declare page 1, so importing both is a uid
 * collision.
 */
final class FluidStyledContentBridgeTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const BASE = 'https://theme.example.com/';

    private const THEME_STATIC = 'EXT:theme_extension_development/Configuration/TypoScript/Static';
    private const BRIDGE_STATIC = 'EXT:theme_extension_development/Configuration/TypoScript/Fsc';
    private const FSC_STATIC = 'EXT:fluid_styled_content/Configuration/TypoScript';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-fluid-styled-content',
    ];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    /**
     * Renders the fixture page through a `sys_template` record listing the
     * given static includes in `include_static_file`, which is the order an
     * installation controls.
     *
     * @param list<string> $staticFiles
     */
    private function renderWith(array $staticFiles): string
    {
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: self::BASE,
                websiteTitle: 'Theme',
            ),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: self::BASE,
                ),
            ],
        );

        $this->setUpFrontendRootPage(1, [], [
            'include_static_file' => implode(',', $staticFiles),
        ]);

        $this->get(CacheManager::class)->flushCaches();

        return (string)$this->executeFrontendSubRequest(new InternalRequest(self::BASE))->getBody();
    }

    /**
     * The same, for a combination that is expected to be broken: a page that
     * throws is one of the outcomes under test, not an error in the test.
     *
     * Returns the rendered page, or a description of the failure - either way a
     * string that can be compared against the page the theme renders alone.
     *
     * @param list<string> $staticFiles
     */
    private function renderOutcome(array $staticFiles): string
    {
        try {
            return $this->renderWith($staticFiles);
        } catch (\Throwable $failure) {
            return sprintf('The page did not render: %s: %s', $failure::class, $failure->getMessage());
        }
    }

    #[Test]
    public function theBridgeRendersExactlyWhatTheThemeRendersAlone(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithCoreContentElements.csv');

        $withoutFsc = $this->renderWith([self::THEME_STATIC]);
        // The order an integrator is told to use: that extension first, the
        // theme, and the bridge last.
        $withBridge = $this->renderWith([self::FSC_STATIC, self::THEME_STATIC, self::BRIDGE_STATIC]);

        // The fixture carries a record of every one of the twenty-two CTypes
        // the bridge clears - "header", "text" and "image" were added for that
        // reason - so no branch that extension declares goes unrepresented.
        //
        // Coverage is not discrimination, and it is worth being exact about
        // which is which. Removing a single ">" line changes this comparison
        // only where the two extensions contribute *conflicting* configuration
        // that this fixture actually exercises:
        //
        //   - "menu_categorized_content" does, and hard. Both wire the same
        //     "DatabaseQueryProcessor" differently, the surviving keys compose
        //     one query out of two, and the page dies on a SQL syntax error.
        //     That is the branch this change was red-proofed against.
        //   - "header", "text", "div" and "html" cannot. That extension gives
        //     them nothing but "templateName", which this theme overwrites, so
        //     clearing them is defensive rather than observable.
        //   - the gallery elements cannot *here*: the fixture declares no
        //     "sys_file_reference", so "GalleryProcessor" gets no files and
        //     that extension's leftover border and spacing settings act on
        //     nothing.
        //
        // The three rows still earn their place. They make a future divergence
        // in those branches visible, instead of unrepresented and unnoticed.
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
     * The control of the comparison above, in both load orders.
     *
     * Without the bridge the combination is broken either way - see the class
     * comment for what each order does. If it were not, the comparison above
     * would be asserting that two equivalent configurations render equally,
     * which is worth nothing.
     *
     * What the breakage *is* deliberately is not asserted: it differs between
     * the two orders and may differ between core versions. That it is not the
     * theme's own page is the whole claim.
     *
     * @return \Generator<string, array{staticFiles: list<string>}>
     */
    public static function unbridgedOrders(): \Generator
    {
        yield 'fluid_styled_content after the theme' => [
            'staticFiles' => [self::THEME_STATIC, self::FSC_STATIC],
        ];
        yield 'fluid_styled_content before the theme' => [
            'staticFiles' => [self::FSC_STATIC, self::THEME_STATIC],
        ];
    }

    /**
     * @param list<string> $staticFiles
     */
    #[DataProvider('unbridgedOrders')]
    #[Test]
    public function withoutTheBridgeTheCombinationDoesNotRenderTheThemesPage(array $staticFiles): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithCoreContentElements.csv');

        $themeAlone = $this->renderWith([self::THEME_STATIC]);
        $unbridged = $this->renderOutcome($staticFiles);

        // The comparison is made on digests, and that is a PHPUnit constraint
        // rather than a stylistic one. "assertNotSame()" wraps its constraint
        // in "LogicalNot", whose "negate()" builds a regex out of the whole
        // *expected* value - and it does so whether the assertion passes or
        // fails. A rendered page of this fixture is ~33 KB, "preg_replace()"
        // answers "regular expression is too large", "negate()" returns null
        // against a "string" return type and the test dies with a TypeError
        // reporting nothing about the subject. Measured on PHPUnit 10.5.64,
        // this branch's version, with an assertion that passes.
        // @todo Compare the strings directly once the PHPUnit constraint of
        //       this branch no longer builds that description eagerly.
        // The positive comparison above is unaffected: "assertSame()" has no
        // "LogicalNot" around it.
        $this->assertNotSame(
            md5($themeAlone),
            md5($unbridged),
            'fluid_styled_content changed nothing in this order, so the bridge comparison proves '
            . 'nothing either. Is the extension loaded and its static include resolving?',
        );
    }

    /**
     * The theme's own elements never depended on `lib.contentElement`, and the
     * bridge must not have changed that: they hang off
     * `lib.themeContentElement`, which nothing else clears.
     *
     * `ThemeContentElementObjectTest` proves that independence itself; this
     * only pins that the bridged site still renders them, because the bridge is
     * the one configuration where the object that *is* cleared sits next to
     * them.
     */
    #[Test]
    public function theThemesOwnElementsStillRenderThroughTheBridge(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithThemeContentElements.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/IconContentElements.csv');

        $body = $this->renderWith([self::FSC_STATIC, self::THEME_STATIC, self::BRIDGE_STATIC]);

        $this->assertStringContainsString('data-ctype="theme_notice"', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);
    }
}
