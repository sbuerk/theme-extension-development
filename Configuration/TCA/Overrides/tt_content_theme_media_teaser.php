<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Text placed beside a single image, rendered through ".theme-teaser" with
// its "__media" part. Same field set as theme_hero - see that file for why
// the "images" tab is spelled out with the traditional LLL:EXT: reference.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_media_teaser.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_media_teaser.description',
        'value' => 'theme_media_teaser',
        'icon' => 'content-beside-text-img-left',
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
