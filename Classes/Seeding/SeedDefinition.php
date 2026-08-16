<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A parsed seed definition: the records to write, in the order they were
 * declared.
 *
 * This is data, not a service.
 *
 * @internal Part of the seeding implementation, not public API.
 */
#[Exclude]
final class SeedDefinition
{
    /**
     * @param list<SeedRecord> $records
     * @param list<SeedFile> $files Files copied into a storage before the
     *        records are written, so records can reference them.
     */
    public function __construct(
        public readonly string $identifier,
        public readonly array $records,
        public readonly array $files = [],
    ) {}
}
