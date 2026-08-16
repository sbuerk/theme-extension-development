<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Seeding;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\DataMapFactory;
use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use SBUERK\ThemeExtensionDevelopment\Seeding\SeedDefinition;
use SBUERK\ThemeExtensionDevelopment\Seeding\SeedFileReference;
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

        $this->assertSame('0', $map['pages']['NEWpages-home']['pid']);
        $this->assertSame('NEWpages-home', $map['pages']['NEWpages-sub']['pid']);
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
        $this->assertSame('0', $map['pages']['NEWpages-first']['pid']);
        $this->assertSame('-NEWpages-first', $map['pages']['NEWpages-second']['pid']);
        $this->assertSame('-NEWpages-second', $map['pages']['NEWpages-third']['pid']);
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
        $this->assertSame('NEWpages-home', $map['tt_content']['NEWttcontent-text-one']['pid']);
        $this->assertSame('-NEWttcontent-text-one', $map['tt_content']['NEWttcontent-text-two']['pid']);
        $this->assertSame('NEWpages-home', $map['pages']['NEWpages-sub']['pid']);
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

        $result = $this->subject->createFromDefinition($definition);

        // Keyed "<table>:<uid>", which is the key "DataHandler::insertDB()"
        // looks the suggestion up under. Keyed by the placeholder instead - as
        // this did - the lookup never matches and the suggestion is dropped
        // without a word.
        $this->assertSame(
            ['pages:1' => true, 'pages:4' => true],
            $result['suggestedUids'],
        );

        // And the uid has to be in the row as well, because that is where
        // DataHandler reads the suggestion from before it looks the key up. It
        // drops the column again before the insert, so this cannot write a uid
        // on its own.
        $this->assertSame(1, $result['dataMap']['pages']['NEWpages-home']['uid']);
        $this->assertArrayNotHasKey('uid', $result['dataMap']['pages']['NEWpages-no-uid']);
    }

    #[Test]
    public function recordsAreSeededVisible(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', ['title' => 'Home'])]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // DataHandler creates records hidden, which would leave a seeded tree
        // invisible in the frontend with nothing saying why.
        $this->assertSame(0, $map['pages']['NEWpages-home']['hidden']);
    }

    #[Test]
    public function aDefinitionCanAskForAHiddenRecord(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', ['hidden' => 1])]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        $this->assertSame(1, $map['pages']['NEWpages-home']['hidden']);
    }

    #[Test]
    public function everyNonStructuralFieldReachesTheDataMapVerbatim(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [
                'title' => 'Home',
                'backend_layout' => 'pagets__content',
                'nav_hide' => 1,
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // "pid" and "hidden" are the only two values the factory sets itself,
        // so a field it has never heard of needs no support to be seedable.
        $this->assertSame(
            ['title' => 'Home', 'backend_layout' => 'pagets__content', 'nav_hide' => 1, 'pid' => '0', 'hidden' => 0],
            $map['pages']['NEWpages-home'],
        );
    }

    #[Test]
    public function theStructuralPidIsNeverTakenFromTheDefinition(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', ['pid' => 99])]);

        $map = $this->subject->createFromDefinition($definition, 7)['dataMap'];

        $this->assertSame('7', $map['pages']['NEWpages-home']['pid']);
    }

    #[Test]
    public function fileReferencesAreReturnedForASecondPassRatherThanPutInTheDataMap(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', ['title' => 'Home'], 1, [], [
                'media' => [new SeedFileReference('hero')],
            ]),
        ]);

        $result = $this->subject->createFromDefinition($definition, 0, ['hero' => 7]);

        // "uid_foreign" is a plain integer column, so a reference cannot be
        // written in the same pass as the record it points at.
        $this->assertArrayNotHasKey('sys_file_reference', $result['dataMap']);
        $this->assertSame(
            [[
                'parent' => 'NEWpages-home',
                'table' => 'pages',
                'field' => 'media',
                'file' => 7,
                'pid' => '0',
                'values' => [],
            ]],
            $result['references'],
        );
    }

    #[Test]
    public function theFieldsOfAReferenceAreCarriedIntoTheSecondPass(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], 1, [], [
                'media' => [new SeedFileReference('hero', ['alternative' => 'A hero image'])],
            ]),
        ]);

        $result = $this->subject->createFromDefinition($definition, 0, ['hero' => 7]);

        $this->assertSame(['alternative' => 'A hero image'], $result['references'][0]['values']);
    }

    #[Test]
    public function referencingAFileTheDefinitionDoesNotDeclareIsRejected(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [], [
                'media' => [new SeedFileReference('missing')],
            ]),
        ]);

        $this->expectException(SeedingException::class);
        $this->expectExceptionCode(1786924828);

        $this->subject->createFromDefinition($definition);
    }

    #[Test]
    public function aPlaceholderCarriesNoUnderscore(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [
                new SeedRecord('tt_content', 'links', [], null, [], [], [
                    'tx_theme_list_items' => [new SeedRecord('tx_theme_list_item', 'links-docs', [])],
                ]),
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // DataHandler::processRemapStack() reads a relation value containing an
        // underscore as the "<table>_<uid>" form and takes it apart there, so a
        // placeholder carrying one resolves to nothing and the relation is
        // written empty - with an empty error log.
        foreach (['pages' => 'NEWpages-home', 'tt_content' => 'NEWttcontent-links', 'tx_theme_list_item' => 'NEWtxthemelistitem-links-docs'] as $table => $placeholder) {
            $this->assertArrayHasKey($placeholder, $map[$table]);
            $this->assertStringNotContainsString('_', $placeholder);
        }
    }

    #[Test]
    public function anInlineFieldIsWrittenAsTheCommaJoinedPlaceholdersInDeclarationOrder(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [
                new SeedRecord('tt_content', 'links', ['CType' => 'theme_linklist'], null, [], [], [
                    'tx_theme_list_items' => [
                        new SeedRecord('tx_theme_list_item', 'links-docs', ['link_label' => 'Docs']),
                        new SeedRecord('tx_theme_list_item', 'links-media', ['link_label' => 'Media']),
                    ],
                ]),
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // DataHandler numbers the relation by walking this list, so its order
        // is the order the children come out in - not the order of the data map
        // and not the "sorting" of the child records.
        $this->assertSame(
            'NEWtxthemelistitem-links-docs,NEWtxthemelistitem-links-media',
            $map['tt_content']['NEWttcontent-links']['tx_theme_list_items'],
        );
    }

    #[Test]
    public function anInlineChildIsWrittenOntoThePageItsParentSitsOn(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [
                new SeedRecord('tt_content', 'first', []),
                new SeedRecord('tt_content', 'links', [], null, [], [], [
                    'tx_theme_list_items' => [
                        new SeedRecord('tx_theme_list_item', 'links-docs', []),
                        new SeedRecord('tx_theme_list_item', 'links-media', []),
                    ],
                ]),
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // Not the parent's placeholder, and not the negative "insert after"
        // hint the sibling levels use: that is a sorting instruction against
        // the same table, and the order of inline children comes from the
        // relation list instead.
        $this->assertSame('NEWpages-home', $map['tx_theme_list_item']['NEWtxthemelistitem-links-docs']['pid']);
        $this->assertSame('NEWpages-home', $map['tx_theme_list_item']['NEWtxthemelistitem-links-media']['pid']);
    }

    #[Test]
    public function anInlineChildTakesTheSuggestedUidAndTheVisibleDefault(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('tt_content', 'links', [], null, [], [], [
                'tx_theme_list_items' => [new SeedRecord('tx_theme_list_item', 'links-docs', [], 42)],
            ]),
        ]);

        $result = $this->subject->createFromDefinition($definition);

        $this->assertTrue($result['suggestedUids']['tx_theme_list_item:42']);
        $this->assertSame(0, $result['dataMap']['tx_theme_list_item']['NEWtxthemelistitem-links-docs']['hidden']);
    }

    #[Test]
    public function theFileReferencesOfAnInlineChildAreCollectedToo(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [
                new SeedRecord('tt_content', 'grid', [], null, [], [], [
                    'tx_theme_list_items' => [
                        new SeedRecord('tx_theme_list_item', 'grid-tile', [], null, [], [
                            'image' => [new SeedFileReference('hero')],
                        ]),
                    ],
                ]),
            ]),
        ]);

        $references = $this->subject->createFromDefinition($definition, 0, ['hero' => 7])['references'];

        $this->assertSame([[
            'parent' => 'NEWtxthemelistitem-grid-tile',
            'table' => 'tx_theme_list_item',
            'field' => 'image',
            'file' => 7,
            // The page the inline child sits on, which is the page its parent
            // sits on as well.
            'pid' => 'NEWpages-home',
            'values' => [],
        ]], $references);
    }

    #[Test]
    public function aRecordMayCarryMoreThanOneInlineField(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('tt_content', 'element', [], null, [], [], [
                'tx_theme_list_items' => [new SeedRecord('tx_theme_list_item', 'first', [])],
                'tx_theme_other_items' => [new SeedRecord('tx_theme_other_item', 'second', [])],
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        $this->assertSame('NEWtxthemelistitem-first', $map['tt_content']['NEWttcontent-element']['tx_theme_list_items']);
        $this->assertSame('NEWtxthemeotheritem-second', $map['tt_content']['NEWttcontent-element']['tx_theme_other_items']);
        $this->assertArrayHasKey('NEWtxthemelistitem-first', $map['tx_theme_list_item']);
        $this->assertArrayHasKey('NEWtxthemeotheritem-second', $map['tx_theme_other_item']);
    }

    #[Test]
    public function inlineChildrenDoNotChainTheirSiblingsIntoTheDeclaredContentOrder(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('pages', 'home', [], null, [
                new SeedRecord('tt_content', 'links', [], null, [], [], [
                    'tx_theme_list_items' => [new SeedRecord('tx_theme_list_item', 'links-docs', [])],
                ]),
                new SeedRecord('tt_content', 'social', [], null, [], [], [
                    'tx_theme_list_items' => [new SeedRecord('tx_theme_list_item', 'social-mastodon', [])],
                ]),
            ]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        // The children of two different parents share one table. Chaining them
        // per table, as the page and content levels do, would make the second
        // parent's child point at the first parent's child.
        $this->assertSame('NEWpages-home', $map['tx_theme_list_item']['NEWtxthemelistitem-social-mastodon']['pid']);
        // The content elements themselves are still chained.
        $this->assertSame('-NEWttcontent-links', $map['tt_content']['NEWttcontent-social']['pid']);
    }

    #[Test]
    public function anEmptyInlineFieldIsNotWritten(): void
    {
        $definition = new SeedDefinition('demo', [
            new SeedRecord('tt_content', 'links', [], null, [], [], ['tx_theme_list_items' => []]),
        ]);

        $map = $this->subject->createFromDefinition($definition)['dataMap'];

        $this->assertArrayNotHasKey('tx_theme_list_items', $map['tt_content']['NEWttcontent-links']);
    }

    #[Test]
    public function recordsAreWrittenBelowTheGivenRootPage(): void
    {
        $definition = new SeedDefinition('demo', [new SeedRecord('pages', 'home', [])]);

        $map = $this->subject->createFromDefinition($definition, 42)['dataMap'];

        $this->assertSame('42', $map['pages']['NEWpages-home']['pid']);
    }
}
