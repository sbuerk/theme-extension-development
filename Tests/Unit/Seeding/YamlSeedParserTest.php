<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Seeding;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
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
