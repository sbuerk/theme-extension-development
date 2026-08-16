<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\ThemeSiteTrait;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Holds the TYPO3 v13 half of the delivery seam to arranging what it claims.
 *
 * See {@see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core12\ThemeDeliveryTest}
 * for why the arrangement is asserted at all rather than only used.
 *
 * The absence of the `sys_template` record is the assertion that matters here.
 * It is not an optimisation: `setUpFrontendRootPage()` writes the record with
 * `'clear' => 3`, a clear-flagged `SysTemplateInclude` resets the AST built so
 * far, and the site set node is added before the records - so a record would
 * throw away exactly the TypoScript this delivery exists to provide, and the
 * page would render empty.
 */
#[Group('not-core-12')]
final class ThemeDeliveryTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(dirname(__DIR__) . '/Fixtures/Database/SiteSetPageTree.csv');
        $this->setUpThemeSite();
    }

    #[Test]
    public function theSeamResolvesTheVersionAwareDelivery(): void
    {
        $this->assertInstanceOf(ThemeDelivery::class, $this->themeDelivery());
    }

    #[Test]
    public function theSiteDeclaresTheThemeSetAsItsDependency(): void
    {
        $configuration = Yaml::parseFile($this->instancePath . '/typo3conf/sites/theme/config.yaml');

        $this->assertIsArray($configuration);
        $this->assertSame(['sbuerk/theme-extension-development'], $configuration['dependencies'] ?? null);
    }

    #[Test]
    public function noSysTemplateRecordIsWritten(): void
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_template')
            ->select(['*'], 'sys_template', ['pid' => 1])
            ->fetchAllAssociative();

        $this->assertSame(
            [],
            $rows,
            'A sys_template record next to a site set discards the TypoScript of the set.',
        );
    }
}
