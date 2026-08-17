<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reads a seed definition from a YAML file.
 *
 * The format keeps the structural keys to a minimum, so that everything which
 * is not structure is a field of the record:
 *
 *     identifier: demo
 *     pages:
 *       - identifier: root
 *         uid: 1
 *         title: 'Theme demo'
 *         slug: '/'
 *         is_siteroot: 1
 *         content:
 *           - identifier: root-welcome
 *             CType: header
 *             header: 'Welcome'
 *         children:
 *           - identifier: about
 *             title: 'About'
 *             slug: '/about'
 *
 * `identifier`, `uid`, `children` and `content` are structure; every other key
 * is written to the record as-is. `children` nests pages, `content` nests
 * `tt_content` records below the page carrying them.
 *
 * `records` nests records of *any* table onto the page carrying them, which is
 * what `content` does for `tt_content` alone. A record declares the table it
 * belongs to itself, exactly as an inline child does:
 *
 *     pages:
 *       - identifier: storage
 *         doktype: 254
 *         title: 'Storage'
 *         records:
 *           - identifier: category-news
 *             table: sys_category
 *             title: 'News'
 *
 * That is what makes a seed definition able to describe the data a plugin
 * reads, rather than only the pages and content elements around it.
 *
 * `inline` nests records into a *relation* rather than below a page, as a map
 * of the parent field carrying the relation to the records declared for it:
 *
 *     content:
 *       - identifier: links
 *         CType: theme_linklist
 *         inline:
 *           tx_theme_list_items:
 *             - identifier: links-docs
 *               table: tx_theme_list_item
 *               link_label: 'Documentation'
 *
 * An inline child declares the `table` it belongs to itself. Inferring it from
 * `config.foreign_table` of the parent's field would make a seed definition
 * depend on the TCA being loaded, and would fail with a null dereference rather
 * than a message when it is not - so `table` is structural on an inline child,
 * exactly as `identifier` is. It is structural under `records` for the same
 * reason and a simpler one: there is no parent field to infer anything from.
 *
 * `uid` is optional. Where it is given it is passed to DataHandler as a
 * *suggested* uid, which makes a seed reproducible - a site configuration can
 * then reference a root page id that is known in advance instead of whatever
 * the database happened to assign.
 *
 * @internal Part of the seeding implementation, not public API.
 */
final readonly class YamlSeedParser
{
    private const PAGES = 'pages';
    private const CONTENT = 'content';
    private const CHILDREN = 'children';
    private const RECORDS = 'records';
    private const IDENTIFIER = 'identifier';
    private const UID = 'uid';
    private const FILES = 'files';
    private const INLINE = 'inline';
    private const TABLE = 'table';

    /**
     * Keys that describe the shape of the definition rather than a field of the
     * record they appear on.
     *
     * `table` is deliberately not in here: it is structural only on an inline
     * child, and `tt_content` and `pages` both have fields whose name starts
     * with `table`. A key that is structure in one place and a field in another
     * has to be decided where the context is known, which is per level.
     *
     * `records` is the same case and is decided the same way. `tt_content` has
     * a **column** of that name - the one the "Insert records" element writes
     * `tt_content_<uid>` into - so the key can only be structure where the
     * level is `pages`, which is also the only level it means anything on.
     * The shipped demo definition uses that column, so this is not theory:
     * putting `records` in this list makes `SeedingTest` red.
     */
    private const STRUCTURAL_KEYS = [self::IDENTIFIER, self::UID, self::CHILDREN, self::CONTENT, self::FILES, self::INLINE];

    /**
     * An identifier ends up inside the `NEW…` placeholder of the record, and a
     * placeholder naming a relation target must not contain an underscore - see
     * the docblock of `SeedRecord::placeholder()` for what DataHandler does with
     * one. Restricting the identifier itself is what keeps that guarantee, and
     * doing it here means the definition is rejected with a message rather than
     * seeding an empty relation without a word.
     */
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9-]*$/';

    public function parseFile(string $fileName): SeedDefinition
    {
        $absoluteFileName = GeneralUtility::getFileAbsFileName($fileName);
        if ($absoluteFileName === '' || !is_file($absoluteFileName)) {
            throw new SeedingException(
                sprintf('The seed definition "%s" does not exist.', $fileName),
                1786924801,
            );
        }

        $content = file_get_contents($absoluteFileName);
        if ($content === false) {
            throw new SeedingException(
                sprintf('The seed definition "%s" could not be read.', $fileName),
                1786924802,
            );
        }

        return $this->parse(Yaml::parse($content), $fileName);
    }

    /**
     * @param mixed $definition The decoded YAML.
     */
    public function parse(mixed $definition, string $source = 'seed definition'): SeedDefinition
    {
        if (!is_array($definition)) {
            throw new SeedingException(
                sprintf('The seed definition "%s" is not a map.', $source),
                1786924803,
            );
        }

        $identifier = $definition[self::IDENTIFIER] ?? null;
        if (!is_string($identifier) || $identifier === '') {
            throw new SeedingException(
                sprintf('The seed definition "%s" has no "identifier".', $source),
                1786924804,
            );
        }

        $pages = $definition[self::PAGES] ?? [];
        if (!is_array($pages)) {
            throw new SeedingException(
                sprintf('The "pages" of the seed definition "%s" are not a list.', $source),
                1786924805,
            );
        }

        $seen = [];
        $files = $this->parseFiles($definition[self::FILES] ?? [], $source);

        return new SeedDefinition(
            $identifier,
            $this->parseRecords($pages, 'pages', $source, $seen),
            $files,
        );
    }

    /**
     * @param mixed $files
     * @return list<SeedFile>
     */
    private function parseFiles(mixed $files, string $source): array
    {
        if (!is_array($files)) {
            throw new SeedingException(
                sprintf('The "files" of the seed definition "%s" are not a list.', $source),
                1786924819,
            );
        }

        $parsed = [];
        $seen = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                throw new SeedingException(
                    sprintf('A file of the seed definition "%s" is not a map.', $source),
                    1786924820,
                );
            }
            $identifier = $file[self::IDENTIFIER] ?? null;
            if (!is_string($identifier) || $identifier === '') {
                throw new SeedingException(
                    sprintf('A file of the seed definition "%s" has no "identifier".', $source),
                    1786924821,
                );
            }
            if (isset($seen[$identifier])) {
                throw new SeedingException(
                    sprintf('The file identifier "%s" is used more than once in "%s".', $identifier, $source),
                    1786924822,
                );
            }
            $seen[$identifier] = true;

            $sourcePath = $file['source'] ?? null;
            if (!is_string($sourcePath) || $sourcePath === '') {
                throw new SeedingException(
                    sprintf('The file "%s" in "%s" has no "source".', $identifier, $source),
                    1786924823,
                );
            }

            $folder = $file['folder'] ?? '/';
            $name = $file['name'] ?? null;
            $storage = $file['storage'] ?? null;

            $parsed[] = new SeedFile(
                $identifier,
                $sourcePath,
                is_string($folder) ? $folder : '/',
                is_string($name) ? $name : null,
                is_int($storage) ? $storage : null,
            );
        }

        return $parsed;
    }

    /**
     * The file references of one record, as a map of field name to the
     * references declared for it.
     *
     * A reference is either the bare identifier of a seeded file, or a map
     * naming that identifier alongside the fields of the `sys_file_reference`
     * record - the alternative text, title, description and link an editor
     * fills in on a file relation:
     *
     *     files:
     *       image:
     *         - placeholder
     *         - identifier: portrait
     *           alternative: 'A portrait placeholder'
     *           description: 'Shown as the caption'
     *
     * @return array<string, list<SeedFileReference>>
     */
    private function parseFileReferences(mixed $files, string $recordIdentifier, string $source): array
    {
        if ($files === [] || $files === null) {
            return [];
        }
        if (!is_array($files)) {
            throw new SeedingException(
                sprintf('The "files" of "%s" in "%s" are not a map of field to file identifiers.', $recordIdentifier, $source),
                1786924824,
            );
        }

        $references = [];
        foreach ($files as $field => $identifiers) {
            if (!is_string($field) || $field === '') {
                throw new SeedingException(
                    sprintf('A file field of "%s" in "%s" is not a field name.', $recordIdentifier, $source),
                    1786924825,
                );
            }
            if (!is_array($identifiers)) {
                throw new SeedingException(
                    sprintf('The file field "%s" of "%s" in "%s" is not a list.', $field, $recordIdentifier, $source),
                    1786924826,
                );
            }
            foreach ($identifiers as $reference) {
                $references[$field][] = $this->parseFileReference($reference, $recordIdentifier, $source);
            }
        }

        return $references;
    }

    private function parseFileReference(mixed $reference, string $recordIdentifier, string $source): SeedFileReference
    {
        if (!is_array($reference)) {
            if (!is_string($reference) || $reference === '') {
                throw new SeedingException(
                    sprintf('A file reference of "%s" in "%s" is not an identifier.', $recordIdentifier, $source),
                    1786924827,
                );
            }

            return new SeedFileReference($reference);
        }

        $identifier = $reference[self::IDENTIFIER] ?? null;
        if (!is_string($identifier) || $identifier === '') {
            throw new SeedingException(
                sprintf('A file reference of "%s" in "%s" has no "identifier".', $recordIdentifier, $source),
                1786924830,
            );
        }

        $values = [];
        foreach ($reference as $key => $value) {
            if ($key === self::IDENTIFIER) {
                continue;
            }
            if (!is_string($key)) {
                throw new SeedingException(
                    sprintf(
                        'A field name of the file reference "%s" of "%s" in "%s" is not a string.',
                        $identifier,
                        $recordIdentifier,
                        $source,
                    ),
                    1786924831,
                );
            }
            if ($value !== null && !is_scalar($value)) {
                throw new SeedingException(
                    sprintf(
                        'The field "%s" of the file reference "%s" of "%s" in "%s" is not a scalar value.',
                        $key,
                        $identifier,
                        $recordIdentifier,
                        $source,
                    ),
                    1786924832,
                );
            }
            $values[$key] = $value;
        }

        return new SeedFileReference($identifier, $values);
    }

    /**
     * @param array<mixed> $records
     * @param string|null $table The table these records belong to, or null when
     *        each of them declares its own - which is the case for inline
     *        children, where one field may even point at a different table than
     *        the next, and for the records of a page.
     * @param array<string, true> $seen Identifiers already used, by reference,
     *                                  so a duplicate is caught across the whole
     *                                  definition rather than per level.
     * @param string $childContext Names the structural key these records were
     *        declared under, for the messages of the levels that do not have a
     *        table to name themselves by.
     * @return list<SeedRecord>
     */
    private function parseRecords(array $records, ?string $table, string $source, array &$seen, string $childContext = self::INLINE): array
    {
        $context = $table ?? $childContext;
        $parsed = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new SeedingException(
                    sprintf('A record of "%s" in the seed definition "%s" is not a map.', $context, $source),
                    1786924806,
                );
            }

            $identifier = $record[self::IDENTIFIER] ?? null;
            if (!is_string($identifier) || $identifier === '') {
                throw new SeedingException(
                    sprintf('A record of "%s" in the seed definition "%s" has no "identifier".', $context, $source),
                    1786924807,
                );
            }
            if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
                throw new SeedingException(
                    sprintf(
                        'The identifier "%s" in the seed definition "%s" is not usable. An identifier may contain letters, digits and dashes only, and has to start with a letter or a digit.',
                        $identifier,
                        $source,
                    ),
                    1786924833,
                );
            }
            if (isset($seen[$identifier])) {
                throw new SeedingException(
                    sprintf(
                        'The identifier "%s" is used more than once in the seed definition "%s". Identifiers have to be unique.',
                        $identifier,
                        $source,
                    ),
                    1786924808,
                );
            }
            $seen[$identifier] = true;

            $uid = $record[self::UID] ?? null;
            if ($uid !== null && (!is_int($uid) || $uid < 1)) {
                throw new SeedingException(
                    sprintf(
                        'The "uid" of "%s" in the seed definition "%s" has to be a positive integer.',
                        $identifier,
                        $source,
                    ),
                    1786924809,
                );
            }

            $recordTable = $table;
            if ($recordTable === null) {
                $declaredTable = $record[self::TABLE] ?? null;
                if (!is_string($declaredTable) || $declaredTable === '') {
                    throw new SeedingException(
                        sprintf(
                            'The "%s" child "%s" in the seed definition "%s" has no "table". It is never inferred: under "inline" it would have to come from the TCA of the parent field, and under "records" there is no field it could come from at all.',
                            $context,
                            $identifier,
                            $source,
                        ),
                        1786924834,
                    );
                }
                $recordTable = $declaredTable;
            }

            $children = [];
            $nestedContent = $record[self::CONTENT] ?? [];
            if ($nestedContent !== []) {
                if (!is_array($nestedContent)) {
                    throw new SeedingException(
                        sprintf('The "content" of "%s" in the seed definition "%s" is not a list.', $identifier, $source),
                        1786924810,
                    );
                }
                $children = [...$children, ...$this->parseRecords($nestedContent, 'tt_content', $source, $seen)];
            }
            // Only on a page, where "records" cannot be a field: see the
            // docblock of STRUCTURAL_KEYS.
            $nestedRecords = $table === self::PAGES ? ($record[self::RECORDS] ?? []) : [];
            if ($nestedRecords !== []) {
                if (!is_array($nestedRecords)) {
                    throw new SeedingException(
                        sprintf('The "records" of "%s" in the seed definition "%s" is not a list.', $identifier, $source),
                        1786955122,
                    );
                }
                // Parsed with no table of their own, so each declares one. They
                // join the children of this record like content does: the page
                // carrying them becomes their pid, and "DataMapFactory" chains
                // the declaration order per table, so records of three tables on
                // one page do not disturb each other's sorting.
                $children = [...$children, ...$this->parseRecords($nestedRecords, null, $source, $seen, self::RECORDS)];
            }
            $nestedChildren = $record[self::CHILDREN] ?? [];
            if ($nestedChildren !== []) {
                if (!is_array($nestedChildren)) {
                    throw new SeedingException(
                        sprintf('The "children" of "%s" in the seed definition "%s" is not a list.', $identifier, $source),
                        1786924811,
                    );
                }
                $children = [...$children, ...$this->parseRecords($nestedChildren, 'pages', $source, $seen)];
            }

            $inline = $this->parseInline($record[self::INLINE] ?? [], $identifier, $source, $seen);

            // "table" is a field everywhere but on an inline or "records"
            // child, where it is the structural key naming the table the record
            // belongs to; "records" is a field everywhere but on a page.
            $structuralKeys = self::STRUCTURAL_KEYS;
            if ($table === null) {
                $structuralKeys[] = self::TABLE;
            }
            if ($table === self::PAGES) {
                $structuralKeys[] = self::RECORDS;
            }

            $values = [];
            foreach ($record as $key => $value) {
                if (in_array($key, $structuralKeys, true)) {
                    continue;
                }
                if (!is_string($key)) {
                    throw new SeedingException(
                        sprintf('A field name of "%s" in the seed definition "%s" is not a string.', $identifier, $source),
                        1786924812,
                    );
                }
                if ($value !== null && !is_scalar($value)) {
                    throw new SeedingException(
                        sprintf(
                            'The field "%s" of "%s" in the seed definition "%s" is not a scalar value.',
                            $key,
                            $identifier,
                            $source,
                        ),
                        1786924813,
                    );
                }
                $values[$key] = $value;
            }

            $parsed[] = new SeedRecord(
                $recordTable,
                $identifier,
                $values,
                $uid,
                $children,
                $this->parseFileReferences($record[self::FILES] ?? [], $identifier, $source),
                $inline,
            );
        }

        return $parsed;
    }

    /**
     * The inline children of one record, as a map of the parent field carrying
     * the relation to the records declared for it.
     *
     * @param array<string, true> $seen Identifiers already used, by reference,
     *        so an inline child cannot reuse an identifier either.
     * @return array<string, list<SeedRecord>>
     */
    private function parseInline(mixed $inline, string $recordIdentifier, string $source, array &$seen): array
    {
        if ($inline === [] || $inline === null) {
            return [];
        }
        if (!is_array($inline)) {
            throw new SeedingException(
                sprintf('The "inline" of "%s" in "%s" is not a map of field name to child records.', $recordIdentifier, $source),
                1786924835,
            );
        }

        $parsed = [];
        foreach ($inline as $field => $children) {
            if (!is_string($field) || $field === '') {
                throw new SeedingException(
                    sprintf('An inline field of "%s" in "%s" is not a field name.', $recordIdentifier, $source),
                    1786924836,
                );
            }
            if (!is_array($children)) {
                throw new SeedingException(
                    sprintf('The inline field "%s" of "%s" in "%s" is not a list of records.', $field, $recordIdentifier, $source),
                    1786924837,
                );
            }
            $parsed[$field] = $this->parseRecords($children, null, $source, $seen);
        }

        return $parsed;
    }
}
