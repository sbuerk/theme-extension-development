<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// The hero without media - heading, text and actions only. No "image" field
// and no "images" tab at all, unlike theme_hero and theme_hero_small.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero_text_only.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero_text_only.description',
        'value' => 'theme_hero_text_only',
        'icon' => 'content-header',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        bodytext,
        --palette--;;theme_link,
    ',
);
