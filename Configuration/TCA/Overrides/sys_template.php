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
