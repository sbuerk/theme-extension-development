<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Every site setting of the theme is declared twice, and the two agree.
 *
 * A site that takes the theme through the site set gets the settings; a site
 * on the classic static include gets TypoScript constants of the same names.
 * The core overrules a set constant with a setting of the same key, which is
 * what lets one template serve both paths - see
 * `docs/architecture/site-settings.md`.
 *
 * Nothing in the core checks that the two halves say the same thing. A
 * default that drifted would be invisible on the path that was tested and
 * wrong on the other one, which is exactly the failure this file exists for.
 */
final class SiteSettingsTest extends UnitTestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const DEFINITIONS = self::ROOT . '/Configuration/Sets/ThemeExtensionDevelopment/settings.definitions.yaml';
    private const CONSTANTS = self::ROOT . '/Configuration/TypoScript/constants.typoscript';

    /**
     * The settings, by key.
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        $parsed = Yaml::parseFile(self::DEFINITIONS);
        $this->assertIsArray($parsed);
        $settings = $parsed['settings'] ?? null;
        $this->assertIsArray($settings, 'The definitions file has no "settings" key.');
        $this->assertNotSame([], $settings, 'The definitions file declares no setting.');

        /** @var array<string, array<string, mixed>> $settings */
        return $settings;
    }

    /**
     * The constants of `constants.typoscript`, flattened to their full paths.
     *
     * A small parser rather than the core's: this is a unit test, the file is
     * a handful of nested blocks and comments, and pulling in the TypoScript
     * include tree would test the core rather than the file.
     *
     * @return array<string, string>
     */
    private function constants(): array
    {
        $constants = [];
        $path = [];
        foreach (explode("\n", (string)file_get_contents(self::CONSTANTS)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_ends_with($line, '{')) {
                $path[] = trim(substr($line, 0, -1));
                continue;
            }
            if ($line === '}') {
                array_pop($path);
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $constants[implode('.', [...$path, trim($key)])] = trim($value);
        }
        $this->assertNotSame([], $constants, 'No constant was parsed - the file or this parser is wrong.');

        return $constants;
    }

    #[Test]
    public function everySettingIsAlsoAConstantWithTheSameDefault(): void
    {
        $constants = $this->constants();
        $missing = [];
        $different = [];
        foreach ($this->definitions() as $key => $definition) {
            if (!array_key_exists($key, $constants)) {
                $missing[] = $key;
                continue;
            }
            $default = (string)($definition['default'] ?? '');
            if ($constants[$key] !== $default) {
                $different[] = sprintf('%s: setting "%s", constant "%s"', $key, $default, $constants[$key]);
            }
        }

        $this->assertSame([], $missing, 'These settings have no TypoScript constant, so a site on the static include never gets them: ' . implode(', ', $missing));
        $this->assertSame([], $different, 'These settings and their constants default to different values: ' . implode('; ', $different));
    }

    /**
     * A setting an editor picks from a list is an "enum", so the settings
     * editor offers the values instead of a free text field - a header
     * variant typed by hand is a value the switch falls back from, silently.
     */
    #[Test]
    public function theHeaderVariantOffersExactlyTheVariantsThatShip(): void
    {
        $definition = $this->definitions()['theme.header.variant'] ?? [];
        $enum = $definition['enum'] ?? null;

        $this->assertIsArray($enum, '"theme.header.variant" is not an enum, so the editor is a free text field.');
        $this->assertSame(['simple', 'centred', 'actions', 'two-tier'], array_keys($enum));

        foreach (array_keys($enum) as $variant) {
            $partial = self::ROOT . '/Resources/Private/Partials/Page/Header/'
                . str_replace(' ', '', ucwords(str_replace('-', ' ', (string)$variant))) . '.html';
            $this->assertFileExists($partial, sprintf('The header variant "%s" has no partial.', $variant));
        }
    }

    /**
     * Every label an integrator reads is a literal.
     *
     * A set may carry a "labels.xlf", and the label and the description of a
     * setting are taken from it on both cores - but the options of an enum
     * only from TYPO3 v14.2 on. On v13.4 the enum is handed over as written,
     * so a "LLL:" reference inside it reaches the settings editor spelled
     * out. The settings here are enums, so all of their text stays literal -
     * see "docs/architecture/site-settings.md".
     */
    #[Test]
    public function noLabelIsATranslationReference(): void
    {
        $references = [];
        foreach ($this->definitions() as $key => $definition) {
            $texts = [$definition['label'] ?? '', $definition['description'] ?? ''];
            foreach ((array)($definition['enum'] ?? []) as $label) {
                $texts[] = $label;
            }
            foreach ($texts as $text) {
                if (is_string($text) && str_starts_with($text, 'LLL:')) {
                    $references[] = $key;
                }
            }
        }

        $this->assertSame([], $references, 'These settings carry a translation reference a TYPO3 v13.4 settings editor would print verbatim: ' . implode(', ', $references));
    }
}
