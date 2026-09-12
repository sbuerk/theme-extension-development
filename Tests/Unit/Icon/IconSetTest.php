<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Icon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class IconSetTest extends UnitTestCase
{
    private const DIRECTORY = __DIR__ . '/../../../Resources/Public/Icons/FontAwesome/Solid';

    #[Test]
    public function theNamesAreEveryShippedFileSortedAndWithoutExtension(): void
    {
        $files = array_values(array_diff(scandir(self::DIRECTORY) ?: [], ['.', '..']));
        $expected = array_map(static fn(string $file): string => basename($file, '.svg'), $files);
        sort($expected, SORT_STRING);

        $names = (new IconSet())->names();

        $this->assertNotSame([], $names, 'No icon was found - the set is missing, run "runTests.sh -s buildIcons".');
        $this->assertSame($expected, $names);
    }

    /**
     * The name is what an editor will store and a template will write, so
     * every one the set brings has to pass the validation the ViewHelper
     * applies - a file the pattern rejects would be an icon nobody can use.
     */
    #[Test]
    public function everyShippedNameIsAValidName(): void
    {
        $invalid = array_values(array_filter(
            (new IconSet())->names(),
            static fn(string $name): bool => preg_match(IconSet::NAME_PATTERN, $name) !== 1,
        ));

        $this->assertSame([], $invalid);
    }

    /**
     * The attribution comment travels with the icon into the page: the licence
     * asks not to remove it, and a public page shares the icons.
     */
    #[Test]
    public function theMarkupIsTheFileAsShippedWithItsAttribution(): void
    {
        $file = (string)file_get_contents(self::DIRECTORY . '/circle-info.svg');

        $markup = (new IconSet())->markup('circle-info');

        $this->assertSame(trim($file), $markup);
        $this->assertStringContainsString('<!--! Font Awesome Free ', $markup);
        $this->assertStringContainsString('(Icons: CC BY 4.0', $markup);
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">', $markup);
        $this->assertStringContainsString('<path fill="currentColor" d="', $markup);
    }

    #[Test]
    public function theNamesComeFromTheDirectoryTheSetIsGiven(): void
    {
        $this->assertSame(['xml-declaration'], (new IconSet(__DIR__ . '/Fixtures/Broken'))->names());
    }

    /**
     * The ViewHelper inserts its attributes right after "<svg", so a file with
     * anything in front of its root element - here an XML declaration, which a
     * file from another source would carry - is refused rather than rendered
     * broken.
     */
    #[Test]
    public function aFileThatDoesNotStartAsAnSvgElementIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1789218003);

        (new IconSet(__DIR__ . '/Fixtures/Broken'))->markup('xml-declaration');
    }

    /**
     * @return \Generator<string, array{name: string}>
     */
    public static function malformedNames(): \Generator
    {
        yield 'empty' => ['name' => ''];
        yield 'capitals' => ['name' => 'Circle-Info'];
        yield 'with the extension' => ['name' => 'circle-info.svg'];
        yield 'a path out of the set' => ['name' => '../LICENSE'];
        yield 'a space' => ['name' => 'circle info'];
        yield 'a trailing line break' => ['name' => "circle-info\n"];
    }

    #[DataProvider('malformedNames')]
    #[Test]
    public function aMalformedNameIsRejectedBeforeItBecomesAPath(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1789218001);

        (new IconSet())->markup($name);
    }

    #[Test]
    public function aNameThatIsNotInTheSetIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1789218002);

        (new IconSet())->markup('no-such-icon');
    }
}
