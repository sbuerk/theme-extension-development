<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The showcase writes plain text into the text fields that hold plain text.
 *
 * `bodytext` is rich text only on the types that enable the editor for it in
 * their `columnsOverrides`; on the heroes, the teasers, the testimonial and
 * the author it is a plain textarea. Their templates still render it through
 * `f:format.html`, whose `lib.parseFunc_RTE` wraps every line of a value that
 * is not already a block of its own in a paragraph. HTML seeded into such a
 * field is shown to an editor as tags in the textarea, and a value wrapped in
 * the source renders one paragraph per source line - "45 per" and "cent" in
 * two paragraphs of the hero lead. A value ending in a newline renders an
 * empty paragraph after the text.
 *
 * The same holds for the `text` of an inline list item, which is plain text
 * unless the relation enables the editor through its `overrideChildTca`, and
 * which the templates render through `f:format.nl2br`: every newline is a
 * line break there.
 *
 * Which field is rich text, and which holds lines rather than paragraphs, is
 * read from the TCA of the running core - the `columnsOverrides` of the type
 * included - rather than listed here, so a type that gains or loses the
 * editor moves between the sides by itself. The TCA is read as the array in
 * `$GLOBALS['TCA']`, not through the schema API of v13.2 (Feature #104002),
 * which v12.4 does not have: `fieldsOfType()` resolves the fields of a type
 * the way `TcaSchemaFactory::findRelevantFieldsForSubSchema()` does on v13.4.
 */
final class PlainTextSeedTest extends AbstractFunctionalTestCase
{
    private const SCENARIO = 'Configuration/DataFactory/theme-demo/Scenario.yaml';

    #[Test]
    public function aPlainTextFieldIsSeededWithPlainTextParagraphs(): void
    {
        $content = $GLOBALS['TCA']['tt_content'];
        $listItemText = $GLOBALS['TCA']['tx_theme_list_item']['columns']['text']['config'] ?? [];
        $this->assertSame('text', $listItemText['type'] ?? null);

        [$records, $listItems] = self::seededRecords();
        $this->assertNotSame([], $records, 'No content element was found - the scenario is read wrong.');
        $this->assertNotSame([], $listItems, 'No list item was found - the scenario is read wrong.');

        $parents = [];
        foreach ($records as $record) {
            foreach (explode(',', (string)($record['tx_theme_list_items'] ?? '')) as $child) {
                if (trim($child) !== '') {
                    $parents[(int)$child] = (string)$record['CType'];
                }
            }
        }

        $checked = ['plain' => 0, 'rich' => 0, 'lines' => 0];
        $violations = [];
        foreach ($records as $record) {
            if (!is_string($record['bodytext'] ?? null)) {
                continue;
            }
            $type = (string)($record['CType'] ?? '');
            $this->assertArrayHasKey($type, $content['types'] ?? [], sprintf('The type "%s" of content element %d is not registered.', $type, $record['id']));
            $fields = self::fieldsOfType($content, $type);
            $this->assertArrayHasKey('bodytext', $fields, sprintf('Content element %d seeds "bodytext", which its type "%s" does not show.', $record['id'], $type));
            $field = $fields['bodytext']['config'] ?? [];
            // A textarea without soft wrapping holds lines that are not
            // paragraphs, and a line break is its structure rather than a
            // wrap: an item per line on "bullets", a row per line on "table",
            // markup that is the point on "html". The core sets "wrap" to
            // "off" for all three on v13.4.35, in the TCA of EXT:frontend. On
            // v12.4.45 EXT:frontend does so for "bullets" and "table" only;
            // "html" gets it from EXT:t3editor, which this suite does not
            // load, so "html" is named as well - on v13.4 the name adds
            // nothing the TCA does not already say.
            if (($field['wrap'] ?? '') === 'off' || $type === 'html') {
                $checked['lines']++;
                continue;
            }
            if (($field['type'] ?? '') === 'text' && (bool)($field['enableRichtext'] ?? false)) {
                $checked['rich']++;
                continue;
            }
            $checked['plain']++;
            foreach (self::violations($record['bodytext']) as $violation) {
                $violations[] = sprintf('tt_content %d (%s) "bodytext": %s', $record['id'], $type, $violation);
            }
        }
        foreach ($listItems as $item) {
            if (!is_string($item['text'] ?? null)) {
                continue;
            }
            $parent = $parents[$item['id']] ?? null;
            $richText = (bool)($listItemText['enableRichtext'] ?? false);
            if ($parent !== null) {
                $relation = self::fieldsOfType($content, $parent)['tx_theme_list_items']['config'] ?? [];
                // A relation whose "text" holds lines rather than paragraphs
                // says so with "wrap = off", the flag the core sets on the
                // "bodytext" of "bullets", "table" and "html" for the same
                // reason - the pricing plans list one feature per line. Such a
                // value is checked for markup like any other plain text, and
                // its line breaks are left alone: they are what the template
                // splits on.
                if (($relation['overrideChildTca']['columns']['text']['config']['wrap'] ?? '') === 'off') {
                    $checked['lines']++;
                    foreach (self::violations($item['text'], false) as $violation) {
                        $violations[] = sprintf('tx_theme_list_item %d (of %s) "text": %s', $item['id'], $parent, $violation);
                    }
                    continue;
                }
                // The editor is enabled for the child's "text" through the
                // "columns" of the relation's "overrideChildTca", the one form
                // this extension uses (tabs, accordion). A "columnsOverrides"
                // of a child type inside "overrideChildTca" is not read here.
                $richText = (bool)($relation['overrideChildTca']['columns']['text']['config']['enableRichtext'] ?? $richText);
            }
            if ($richText) {
                $checked['rich']++;
                continue;
            }
            $checked['plain']++;
            foreach (self::violations($item['text']) as $violation) {
                $violations[] = sprintf('tx_theme_list_item %d (of %s) "text": %s', $item['id'], $parent ?? 'no parent', $violation);
            }
        }

        // Every side is seeded many times over, so a TCA lookup that answers
        // the same for every field cannot pass.
        $this->assertGreaterThan(10, $checked['plain'], 'Almost no plain text field was checked.');
        $this->assertGreaterThan(10, $checked['rich'], 'Almost no rich text field was recognised.');
        $this->assertGreaterThan(10, $checked['lines'], 'Almost no field of lines was recognised - "wrap" is read wrong.');
        $this->assertSame(
            [],
            $violations,
            "These plain text fields of the showcase are seeded with something else. Write plain text, as a folded\n"
            . "scalar (\">-\") where it is long - a line of the value is a paragraph:\n  " . implode("\n  ", $violations),
        );
    }

    /**
     * The fields a type of a table shows, each with its configuration after
     * the `columnsOverrides` of the type: the `showitem` of the type, with
     * every palette it names unpacked, reduced to the fields the table has
     * a column for. The configuration is the column merged with the override
     * by `array_replace_recursive()`, as
     * `TcaSchemaFactory::getFinalFieldConfiguration()` does it on v13.4.
     *
     * @param array<string, mixed> $tca the TCA of one table
     * @return array<string, array<string, mixed>>
     */
    private static function fieldsOfType(array $tca, string $type): array
    {
        $typeConfiguration = $tca['types'][$type] ?? [];
        $fields = [];
        $add = static function (string $name) use (&$fields, $tca, $typeConfiguration): void {
            if (!isset($tca['columns'][$name])) {
                return;
            }
            $fields[$name] = array_replace_recursive(
                $tca['columns'][$name],
                $typeConfiguration['columnsOverrides'][$name] ?? [],
            );
        };
        foreach (GeneralUtility::trimExplode(',', (string)($typeConfiguration['showitem'] ?? ''), true) as $entry) {
            [$name, , $palette] = GeneralUtility::trimExplode(';', $entry . ';;;');
            if ($name === '--palette--') {
                foreach (GeneralUtility::trimExplode(',', (string)($tca['palettes'][$palette]['showitem'] ?? ''), true) as $paletteEntry) {
                    $add(GeneralUtility::trimExplode(';', $paletteEntry . ';')[0]);
                }
                continue;
            }
            $add($name);
        }

        return $fields;
    }

    /**
     * What is wrong with a plain text value, if anything.
     *
     * A line of the value is a paragraph (`f:format.html`) or ends in a line
     * break (`f:format.nl2br`), so a line that ends in the middle of a sentence
     * is a wrap of the source that reached the database.
     *
     * `$linesAreParagraphs` is false for a column whose relation declares
     * `wrap = off`: there a line break is the structure of the value - one
     * feature of a pricing plan per line - and a line is not meant to be a
     * sentence. Markup and stray white space are still wrong there.
     *
     * @return list<string>
     */
    private static function violations(string $value, bool $linesAreParagraphs = true): array
    {
        $violations = [];
        if (preg_match('#</?[a-z][^>]*>|&[a-z]+;#i', $value, $match) === 1) {
            $violations[] = sprintf('holds markup, "%s"', $match[0]);
        }
        if (trim($value) !== $value) {
            $violations[] = 'starts or ends with white space - a trailing newline is an empty paragraph or a line break';
        }
        if (str_contains($value, "\n\n")) {
            $violations[] = 'holds an empty line - an empty paragraph';
        }
        if ($linesAreParagraphs) {
            foreach (explode("\n", trim($value)) as $line) {
                $line = rtrim($line);
                if ($line !== '' && preg_match('/[.!?:"\')\x{201D}\x{2026}]$/u', $line) !== 1) {
                    $violations[] = sprintf('breaks a line inside a sentence, after "%s"', mb_substr($line, -24));
                }
            }
        }

        return $violations;
    }

    /**
     * The content elements and the list items of the showcase, each with its
     * declared id, from wherever the page tree nests them.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private static function seededRecords(): array
    {
        $scenario = Yaml::parseFile(dirname(__DIR__, 2) . '/' . self::SCENARIO);
        self::assertIsArray($scenario);
        $tables = [];
        foreach ($scenario['entitySettings'] ?? [] as $entity => $settings) {
            if (is_array($settings) && isset($settings['tableName'])) {
                $tables[(string)$entity] = (string)$settings['tableName'];
            }
        }

        $records = ['tt_content' => [], 'tx_theme_list_item' => []];
        $walk = static function (string $entity, array $items) use (&$walk, &$records, $tables): void {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $table = $tables[$entity] ?? $entity;
                if (isset($records[$table])) {
                    // A record without its id would be checked under none,
                    // and a violation in it could not be named.
                    self::assertIsArray($item['self'] ?? null, sprintf('A record of "%s" has no "self".', $entity));
                    self::assertArrayHasKey('id', $item['self'], sprintf('A record of "%s" declares no id: %s', $entity, implode(', ', array_keys($item['self']))));
                    $records[$table][] = ['id' => (int)$item['self']['id']] + $item['self'];
                }
                foreach ($item['entities'] ?? [] as $nested => $nestedItems) {
                    if (is_array($nestedItems)) {
                        $walk((string)$nested, $nestedItems);
                    }
                }
                if (is_array($item['children'] ?? null)) {
                    $walk($entity, $item['children']);
                }
            }
        };
        foreach ($scenario['entities'] ?? [] as $entity => $items) {
            if (is_array($items)) {
                $walk((string)$entity, $items);
            }
        }

        return [$records['tt_content'], $records['tx_theme_list_item']];
    }
}
