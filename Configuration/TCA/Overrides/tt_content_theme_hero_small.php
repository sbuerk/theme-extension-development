<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// The reduced hero variant, same field set as theme_hero - see that file for
// why the "images" tab is spelled out with the traditional LLL:EXT: reference
// rather than camino's "core.form.tabs:images" shorthand.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero_small.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero_small.description',
        'value' => 'theme_hero_small',
        'icon' => 'content-header',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        bodytext,
        --palette--;;theme_link,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:images,
        image,
    ',
);
