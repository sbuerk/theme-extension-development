<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Steps of a process, rendered through ".theme-steps", an ordered list. Every
// step is one row of the shared child table "tx_theme_list_item", narrowed to
// its title, its text and an optional icon, which the marker shows instead of
// the number.
//
// The title of a step is required: it is its heading.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_steps.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_steps.description',
        'value' => 'theme_steps',
        'icon' => 'content-target',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.steps,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_steps.label',
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
