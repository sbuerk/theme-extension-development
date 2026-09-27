<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Imports a seed set of `sbuerk/data-factory` into the functional test
 * instance, the way `vendor/bin/typo3 data-factory:import <identifier>` does.
 *
 * It goes through the command rather than through the services behind it, and
 * finds the command by its name rather than by its class. The command name, its
 * options and its exit codes are the supported interface of that extension;
 * everything below `Classes/` - the command class included - is `@internal`
 * there and may change in any release. A test that assembled the parser, the
 * composer and the seeder itself would be pinned to the one part of the
 * dependency that promises nothing.
 *
 * A test using this has to load `sbuerk/data-factory` and the extension
 * shipping the set, and has to import `Fixtures/Database/AdminBackendUser.csv`:
 * DataHandler honours a declared uid for an administrator only, and the command
 * refuses to run as anybody else rather than write the set under uids it does
 * not declare. `importSeedSetOncePerClass()` does that itself, and is what a
 * test class reading a set from more than one test uses.
 *
 * @phpstan-require-extends AbstractFunctionalTestCase
 */
trait DataFactoryImportTrait
{
    /**
     * @param array<string, int|string|bool> $options Further options of the
     *        command, keyed with their dashes: `['--root-page' => 12]`.
     */
    protected function importSeedSet(string $identifier, array $options = []): CommandTester
    {
        $backendUser = $this->setUpBackendUser(1);
        // What the console application does before it runs a command
        // ("CommandApplication", "$GLOBALS['LANG'] = ...createFromUserPreferences()")
        // and a CommandTester does not. DataHandler reads it; on TYPO3 v12 the
        // testing framework leaves it unset, and the import fails without it.
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $commandTester = new CommandTester($this->get(CommandRegistry::class)->get('data-factory:import'));
        $exitCode = $commandTester->execute(
            ['identifier' => $identifier] + $options,
            ['interactive' => false],
        );

        self::assertSame(
            Command::SUCCESS,
            $exitCode,
            sprintf(
                "Importing the seed set \"%s\" failed with exit code %d:\n%s",
                $identifier,
                $exitCode,
                $commandTester->getDisplay(),
            ),
        );

        return $commandTester;
    }

    /**
     * Writes the administrator the import runs as, the default file storage
     * and the seed set - once per test class - and hands every later test of
     * the class the database as that first import left it.
     *
     * An import goes through DataHandler record by record and costs seconds;
     * restoring the snapshot the testing framework takes of it costs a file
     * copy on SQLite and a bulk insert elsewhere. See
     * `FunctionalTestCase::withDatabaseSnapshot()`. Call it before anything
     * else writes to the database in `setUp()`, because a restore inserts
     * into empty tables. Everything that is not a row in the default
     * connection is left to the caller and is not restored:
     *
     * - The files the import copies to `fileadmin/` are written by the first
     *   test and stay, as the instance directory is set up once per class.
     *   The `sys_file` rows describing them are restored with the rest.
     * - Site configurations, written below `typo3conf/sites/` or `config/sites/`
     *   and removed after each test, are written by the test after this.
     * - The logged in backend user is set up again after a restore, because
     *   the import leaves the administrator logged in, and a test of the class
     *   is entitled to the same state whether it came first or not.
     *
     * The snapshot is only as good as the tests that read it are read-only:
     * whatever a test writes to the database is gone for the next one, but
     * whatever it writes elsewhere stays.
     */
    protected function importSeedSetOncePerClass(string $identifier): void
    {
        $this->withDatabaseSnapshot(
            function () use ($identifier): void {
                $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
                $this->createDefaultFileStorage();
                $this->importSeedSet($identifier);
            },
            function (): void {
                $this->setUpBackendUser(1);
            },
        );
    }

    /**
     * A functional test instance has a `fileadmin/` folder and no
     * `sys_file_storage` record - a real installation gets that one from
     * `typo3 setup`. Without it the file pass of an import has nowhere to put
     * the files of a set.
     */
    protected function createDefaultFileStorage(): void
    {
        GeneralUtility::makeInstance(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
    }
}
