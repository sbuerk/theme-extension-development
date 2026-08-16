<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Seeding;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\FileImporterInterface;
use SBUERK\ThemeExtensionDevelopment\Seeding\FileSeeder;
use SBUERK\ThemeExtensionDevelopment\Seeding\SeedFile;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Covers the core version aware seam every seed file goes through.
 *
 * `ResourceStorage::addFile()` takes its conflict mode as a class constant on
 * TYPO3 v12 and as a native enum on v13 (#101151), so the call is split into
 * `Core12/Seeding/FileImporter` and `Core13/Seeding/FileImporter` behind
 * {@see FileImporterInterface}. What that split promises is a single behaviour:
 * seeding the same target name twice replaces the file instead of adding a
 * second one — which is what makes a seed definition repeatable.
 *
 * The tests carry **no** PHPUnit group on purpose. A group would restrict them
 * to the version they were written on, and the whole point of a version split
 * is that both implementations keep the same promise. What differs per version
 * is the class name the container hands out, and that is asserted by computing
 * it from `Typo3Version` rather than by having two test classes.
 *
 * {@see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\SeedingTest} covers
 * the rest of the seeding; this class covers only what the split is about.
 */
final class FileImporterTest extends AbstractFunctionalTestCase
{
    private const SOURCE_LANDSCAPE = 'EXT:theme_extension_development/Configuration/Seeds/Files/placeholder.svg';
    private const SOURCE_PORTRAIT = 'EXT:theme_extension_development/Configuration/Seeds/Files/placeholder-portrait.svg';

    private const TARGET_FOLDER = 'importer-test';
    private const TARGET_NAME = 'placeholder.svg';

    protected function setUp(): void
    {
        parent::setUp();

        // A functional instance has no file storage: the testing framework
        // creates the fileadmin folders but no sys_file_storage record, which a
        // real instance gets from "typo3 setup".
        GeneralUtility::makeInstance(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'File importer test storage', true);
    }

    #[Test]
    public function registeredImplementationMatchesTheRunningCoreVersion(): void
    {
        $importer = $this->get(FileImporterInterface::class);

        $this->assertInstanceOf(FileImporterInterface::class, $importer);
        $this->assertSame(
            sprintf(
                'SBUERK\\ThemeExtensionDevelopment\\Core%d\\Seeding\\FileImporter',
                (new Typo3Version())->getMajorVersion(),
            ),
            $importer::class,
            'The container registered the file importer of a different core version than the one running. '
            . 'Only the "Core<major>/" directory of the running version is loaded, see "Configuration/Services.php".',
        );
    }

    #[Test]
    public function seedingTheSameTargetNameTwiceReplacesTheFile(): void
    {
        $first = $this->seed(self::SOURCE_LANDSCAPE);
        $second = $this->seed(self::SOURCE_PORTRAIT);

        // The same "sys_file" uid comes back, so the second run updated the
        // index entry of the existing file rather than indexing a second one.
        // With the "rename" conflict mode this is a new uid, with "cancel" the
        // second call throws before it gets here.
        $this->assertSame(
            $first['placeholder'],
            $second['placeholder'],
            'Seeding the same file twice produced two "sys_file" records.',
        );

        $files = $this->queryColumn('sys_file', 'identifier');
        $this->assertSame(
            ['/' . self::TARGET_FOLDER . '/' . self::TARGET_NAME],
            $files,
            'The storage holds more than the one file that was seeded twice — the second run renamed instead '
            . 'of replacing.',
        );
    }

    #[Test]
    public function seedingTheSameTargetNameTwiceOverwritesTheContent(): void
    {
        $this->seed(self::SOURCE_LANDSCAPE);
        $this->seed(self::SOURCE_PORTRAIT);

        // Reusing the row is only half of "replace": the bytes on disk have to
        // be the ones of the second source, otherwise a seed could never
        // correct a file it shipped earlier.
        $this->assertSame(
            (string)file_get_contents(GeneralUtility::getFileAbsFileName(self::SOURCE_PORTRAIT)),
            $this->storage()->getFile('/' . self::TARGET_FOLDER . '/' . self::TARGET_NAME)->getContents(),
        );
    }

    #[Test]
    public function seedingLeavesTheSourceFileWhereItIs(): void
    {
        $this->seed(self::SOURCE_LANDSCAPE);

        // "addFile()" defaults to removing the original, which would move the
        // seed file out of the extension it was read from — the seed would
        // destroy itself on its first run.
        $this->assertFileExists(GeneralUtility::getFileAbsFileName(self::SOURCE_LANDSCAPE));
    }

    /**
     * Seeds a single file under the fixed target name, through the real
     * {@see FileSeeder} and the container's importer.
     *
     * The seeder is constructed rather than fetched, because it is not the
     * subject here — the importer it delegates to is, and that one has to come
     * from the container to be the implementation of the running core version.
     *
     * @return array<string, int>
     */
    private function seed(string $source): array
    {
        $seeder = new FileSeeder(
            GeneralUtility::makeInstance(StorageRepository::class),
            $this->get(FileImporterInterface::class),
        );

        return $seeder->seed([
            new SeedFile('placeholder', $source, self::TARGET_FOLDER, self::TARGET_NAME),
        ]);
    }

    private function storage(): ResourceStorage
    {
        $storage = GeneralUtility::makeInstance(StorageRepository::class)->getDefaultStorage();
        $this->assertInstanceOf(ResourceStorage::class, $storage);

        return $storage;
    }

    /**
     * @return list<string>
     */
    private function queryColumn(string $table, string $column): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<string> $values */
        $values = $queryBuilder
            ->select($column)
            ->from($table)
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();

        return $values;
    }
}
