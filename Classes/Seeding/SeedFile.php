<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A file a seed definition copies into a file storage before it writes records.
 *
 * This is data, not a service.
 *
 * @internal Part of the seeding implementation, not public API.
 */
#[Exclude]
final class SeedFile
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $source,
        public readonly string $folder = '/',
        public readonly ?string $name = null,
        public readonly ?int $storage = null,
    ) {}
}
