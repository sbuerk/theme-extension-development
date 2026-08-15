<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

/**
 * The classic static include of the theme is registered.
 *
 * This is the fallback for installations that do not use the site set, and it
 * is registered from "Configuration/TCA/Overrides/sys_template.php" rather than
 * from "ext_localconf.php" - `addStaticFile()` appends to the TCA of
 * `sys_template` and is guarded by `is_array()` on that column, so calling it
 * before the TCA exists does nothing at all, without any error.
 *
 * A test is therefore the only thing standing between "registered" and
 * "silently absent".
 */
final class StaticTypoScriptIncludeTest extends AbstractFunctionalTestCase
{
    private const EXPECTED_VALUE = 'EXT:theme_extension_development/Configuration/TypoScript/Static';

    #[Test]
    public function staticFileIsRegisteredForSysTemplateRecords(): void
    {
        $items = $GLOBALS['TCA']['sys_template']['columns']['include_static_file']['config']['items'] ?? [];

        $values = array_map(
            static fn(array $item): string => (string)($item['value'] ?? ''),
            $items,
        );

        $this->assertContains(
            self::EXPECTED_VALUE,
            $values,
            'The static TypoScript include of the theme is not registered. It is added in '
            . '"Configuration/TCA/Overrides/sys_template.php"; moving that call to "ext_localconf.php" '
            . 'makes it a silent no-op.',
        );
    }

    #[Test]
    public function staticFileDirectoryProvidesBothTypoScriptFiles(): void
    {
        $directory = dirname(__DIR__, 2) . '/Configuration/TypoScript/Static/';

        // The core appends the file name to the registered path, so a missing
        // file means the include resolves to nothing rather than failing.
        $this->assertFileExists($directory . 'setup.typoscript');
        $this->assertFileExists($directory . 'constants.typoscript');
    }
}
