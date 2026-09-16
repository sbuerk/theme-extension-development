<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Registers the classic static include of the theme, so an installation that
// does not use the site set can still select the theme in a sys_template
// record.
//
// This belongs in a TCA override and not in "ext_localconf.php":
// "addStaticFile()" appends an item to
// $GLOBALS['TCA']['sys_template']['columns']['include_static_file'] and is
// guarded by "is_array()" on that column - while the TCA is not built yet the
// call therefore does nothing at all, silently.
ExtensionManagementUtility::addStaticFile(
    'theme_extension_development',
    'Configuration/TypoScript/Static',
    'Theme Extension Development',
);

// The bridge to "fluid_styled_content", for a "sys_template" installation that
// has both extensions. It is the static counterpart of the site set
// "sbuerk/theme-extension-development-fsc" and reads the very same file.
//
// It is included *in addition to* the two above and has to come last, after
// both "Fluid Content Elements" and "Theme Extension Development": it clears
// the classic "tt_content" branches and declares them again from the theme's
// own file, which only settles the order if nothing writes to them afterwards.
// The order of "include_static_file" is the order of that field.
ExtensionManagementUtility::addStaticFile(
    'theme_extension_development',
    'Configuration/TypoScript/Fsc',
    'Theme Extension Development (fluid_styled_content)',
);
