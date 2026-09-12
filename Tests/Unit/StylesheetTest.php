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
 * in the other. Next to it, that the file survives being inlined: it starts
 * without a byte order mark.
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
     * dart-sass starts compressed output that contains non-ASCII characters
     * with a byte order mark, and this stylesheet does - the typographic
     * quotes. Linked, the mark is consumed by the
     * decoder. Inlined or concatenated - `includeCSS.*.inline`, critical CSS,
     * a shadow root - it becomes part of the first selector, `U+FEFF:root`
     * matches nothing, and the whole token block is gone. Built with
     * `--no-charset`, see "package.json".
     */
    #[Test]
    public function compiledStylesheetStartsWithoutAByteOrderMark(): void
    {
        $this->assertStringStartsNotWith("\u{FEFF}", $this->stylesheet());
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
     * @return \Generator<string, array{appearance: 'light'|'dark', background: string}>
     */
    public static function controlBoundaryBackgrounds(): \Generator
    {
        foreach (['light', 'dark'] as $appearance) {
            foreach (['--theme-color-background', '--theme-color-surface', '--theme-color-surface-raised'] as $background) {
                yield sprintf('%s %s', $appearance, $background) => ['appearance' => $appearance, 'background' => $background];
            }
        }
    }

    /**
     * The border of a control is the only thing that identifies an empty text
     * field, and WCAG 1.4.11 holds it to 3:1 against the colours next to it.
     * `--theme-color-border-strong` is that border, so it has to reach 3:1 on
     * every background a control sits on, in both appearances - including the
     * raised surface, which is the background of a control itself and, in
     * dark, the lightest of the three.
     *
     * axe checks text contrast only, so nothing else reports a border that
     * falls below it. Computed from the sources, where the two values of each
     * token are still readable, with the formula of WCAG 2.2.
     *
     * @param 'light'|'dark' $appearance
     */
    #[DataProvider('controlBoundaryBackgrounds')]
    #[Test]
    public function theControlBorderReachesThreeToOneOnEveryBackground(string $appearance, string $background): void
    {
        $border = $this->tokenColour('--theme-color-border-strong', $appearance);
        $ground = $this->tokenColour($background, $appearance);

        $ratio = $this->contrastRatio($border, $ground);

        $this->assertGreaterThanOrEqual(
            3.0,
            $ratio,
            sprintf(
                '--theme-color-border-strong %s reaches only %.2f:1 on %s %s in %s, WCAG 1.4.11 needs 3:1.',
                $border,
                $ratio,
                $background,
                $ground,
                $appearance,
            ),
        );
    }

    /**
     * One half of a `light-dark()` token as declared in "_tokens.scss".
     *
     * @param 'light'|'dark' $appearance
     */
    private function tokenColour(string $token, string $appearance): string
    {
        $file = dirname(__DIR__, 2) . '/Resources/Private/Scss/abstracts/_tokens.scss';
        $scss = (string)preg_replace('#//.*$#m', '', (string)file_get_contents($file));

        $matched = preg_match_all(
            '/(?:^|[;{\s])' . preg_quote($token, '/') . '\s*:\s*light-dark\(\s*(#[0-9a-f]{6})\s*,\s*(#[0-9a-f]{6})\s*\)\s*;/i',
            $scss,
            $values,
        );
        $this->assertSame(1, $matched, sprintf('"%s" is not declared exactly once as a light-dark() pair of hex colours.', $token));

        return strtolower($values[$appearance === 'light' ? 1 : 2][0]);
    }

    /**
     * The contrast ratio of WCAG 2.2: relative luminance from the linearised
     * sRGB channels, then (L1 + 0.05) / (L2 + 0.05) with the lighter first.
     */
    private function contrastRatio(string $first, string $second): float
    {
        $luminance = static function (string $hex): float {
            $channels = array_map(
                static function (string $channel): float {
                    $value = hexdec($channel) / 255;

                    return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
                },
                str_split(ltrim($hex, '#'), 2),
            );

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };

        $lighter = max($luminance($first), $luminance($second));
        $darker = min($luminance($first), $luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
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
