<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Core13\Seeding;

use SBUERK\ThemeExtensionDevelopment\Seeding\FileImporterInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * TYPO3 v13 implementation of {@see FileImporterInterface}.
 *
 * The difference to the v12 implementation is the `use` statement above and
 * nothing else: TYPO3 v13 introduced the native enum
 * `TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior` (#101151) and
 * `ResourceStorage::addFile()` answers anything that is not an instance of it
 * with `E_USER_DEPRECATED`. The suites of this extension fail on deprecations,
 * so the v12 spelling is not an option here even though v13 still accepts its
 * value. See {@see FileImporterInterface} for the full reasoning behind the
 * split.
 *
 * Only the `Core13/` directory is registered in the dependency injection
 * container when running on TYPO3 v13, see `Configuration/Services.php`.
 * `#[AsAlias]` makes this class the default implementation of the interface, so
 * consumers type hint the interface and receive the implementation matching the
 * running TYPO3 version. The alias is public because the functional tests
 * fetch it from the container to assert that wiring.
 *
 * The class is plain `final`. Readonly classes are PHP 8.2 and this branch
 * supports PHP 8.1 for TYPO3 v12; the rule is branch wide rather than per
 * directory, so a class arriving from `main` as `final readonly` becomes
 * `final` here. See `docs/architecture/class-design.md`.
 *
 * @todo Remove together with {@see FileImporterInterface} when TYPO3 v12
 *       support is dropped, and inline the call back into the seeder.
 *
 * @internal Part of the seeding implementation, not public API.
 */
#[AsAlias(id: FileImporterInterface::class, public: true)]
final class FileImporter implements FileImporterInterface
{
    public function addFileReplacingExisting(
        ResourceStorage $storage,
        string $localFilePath,
        Folder $targetFolder,
        string $targetFileName,
    ): File {
        return $storage->addFile(
            $localFilePath,
            $targetFolder,
            $targetFileName,
            DuplicationBehavior::REPLACE,
            false,
        );
    }
}
