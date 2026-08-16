<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Copies the files of a seed definition into a file storage and returns the
 * `sys_file` uid each one was indexed under.
 *
 * The copy goes through the storage API rather than through the filesystem,
 * because that is what indexes the file: a file copied into `fileadmin/` with
 * `copy()` exists on disk and does not exist for TYPO3, so nothing can
 * reference it.
 *
 * Two details of `addFile()` are worth naming, both of them easy to get wrong:
 *
 * - **`removeOriginal` defaults to `true`**, which would *move* the file and
 *   delete the source out of the repository. It is passed as `false` here.
 * - The conflict mode is the **native enum**
 *   `TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior`. TYPO3 v13 still carries
 *   the older class of the same name, and passing that one triggers a
 *   deprecation (#101151) which this test suite turns into a failure. The
 *   native enum exists in v13.4 and v14 alike, so this needs no version split.
 *
 * @internal Part of the seeding implementation, not public API.
 */
final readonly class FileSeeder
{
    public function __construct(
        private StorageRepository $storageRepository,
    ) {}

    /**
     * @param list<SeedFile> $files
     * @return array<string, int> The sys_file uid of each file, keyed by its
     *                            seed identifier.
     */
    public function seed(array $files): array
    {
        $uids = [];
        foreach ($files as $file) {
            $storage = $this->resolveStorage($file);
            $source = GeneralUtility::getFileAbsFileName($file->source);
            if ($source === '' || !is_file($source)) {
                throw new SeedingException(
                    sprintf('The seed file "%s" does not exist at "%s".', $file->identifier, $file->source),
                    1786924817,
                );
            }

            // A storage evaluates the file mounts of the backend user, which
            // is meaningless here: seeding runs on the command line, as an
            // admin, into a folder no user has been given a mount for yet.
            // Without this it refuses with "You are not allowed to access the
            // given folder". The flag is restored, because the storage is
            // shared and the next caller is entitled to the checks.
            $evaluatePermissions = $storage->getEvaluatePermissions();
            $storage->setEvaluatePermissions(false);

            try {
                $addedFile = $storage->addFile(
                    $source,
                    $this->resolveFolder($storage, $file->folder),
                    $file->name ?? basename($source),
                    DuplicationBehavior::REPLACE,
                    false,
                );
            } finally {
                $storage->setEvaluatePermissions($evaluatePermissions);
            }

            $uids[$file->identifier] = $addedFile->getUid();
        }

        return $uids;
    }

    private function resolveStorage(SeedFile $file): ResourceStorage
    {
        $storage = $file->storage !== null
            ? $this->storageRepository->findByUid($file->storage)
            : $this->storageRepository->getDefaultStorage();

        if (!$storage instanceof ResourceStorage) {
            throw new SeedingException(
                sprintf(
                    'No file storage is available for the seed file "%s". A TYPO3 instance gets its default '
                    . 'storage from "typo3 setup"; a functional test has to import one.',
                    $file->identifier,
                ),
                1786924818,
            );
        }

        return $storage;
    }

    private function resolveFolder(ResourceStorage $storage, string $folder): Folder
    {
        $folder = trim($folder, '/');
        if ($folder === '') {
            return $storage->getRootLevelFolder();
        }

        return $storage->hasFolder($folder)
            ? $storage->getFolder($folder)
            : $storage->createFolder($folder);
    }
}
