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

        $map = $this->dataMapFactory->createFromDefinition($definition, $rootPageId);
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

        return $this->collectWrittenUids($definition->records, $dataHandler);
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
