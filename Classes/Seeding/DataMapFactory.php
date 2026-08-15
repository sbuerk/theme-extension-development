<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

/**
 * Turns a seed definition into the data map DataHandler consumes.
 *
 * Two details carry the weight here.
 *
 * **Nesting becomes `pid`.** A child is written with the placeholder of its
 * parent as its `pid`, which DataHandler resolves to the real uid once the
 * parent has been created.
 *
 * **Order is preserved through negative pids.** DataHandler puts a new record
 * at the *top* of its parent by default, so records created in the order they
 * are declared would come out reversed. The convention it offers instead is a
 * negative `pid`, meaning "directly after this record". Only the first sibling
 * therefore addresses its parent; every following one addresses the sibling
 * before it.
 *
 * That predecessor is tracked **per table**: a negative pid names a record of
 * the same table, and the children of a page are a mix of sub pages and content
 * elements. Pointing a content element at the page before it would place it
 * somewhere else entirely.
 *
 * Records are also seeded **visible**. DataHandler creates them hidden, which is
 * right for an editor and wrong for a seed: the tree would exist, the frontend
 * would render nothing, and nothing would say why.
 *
 * @internal Part of the seeding implementation, not public API.
 */
final readonly class DataMapFactory
{
    /**
     * @return array{
     *     dataMap: array<string, array<string, array<string, scalar|null>>>,
     *     suggestedUids: array<string, int>
     * }
     */
    public function createFromDefinition(SeedDefinition $definition, int $rootPageId = 0): array
    {
        $dataMap = [];
        $suggestedUids = [];

        $this->collect($definition->records, (string)$rootPageId, $dataMap, $suggestedUids);

        return ['dataMap' => $dataMap, 'suggestedUids' => $suggestedUids];
    }

    /**
     * @param list<SeedRecord> $records
     * @param array<string, array<string, array<string, scalar|null>>> $dataMap
     * @param array<string, int> $suggestedUids
     */
    private function collect(array $records, string $parentId, array &$dataMap, array &$suggestedUids): void
    {
        /** @var array<string, string> $previousIdPerTable */
        $previousIdPerTable = [];

        foreach ($records as $record) {
            $placeholder = $record->placeholder();
            $previousId = $previousIdPerTable[$record->table] ?? null;
            $pid = $previousId === null ? $parentId : '-' . $previousId;

            $values = $record->values;
            // Structural, so never taken from the definition.
            $values['pid'] = $pid;
            // A record created through DataHandler is hidden by default, which
            // for a seed is the wrong way round: the tree exists, the frontend
            // renders nothing and nothing says why. A definition can still ask
            // for a hidden record by declaring "hidden: 1" itself.
            $values += ['hidden' => 0];

            $dataMap[$record->table][$placeholder] = $values;

            if ($record->uid !== null) {
                $suggestedUids[$placeholder] = $record->uid;
            }

            $previousIdPerTable[$record->table] = $placeholder;

            if ($record->children !== []) {
                $this->collect($record->children, $placeholder, $dataMap, $suggestedUids);
            }
        }
    }
}
