<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13;

use SBUERK\ThemeExtensionDevelopment\Tests\Functional\ThemeDeliveryInterface;

/**
 * TYPO3 v13 delivery of the theme in functional tests: the site set, and no
 * `sys_template` record.
 *
 * The set dependency goes into the site array rather than into the `additional`
 * argument of `writeSiteConfiguration()`. That argument is silently dropped by
 * `sbuerk/typo3-site-based-test-trait`: its merge takes `$site` instead of
 * `$additional`, and `$configuration` already is `$site`.
 *
 * @todo Move this back into "additional:" once
 *       https://github.com/sbuerk/typo3-site-based-test-trait/issues/25
 *       is fixed and the constraint here requires that version.
 *
 * The absence of a `sys_template` record is not an economy, it is a
 * requirement: the record would be written with `clear = 3` and would discard
 * the AST the set had built. See {@see ThemeDeliveryInterface}.
 *
 * The class is plain `final`. Readonly classes are PHP 8.2 and this branch
 * supports PHP 8.1 for TYPO3 v12; there is no state to declare `readonly` here
 * anyway. See `docs/architecture/class-design.md`.
 */
final class ThemeDelivery implements ThemeDeliveryInterface
{
    private const SET = 'sbuerk/theme-extension-development';

    public function siteConfiguration(): array
    {
        return [
            'dependencies' => [
                self::SET,
            ],
        ];
    }

    public function templateValues(): array
    {
        return [];
    }

    public function createsSysTemplateRecord(): bool
    {
        return false;
    }
}
