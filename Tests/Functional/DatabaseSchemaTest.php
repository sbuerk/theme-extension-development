<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The extension's own database schema, asserted against the database the test
 * instance actually created.
 *
 * This test exists because the two supported core versions build that schema
 * from different sources. TYPO3 v13 derives a column from every TCA `columns`
 * entry (Feature #101553, extended by #104311 in 13.3), so the extension needs
 * no `ext_tables.sql` there at all. TYPO3 v12.4 derives only the management
 * columns from `ctrl`, the types `category|datetime|slug|json|uuid` and MM
 * tables — it has no branch for `input`, `text`, `link`, `file` or `inline`,
 * and it only enriches tables some `ext_tables.sql` defined in the first place.
 * On v12 the whole `tx_theme_list_item` table and the four `tx_theme_*` columns
 * on `tt_content` therefore exist only because `ext_tables.sql` declares them.
 *
 * A missing column is not loud: nothing in the extension references it at boot,
 * so the suite stays green until a rendering test happens to select it. This
 * test is the one that fails immediately instead, and it is deliberately
 * version independent — the point is that both versions end up with the same
 * schema.
 *
 * Asserted per column are its presence and the Doctrine type the schema manager
 * reads back. Length, default, and in particular `unsigned` are not asserted:
 * they are not portable across the four DBMS this suite runs on — PostgreSQL
 * has no unsigned integers, and every platform normalises defaults its own way.
 * The type plus the presence is what distinguishes a correct declaration from a
 * wrong one; the exact rendering is the schema analyzer's business, and that
 * the analyzer agrees with the declaration is proven by it not asking for any
 * change after the instance was set up.
 */
final class DatabaseSchemaTest extends AbstractFunctionalTestCase
{
    /**
     * Every column `Configuration/TCA/tx_theme_list_item.php` declares, plus
     * the two columns the `tx_theme_list_items` inline relation on the parent
     * side writes into the child table (`foreign_field` and
     * `foreign_table_field`), and the four columns
     * `Configuration/TCA/Overrides/tt_content.php` adds to `tt_content`.
     *
     * The management columns — `uid`, `pid`, `tstamp`, `crdate`, `deleted`,
     * `hidden`, `sorting_foreign`, the language fields and the `t3ver_*`
     * fields — are not listed. Both core versions derive those from `ctrl`,
     * and `ExtensionLoadedTest` already proves the table is set up at all.
     *
     * @return \Generator<string, array{table: string, column: string, type: class-string}>
     */
    public static function expectedColumns(): \Generator
    {
        yield 'tx_theme_list_item.uid_foreign is an integer' => [
            'table' => 'tx_theme_list_item',
            'column' => 'uid_foreign',
            'type' => IntegerType::class,
        ];
        yield 'tx_theme_list_item.tablename is a string' => [
            'table' => 'tx_theme_list_item',
            'column' => 'tablename',
            'type' => StringType::class,
        ];
        yield 'tx_theme_list_item.fieldname is a string' => [
            'table' => 'tx_theme_list_item',
            'column' => 'fieldname',
            'type' => StringType::class,
        ];
        yield 'tx_theme_list_item.header is a string' => [
            'table' => 'tx_theme_list_item',
            'column' => 'header',
            'type' => StringType::class,
        ];
        yield 'tx_theme_list_item.text is a text' => [
            'table' => 'tx_theme_list_item',
            'column' => 'text',
            'type' => TextType::class,
        ];
        yield 'tx_theme_list_item.image is an integer' => [
            'table' => 'tx_theme_list_item',
            'column' => 'image',
            'type' => IntegerType::class,
        ];
        yield 'tx_theme_list_item.link is a text' => [
            'table' => 'tx_theme_list_item',
            'column' => 'link',
            'type' => TextType::class,
        ];
        yield 'tx_theme_list_item.link_label is a string' => [
            'table' => 'tx_theme_list_item',
            'column' => 'link_label',
            'type' => StringType::class,
        ];
        yield 'tt_content.tx_theme_link is a text' => [
            'table' => 'tt_content',
            'column' => 'tx_theme_link',
            'type' => TextType::class,
        ];
        yield 'tt_content.tx_theme_link_label is a string' => [
            'table' => 'tt_content',
            'column' => 'tx_theme_link_label',
            'type' => StringType::class,
        ];
        yield 'tt_content.tx_theme_link_variant is a string' => [
            'table' => 'tt_content',
            'column' => 'tx_theme_link_variant',
            'type' => StringType::class,
        ];
        yield 'tt_content.tx_theme_list_items is an integer' => [
            'table' => 'tt_content',
            'column' => 'tx_theme_list_items',
            'type' => IntegerType::class,
        ];
    }

    #[Test]
    public function childTableOfTheInlineRelationExists(): void
    {
        $schemaManager = $this->getConnectionPool()
            ->getConnectionForTable('tx_theme_list_item')
            ->createSchemaManager();

        $this->assertTrue(
            $schemaManager->tablesExist(['tx_theme_list_item']),
            'The "tx_theme_list_item" table exists in the database of the test instance.',
        );
    }

    /**
     * A row can be written without naming the extension's own columns.
     *
     * This is not a theoretical property. Every CSV fixture of this suite is
     * imported with `importCSVDataSet()`, which inserts exactly the columns the
     * file names, and none of them names a `tx_theme_*` column. An integrator
     * writing `tt_content` rows from a migration or an importer does the same.
     * A column that is `NOT NULL` without a usable default therefore does not
     * fail somewhere in the theme — it fails on the first insert of any content
     * element, whether the theme is involved or not.
     *
     * The reason this needs asserting is that "usable default" is per DBMS.
     * TYPO3 v12 cannot render a default for a `TEXT` column on MySQL at all —
     * Doctrine's `AbstractMySQLPlatform::getDefaultValueDeclarationSQL()` drops
     * it, and the platform override that turns it into MySQL's expression
     * default is TYPO3 v13 (Feature #103578). The two `link` columns are
     * therefore declared nullable in `ext_tables.sql`, and this test is what
     * says so in a way that fails on the DBMS that cares. MySQL is the only one
     * of the four that ever failed here, so this assertion is only as good as
     * the `-d mysql` leg of the matrix.
     */
    #[Test]
    public function rowsCanBeWrittenWithoutTheExtensionsOwnColumns(): void
    {
        $connectionPool = $this->getConnectionPool();

        $contentInserted = $connectionPool->getConnectionForTable('tt_content')->insert(
            'tt_content',
            ['pid' => 1, 'CType' => 'text', 'header' => 'Written without any tx_theme_ column'],
        );
        $this->assertSame(1, $contentInserted);

        $listItemInserted = $connectionPool->getConnectionForTable('tx_theme_list_item')->insert(
            'tx_theme_list_item',
            ['pid' => 1, 'header' => 'Written without a link'],
        );
        $this->assertSame(1, $listItemInserted);
    }

    /**
     * @param class-string $type
     */
    #[DataProvider('expectedColumns')]
    #[Test]
    public function columnExistsWithExpectedType(string $table, string $column, string $type): void
    {
        $schemaManager = $this->getConnectionPool()
            ->getConnectionForTable($table)
            ->createSchemaManager();

        $columns = [];
        foreach ($schemaManager->listTableColumns($table) as $tableColumn) {
            $columns[$tableColumn->getName()] = $tableColumn;
        }

        $this->assertArrayHasKey($column, $columns, sprintf(
            'Column "%s" exists on table "%s". Present columns: %s',
            $column,
            $table,
            implode(', ', array_keys($columns)),
        ));
        $this->assertInstanceOf($type, $columns[$column]->getType(), sprintf(
            'Column "%s.%s" is of the expected Doctrine type.',
            $table,
            $column,
        ));
    }
}
