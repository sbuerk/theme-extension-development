<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
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
 * The `addFile()` call itself is **not** made here but delegated to
 * {@see FileImporterInterface}, whose implementation is picked by the running
 * core version. That is the one operation of this class that differs between
 * TYPO3 v12 and v13: the conflict mode argument is a class constant on v12 and
 * a native enum on v13 (#101151), and there is no spelling that is correct on
 * both. Everything else in this class — resolving the storage, resolving the
 * folder, the permission dance below, the returned uid map — is identical on
 * both versions and stays shared rather than being duplicated into two copies
 * of the seeder. The reasoning is written out in full on the interface.
 *
 * The one `addFile()` detail worth naming here, because it is easy to get wrong
 * and invisible from the call site: **`removeOriginal` defaults to `true`**,
 * which would *move* the file and delete the source out of the repository. The
 * importer passes `false`, and its method name says so.
 *
 * @internal Part of the seeding implementation, not public API.
 */
final class FileSeeder
{
    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly FileImporterInterface $fileImporter,
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
                $addedFile = $this->fileImporter->addFileReplacingExisting(
                    $storage,
                    $source,
                    $this->resolveFolder($storage, $file->folder),
                    $file->name ?? basename($source),
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
