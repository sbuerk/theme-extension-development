<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Covers what the server sends for appearance and palette switching.
 *
 * A functional test renders HTML and never executes a script, so what is
 * asserted here is deliberately the *delivered* state: the attributes the
 * document carries before any JavaScript runs, and the markup the script will
 * later operate. That is not a gap - it is the state every visitor sees first,
 * and the one a broken default would ruin.
 */
final class AppearanceRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
        $this->setUpThemeSite();
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    private function htmlTag(string $body): string
    {
        $matched = preg_match('#<html\b[^>]*>#', $body, $matches);
        $this->assertSame(1, $matched, 'The document has no html tag.');

        return $matches[0];
    }

    /**
     * The absence of `data-theme` is what hands the decision to the operating
     * system - `light-dark()` resolves against `color-scheme`, and the root
     * declares `light dark` until an attribute overrides it.
     *
     * Rendering `data-theme="auto"` would look like a working default and
     * match no selector at all.
     */
    #[Test]
    public function theDefaultAppearanceIsNotStampedOntoTheDocument(): void
    {
        $this->assertStringNotContainsString('data-theme=', $this->htmlTag($this->render()));
    }

    #[Test]
    public function theConfiguredPaletteIsRenderedServerSide(): void
    {
        $this->assertStringContainsString('data-palette="neutral"', $this->htmlTag($this->render()));
    }

    #[Test]
    public function theContentOutlineIsOnByDefault(): void
    {
        $this->assertStringContainsString(
            'data-theme-content-outline="on"',
            $this->htmlTag($this->render()),
        );
    }

    /**
     * The marker has to be set before the first paint, which means an inline
     * script in the head. Moved to the end of the body - which the asset
     * collector may do - a stored dark appearance paints light first, and the
     * flash is exactly what the script exists to prevent.
     */
    #[Test]
    public function theNoFlashScriptIsInlineAndInTheHead(): void
    {
        $body = $this->render();

        $matched = preg_match('#<head\b[^>]*>(.*?)</head>#s', $body, $head);
        $this->assertSame(1, $matched, 'The document has no head.');

        $this->assertStringContainsString(
            'data-js',
            $head[1],
            'The script that sets the "data-js" marker is not in the head.',
        );
    }

    /**
     * The marker is what switches the navigation from "always expanded" to
     * "collapsible" and what reveals the switcher. It must not be in the
     * delivered markup: with no JavaScript, a collapsible navigation cannot be
     * opened and a switcher cannot switch.
     */
    #[Test]
    public function theScriptMarkerIsNotRenderedServerSide(): void
    {
        $this->assertStringNotContainsString('data-js', $this->htmlTag($this->render()));
    }

    #[Test]
    public function theSwitcherOffersEveryAppearanceAndEveryPalette(): void
    {
        $body = $this->render();

        foreach (['auto', 'light', 'dark'] as $appearance) {
            $this->assertStringContainsString(
                sprintf('data-theme-appearance="%s"', $appearance),
                $body,
                sprintf('The "%s" appearance cannot be chosen.', $appearance),
            );
        }

        foreach (['neutral', 'ember', 'ocean', 'moss', 'violet'] as $palette) {
            $this->assertStringContainsString(
                sprintf('data-theme-palette="%s"', $palette),
                $body,
                sprintf('The "%s" palette cannot be chosen.', $palette),
            );
        }
    }

    /**
     * Two groups of toggle buttons on one page are meaningless to a screen
     * reader without their own names.
     */
    #[Test]
    public function eachSwitcherGroupIsLabelled(): void
    {
        $body = $this->render();

        preg_match_all('#<div\b[^>]*role="group"[^>]*>#', $body, $groups);
        $this->assertCount(2, $groups[0], 'Expected an appearance group and a palette group.');

        foreach ($groups[0] as $group) {
            $this->assertSame(
                1,
                preg_match('#aria-labelledby="([^"]+)"#', $group, $label),
                sprintf('A switcher group has no accessible name: %s', $group),
            );
            $this->assertMatchesRegularExpression(
                sprintf('#id="%s"#', preg_quote($label[1], '#')),
                $body,
                'A group points at a label id that nothing carries.',
            );
        }
    }

    #[Test]
    public function everySwitcherOptionReportsWhetherItIsActive(): void
    {
        preg_match_all('#<button\b[^>]*data-theme-(?:appearance|palette)="[^"]*"[^>]*>#', $this->render(), $options);
        $this->assertNotEmpty($options[0], 'The switcher renders no options.');

        foreach ($options[0] as $option) {
            $this->assertMatchesRegularExpression(
                '#aria-pressed="(true|false)"#',
                $option,
                sprintf('A switcher option does not report its state: %s', $option),
            );
        }
    }

    /**
     * A module is deferred by definition, so nothing else has to be arranged
     * for the script to run after parsing.
     *
     * The attribute order is not asserted: `PageRenderer::addJsFooterFile()`
     * builds `src` before `type` and `implodeAttributes()` keeps insertion
     * order, so a regex that expects `type` first passes only by accident of
     * how the tag happens to be assembled.
     */
    #[Test]
    public function theThemeScriptIsLoadedAsAModule(): void
    {
        $matched = preg_match('#<script\b[^>]*theme\.js[^>]*>#', $this->render(), $tag);
        $this->assertSame(1, $matched, 'The theme script is not included at all.');

        $this->assertStringContainsString('type="module"', $tag[0]);
    }
}
