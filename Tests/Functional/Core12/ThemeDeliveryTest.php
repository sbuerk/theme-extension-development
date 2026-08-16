<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core12;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\ThemeSiteTrait;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Holds the TYPO3 v12 half of the delivery seam to arranging what it claims.
 *
 * A test harness that arranges nothing looks exactly like a harness that
 * arranges something: the site is written either way, the request is made
 * either way, and the eleven rendering tests then fail with symptoms pointing
 * at templates and TypoScript rather than at the arrangement. So the
 * arrangement is asserted directly, once, here.
 *
 * The group is the selector on top of the class split, the same way
 * `Tests/Functional/Core12/Example/` is selected: `Build/Scripts/runTests.sh`
 * passes `--exclude-group not-core-<version>`, so this class is removed from a
 * v13 run - where `Core12\ThemeDelivery` is not what the seam resolves and the
 * assertions below would be false.
 *
 * @see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13\ThemeDeliveryTest
 *      for the opposite arrangement
 */
#[Group('not-core-13')]
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
    public function aSysTemplateRecordSelectsTheStaticIncludeOfTheTheme(): void
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_template')
            ->select(['*'], 'sys_template', ['pid' => 1])
            ->fetchAllAssociative();

        $this->assertCount(1, $rows, 'The v12 delivery has to write exactly one sys_template record.');
        $this->assertSame(
            'EXT:theme_extension_development/Configuration/TypoScript/Static',
            $rows[0]['include_static_file'],
        );
        // Without the root flag the record is not a root template and the
        // static include is never evaluated for the page tree below it.
        $this->assertSame(1, (int)$rows[0]['root']);
    }

    /**
     * The `dependencies` key would do nothing at all on v12 - site sets are
     * v13.1, #103437 - so writing it anyway would be a claim the version
     * cannot honour, and it would hide a delivery that is not happening.
     */
    #[Test]
    public function theSiteDeclaresNoSetDependency(): void
    {
        $configuration = Yaml::parseFile($this->instancePath . '/typo3conf/sites/theme/config.yaml');

        $this->assertIsArray($configuration);
        $this->assertArrayNotHasKey('dependencies', $configuration);
    }
}
