<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Text and icon, rendered through ".theme-media-object": the heading, the rich
// text and the link of the element beside one icon of the set. bootstrap_package
// ships the same element as "texticon".
//
// The icon is "tx_theme_icon", the column the bullet list shows its icon with,
// and the "theme_icon" palette adds where the icon goes, what it sits on and
// how large it is ("Configuration/TCA/Overrides/tt_content.php"). The column's
// description speaks of the items of a list, so it is replaced for this type.
// The heading goes through the shared header partial, so "header_position" and
// "tx_theme_header_style" apply as they do to a text element.
//
// "bodytext" is rich text, as for the notice: the element is prose.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_text_icon.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_text_icon.description',
        'value' => 'theme_text_icon',
        'icon' => 'content-idea',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --palette--;;theme_icon,
        bodytext,
        --palette--;;theme_link,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_icon' => [
                'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon.types.theme_text_icon.description',
            ],
            'bodytext' => [
                'config' => [
                    'enableRichtext' => true,
                ],
            ],
        ],
    ],
);
