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
final readonly class SeedDefinition
{
    /**
     * @param list<SeedRecord> $records
     */
    public function __construct(
        public string $identifier,
        public array $records,
    ) {}
}
