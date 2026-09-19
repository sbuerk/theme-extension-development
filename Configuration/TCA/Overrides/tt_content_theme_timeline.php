<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Dated entries on a line, rendered through ".theme-timeline"
// (components/_timeline.scss). The entries are rows of the shared child table
// "tx_theme_list_item", narrowed to a date, a title, a text, an icon and an
// image. The icon is the item's "icon" the features, the figures and the steps
// show, with their curated list of icons; the entry shows it on the line in
// place of the ring.
//
// "tx_theme_sort_direction" orders them by their date, oldest or newest first;
// the order of the inline list decides only between entries of one date - see
// "tt_content.theme_timeline" in ContentElements.typoscript.
//
// The date and the title are required: an entry without a date has no place
// on the line, and one without a title has no name.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_timeline.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_timeline.description',
        'value' => 'theme_timeline',
        'icon' => 'content-timeline',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.entries,
        tx_theme_sort_direction,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_timeline.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    date,
                                    --linebreak--,
                                    header,
                                    --linebreak--,
                                    text,
                                    --linebreak--,
                                    icon,
                                    --linebreak--,
                                    image,
                                ',
                            ],
                        ],
                        'columns' => [
                            'date' => [
                                'config' => [
                                    'required' => true,
                                ],
                            ],
                            'header' => [
                                'config' => [
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
);
