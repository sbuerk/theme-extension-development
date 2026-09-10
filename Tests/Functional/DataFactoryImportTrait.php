<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Console\CommandRegistry;
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
 * not declare.
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
        $this->setUpBackendUser(1);

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
