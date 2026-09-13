<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A call to action - a band or a box with a heading, a short text, an
// optional large icon and up to two links - rendered through ".theme-cta"
// (docs/development/component-library.md).
//
// The tone and the width come first, as the kind comes first on a notice:
// they decide how everything below them is set. The heading is the
// "headers" palette, rendered inside the component as ".theme-cta__title",
// so "header_position" and "tx_theme_header_style" are disabled for this
// type in the page TSconfig, like for the heroes. The icon is the element's
// "tx_theme_icon", the column the bullet list uses for its icon layout.
//
// The icon identifier is one of the core registry
// (".Build/vendor/typo3/cms-core/Resources/Public/Icons/T3Icons/icons.json"),
// not an image of this extension's own - see the note on unresolved
// identifiers in docs/architecture/content-elements.md.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_cta.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_cta.description',
        'value' => 'theme_cta',
        'icon' => 'content-widget-calltoaction',
        'group' => 'theme',
    ],
    '
        tx_theme_cta_tone,
        tx_theme_cta_width,
        --palette--;;headers,
        bodytext,
        tx_theme_icon,
        --palette--;;theme_link,
        --palette--;;theme_secondary_link,
    ',
    [
        'columnsOverrides' => [
            'bodytext' => [
                'config' => [
                    'enableRichtext' => true,
                ],
            ],
        ],
    ],
);
