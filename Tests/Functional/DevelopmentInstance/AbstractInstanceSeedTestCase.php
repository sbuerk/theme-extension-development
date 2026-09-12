<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\DataFactoryImportTrait;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds what a development instance is built from, in a functional test
 * instance: the seed set `theme-instance` of `packages-dev/dev-site`, and the
 * site configurations committed below `instance-core-<major>/config/sites/`.
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

    protected const SEED_SET = 'theme-instance';

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
        $writer = $this->get(SiteWriter::class);
        $adopted = [];

        foreach (glob(self::committedSitesPath() . '/*', GLOB_ONLYDIR) ?: [] as $site) {
            $identifier = basename($site);
            /** @var array<string, mixed> $configuration */
            $configuration = Yaml::parseFile($site . '/config.yaml');
            $writer->write($identifier, $configuration);
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
