<?php

declare(strict_types=1);

use TESTS\PluginFixture\Controller\PluginController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

// Registers the controller action and the TypoScript rendering definition of
// the plugin's content element.
//
// The plugin type is passed explicitly as "CType" - the only value that does
// not trigger a deprecation on v13.4 (the fifth argument omitted, or
// "list_type", logs "Plugin subtype \"list_type\" has been deprecated...",
// #105076, which this test suite turns into a failure). Read in the installed
// core before this file was written
// (".Build/vendor/typo3/cms-extbase/Classes/Utility/ExtensionUtility.php").
//
// Unlike "tests/example-fixture"'s "Configuration/TypoScript/setup.typoscript",
// nothing here overrides what "configurePlugin()" generates
// ("tt_content.<signature> =< lib.contentElement" / "templateName = Generic").
// That is the entire reason this fixture exists: it proves the theme's own
// "lib.contentElement" and "Resources/Private/Templates/ContentElements/
// Generic.html" - not a fixture-provided rendering definition - are what make
// a third-party Extbase plugin render at all once "fluid_styled_content" is
// out of the picture.
//
// "Configuration/TCA/Overrides/tt_content.php" then takes no plugin type at
// all: ExtensionUtility::registerPlugin() reads it back from what this file
// registered, and "ext_localconf.php" is loaded before the TCA overrides.
ExtensionUtility::configurePlugin(
    'TestsPluginFixture',
    'Plugin',
    [
        PluginController::class => 'index',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);
