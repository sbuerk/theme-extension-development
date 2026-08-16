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
 * delete the dark half of a colour and the gate stays green, because the
 * committed file still matches the build.
 *
 * What is asserted here is therefore the appearance contract of "DESIGN.md" -
 * that both appearances ship for every colour, that the `data-theme` override
 * exists in both directions, and that a palette varies accents only. A theme
 * silently shipping one appearance looks perfectly fine until someone views it
 * in the other.
 */
final class StylesheetTest extends UnitTestCase
{
    /**
     * The tokens a palette is allowed to override.
     *
     * Everything else - neutrals, semantic colour, spacing, typography - is
     * shared, and that is what keeps a palette to a single block.
     */
    private const PALETTE_TOKENS = [
        '--theme-color-primary',
        '--theme-color-primary-hover',
        '--theme-color-on-primary',
        '--theme-color-secondary',
        '--theme-color-secondary-hover',
        '--theme-color-on-secondary',
        '--theme-focus-ring-color',
    ];

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
        yield 'an explicit light request forces the light appearance' => ['needle' => ':root[data-theme=light]{color-scheme:light}'];
        yield 'an explicit dark request forces the dark appearance' => ['needle' => ':root[data-theme=dark]{color-scheme:dark}'];
        yield 'reduced motion is honoured' => ['needle' => '@media(prefers-reduced-motion: reduce)'];
    }

    #[DataProvider('requiredSelectors')]
    #[Test]
    public function compiledStylesheetCarriesTheAppearanceSelectors(string $needle): void
    {
        $this->assertStringContainsString($needle, $this->stylesheet());
    }

    /**
     * Without `color-scheme: light dark` on the root, the used colour scheme is
     * light and the second argument of every `light-dark()` is unreachable -
     * the whole dark appearance silently disappears while every other
     * assertion here still passes.
     */
    #[Test]
    public function bothAppearancesAreReachable(): void
    {
        $this->assertStringContainsString('color-scheme:light dark', $this->stylesheet());
    }

    /**
     * @return \Generator<string, array{token: string, light: string, dark: string}>
     */
    public static function appearanceCarryingTokens(): \Generator
    {
        yield 'background' => ['token' => '--theme-color-background', 'light' => '#ffffff', 'dark' => '#0f1319'];
        yield 'surface' => ['token' => '--theme-color-surface', 'light' => '#f4f6fa', 'dark' => '#161c25'];
        yield 'primary text' => ['token' => '--theme-color-text-primary', 'light' => '#14181f', 'dark' => '#e9edf4'];
        yield 'primary accent' => ['token' => '--theme-color-primary', 'light' => '#0b57d0', 'dark' => '#82abff'];
        yield 'danger' => ['token' => '--theme-color-danger', 'light' => '#b3261e', 'dark' => '#ff8a80'];
        yield 'danger surface' => ['token' => '--theme-color-danger-surface', 'light' => '#fbeae9', 'dark' => '#2e1717'];
    }

    #[DataProvider('appearanceCarryingTokens')]
    #[Test]
    public function aColourTokenCarriesBothAppearancesInOneDeclaration(string $token, string $light, string $dark): void
    {
        $this->assertStringContainsString(
            sprintf('%s: light-dark(%s, %s)', $token, $light, $dark),
            $this->stylesheet(),
        );
    }

    /**
     * The architectural invariant behind "light-dark()": one declaration per
     * token, carrying both appearances.
     *
     * Reintroducing a duplicated palette - a second block behind
     * `prefers-color-scheme` or behind the attribute selector - makes this
     * count rise, and it is exactly the drift the notation exists to prevent.
     */
    #[Test]
    public function aNeutralTokenIsDeclaredExactlyOnce(): void
    {
        $css = $this->stylesheet();

        $this->assertSame(1, substr_count($css, '--theme-color-background:'));
        $this->assertSame(1, substr_count($css, '--theme-color-text-primary:'));
        $this->assertSame(1, substr_count($css, '--theme-color-border:'));
    }

    /**
     * @return \Generator<string, array{palette: string}>
     */
    public static function alternatePalettes(): \Generator
    {
        yield 'ember' => ['palette' => 'ember'];
        yield 'ocean' => ['palette' => 'ocean'];
        yield 'moss' => ['palette' => 'moss'];
        yield 'violet' => ['palette' => 'violet'];
    }

    #[DataProvider('alternatePalettes')]
    #[Test]
    public function anAlternatePaletteShipsAndOverridesTheAccent(string $palette): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('/:root\[data-palette=%s\]\{[^}]*--theme-color-primary: light-dark\(/', preg_quote($palette, '/')),
            $this->stylesheet(),
        );
    }

    /**
     * A palette that restates a neutral stops being one block and starts being
     * a second theme, which is how the declaration count multiplies again.
     */
    #[DataProvider('alternatePalettes')]
    #[Test]
    public function anAlternatePaletteVariesAccentsOnly(string $palette): void
    {
        $matched = preg_match(
            sprintf('/:root\[data-palette=%s\]\{([^}]*)\}/', preg_quote($palette, '/')),
            $this->stylesheet(),
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('The "%s" palette block was not found.', $palette));

        preg_match_all('/(--[a-z0-9-]+):/', $matches[1], $declared);

        foreach ($declared[1] as $token) {
            $this->assertContains(
                $token,
                self::PALETTE_TOKENS,
                sprintf('The "%s" palette overrides "%s", which is not an accent token.', $palette, $token),
            );
        }
    }

    /**
     * "light-dark()" takes colours, not shadows, so the focus ring is declared
     * as a colour plus a composite built around it. Collapsing the two back
     * into one declaration is what breaks the dark appearance of the ring.
     */
    #[Test]
    public function theFocusRingIsDecomposedIntoAColourAndAShadow(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString('--theme-focus-ring-color: light-dark(', $css);
        $this->assertStringContainsString('--theme-focus-ring: 0 0 0 3px var(--theme-focus-ring-color)', $css);
    }
}
