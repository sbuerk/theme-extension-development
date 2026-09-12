<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Icon;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconCatalogue;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class IconCatalogueTest extends UnitTestCase
{
    private const FIXTURES = __DIR__ . '/Fixtures/Catalogue';

    private function fixtureCatalogue(string $categories = 'categories.yml'): IconCatalogue
    {
        return new IconCatalogue(new IconSet(self::FIXTURES . '/Icons'), self::FIXTURES . '/' . $categories);
    }

    /**
     * @return array<string, string> The group of every icon offered, by name.
     */
    private function groupsByIcon(IconCatalogue $catalogue): array
    {
        return array_column($catalogue->icons(), 'group', 'name');
    }

    #[Test]
    public function anIconIsPlacedInTheFirstCategoryThatListsIt(): void
    {
        $groups = $this->groupsByIcon($this->fixtureCatalogue());

        $this->assertSame('first', $groups['alpha'] ?? null);
        $this->assertSame('second', $groups['beta'] ?? null);
    }

    /**
     * An alias of a renamed icon is its own file with the glyph of the icon it
     * stands for. It would repeat that glyph in the list.
     */
    #[Test]
    public function aNameNoCategoryListsIsLeftOutWhenItDrawsTheGlyphOfOneThatIsListed(): void
    {
        $this->assertArrayNotHasKey('delta', $this->groupsByIcon($this->fixtureCatalogue()));
    }

    #[Test]
    public function aNameNoCategoryListsThatDrawsItsOwnGlyphIsKeptInTheFallbackGroup(): void
    {
        $this->assertSame(
            ['alpha' => 'first', 'beta' => 'second', 'gamma' => IconCatalogue::UNCATEGORISED],
            $this->groupsByIcon($this->fixtureCatalogue()),
        );
    }

    /**
     * Every category of the file, in its order, the fallback group last -
     * whether an icon is placed in it or not: the core leaves an empty group
     * out of the list, so the groups need no icon file read.
     */
    #[Test]
    public function theGroupsAreEveryCategoryInTheOrderOfTheFileAndTheFallbackLast(): void
    {
        $this->assertSame(
            [
                'second' => 'Second',
                'first' => 'First',
                'empty' => 'Empty',
                IconCatalogue::UNCATEGORISED => IconCatalogue::UNCATEGORISED_LABEL,
            ],
            $this->fixtureCatalogue()->groups(),
        );
    }

    #[Test]
    public function theShippedSetOffersEveryIconOnceWithoutTheAliasesOfRenamedIcons(): void
    {
        $catalogue = new IconCatalogue();
        $groups = $this->groupsByIcon($catalogue);
        $shipped = (new IconSet())->names();

        $this->assertArrayHasKey('circle-arrow-right', $groups);
        $this->assertArrayNotHasKey('arrow-circle-right', $groups, 'An alias of a renamed icon is offered.');
        $this->assertLessThan(count($shipped), count($groups));
        $this->assertSame([], array_values(array_diff(array_keys($groups), $shipped)), 'A name is offered that the set does not ship.');

        $undeclared = array_values(array_diff(array_unique(array_values($groups)), array_keys($catalogue->groups())));
        $this->assertSame([], $undeclared, 'An icon is placed in a group the field does not declare.');
    }

    #[Test]
    public function theFingerprintFollowsTheSetAndItsCategories(): void
    {
        $this->assertSame($this->fixtureCatalogue()->fingerprint(), $this->fixtureCatalogue()->fingerprint());
        $this->assertNotSame($this->fixtureCatalogue()->fingerprint(), $this->fixtureCatalogue('no-categories.yml')->fingerprint());
        $this->assertNotSame($this->fixtureCatalogue()->fingerprint(), (new IconCatalogue())->fingerprint());
    }

    #[Test]
    public function aCategoryFileWithoutCategoriesIsRefused(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1789300001);

        $this->fixtureCatalogue('no-categories.yml')->groups();
    }
}
