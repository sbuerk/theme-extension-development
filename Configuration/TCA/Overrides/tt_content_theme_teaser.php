<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// A short text teaser without media, rendered through ".theme-teaser" without
// its "__media" part. The plain "header" field is used rather than the
// "headers" palette: header_layout, header_position and date have no meaning
// for a plain text teaser and would only clutter the form.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_teaser.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_teaser.description',
        'value' => 'theme_teaser',
        'icon' => 'content-text-teaser',
        'group' => 'theme',
    ],
    '
        header,
        bodytext,
        --palette--;;theme_link,
    ',
);
