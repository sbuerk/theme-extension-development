<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Features, rendered through ".theme-feature" in a ".theme-feature-grid": the
// "features" examples of Bootstrap, the "icon group" of bootstrap_package.
// Every feature is one row of the shared child table "tx_theme_list_item",
// narrowed to its title, its text, its icon and its link.
//
// "layout" is the core column, re-enabled for this CType alone and relabelled
// in "Configuration/PageTsConfig/ContentElementAppearance.tsconfig"; with
// "tx_theme_columns" it is the "theme_grid" palette. "header" and the rich
// text "bodytext" are the introduction of the element: above the grid, or
// beside it in the layout "With an introduction".
//
// The title of a feature is required: it is its heading.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_features.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_features.description',
        'value' => 'theme_features',
        'icon' => 'content-widget-list',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        bodytext,
        --palette--;;theme_grid,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.features,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'bodytext' => [
                'config' => [
                    'enableRichtext' => true,
                ],
            ],
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_features.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    header,
                                    --linebreak--,
                                    text,
                                    --linebreak--,
                                    icon,
                                    --linebreak--,
                                    --palette--;;theme_link,
                                ',
                            ],
                        ],
                        'columns' => [
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
