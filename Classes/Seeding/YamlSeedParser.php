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
    private const IDENTIFIER = 'identifier';
    private const UID = 'uid';
    private const FILES = 'files';

    /**
     * Keys that describe the shape of the definition rather than a field of the
     * record they appear on.
     */
    private const STRUCTURAL_KEYS = [self::IDENTIFIER, self::UID, self::CHILDREN, self::CONTENT, self::FILES];

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
     * @return array<string, list<string>>
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
            foreach ($identifiers as $fileIdentifier) {
                if (!is_string($fileIdentifier) || $fileIdentifier === '') {
                    throw new SeedingException(
                        sprintf('A file reference of "%s" in "%s" is not an identifier.', $recordIdentifier, $source),
                        1786924827,
                    );
                }
                $references[$field][] = $fileIdentifier;
            }
        }

        return $references;
    }

    /**
     * @param array<mixed> $records
     * @param array<string, true> $seen Identifiers already used, by reference,
     *                                  so a duplicate is caught across the whole
     *                                  definition rather than per level.
     * @return list<SeedRecord>
     */
    private function parseRecords(array $records, string $table, string $source, array &$seen): array
    {
        $parsed = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new SeedingException(
                    sprintf('A record of "%s" in the seed definition "%s" is not a map.', $table, $source),
                    1786924806,
                );
            }

            $identifier = $record[self::IDENTIFIER] ?? null;
            if (!is_string($identifier) || $identifier === '') {
                throw new SeedingException(
                    sprintf('A record of "%s" in the seed definition "%s" has no "identifier".', $table, $source),
                    1786924807,
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

            $values = [];
            foreach ($record as $key => $value) {
                if (in_array($key, self::STRUCTURAL_KEYS, true)) {
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
                $table,
                $identifier,
                $values,
                $uid,
                $children,
                $this->parseFileReferences($record[self::FILES] ?? [], $identifier, $source),
            );
        }

        return $parsed;
    }
}
