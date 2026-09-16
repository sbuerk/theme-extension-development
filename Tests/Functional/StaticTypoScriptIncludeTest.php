<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * Both static includes the extension registers: the theme itself, and the
     * bridge to `fluid_styled_content`.
     *
     * @return \Generator<string, array{path: string}>
     */
    public static function registeredStaticFiles(): \Generator
    {
        yield 'the theme' => [
            'path' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
        ];
        yield 'the fluid_styled_content bridge' => [
            'path' => 'EXT:theme_extension_development/Configuration/TypoScript/Fsc',
        ];
    }

    #[DataProvider('registeredStaticFiles')]
    #[Test]
    public function staticFileIsRegisteredForSysTemplateRecords(string $path): void
    {
        $items = $GLOBALS['TCA']['sys_template']['columns']['include_static_file']['config']['items'] ?? [];

        $values = array_map(
            static fn(array $item): string => (string)($item['value'] ?? ''),
            $items,
        );

        $this->assertContains(
            $path,
            $values,
            'A static TypoScript include of the theme is not registered. It is added in '
            . '"Configuration/TCA/Overrides/sys_template.php"; moving that call to "ext_localconf.php" '
            . 'makes it a silent no-op.',
        );
    }

    /**
     * The bridge is selectable in the backend only through this registration.
     * `FluidStyledContentBridgeTest` writes the literal path into
     * `include_static_file`, and `SysTemplateTreeBuilder` resolves that without
     * consulting the TCA items at all - so dropping the `addStaticFile()` call
     * would leave every other gate green while the bridge became unselectable.
     *
     * @param string $path
     */
    #[DataProvider('registeredStaticFiles')]
    #[Test]
    public function staticFileDirectoryProvidesBothTypoScriptFiles(string $path): void
    {
        $directory = dirname(__DIR__, 2) . '/' . substr($path, strlen('EXT:theme_extension_development/')) . '/';

        // The core appends the file name to the registered path, so a missing
        // file means the include resolves to nothing rather than failing.
        $this->assertFileExists($directory . 'setup.typoscript');
        $this->assertFileExists($directory . 'constants.typoscript');
    }
}
