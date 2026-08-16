<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One record of a seed definition, with the records nested below it.
 *
 * A record is described by the table it belongs to, a symbolic identifier
 * unique within the definition, the field values to write, and optionally the
 * uid it should be given. Nesting expresses the page tree: the children of a
 * page are written with that page as their parent, whatever table they belong
 * to, which is what lets a page carry both sub pages and content.
 *
 * This is data, not a service. It is created by the parser and never fetched
 * from the container.
 *
 * @internal This is part of the seeding implementation of this extension and
 *           not public API. It is deliberately kept free of dependencies on the
 *           rest of the extension so it can be extracted into a package of its
 *           own later.
 */
#[Exclude]
final readonly class SeedRecord
{
    /**
     * @param array<string, scalar|null> $values
     * @param list<SeedRecord> $children
     * @param array<string, list<string>> $files File references to create, as
     *        a map of field name to the seed identifiers of the files.
     */
    public function __construct(
        public string $table,
        public string $identifier,
        public array $values,
        public ?int $uid = null,
        public array $children = [],
        public array $files = [],
    ) {}

    /**
     * The placeholder DataHandler is given for this record. A record with a
     * declared uid keeps a stable placeholder as well, because the uid is only
     * a *suggestion* to DataHandler and the placeholder is what the data map is
     * keyed by either way.
     */
    public function placeholder(): string
    {
        return 'NEW' . $this->table . '_' . $this->identifier;
    }
}
