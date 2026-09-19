<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\DataFactoryImportTrait;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\ThemeSiteTrait;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds what a development instance is built from, in a functional test
 * instance: the seed set of `packages-dev/dev-site` for the running core
 * version - `theme-instance`, `theme-instance-core12` on TYPO3 v12, see
 * `instanceSeedSet()` - and the site configurations committed below
 * `instance-core-<major>/config/sites/`.
 *
 * The site configurations are **adopted, not written** here. They are what a
 * development instance serves, and a test that built its own would prove that
 * its own configuration works - a trailing slash dropped from the `/legacy/`
 * base, or a set dependency added to the legacy site, would pass here and
 * break the instance. Read from the instance of the running core version, so
 * a site changed for one version is measured on that version.
 */
abstract class AbstractInstanceSeedTestCase extends AbstractFunctionalTestCase
{
    use DataFactoryImportTrait;
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    /**
     * The login page of the seed carries a felogin element, and the development
     * instances require EXT:felogin for it. Loaded here as well, so the seed is
     * written into an installation that has the same content types as the
     * instances it is written into.
     */
    protected array $coreExtensionsToLoad = [
        'typo3/cms-felogin',
        // The instances require rte_ckeditor, so the rich text preset of the
        // theme is registered there, and a save keeps what its processing
        // allows rather than the core's short default list. The seed is
        // written through the same processing here.
        'typo3/cms-rte-ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'sbuerk/data-factory',
        'tests/dev-site',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/AdminBackendUser.csv');
        $this->createDefaultFileStorage();
    }

    /**
     * The seed set the development instance of the running core version is
     * built from - see `ThemeDeliveryInterface::instanceSeedSet()`.
     */
    protected function instanceSeedSet(): string
    {
        return $this->themeDelivery()->instanceSeedSet();
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->instancePath . '/config/sites', true);
        parent::tearDown();
    }

    /**
     * @return array<string, array<string, mixed>> The adopted configurations,
     *         keyed by site identifier.
     */
    protected function adoptCommittedSiteConfigurations(): array
    {
        $adopted = [];

        foreach (glob(self::committedSitesPath() . '/*', GLOB_ONLYDIR) ?: [] as $site) {
            // The name of a directory glob() found: never empty.
            /** @var non-empty-string $identifier */
            $identifier = basename($site);
            /** @var array<string, mixed> $configuration */
            $configuration = Yaml::parseFile($site . '/config.yaml');
            $this->writeSiteConfiguration($identifier, $configuration);
            $adopted[$identifier] = $configuration;
        }

        $this->assertNotSame([], $adopted, 'No committed site configuration found below ' . self::committedSitesPath());

        return $adopted;
    }

    protected static function committedSitesPath(): string
    {
        return sprintf(
            '%s/instance-core-%d/config/sites',
            dirname(__DIR__, 3),
            (new Typo3Version())->getMajorVersion(),
        );
    }
}
