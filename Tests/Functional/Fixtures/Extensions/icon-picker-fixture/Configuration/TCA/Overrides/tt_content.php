<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Tca\IconItems;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// One column that uses the icon picker the way a content element of the theme
// or of another extension would, on every type of the table. The theme itself
// adds such a column only where an element renders the icon, so this is what
// "IconPickerFormEngineTest" compiles a form for.
ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_iconpickerfixture_icon' => [
        'label' => 'Icon',
        'config' => IconItems::selectConfig(),
    ],
]);
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_iconpickerfixture_icon');
