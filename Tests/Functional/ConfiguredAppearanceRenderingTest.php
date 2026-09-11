<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The appearance constants on values other than their defaults.
 *
 * `AppearanceRenderingTest` renders the shipped defaults, and every value it
 * asserts would also be produced by a template that hard codes "auto",
 * "neutral" and "on". Here each constant is changed, so the html tag, the
 * initially checked controls and the server defaults handed to "Reset" can
 * only be right if they are all derived from the constants.
 *
 * Rendered through the classic static include on both core versions rather
 * than through `ThemeSiteTrait`, which delivers the site set on v13, so a
 * sys_template record can carry the changed constants - and so the path the
 * settings reach the template by is shown to work without the set, which is
 * the only path there is on v12.
 */
final class ConfiguredAppearanceRenderingTest extends AbstractFunctionalTestCase
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
        // Deliberately no "dependencies": this site does not use the site set,
        // not even on v13.
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        // The changed constants are imported after the theme's own, so they
        // override them the way a site package's constants would.
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => [
                    'EXT:theme_extension_development/Configuration/TypoScript/Static/constants.typoscript',
                    'EXT:theme_extension_development/Tests/Functional/Fixtures/TypoScript/ConfiguredAppearance.constants.typoscript',
                ],
                'setup' => [
                    'EXT:theme_extension_development/Configuration/TypoScript/Static/setup.typoscript',
                ],
            ],
        );
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    #[Test]
    public function theConfiguredValuesAreRenderedOntoTheDocument(): void
    {
        $matched = preg_match('#<html\b[^>]*>#', $this->render(), $matches);
        $this->assertSame(1, $matched, 'The document has no html tag.');

        $this->assertStringContainsString('data-theme="dark"', $matches[0]);
        $this->assertStringContainsString('data-palette="ember"', $matches[0]);
        $this->assertStringContainsString('data-theme-content-outline="off"', $matches[0]);
    }

    #[Test]
    public function theConfiguredValuesAreCheckedAndExposedToTheScript(): void
    {
        $xpath = $this->deliveredDocument($this->render());

        $settings = $this->elementsMatching($xpath, '//div[' . $this->hasClass('theme-settings') . ']');
        $this->assertCount(1, $settings);
        $this->assertSame('dark', $settings[0]->getAttribute('data-theme-default-appearance'));
        $this->assertSame('ember', $settings[0]->getAttribute('data-theme-default-palette'));
        $this->assertSame('off', $settings[0]->getAttribute('data-theme-default-content-outline'));

        $checked = [];
        foreach ($this->elementsMatching($xpath, './/input[@checked]', $settings[0]) as $input) {
            $checked[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        // The outline is off, so its switch is not checked at all.
        $this->assertSame(['theme-settings-appearance' => 'dark', 'theme-settings-palette' => 'ember'], $checked);
    }
}
