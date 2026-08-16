<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Arranges a site that delivers the theme, whichever way the running TYPO3
 * version delivers it.
 *
 * Every rendering test needs the same thing — "a site rooted at page N whose
 * pages render through this theme" — and everything else about those tests is
 * version neutral. So this is the one seam where the version difference lives:
 * one entry point, {@see setUpThemeSite()}, and two implementations of
 * {@see ThemeDeliveryInterface} below `Core12/` and `Core13/`. No rendering test
 * knows which one it got, and none of them carries a core version group: a
 * group would delete the test on the other version, which on a v12/v13 branch
 * would mean giving up two thirds of the v12 coverage.
 *
 * ## Why the implementation is picked by hand
 *
 * `Configuration/Services.php` resolves the version aware directory of the
 * *extension* with `sprintf('…Core%d…', (new Typo3Version())->getMajorVersion())`
 * and lets the container do the rest. A test case is not container managed —
 * PHPUnit instantiates it — so the same selector has to be written out here and
 * end in `new`. It is deliberately the identical expression, so the two places
 * stay recognisably the same mechanism rather than looking like two ideas.
 *
 * ## Using it
 *
 * The using class must also use `SiteBasedTestTrait` and declare the
 * `LANGUAGE_PRESETS` constant that trait requires, with at least the preset
 * named by `$languageIdentifier`:
 *
 * ```php
 * final class SomeRenderingTest extends AbstractFunctionalTestCase
 * {
 *     use SiteBasedTestTrait;
 *     use ThemeSiteTrait;
 *
 *     protected const LANGUAGE_PRESETS = [
 *         'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
 *     ];
 *
 *     protected function setUp(): void
 *     {
 *         parent::setUp();
 *         $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SomePageTree.csv');
 *         $this->setUpThemeSite();
 *     }
 * }
 * ```
 */
trait ThemeSiteTrait
{
    /**
     * Writes a single language site rooted at `$rootPageId` and arranges the
     * theme delivery of the running core version for it.
     *
     * The page tree itself is not created here. A test imports the fixture it
     * needs first; this only turns page `$rootPageId` into a site root.
     *
     * The string parameters are `non-empty-string` rather than `string`
     * because that is what `SiteBasedTestTrait` declares for the arguments they
     * are forwarded to. Narrowing them here rather than widening them there
     * keeps the guarantee where it belongs: an empty site identifier writes a
     * configuration nothing can find, and an empty base makes every request
     * miss the site.
     *
     * @param non-empty-string $identifier
     * @param non-empty-string $base
     * @param non-empty-string $websiteTitle
     * @param non-empty-string $languageIdentifier
     */
    protected function setUpThemeSite(
        int $rootPageId = 1,
        string $identifier = 'theme',
        string $base = 'https://theme.example.com/',
        string $websiteTitle = 'Theme',
        string $languageIdentifier = 'EN',
    ): void {
        $delivery = $this->themeDelivery();

        $this->writeSiteConfiguration(
            $identifier,
            $this->buildSiteConfiguration(
                rootPageId: $rootPageId,
                base: $base,
                websiteTitle: $websiteTitle,
            ) + $delivery->siteConfiguration(),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: $languageIdentifier,
                    base: $base,
                ),
            ],
        );

        $this->setUpFrontendRootPage(
            $rootPageId,
            [],
            $delivery->templateValues(),
            $delivery->createsSysTemplateRecord(),
        );
    }

    /**
     * The delivery of the running core version.
     *
     * Protected rather than private because the `ThemeDeliveryTest` beside each
     * implementation asserts what this hands back, and because a test with an
     * arrangement of its own may want to read the same values rather than
     * restate them.
     */
    protected function themeDelivery(): ThemeDeliveryInterface
    {
        $className = sprintf(
            '%s\\Core%d\\ThemeDelivery',
            __NAMESPACE__,
            (new Typo3Version())->getMajorVersion(),
        );

        if (!class_exists($className) || !is_subclass_of($className, ThemeDeliveryInterface::class)) {
            // Not an exception: a missing delivery is a broken test harness,
            // and a failed assertion names the file that has to be added.
            self::fail(sprintf(
                'No theme delivery for the running TYPO3 version: "%s" does not exist or does not implement "%s".',
                $className,
                ThemeDeliveryInterface::class,
            ));
        }

        return new $className();
    }
}
