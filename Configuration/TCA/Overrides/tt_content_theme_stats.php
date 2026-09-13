<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Figures, rendered through ".theme-stat" in a ".theme-stats" description list.
// Every figure is one row of the shared child table "tx_theme_list_item":
// "header" is the figure, "subheader" what it counts, "text" an optional
// sentence about it, and "icon" an optional icon before the figure.
//
// Both "header" and "subheader" are relabelled here - "Title" and "Subheader"
// say nothing about a figure - and both are required: a figure without what it
// counts is a number without a meaning, and the template renders a row without
// its figure not at all. "overrideChildTca" is a FormEngine setting, merged
// into the child's form by "InlineOverrideChildTca::overrideColumns()" with
// "array_replace_recursive()", so a label is overridden the same way "required"
// is.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_stats.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_stats.description',
        'value' => 'theme_stats',
        'icon' => 'content-widget-number',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.stats,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_stats.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    header,
                                    subheader,
                                    --linebreak--,
                                    text,
                                    --linebreak--,
                                    icon,
                                ',
                            ],
                        ],
                        'columns' => [
                            'header' => [
                                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.header.types.theme_stats.label',
                                'config' => [
                                    'required' => true,
                                ],
                            ],
                            'subheader' => [
                                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.subheader.types.theme_stats.label',
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
