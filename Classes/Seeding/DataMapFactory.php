<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Seeding;

use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;

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
 * **An inline child is nested by a relation, not by a pid.** Its `pid` is the
 * page the parent sits on, and the relation is expressed by writing the
 * parent's field as the comma separated list of the children's placeholders.
 * DataHandler resolves those and writes the relation columns of the child
 * itself - which columns those are is per relation and comes from the TCA of
 * the parent field, so nothing here names one. Their order comes from that list
 * and from nothing else, which is why an inline child gets a plain pid rather
 * than the negative "insert after" hint used for a page or a content element.
 *
 * Records are also seeded **visible**. DataHandler creates them hidden, which is
 * right for an editor and wrong for a seed: the tree would exist, the frontend
 * would render nothing, and nothing would say why.
 *
 * @internal Part of the seeding implementation, not public API.
 */
final class DataMapFactory
{
    /**
     * @param array<string, int> $fileUids The sys_file uid of each seeded file,
     *        keyed by its seed identifier.
     * @return array{
     *     dataMap: array<string, array<string, array<string, scalar|null>>>,
     *     suggestedUids: array<string, true>,
     *     references: list<array{parent: string, table: string, field: string, file: int, pid: string, values: array<string, scalar|null>}>
     * }
     */
    public function createFromDefinition(
        SeedDefinition $definition,
        int $rootPageId = 0,
        array $fileUids = [],
    ): array {
        $dataMap = [];
        $suggestedUids = [];
        $references = [];

        $this->collect($definition->records, (string)$rootPageId, $dataMap, $suggestedUids, $fileUids, $references);

        return ['dataMap' => $dataMap, 'suggestedUids' => $suggestedUids, 'references' => $references];
    }

    /**
     * @param list<SeedRecord> $records
     * @param array<string, array<string, array<string, scalar|null>>> $dataMap
     * @param array<string, true> $suggestedUids
     * @param array<string, int> $fileUids
     * @param list<array{parent: string, table: string, field: string, file: int, pid: string, values: array<string, scalar|null>}> $references
     */
    private function collect(
        array $records,
        string $parentId,
        array &$dataMap,
        array &$suggestedUids,
        array $fileUids,
        array &$references,
    ): void {
        /** @var array<string, string> $previousIdPerTable */
        $previousIdPerTable = [];

        foreach ($records as $record) {
            $placeholder = $record->placeholder();
            $previousId = $previousIdPerTable[$record->table] ?? null;
            $pid = $previousId === null ? $parentId : '-' . $previousId;

            $this->write($record, $pid, $parentId, $dataMap, $suggestedUids, $fileUids, $references);

            $previousIdPerTable[$record->table] = $placeholder;

            if ($record->children !== []) {
                $this->collect($record->children, $placeholder, $dataMap, $suggestedUids, $fileUids, $references);
            }
        }
    }

    /**
     * Writes one record into the data map, together with its inline children.
     *
     * @param string $pid The pid to write, which for a page or a content
     *        element may be the negative "insert after" hint.
     * @param string $parentId The page the record sits on. Needed separately
     *        from $pid, because a file reference and an inline child both have
     *        to go onto a page and the negative hint is a sorting instruction
     *        rather than one.
     * @param array<string, array<string, array<string, scalar|null>>> $dataMap
     * @param array<string, true> $suggestedUids
     * @param array<string, int> $fileUids
     * @param list<array{parent: string, table: string, field: string, file: int, pid: string, values: array<string, scalar|null>}> $references
     */
    private function write(
        SeedRecord $record,
        string $pid,
        string $parentId,
        array &$dataMap,
        array &$suggestedUids,
        array $fileUids,
        array &$references,
    ): void {
        $placeholder = $record->placeholder();

        $values = $record->values;
        // Structural, so never taken from the definition.
        $values['pid'] = $pid;
        // A record created through DataHandler is hidden by default, which
        // for a seed is the wrong way round: the tree exists, the frontend
        // renders nothing and nothing says why. A definition can still ask
        // for a hidden record by declaring "hidden: 1" itself.
        $values += ['hidden' => 0];

        foreach ($record->inline as $field => $children) {
            if ($children === []) {
                continue;
            }
            // Declaration order, because that is the order DataHandler numbers
            // the relation by - it walks this list, not the data map.
            $values[$field] = implode(',', array_map(
                static fn(SeedRecord $child): string => $child->placeholder(),
                $children,
            ));
        }

        foreach ($record->files as $field => $fileReferences) {
            foreach ($fileReferences as $fileReference) {
                if (!isset($fileUids[$fileReference->identifier])) {
                    throw new SeedingException(
                        sprintf(
                            'The record "%s" references the file "%s", which the definition does not declare.',
                            $record->identifier,
                            $fileReference->identifier,
                        ),
                        1786924828,
                    );
                }
                $references[] = [
                    'parent' => $placeholder,
                    'table' => $record->table,
                    'field' => $field,
                    'file' => $fileUids[$fileReference->identifier],
                    // The parent of this level, never the record's own pid:
                    // that one may be the negative "insert after" hint,
                    // which is a sorting instruction and not a page.
                    'pid' => $parentId,
                    // The fields of the reference record itself, for
                    // instance the alternative text and the description.
                    'values' => $fileReference->values,
                ];
            }
        }

        if ($record->uid !== null) {
            // Both halves are required, and neither is obvious.
            //
            // The uid goes into the data map row, because DataHandler reads the
            // suggestion from "$incomingFieldArray['uid']" when it calls
            // "insertDB()" - not from "suggestedInsertUids". It then drops the
            // column again ("Do NOT insert the UID field, ever!") before the
            // insert, so putting it here cannot write a uid by itself.
            //
            // And "suggestedInsertUids" is keyed "<table>:<uid>", not by the
            // placeholder, because that is the key "insertDB()" looks up. A
            // placeholder key is simply never found.
            //
            // Getting either one wrong fails silently: DataHandler assigns the
            // next free uid, the seed reports whatever it got, and the result
            // is right only as long as declaration order happens to equal
            // insertion order.
            // .Build/vendor/typo3/cms-core/Classes/DataHandling/DataHandler.php
            $values['uid'] = $record->uid;
            $suggestedUids[$record->table . ':' . $record->uid] = true;
        }

        $dataMap[$record->table][$placeholder] = $values;

        foreach ($record->inline as $children) {
            foreach ($children as $child) {
                // The page the parent sits on, for the child and for anything
                // the child references in turn.
                $this->write($child, $parentId, $parentId, $dataMap, $suggestedUids, $fileUids, $references);
            }
        }
    }
}
