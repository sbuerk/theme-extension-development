<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\DataProcessing;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\DataProcessing\TableProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TableProcessorTest extends UnitTestCase
{
    private TableProcessor $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TableProcessor();
    }

    /**
     * Built through reflection rather than with "new", deliberately:
     * "TableProcessor::process()" never touches anything
     * "ContentObjectRenderer" is constructed with. It only reads
     * "$cObj->data" and calls "stdWrapValue()"/"checkIf()" in the branch that
     * runs when a "processorConfiguration" carries a trailing ".", which these
     * tests never pass. Skipping the constructor therefore keeps this test
     * independent of whatever that constructor requires, and it makes visible
     * that nothing the constructor would set up is part of what is under test.
     */
    private function newContentObjectRenderer(): ContentObjectRenderer
    {
        return (new \ReflectionClass(ContentObjectRenderer::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<string, mixed> $row A "tt_content" row, as it appears on
     *                                  the frontend: "table_delimiter" and
     *                                  "table_enclosure" already resolved to
     *                                  their plain scalar TCA "select" value.
     * @return array<string, mixed>
     */
    private function process(array $row): array
    {
        $cObj = $this->newContentObjectRenderer();
        $cObj->data = array_merge([
            'bodytext' => '',
            'table_caption' => '',
            'table_delimiter' => 124,
            'table_enclosure' => 0,
            'table_header_position' => 0,
            'table_tfoot' => 0,
        ], $row);

        return $this->subject->process($cObj, [], [], [])['table'];
    }

    #[Test]
    public function emptyBodytextProducesNoRows(): void
    {
        $result = $this->process(['bodytext' => '']);

        $this->assertNull($result['headerRow']);
        $this->assertNull($result['footerRow']);
        $this->assertSame([], $result['bodyRows']);
    }

    #[Test]
    public function aSingleRowIsKeptAsOneBodyRow(): void
    {
        $result = $this->process(['bodytext' => 'a|b|c']);

        $this->assertNull($result['headerRow']);
        $this->assertNull($result['footerRow']);
        $this->assertSame([['a', 'b', 'c']], $result['bodyRows']);
    }

    #[Test]
    public function rowsWithDifferingCellCountsArePaddedToTheWidestRow(): void
    {
        $result = $this->process(['bodytext' => "a|b|c\nd|e"]);

        $this->assertSame([
            ['a', 'b', 'c'],
            ['d', 'e', ''],
        ], $result['bodyRows']);
    }

    #[Test]
    public function anEnclosureCharacterActuallyAppearingInACellStaysInsideThatCell(): void
    {
        // 34 = '"' (Double quotes), 124 = '|' (Pipe) - the cell's own value
        // contains both the delimiter and the enclosure character, quoted and
        // doubled the way an editor typing into the table wizard would
        // produce it ("""" -> a literal '"').
        $result = $this->process([
            'bodytext' => '"He said ""hi|there"""|b',
            'table_delimiter' => 124,
            'table_enclosure' => 34,
        ]);

        $this->assertSame([['He said "hi|there"', 'b']], $result['bodyRows']);
    }

    #[Test]
    public function headerPositionNoneKeepsEveryRowInTheBody(): void
    {
        $result = $this->process([
            'bodytext' => "a|b\nc|d",
            'table_header_position' => 0,
        ]);

        $this->assertNull($result['headerRow']);
        $this->assertFalse($result['headerColumn']);
        $this->assertSame([['a', 'b'], ['c', 'd']], $result['bodyRows']);
    }

    #[Test]
    public function headerPositionTopExtractsTheFirstRowAsTheHeaderRow(): void
    {
        $result = $this->process([
            'bodytext' => "Name|Age\nAda|36\nGrace|85",
            'table_header_position' => 1,
        ]);

        $this->assertSame(['Name', 'Age'], $result['headerRow']);
        $this->assertFalse($result['headerColumn']);
        $this->assertSame([['Ada', '36'], ['Grace', '85']], $result['bodyRows']);
    }

    #[Test]
    public function headerPositionLeftMarksTheFirstColumnRatherThanRemovingARow(): void
    {
        $result = $this->process([
            'bodytext' => "Name|Ada\nAge|36",
            'table_header_position' => 2,
        ]);

        $this->assertNull($result['headerRow']);
        $this->assertTrue($result['headerColumn']);
        $this->assertSame([['Name', 'Ada'], ['Age', '36']], $result['bodyRows']);
    }

    #[Test]
    public function tfootWrapsTheLastRemainingRowAsTheFooterRow(): void
    {
        $result = $this->process([
            'bodytext' => "Name|Age\nAda|36\nGrace|85\nTotal|121",
            'table_header_position' => 1,
            'table_tfoot' => 1,
        ]);

        $this->assertSame(['Name', 'Age'], $result['headerRow']);
        $this->assertSame([['Ada', '36'], ['Grace', '85']], $result['bodyRows']);
        $this->assertSame(['Total', '121'], $result['footerRow']);
    }

    #[Test]
    public function tfootOnASingleRowConsumedAsTheHeaderLeavesNoFooter(): void
    {
        // The one row becomes the header; there is nothing left to also
        // serve as a footer, so "footerRow" stays null rather than
        // duplicating the header.
        $result = $this->process([
            'bodytext' => 'Name|Age',
            'table_header_position' => 1,
            'table_tfoot' => 1,
        ]);

        $this->assertSame(['Name', 'Age'], $result['headerRow']);
        $this->assertNull($result['footerRow']);
        $this->assertSame([], $result['bodyRows']);
    }

    #[Test]
    public function tfootOnASingleRowWithNoHeaderMovesItToTheFooterInstead(): void
    {
        $result = $this->process([
            'bodytext' => 'Total|121',
            'table_header_position' => 0,
            'table_tfoot' => 1,
        ]);

        $this->assertNull($result['headerRow']);
        $this->assertSame(['Total', '121'], $result['footerRow']);
        $this->assertSame([], $result['bodyRows']);
    }

    #[Test]
    public function theDelimiterCharacterCodeIsDecodedRatherThanUsedAsAnInteger(): void
    {
        // 59 = ';' (Semicolon). A literal "59" delimiter would never split
        // this string at all.
        $result = $this->process([
            'bodytext' => 'a;b;c',
            'table_delimiter' => 59,
        ]);

        $this->assertSame([['a', 'b', 'c']], $result['bodyRows']);
    }

    #[Test]
    public function theCaptionIsTrimmed(): void
    {
        $result = $this->process(['table_caption' => '  Quarterly figures  ']);

        $this->assertSame('Quarterly figures', $result['caption']);
    }

    #[Test]
    public function theTargetVariableNameDefaultsToTable(): void
    {
        $cObj = $this->newContentObjectRenderer();
        $cObj->data = ['bodytext' => 'a|b'];

        $result = $this->subject->process($cObj, [], [], []);

        $this->assertArrayHasKey('table', $result);
    }

    #[Test]
    public function theTargetVariableNameCanBeOverriddenThroughTypoScript(): void
    {
        $cObj = $this->newContentObjectRenderer();
        $cObj->data = ['bodytext' => 'a|b'];

        $result = $this->subject->process($cObj, [], ['as' => 'myTable'], []);

        $this->assertArrayHasKey('myTable', $result);
        $this->assertArrayNotHasKey('table', $result);
    }
}
