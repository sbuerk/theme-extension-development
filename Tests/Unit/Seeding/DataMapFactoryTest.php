<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Seeding;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\DataMapFactory;
use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use SBUERK\ThemeExtensionDevelopment\Seeding\SeedDefinition;
use SBUERK\ThemeExtensionDevelopment\Seeding\SeedRecord;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DataMapFactoryTest extends UnitTestCase
{
    private DataMapFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new DataMapFactory();
    }

    #[Test]
    public function nestingBecomesThePidOfTheChild(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', ['title' => 'Home'], 1, [
                new SeedRecord('pages', 'sub', ['title' => 'Sub']),
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        $this->assertSame('0', $map['pages']['NEWpages_home']['pid']);
        $this->assertSame('NEWpages_home', $map['pages']['NEWpages_sub']['pid']);
    }

    #[Test]
    public function siblingsAfterTheFirstArePlacedBehindTheirPredecessor(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'first', []),
            new SeedRecord('pages', 'second', []),
            new SeedRecord('pages', 'third', []),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // Without this a new record goes to the top of its parent, and the tree
        // would come out in reverse declaration order.
        $this->assertSame('0', $map['pages']['NEWpages_first']['pid']);
        $this->assertSame('-NEWpages_first', $map['pages']['NEWpages_second']['pid']);
        $this->assertSame('-NEWpages_second', $map['pages']['NEWpages_third']['pid']);
    }

    #[Test]
    public function thePredecessorIsTrackedPerTable(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [
                new SeedRecord('tt_content', 'text-one', []),
                new SeedRecord('tt_content', 'text-two', []),
                new SeedRecord('pages', 'sub', []),
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // A negative pid names a record of the SAME table, so the first content
        // element and the first sub page both address the page they belong to.
        $this->assertSame('NEWpages_home', $map['tt_content']['NEWtt_content_text-one']['pid']);
        $this->assertSame('-NEWtt_content_text-one', $map['tt_content']['NEWtt_content_text-two']['pid']);
        $this->assertSame('NEWpages_home', $map['pages']['NEWpages_sub']['pid']);
    }

    #[Test]
    public function declaredUidsAreCollectedAsSuggestions(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], 1, [
                new SeedRecord('pages', 'sub', [], 4),
                new SeedRecord('pages', 'no-uid', []),
            ]),
        ]);

        $suggested = $this->subject->createFromDefinition($definition)['suggestedUids'];

        $this->assertSame(
            ['NEWpages_home' => 1, 'NEWpages_sub' => 4],
            $suggested,
        );
    }

    #[Test]
    public function recordsAreSeededVisible(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', ['title' => 'Home'])]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // DataHandler creates records hidden, which would leave a seeded tree
        // invisible in the frontend with nothing saying why.
        $this->assertSame(0, $map['pages']['NEWpages_home']['hidden']);
    }

    #[Test]
    public function aDefinitionCanAskForAHiddenRecord(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', ['hidden' => 1])]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        $this->assertSame(1, $map['pages']['NEWpages_home']['hidden']);
    }

    #[Test]
    public function theStructuralPidIsNeverTakenFromTheDefinition(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', ['pid' => 99])]);

        $map = $this->subject->createFromDefinition($definition, 7)['dataMap'];

        $this->assertSame('7', $map['pages']['NEWpages_home']['pid']);
    }

    #[Test]
    public function fileReferencesAreReturnedForASecondPassRatherThanPutInTheDataMap(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', ['title' => 'Home'], 1, [], ['media' => ['hero']]),
        ]);

        $result = $this->subject->createFromDefinition($definition, 0, ['hero' => 7]);

        // "uid_foreign" is a plain integer column, so a reference cannot be
        // written in the same pass as the record it points at.
        $this->assertArrayNotHasKey('sys_file_reference', $result['dataMap']);
        $this->assertSame(
            [['parent' => 'NEWpages_home', 'table' => 'pages', 'field' => 'media', 'file' => 7, 'pid' => '0']],
            $result['references'],
        );
    }

    #[Test]
    public function referencingAFileTheDefinitionDoesNotDeclareIsRejected(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [], ['media' => ['missing']]),
        ]);

        $this->expectException(SeedingException::class);
        $this->expectExceptionCode(1786924828);

        $this->subject->createFromDefinition($definition);
    }

    #[Test]
    public function recordsAreWrittenBelowTheGivenRootPage(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', [])]);

        $map = $this->subject->createFromDefinition($definition, 42)['dataMap'];

        $this->assertSame('42', $map['pages']['NEWpages_home']['pid']);
    }
}
