<?php

declare(strict_types=1);

use TESTS\ExampleFixture\Controller\HelloController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

// Registers the controller actions of the plugin and the TypoScript rendering
// definition of its content element.
//
// The plugin type is passed explicitly, and it has to be: with the fifth
// argument omitted "configurePlugin()" defaults to "list_type" and triggers
// "Plugin subtype \"list_type\" has been deprecated..." (#105076), which this
// test suite turns into a failure. Naming "CType" is what avoids it.
//
// "Configuration/TCA/Overrides/tt_content.php" then takes no plugin type at
// all: ExtensionUtility::registerPlugin() reads it back from what this file
// registered, and "ext_localconf.php" is loaded before the TCA overrides.
ExtensionUtility::configurePlugin(
    'TestsExampleFixture',
    'Hello',
    [
        HelloController::class => 'index',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);
