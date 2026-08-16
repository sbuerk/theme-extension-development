<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Guards the parts of the compiled stylesheet that no other gate protects.
 *
 * `checkCssBuild` proves that the committed `theme.css` matches what the SCSS
 * sources compile to. It cannot notice that those sources lost something:
 * delete the dark palette and the gate stays green, because the committed file
 * still matches the build.
 *
 * What is asserted here is therefore the light/dark contract of "DESIGN.md" —
 * that both palettes ship, that the `data-theme` override exists in both
 * directions, and that a token really does carry two different values. A theme
 * silently shipping one palette looks perfectly fine until someone views it in
 * the other mode.
 */
final class StylesheetTest extends UnitTestCase
{
    private function stylesheet(): string
    {
        $file = dirname(__DIR__, 2) . '/Resources/Public/Css/theme.css';
        $this->assertFileExists($file, 'The compiled stylesheet is committed - run "runTests.sh -s buildCss".');

        return (string)file_get_contents($file);
    }

    /**
     * @return \Generator<string, array{needle: string}>
     */
    public static function requiredSelectors(): \Generator
    {
        // Compiled with "--style=compressed", so these are matched as they end
        // up in the file: no space after "@media", and the attribute selector
        // has lost the quotes around its value.
        yield 'system preference selects dark' => ['needle' => '@media(prefers-color-scheme: dark)'];
        yield 'an explicit light request opts out of it' => ['needle' => ':root:not([data-theme=light])'];
        yield 'an explicit dark request opts in' => ['needle' => ':root[data-theme=dark]'];
        yield 'reduced motion is honoured' => ['needle' => '@media(prefers-reduced-motion: reduce)'];
    }

    #[DataProvider('requiredSelectors')]
    #[Test]
    public function compiledStylesheetCarriesTheModeSelectors(string $needle): void
    {
        $this->assertStringContainsString($needle, $this->stylesheet());
    }

    #[Test]
    public function bothPalettesAreShipped(): void
    {
        $css = $this->stylesheet();

        // The same token, once per mode. Asserting a selector alone would pass
        // on an empty dark block.
        $this->assertStringContainsString('--theme-color-background: #ffffff', $css);
        $this->assertStringContainsString('--theme-color-background: #0f1319', $css);
        $this->assertStringContainsString('color-scheme:light', $css);
        $this->assertStringContainsString('color-scheme:dark', $css);
    }

    #[Test]
    public function theDarkPaletteIsEmittedForBothSelectors(): void
    {
        // Written once as a Sass mixin and emitted twice - so the dark
        // background has to appear exactly twice. One occurrence means a
        // selector lost its palette.
        $this->assertSame(2, substr_count($this->stylesheet(), '--theme-color-background: #0f1319'));
    }
}
