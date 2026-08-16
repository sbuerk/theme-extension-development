<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes a seed definition into the database, through DataHandler.
 *
 * Going through DataHandler rather than writing rows directly is the whole
 * point of this class. It is what makes the result a TYPO3 page tree rather
 * than a set of rows that merely look like one: slugs are generated, the TCA
 * defaults and evaluations are applied, `sorting` is computed, the reference
 * index is updated and the caches are flushed. A seeder writing SQL has to
 * reimplement all of that, and gets it subtly wrong.
 *
 * @internal Part of the seeding implementation, not public API.
 */
final readonly class Seeder
{
    public function __construct(
        private DataMapFactory $dataMapFactory,
        private FileSeeder $fileSeeder,
    ) {}

    /**
     * @return array<string, int> The uids the records were written with, keyed
     *                            by their identifier.
     */
    public function seed(
        SeedDefinition $definition,
        BackendUserAuthentication $backendUser,
        int $rootPageId = 0,
    ): array {
        if (!$backendUser->isAdmin()) {
            // Without an admin user DataHandler ignores suggested uids silently,
            // so a seed declaring them would come out with different ones and
            // any site configuration pointing at them would be wrong.
            throw new SeedingException(
                'Seeding requires an admin backend user, because DataHandler only honours suggested uids for one.',
                1786924814,
            );
        }

        // Files first: a record referencing one needs its sys_file uid before
        // the data map can be built.
        $fileUids = $this->fileSeeder->seed($definition->files);

        $map = $this->dataMapFactory->createFromDefinition($definition, $rootPageId, $fileUids);
        if ($map['dataMap'] === []) {
            throw new SeedingException(
                sprintf('The seed definition "%s" contains no records.', $definition->identifier),
                1786924815,
            );
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->suggestedInsertUids = $map['suggestedUids'];
        $dataHandler->start($map['dataMap'], [], $backendUser);
        $dataHandler->process_datamap();
        $this->assertNoErrors($dataHandler, $definition);

        $written = $dataHandler->substNEWwithIDs;
        $this->attachFileReferences($map['references'], $written, $backendUser, $definition);

        return $this->collectWrittenUids($definition->records, $dataHandler);
    }

    /**
     * Attaches the file references in a second pass.
     *
     * A reference carries the uid of the record it belongs to in "uid_foreign",
     * and that is a plain integer column rather than a relation DataHandler
     * resolves - a "NEW..." placeholder written there stays unresolved and the
     * reference ends up pointing at record 0. The records therefore have to
     * exist before their references can be written, which is what makes this a
     * second pass rather than more entries in the same data map.
     *
     * @param list<array{parent: string, table: string, field: string, file: int, pid: string, values: array<string, scalar|null>}> $references
     * @param array<string, int|string> $written Placeholder to written uid.
     */
    private function attachFileReferences(
        array $references,
        array $written,
        BackendUserAuthentication $backendUser,
        SeedDefinition $definition,
    ): void {
        if ($references === []) {
            return;
        }

        $dataMap = [];
        $counter = 0;
        /** @var array<string, list<string>> $perRecord */
        $perRecord = [];

        foreach ($references as $reference) {
            $parentUid = $written[$reference['parent']] ?? null;
            if ($parentUid === null) {
                throw new SeedingException(
                    sprintf(
                        'The record "%s" was not written, so its file reference cannot be attached.',
                        $reference['parent'],
                    ),
                    1786924829,
                );
            }
            $pid = $written[ltrim($reference['pid'], '-')] ?? $reference['pid'];
            $placeholder = 'NEWsys_file_reference_' . ++$counter;
            // The declared fields first and the structural ones on top:
            // "array_merge" lets the later value win, so a definition cannot
            // point a reference somewhere else by declaring "uid_foreign"
            // itself. This mirrors how a record's "pid" is handled.
            $dataMap['sys_file_reference'][$placeholder] = array_merge($reference['values'], [
                'uid_local' => $reference['file'],
                'uid_foreign' => (int)$parentUid,
                'tablenames' => $reference['table'],
                'fieldname' => $reference['field'],
                'pid' => (int)$pid,
            ]);
            $perRecord[$reference['table'] . ':' . $parentUid . ':' . $reference['field']][] = $placeholder;
        }

        foreach ($perRecord as $key => $placeholders) {
            [$table, $uid, $field] = explode(':', $key, 3);
            $dataMap[$table][$uid][$field] = implode(',', $placeholders);
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, [], $backendUser);
        $dataHandler->process_datamap();
        $this->assertNoErrors($dataHandler, $definition);
    }

    private function assertNoErrors(DataHandler $dataHandler, SeedDefinition $definition): void
    {
        if ($dataHandler->errorLog !== []) {
            throw new SeedingException(
                sprintf(
                    'Seeding "%s" failed: %s',
                    $definition->identifier,
                    implode(' | ', $dataHandler->errorLog),
                ),
                1786924816,
            );
        }
    }

    /**
     * Whether anything has been seeded into the page tree already. A seed
     * declares uids, so running it twice would collide rather than duplicate.
     */
    public function pageTreeIsEmpty(): bool
    {
        return BackendUtility::getRecord('pages', 1, 'uid') === null;
    }

    /**
     * @param list<SeedRecord> $records
     * @return array<string, int>
     */
    private function collectWrittenUids(array $records, DataHandler $dataHandler): array
    {
        $uids = [];
        foreach ($records as $record) {
            $written = $dataHandler->substNEWwithIDs[$record->placeholder()] ?? null;
            if ($written !== null) {
                $uids[$record->identifier] = (int)$written;
            }
            if ($record->children !== []) {
                $uids = [...$uids, ...$this->collectWrittenUids($record->children, $dataHandler)];
            }
        }

        return $uids;
    }
}
