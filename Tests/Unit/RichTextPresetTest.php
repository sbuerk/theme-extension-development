<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Every class the rich text preset of the theme can write is styled.
 *
 * `Configuration/RTE/Theme.yaml` exists because the core preset writes
 * Bootstrap's classes - `text-center`, `lead`, `text-muted` - which this theme
 * does not style: an editor chooses a style, the page does not change, and
 * nothing reports it. So each class the preset offers, as a style or as an
 * alignment, has to be a selector of the compiled stylesheet, and each style
 * without a class has to name an element the element baseline styles.
 *
 * This reads the preset as written. What the core makes of it - the imports
 * merged in, the page TSconfig applied - is `Functional/RichTextPresetTest`.
 * The one thing about the imports asserted here is that none of them can add
 * a style: the core `Default.yaml`, `Full.yaml` and `Minimal.yaml` carry the
 * Bootstrap ones, and the loader appends a list of an imported file to the
 * list of the importing one.
 */
final class RichTextPresetTest extends UnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function preset(): array
    {
        $preset = Yaml::parseFile(dirname(__DIR__, 2) . '/Configuration/RTE/Theme.yaml');
        self::assertIsArray($preset);

        return $preset;
    }

    private function stylesheet(): string
    {
        $file = dirname(__DIR__, 2) . '/Resources/Public/Css/theme.css';
        $this->assertFileExists($file, 'The compiled stylesheet is committed - run "runTests.sh -s buildCss".');

        return (string)file_get_contents($file);
    }

    #[Test]
    public function thePresetImportsNoCoreFileThatCarriesStyles(): void
    {
        $this->assertSame(
            [
                'EXT:rte_ckeditor/Configuration/RTE/Processing.yaml',
                'EXT:rte_ckeditor/Configuration/RTE/Editor/Base.yaml',
                'EXT:rte_ckeditor/Configuration/RTE/Editor/Plugins.yaml',
            ],
            array_column(self::preset()['imports'] ?? [], 'resource'),
        );
    }

    /**
     * @return \Generator<string, array{class: string}>
     */
    public static function offeredClasses(): \Generator
    {
        $config = self::preset()['editor']['config'] ?? [];
        foreach ($config['style']['definitions'] ?? [] as $definition) {
            foreach ($definition['classes'] ?? [] as $class) {
                yield 'style ' . $class => ['class' => $class];
            }
        }
        foreach ($config['alignment']['options'] ?? [] as $option) {
            yield 'alignment ' . $option['className'] => ['class' => $option['className']];
        }
    }

    #[Test]
    public function thePresetOffersTheThemeStylesAndAlignments(): void
    {
        // Two text roles and the four alignments rte_ckeditor always offers.
        // An empty list would let the test below pass on nothing.
        $this->assertSame(
            ['theme-lead', 'theme-eyebrow', 'theme-text--start', 'theme-text--center', 'theme-text--end', 'theme-text--justify'],
            array_column(iterator_to_array(self::offeredClasses(), false), 'class'),
        );
    }

    #[DataProvider('offeredClasses')]
    #[Test]
    public function aClassThePresetOffersIsStyled(string $class): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('/\.%s(?![\w-])/', preg_quote($class, '/')),
            $this->stylesheet(),
            sprintf('The preset offers "%s", and no rule of the stylesheet matches it.', $class),
        );
    }

    /**
     * @return \Generator<string, array{element: string}>
     */
    public static function elementsWithoutClass(): \Generator
    {
        foreach (self::preset()['editor']['config']['style']['definitions'] ?? [] as $definition) {
            if (($definition['classes'] ?? []) === []) {
                yield $definition['element'] => ['element' => $definition['element']];
            }
        }
    }

    /**
     * A style without a class writes the bare element, which only the element
     * baseline can style - by its tag, at the start of a rule of its own.
     */
    #[DataProvider('elementsWithoutClass')]
    #[Test]
    public function anElementThePresetOffersIsStyledByItsTag(string $element): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('/(?:^|\})%s\{/', preg_quote($element, '/')),
            $this->stylesheet(),
            sprintf('The preset offers "<%s>", and the element baseline has no rule for it.', $element),
        );
    }
}
