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
final class SeedRecord
{
    /**
     * @param array<string, scalar|null> $values
     * @param list<SeedRecord> $children
     * @param array<string, list<SeedFileReference>> $files File references to
     *        create, as a map of field name to the references declared for it.
     * @param array<string, list<SeedRecord>> $inline Inline children, as a map
     *        of the parent field carrying the relation to the records declared
     *        for it. Unlike `$children` these are not nested by `pid` but by a
     *        relation, so the field name is what ties them to the parent.
     */
    public function __construct(
        public readonly string $table,
        public readonly string $identifier,
        public readonly array $values,
        public readonly ?int $uid = null,
        public readonly array $children = [],
        public readonly array $files = [],
        public readonly array $inline = [],
    ) {}

    /**
     * The placeholder DataHandler is given for this record. A record with a
     * declared uid keeps a stable placeholder as well, because the uid is only
     * a *suggestion* to DataHandler and the placeholder is what the data map is
     * keyed by either way.
     *
     * **The placeholder carries no underscore, and that is not cosmetic.** A
     * placeholder used as the value of a relation field goes through
     * `DataHandler::processRemapStack()`, which reads a value containing an
     * underscore as the `<table>_<uid>` form and splits it there
     * (.Build/vendor/typo3/cms-core/Classes/DataHandling/DataHandler.php,
     * around line 7169). `NEWtx_theme_list_item_docs` would be taken apart into
     * the table `NEWtx_theme_list_item` and the id `docs`, neither of which
     * resolves, and the relation is written as empty - with an empty error log,
     * so nothing reports it. `YamlSeedParser` therefore rejects an identifier
     * carrying one, which is what keeps the guarantee for the whole placeholder.
     *
     * **The table name is not part of the placeholder, and that is deliberate.**
     * It never contributed uniqueness: `YamlSeedParser` tracks the identifiers
     * of a definition in a single set across all levels and all tables, so two
     * records can never share one. What the table name did contribute was
     * length, and length is a hard limit here: TYPO3 v12 logs every record
     * DataHandler creates with the placeholder in `sys_log.NEWid`, a column its
     * own `ext_tables.sql` declares as `varchar(30)`
     * (.Build/vendor/typo3/cms-core/ext_tables.sql, "NEWid varchar(30)"), and
     * `BackendUserAuthentication::writelog()` writes it there unconditionally.
     * A longer value passes only on SQLite, which does not enforce a declared
     * `varchar` length at all; PostgreSQL answers "value too long for type
     * character varying(30)" and MySQL and MariaDB answer "Data too long for
     * column 'NEWid'", each out of the middle of `process_datamap()`.
     * `NEWttcontent-theme-linklist-unlabelled` is 38 characters, so with the
     * table name in front the seeder worked on one of the four supported DBMS.
     *
     * TYPO3 v13 dropped the column - `sys_log` has no `NEWid` there and its
     * `writelog()` takes the parameter as the unused `$___` - which is why this
     * never surfaced on the v13/v14 line this branch was split from.
     *
     * @todo Once support for TYPO3 v12 is dropped, the length limit
     *       `YamlSeedParser` enforces on an identifier can go with it.
     */
    public function placeholder(): string
    {
        return 'NEW' . $this->identifier;
    }
}
