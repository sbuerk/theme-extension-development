<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Covers what the server sends for appearance, palette and outline switching.
 *
 * A functional test renders HTML and never executes a script, so what is
 * asserted here is deliberately the *delivered* state: the attributes the
 * document carries before any JavaScript runs, and the markup the script will
 * later operate. That is not a gap - it is the state every visitor sees first,
 * and the one a broken default would ruin. What the script then does with it
 * is the acceptance suite's part, see "Tests/Acceptance/frontend.spec.ts".
 *
 * The defaults asserted here are the shipped constants.
 * `ConfiguredAppearanceRenderingTest` renders with every constant changed.
 */
final class AppearanceRenderingTest extends AbstractFunctionalTestCase
{
    use DeliveredMarkupTrait;
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + [
                'dependencies' => [
                    'sbuerk/theme-extension-development',
                ],
            ],
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        $this->setUpFrontendRootPage(1, [], [], false);
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

    private function head(string $body): string
    {
        $matched = preg_match('#<head\b[^>]*>(.*?)</head>#s', $body, $head);
        $this->assertSame(1, $matched, 'The document has no head.');

        return $head[1];
    }

    private function settingsTrigger(\DOMXPath $xpath): \DOMElement
    {
        $triggers = $this->elementsMatching($xpath, '//button[' . $this->hasClass('theme-settings__trigger') . ']');
        $this->assertCount(1, $triggers, 'Expected exactly one display settings button.');

        return $triggers[0];
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
        $this->assertStringContainsString(
            'data-js',
            $this->head($this->render()),
            'The script that sets the "data-js" marker is not in the head.',
        );
    }

    /**
     * A stored outline has to be applied before first paint like the other
     * two settings: applied by the module script instead, every page of a
     * visitor who switched the outlines off would draw them for a moment.
     *
     * And the marker still comes first - a read that throws must not be able
     * to cost the page its "data-js".
     */
    #[Test]
    public function theNoFlashScriptAppliesAStoredOutlineAfterTheMarker(): void
    {
        $head = $this->head($this->render());

        $marker = strpos($head, "root.setAttribute('data-js', '')");
        $outline = strpos($head, "window.localStorage.getItem('theme-content-outline')");
        $this->assertIsInt($marker, 'The head script does not set the "data-js" marker.');
        $this->assertIsInt($outline, 'The head script does not read the stored outline.');
        $this->assertStringContainsString("root.setAttribute('data-theme-content-outline', outline)", $head);

        $this->assertLessThan(
            $outline,
            $marker,
            'The marker has to be set before anything reads "localStorage".',
        );
        $this->assertLessThan(
            (int)strpos($head, 'window.localStorage.getItem('),
            $marker,
            'The marker has to be set before anything reads "localStorage".',
        );
    }

    /**
     * The marker is what switches the navigation from "always expanded" to
     * "collapsible" and what reveals the settings control. It must not be in
     * the delivered markup: with no JavaScript, a collapsible navigation
     * cannot be opened and a settings control cannot apply anything.
     */
    #[Test]
    public function theScriptMarkerIsNotRenderedServerSide(): void
    {
        $this->assertStringNotContainsString('data-js', $this->htmlTag($this->render()));
    }

    /**
     * A disclosure: the button reports that it is collapsed and names the
     * element it controls, and that element exists and starts closed. An
     * "aria-controls" pointing at nothing is announced as a working control
     * by nothing and breaks nothing visible either.
     */
    #[Test]
    public function theSettingsButtonControlsAPanelThatStartsClosed(): void
    {
        $xpath = $this->deliveredDocument($this->render());
        $trigger = $this->settingsTrigger($xpath);

        $this->assertSame('button', $trigger->getAttribute('type'));
        $this->assertSame('false', $trigger->getAttribute('aria-expanded'));

        $controls = $trigger->getAttribute('aria-controls');
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9-]*$/', $controls, 'The button controls no element.');

        $panels = $this->elementsMatching($xpath, sprintf('//*[@id="%s"]', $controls));
        $this->assertCount(1, $panels, sprintf('"aria-controls" points at %d elements with the id "%s".', count($panels), $controls));
        $this->assertTrue($panels[0]->hasAttribute('hidden'), 'The panel has to be delivered closed.');
        $this->assertStringContainsString(
            'theme-settings__panel',
            $panels[0]->getAttribute('class'),
            'The button controls an element that is not its panel.',
        );
    }

    /**
     * The button shows only a cog, so its name has to be text a screen
     * reader reads out - and a translated one, not an empty span.
     */
    #[Test]
    public function theSettingsButtonHasAnAccessibleName(): void
    {
        $trigger = $this->settingsTrigger($this->deliveredDocument($this->render()));

        $this->assertSame('Display settings', trim($trigger->textContent));
    }

    /**
     * One radio per appearance and per palette, each group sharing a "name"
     * - that is what makes the arrow keys move between them - and each group
     * inside a fieldset whose legend names it. Two unnamed radio groups next
     * to each other are meaningless to a screen reader.
     */
    #[Test]
    public function everyAppearanceAndEveryPaletteIsARadioInItsOwnLabelledGroup(): void
    {
        $xpath = $this->deliveredDocument($this->render());
        $groups = [
            'theme-settings-appearance' => ['setting' => 'appearance', 'values' => ['auto', 'light', 'dark']],
            'theme-settings-palette' => ['setting' => 'palette', 'values' => ['neutral', 'ember', 'ocean', 'moss', 'violet']],
        ];

        $legends = [];
        foreach ($groups as $name => $group) {
            $radios = $this->elementsMatching($xpath, sprintf('//input[@type="radio"][@name="%s"]', $name));
            $this->assertSame(
                $group['values'],
                array_map(static fn(\DOMElement $radio): string => $radio->getAttribute('value'), $radios),
                sprintf('The "%s" group does not offer every value.', $name),
            );

            $fieldsets = [];
            foreach ($radios as $radio) {
                $this->assertSame($group['setting'], $radio->getAttribute('data-theme-setting'));
                $fieldset = $this->elementsMatching($xpath, 'ancestor::fieldset[1]', $radio);
                $this->assertCount(1, $fieldset, sprintf('A "%s" radio is not inside a fieldset.', $name));
                $fieldsets[spl_object_id($fieldset[0])] = $fieldset[0];
            }
            $this->assertCount(1, $fieldsets, sprintf('The "%s" radios are spread over several fieldsets.', $name));

            $legend = $this->elementsMatching($xpath, 'legend', reset($fieldsets));
            $this->assertCount(1, $legend, sprintf('The "%s" group has no legend.', $name));
            $legends[$name] = trim($legend[0]->textContent);
        }

        $this->assertSame(['theme-settings-appearance' => 'Appearance', 'theme-settings-palette' => 'Palette'], $legends);
    }

    /**
     * A checkbox announced as a switch - "on" and "off" rather than
     * "checked" - since flipping it applies at once, and named by its label.
     */
    #[Test]
    public function theElementOutlinesAreASwitch(): void
    {
        $xpath = $this->deliveredDocument($this->render());

        $switches = $this->elementsMatching($xpath, '//input[@data-theme-setting="content-outline"]');
        $this->assertCount(1, $switches, 'The outline cannot be switched.');
        $this->assertSame('checkbox', $switches[0]->getAttribute('type'));
        $this->assertSame('switch', $switches[0]->getAttribute('role'));

        $label = $this->elementsMatching($xpath, 'ancestor::label[1]', $switches[0]);
        $this->assertCount(1, $label, 'The outline switch has no label.');
        $this->assertSame('Element outlines', trim($label[0]->textContent));
    }

    /**
     * The controls start on the server defaults, and the server defaults are
     * handed to the script as data attributes, because that is where "Reset"
     * takes them from: once a stored choice has been applied to the html tag,
     * the tag no longer says what the site's default was.
     */
    #[Test]
    public function theServerDefaultsAreCheckedAndExposedToTheScript(): void
    {
        $xpath = $this->deliveredDocument($this->render());

        $settings = $this->elementsMatching($xpath, '//div[' . $this->hasClass('theme-settings') . ']');
        $this->assertCount(1, $settings);
        $this->assertSame('auto', $settings[0]->getAttribute('data-theme-default-appearance'));
        $this->assertSame('neutral', $settings[0]->getAttribute('data-theme-default-palette'));
        $this->assertSame('on', $settings[0]->getAttribute('data-theme-default-content-outline'));

        $checked = [];
        foreach ($this->elementsMatching($xpath, './/input[@checked]', $settings[0]) as $input) {
            $checked[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $this->assertSame(
            ['theme-settings-appearance' => 'auto', 'theme-settings-palette' => 'neutral', 'theme-settings-content-outline' => 'on'],
            $checked,
        );
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
