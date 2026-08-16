<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A list of links. "header" is the optional list title, not a content
// heading, so it is used as a bare field rather than through the "headers"
// palette - header_layout, header_position and date have no meaning for it.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_linklist.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_linklist.description',
        'value' => 'theme_linklist',
        'icon' => 'content-bullets',
        'group' => 'theme',
    ],
    '
        header,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'header' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.header.types.theme_linklist.label',
            ],
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_linklist.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '--palette--;;theme_link',
                            ],
                        ],
                        'columns' => [
                            'link' => [
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
