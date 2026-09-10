<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core12\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance\AbstractInstanceSeedTestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * How the TYPO3 v12 development instance delivers the theme: through a root
 * TypoScript record in both trees, because TYPO3 v12 has no site sets.
 *
 * The v13 counterparts are the two `not-core-12` tests of
 * `DevelopmentInstance/LegacyDeliveryTest`. Without the record on page 1 the
 * "/" tree of a v12 instance renders nothing of the theme, and its markup
 * comparison with the mirror would fail on every page - this names the cause.
 */
#[Group('not-core-13')]
final class InstanceDeliveryTest extends AbstractInstanceSeedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importSeedSet($this->instanceSeedSet());
    }

    #[Test]
    public function bothTreeRootsCarryATypoScriptRecordWithTheStaticInclude(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_template');
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('pid', 'root', 'include_static_file')
            ->from('sys_template')
            ->orderBy('pid')
            ->executeQuery()
            ->fetchAllAssociative();

        $this->assertSame([1, 1001], array_map(static fn(array $row): int => (int)$row['pid'], $rows));
        foreach ($rows as $row) {
            $this->assertSame(1, (int)$row['root']);
            $this->assertSame('EXT:theme_extension_development/Configuration/TypoScript/Static', $row['include_static_file']);
        }
    }

    #[Test]
    public function noSiteDeclaresASetDependency(): void
    {
        foreach ($this->adoptCommittedSiteConfigurations() as $identifier => $configuration) {
            $this->assertArrayNotHasKey('dependencies', $configuration, sprintf('The site "%s" declares a set dependency, which TYPO3 v12 does not read.', $identifier));
        }
    }
}
