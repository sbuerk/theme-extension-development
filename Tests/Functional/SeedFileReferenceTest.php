<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Every file reference of the showcase hangs on a record that can carry it.
 *
 * A reference in `config.yml` names its record by table and uid - the one
 * relation the scenario format cannot express, because a `sys_file_reference`
 * points at its file through a uid the FAL indexer hands out while the file is
 * placed (see `docs/development/seeding.md`). That uid is a literal, and
 * literals do not move when the records around them do.
 *
 * They did move. Inserting the byline into the article renumbered the elements
 * of page 56, and the image of the `textpic` stayed behind on uid `5604` -
 * which by then was a `text` element, a type that renders no image at all. The
 * element that needed the image had none, the element that had it showed
 * nothing, and the prose of the page went on describing an image beside the
 * text.
 *
 * Nothing caught it. `GeneratedLegacyScenarioTest::theInstanceSetDeclaresNoUidTwice()`
 * only asks whether a uid is declared twice, and the generated mirror inherits
 * the mistake unchanged, because `buildReferences()` adds the offset to
 * whatever `config.yml` says rather than looking the record up. So the mirror
 * was wrong in exactly the same way, and `--check` was green.
 *
 * This asks the question that would have caught it, of every reference rather
 * than of the one that broke: the record exists, and the **type** of that
 * record shows the field the reference writes into. It is read off the TCA of
 * the running core - the `showitem` of the record's own type, with its
 * palettes expanded - so it costs no second list of which element carries
 * which field, and a field a core version moves out of a type is a failure
 * here rather than a blank space on a page.
 *
 * --- One test, not one per reference -----------------------------------------
 *
 * The obvious shape is a data provider, one case per reference, which names
 * the offender in the test name and needs no collecting. It was written that
 * way first and is wrong here: `setUp()` imports the whole seed set, so a
 * provider of 103 cases imports it 103 times. On SQLite that is five minutes;
 * on MariaDB it pushed the suite slice from eleven minutes to over half an
 * hour, for a fixture that is identical every time.
 *
 * So the set is imported once and the loop is inside the test, which collects
 * every offender instead of stopping at the first - a failure message listing
 * all of them is what a renumbering needs anyway.
 *
 * It does not assert that the file is *rendered*. A reference on
 * `pages.media` is rendered by a menu on another page, and one on
 * `tx_theme_list_item.image` by whichever element owns the row, so "renders"
 * has no single place to look. What it does assert is the property that was
 * violated, which is that the reference names a record the field belongs to.
 */
final class SeedFileReferenceTest extends AbstractFunctionalTestCase
{
    use DataFactoryImportTrait;

    private const DESCRIPTOR = 'Configuration/DataFactory/theme-demo/config.yml';

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'sbuerk/data-factory',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->createDefaultFileStorage();
        $this->importSeedSet('theme-demo');
    }

    #[Test]
    public function everyFileReferenceHangsOnARecordThatShowsTheField(): void
    {
        $descriptor = Yaml::parseFile(dirname(__DIR__, 2) . '/' . self::DESCRIPTOR);
        $references = $descriptor['references'] ?? [];

        $this->assertGreaterThan(
            50,
            count($references),
            'The descriptor declares almost no file reference, so this proves nothing.',
        );

        $missingRecords = [];
        $wrongField = [];

        foreach ($references as $reference) {
            $table = (string)($reference['table'] ?? '');
            $uid = (int)($reference['uid'] ?? 0);
            $field = (string)($reference['field'] ?? '');

            $record = BackendUtility::getRecord($table, $uid);
            if (!is_array($record)) {
                $missingRecords[] = sprintf('%s %d', $table, $uid);
                continue;
            }

            $type = $this->typeOf($table, $record);
            if (!in_array($field, $this->fieldsShownBy($table, $type), true)) {
                $wrongField[] = sprintf('%s %d is a "%s" and shows no "%s"', $table, $uid, $type, $field);
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missingRecords)),
            "The showcase attaches a file to records the scenario does not declare:\n  "
                . implode("\n  ", array_unique($missingRecords)),
        );
        $this->assertSame(
            [],
            array_values(array_unique($wrongField)),
            "The showcase attaches a file to a field the record's type does not show, so the file is written "
                . "and nothing renders it:\n  " . implode("\n  ", array_unique($wrongField)),
        );
    }

    /**
     * The value of the record's type field, or the one type of a table that
     * has no type field - `tx_theme_list_item` is such a table.
     *
     * @param array<string, mixed> $record
     */
    private function typeOf(string $table, array $record): string
    {
        $column = (string)($GLOBALS['TCA'][$table]['ctrl']['type'] ?? '');

        if ($column !== '') {
            return (string)($record[$column] ?? '');
        }

        $types = array_keys($GLOBALS['TCA'][$table]['types'] ?? []);
        $this->assertCount(
            1,
            $types,
            sprintf('"%s" declares no type field and more than one type, so this test cannot pick one.', $table),
        );

        return (string)$types[0];
    }

    /**
     * The field names of a type's `showitem`, with its palettes expanded.
     *
     * `showitem` is a comma separated list whose entries are
     * `field;label`, or `--palette--;label;paletteName`, or
     * `--div--;label`. A palette's own `showitem` is a list of the first kind
     * plus `--linebreak--`. Core does not nest a palette in a palette, so one
     * level of expansion is all of it.
     *
     * @return list<string>
     */
    private function fieldsShownBy(string $table, string $type): array
    {
        $showitem = (string)($GLOBALS['TCA'][$table]['types'][$type]['showitem'] ?? '');
        $this->assertNotSame('', $showitem, sprintf('"%s" has no type "%s".', $table, $type));

        $fields = [];
        foreach (explode(',', $showitem) as $item) {
            $parts = array_map(trim(...), explode(';', trim($item)));
            $name = $parts[0] ?? '';

            if ($name === '--palette--') {
                $palette = (string)($GLOBALS['TCA'][$table]['palettes'][$parts[2] ?? '']['showitem'] ?? '');
                foreach (explode(',', $palette) as $inner) {
                    $inner = trim((string)(explode(';', trim($inner))[0] ?? ''));
                    if ($inner !== '' && !str_starts_with($inner, '--')) {
                        $fields[] = $inner;
                    }
                }
                continue;
            }

            if ($name !== '' && !str_starts_with($name, '--')) {
                $fields[] = $name;
            }
        }

        return array_values(array_unique($fields));
    }
}
