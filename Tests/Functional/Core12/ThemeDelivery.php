<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core12;

use SBUERK\ThemeExtensionDevelopment\Tests\Functional\ThemeDeliveryInterface;

/**
 * TYPO3 v12 delivery of the theme in functional tests: a `sys_template` record
 * selecting the static include.
 *
 * Site sets are a TYPO3 v13.1 feature (#103437). On v12 the `dependencies` key
 * of a site configuration is read by nothing, so a test arranging it would
 * exercise an empty page and still look like it had arranged something. The
 * classic path is the only one there, and it is the one an installation on v12
 * uses as well: a `sys_template` record whose "Include static (from
 * extensions)" field carries the directory registered by
 * `Configuration/TCA/Overrides/sys_template.php`.
 *
 * The value is the directory, without a trailing slash and without a file name.
 * `SysTemplateTreeBuilder::handleSingleIncludeStaticFile()` appends
 * `constants.typoscript` and `setup.typoscript` to it itself, which is also why
 * the same directory has to hold both files.
 *
 * The class is plain `final`. Readonly classes are PHP 8.2 and this branch
 * supports PHP 8.1 for TYPO3 v12; there is no state to declare `readonly` here
 * anyway. See `docs/architecture/class-design.md`.
 *
 * @see ThemeDeliveryInterface for why the class describes an arrangement
 *      instead of performing it
 */
final class ThemeDelivery implements ThemeDeliveryInterface
{
    private const STATIC_INCLUDE = 'EXT:theme_extension_development/Configuration/TypoScript/Static';

    public function siteConfiguration(): array
    {
        // Nothing. A `dependencies` key here would be inert on v12 and would
        // suggest a delivery that does not happen.
        return [];
    }

    public function templateValues(): array
    {
        return [
            'include_static_file' => self::STATIC_INCLUDE,
        ];
    }

    public function createsSysTemplateRecord(): bool
    {
        return true;
    }
}
