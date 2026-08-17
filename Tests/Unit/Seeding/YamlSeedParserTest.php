<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Seeding;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use SBUERK\ThemeExtensionDevelopment\Seeding\SeedRecord;
use SBUERK\ThemeExtensionDevelopment\Seeding\YamlSeedParser;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class YamlSeedParserTest extends UnitTestCase
{
    private YamlSeedParser $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new YamlSeedParser();
    }

    #[Test]
    public function structuralKeysAreNotWrittenAsFields(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [
                ['identifier' => 'home', 'uid' => 1, 'title' => 'Home', 'children' => [], 'content' => []],
            ],
        ]);

        $record = $definition->records[0];

        $this->assertSame(['title' => 'Home'], $record->values);
        $this->assertSame(1, $record->uid);
        $this->assertSame('home', $record->identifier);
        $this->assertSame('pages', $record->table);
    }

    #[Test]
    public function contentIsNestedAsTtContentAndChildrenAsPages(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [
                [
                    'identifier' => 'home',
                    'content' => [['identifier' => 'text', 'CType' => 'text']],
                    'children' => [['identifier' => 'sub', 'title' => 'Sub']],
                ],
            ],
        ]);

        $children = $definition->records[0]->children;

        $this->assertCount(2, $children);
        // Content first, so it lands above the sub pages of the same parent.
        $this->assertSame('tt_content', $children[0]->table);
        $this->assertSame('pages', $children[1]->table);
    }

    #[Test]
    public function uidIsOptional(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [['identifier' => 'home', 'title' => 'Home']],
        ]);

        $this->assertNull($definition->records[0]->uid);
    }

    #[Test]
    public function aFileReferenceIsEitherAnIdentifierOrAMapCarryingItsFields(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [
                [
                    'identifier' => 'home',
                    'files' => [
                        'media' => [
                            'plain',
                            ['identifier' => 'annotated', 'alternative' => 'Alt text', 'description' => 'Caption'],
                        ],
                    ],
                ],
            ],
        ]);

        $references = $definition->records[0]->files['media'];

        $this->assertSame('plain', $references[0]->identifier);
        // The short form declares no fields rather than empty ones, so nothing
        // it does not mention is written to the reference at all.
        $this->assertSame([], $references[0]->values);

        $this->assertSame('annotated', $references[1]->identifier);
        $this->assertSame(['alternative' => 'Alt text', 'description' => 'Caption'], $references[1]->values);
    }

    #[Test]
    public function inlineChildrenAreKeyedByTheParentFieldAndCarryTheirOwnTable(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'home',
                'content' => [[
                    'identifier' => 'links',
                    'CType' => 'theme_linklist',
                    'inline' => [
                        'tx_theme_list_items' => [
                            ['identifier' => 'links-docs', 'table' => 'tx_theme_list_item', 'link_label' => 'Docs'],
                            ['identifier' => 'links-media', 'table' => 'tx_theme_list_item', 'link_label' => 'Media'],
                        ],
                    ],
                ]],
            ]],
        ]);

        $parent = $definition->records[0]->children[0];
        $children = $parent->inline['tx_theme_list_items'];

        $this->assertSame(['tx_theme_list_items'], array_keys($parent->inline));
        $this->assertCount(2, $children);
        $this->assertSame('tx_theme_list_item', $children[0]->table);
        $this->assertSame('links-docs', $children[0]->identifier);
        $this->assertSame('links-media', $children[1]->identifier);
        // "table" is structure on an inline child, so it is not written as a
        // field of the record.
        $this->assertSame(['link_label' => 'Docs'], $children[0]->values);
        // ... and "inline" is not written as a field of the parent either.
        $this->assertSame(['CType' => 'theme_linklist'], $parent->values);
    }

    #[Test]
    public function aRecordMayCarryMoreThanOneInlineField(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'home',
                'content' => [[
                    'identifier' => 'element',
                    'inline' => [
                        'tx_theme_list_items' => [
                            ['identifier' => 'first', 'table' => 'tx_theme_list_item'],
                        ],
                        'tx_theme_other_items' => [
                            ['identifier' => 'second', 'table' => 'tx_theme_other_item'],
                        ],
                    ],
                ]],
            ]],
        ]);

        $inline = $definition->records[0]->children[0]->inline;

        $this->assertSame(['tx_theme_list_items', 'tx_theme_other_items'], array_keys($inline));
        $this->assertSame('tx_theme_list_item', $inline['tx_theme_list_items'][0]->table);
        $this->assertSame('tx_theme_other_item', $inline['tx_theme_other_items'][0]->table);
    }

    #[Test]
    public function anInlineChildTakesTheSameUidAndFilesAsAnyOtherRecord(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'home',
                'content' => [[
                    'identifier' => 'grid',
                    'inline' => [
                        'tx_theme_list_items' => [[
                            'identifier' => 'grid-tile',
                            'table' => 'tx_theme_list_item',
                            'uid' => 42,
                            'files' => ['image' => ['placeholder']],
                        ]],
                    ],
                ]],
            ]],
        ]);

        $child = $definition->records[0]->children[0]->inline['tx_theme_list_items'][0];

        $this->assertSame(42, $child->uid);
        $this->assertSame('placeholder', $child->files['image'][0]->identifier);
    }

    #[Test]
    public function recordsCarryTheirOwnTableAndAreNestedOntoThePageDeclaringThem(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'storage',
                'doktype' => 254,
                'records' => [
                    ['identifier' => 'category-news', 'table' => 'sys_category', 'title' => 'News'],
                    ['identifier' => 'user-doe', 'table' => 'fe_users', 'username' => 'doe'],
                ],
            ]],
        ]);

        $page = $definition->records[0];
        $records = $page->children;

        $this->assertCount(2, $records);
        $this->assertSame('sys_category', $records[0]->table);
        $this->assertSame('fe_users', $records[1]->table);
        // "table" is structure under "records", exactly as it is on an inline
        // child, so it is not written as a field of the record.
        $this->assertSame(['title' => 'News'], $records[0]->values);
        // ... and "records" is not written as a field of the page either.
        $this->assertSame(['doktype' => 254], $page->values);
    }

    #[Test]
    public function recordsJoinContentAndChildrenRatherThanReplacingThem(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'home',
                'content' => [['identifier' => 'home-heading', 'CType' => 'header']],
                'records' => [['identifier' => 'home-category', 'table' => 'sys_category']],
                'children' => [['identifier' => 'about']],
            ]],
        ]);

        $tables = array_map(
            static fn(SeedRecord $record): string => $record->table,
            $definition->records[0]->children,
        );

        $this->assertSame(['tt_content', 'sys_category', 'pages'], $tables);
    }

    #[Test]
    public function aRecordTakesTheSameUidFilesAndInlineAsAnyOtherRecord(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'storage',
                'records' => [[
                    'identifier' => 'profile-doe',
                    'table' => 'tx_example_profile',
                    'uid' => 42,
                    'files' => ['image' => ['placeholder']],
                    'inline' => [
                        'contracts' => [[
                            'identifier' => 'contract-doe',
                            'table' => 'tx_example_contract',
                            'position' => 'Professor',
                        ]],
                    ],
                ]],
            ]],
        ]);

        $record = $definition->records[0]->children[0];

        $this->assertSame(42, $record->uid);
        $this->assertSame('placeholder', $record->files['image'][0]->identifier);
        $this->assertSame('tx_example_contract', $record->inline['contracts'][0]->table);
        $this->assertSame(['position' => 'Professor'], $record->inline['contracts'][0]->values);
    }

    #[Test]
    public function recordsIsAFieldEverywhereButOnAPage(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'home',
                'content' => [['identifier' => 'insert', 'CType' => 'shortcut', 'records' => 'tt_content_601']],
            ]],
        ]);

        $record = $definition->records[0]->children[0];

        // "tt_content" has a column of that name - the one the "Insert records"
        // element writes into - so on a content element it is an ordinary field
        // and has to survive as one.
        $this->assertSame(['CType' => 'shortcut', 'records' => 'tt_content_601'], $record->values);
        $this->assertSame([], $record->children);
    }

    #[Test]
    public function tableIsAFieldEverywhereButOnAnInlineChild(): void
    {
        $definition = $this->subject->parse([
            'identifier' => 'demo',
            'pages' => [[
                'identifier' => 'home',
                'content' => [['identifier' => 'a-table', 'CType' => 'table', 'table_caption' => 'Prices', 'table' => 'nope']],
            ]],
        ]);

        $record = $definition->records[0]->children[0];

        // The table of a "content" record comes from the nesting, so a "table"
        // key there is an ordinary field and has to survive as one.
        $this->assertSame('tt_content', $record->table);
        $this->assertSame(['CType' => 'table', 'table_caption' => 'Prices', 'table' => 'nope'], $record->values);
    }

    /**
     * @return \Generator<string, array{definition: mixed, code: int}>
     */
    public static function invalidDefinitions(): \Generator
    {
        yield 'not a map' => [
            'definition' => 'nope',
            'code' => 1786924803,
        ];
        yield 'no identifier' => [
            'definition' => ['pages' => []],
            'code' => 1786924804,
        ];
        yield 'pages not a list' => [
            'definition' => ['identifier' => 'demo', 'pages' => 'nope'],
            'code' => 1786924805,
        ];
        yield 'record without identifier' => [
            'definition' => ['identifier' => 'demo', 'pages' => [['title' => 'Home']]],
            'code' => 1786924807,
        ];
        yield 'duplicate identifier' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home'], ['identifier' => 'home']],
            ],
            'code' => 1786924808,
        ];
        yield 'duplicate identifier across nesting levels' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [
                    ['identifier' => 'home', 'children' => [['identifier' => 'home']]],
                ],
            ],
            'code' => 1786924808,
        ];
        yield 'uid not a positive integer' => [
            'definition' => ['identifier' => 'demo', 'pages' => [['identifier' => 'home', 'uid' => 0]]],
            'code' => 1786924809,
        ];
        yield 'field value not scalar' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home', 'title' => ['nope']]],
            ],
            'code' => 1786924813,
        ];
        yield 'file reference neither identifier nor map' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home', 'files' => ['media' => [17]]]],
            ],
            'code' => 1786924827,
        ];
        yield 'file reference map without identifier' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home', 'files' => ['media' => [['alternative' => 'Alt']]]]],
            ],
            'code' => 1786924830,
        ];
        yield 'file reference field name not a string' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [[
                    'identifier' => 'home',
                    'files' => ['media' => [['identifier' => 'hero', 7 => 'nope']]],
                ]],
            ],
            'code' => 1786924831,
        ];
        yield 'identifier carrying an underscore' => [
            'definition' => ['identifier' => 'demo', 'pages' => [['identifier' => 'home_page']]],
            'code' => 1786924833,
        ];
        yield 'identifier starting with a dash' => [
            'definition' => ['identifier' => 'demo', 'pages' => [['identifier' => '-home']]],
            'code' => 1786924833,
        ];
        yield 'inline not a map' => [
            'definition' => ['identifier' => 'demo', 'pages' => [['identifier' => 'home', 'inline' => 'nope']]],
            'code' => 1786924835,
        ];
        yield 'inline field name not a string' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home', 'inline' => [7 => []]]],
            ],
            'code' => 1786924836,
        ];
        yield 'inline field not a list of records' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home', 'inline' => ['tx_theme_list_items' => 'nope']]],
            ],
            'code' => 1786924837,
        ];
        yield 'inline child without table' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [[
                    'identifier' => 'home',
                    'inline' => ['tx_theme_list_items' => [['identifier' => 'child']]],
                ]],
            ],
            'code' => 1786924834,
        ];
        yield 'duplicate identifier between an inline child and a page' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [[
                    'identifier' => 'home',
                    'inline' => [
                        'tx_theme_list_items' => [['identifier' => 'home', 'table' => 'tx_theme_list_item']],
                    ],
                ]],
            ],
            'code' => 1786924808,
        ];
        yield 'records not a list' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home', 'records' => 'nope']],
            ],
            'code' => 1786955122,
        ];
        yield 'record without table' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [['identifier' => 'home', 'records' => [['identifier' => 'orphan']]]],
            ],
            'code' => 1786924834,
        ];
        yield 'duplicate identifier between a record and a page' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [[
                    'identifier' => 'home',
                    'records' => [['identifier' => 'home', 'table' => 'sys_category']],
                ]],
            ],
            'code' => 1786924808,
        ];
        yield 'file reference field value not scalar' => [
            'definition' => [
                'identifier' => 'demo',
                'pages' => [[
                    'identifier' => 'home',
                    'files' => ['media' => [['identifier' => 'hero', 'alternative' => ['nope']]]],
                ]],
            ],
            'code' => 1786924832,
        ];
    }

    #[DataProvider('invalidDefinitions')]
    #[Test]
    public function invalidDefinitionIsRejected(mixed $definition, int $code): void
    {
        $this->expectException(SeedingException::class);
        $this->expectExceptionCode($code);

        $this->subject->parse($definition);
    }
}
