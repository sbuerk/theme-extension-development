<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Tabs, rendered through ".theme-tabs". Every tab is one row of the shared
// child table "tx_theme_list_item", narrowed to its title and its text.
//
// The child's "text" is plain text everywhere else - theme_media_teaser_grid
// renders it through "f:format.nl2br" - and rich text only here and in
// theme_accordion, which is why "enableRichtext" is set through
// "overrideChildTca" rather than on the column: the column itself, and with it
// the form of the four other relations, stays exactly as it was.
//
// "overrideChildTca" is a FormEngine setting and nothing else. DataHandler
// resolves a field's configuration from the table's TCA and the columnsOverrides
// of the record's own type ("resolveFieldConfigurationAndRespectColumnsOverrides()"),
// and the child table has no type field, so the RTE transformation on save does
// not run for this column: the value is stored as the editor submitted it. The
// frontend does not depend on that transformation - "f:format.html" parses the
// value through "lib.parseFunc_RTE", which sanitises it on output.
//
// The title is required: it is the name of the tab, and a tab without one is a
// button with no accessible name.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_tabs.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_tabs.description',
        'value' => 'theme_tabs',
        'icon' => 'content-tab',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.tabs,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_tabs.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    header,
                                    --linebreak--,
                                    text,
                                ',
                            ],
                        ],
                        'columns' => [
                            'header' => [
                                'config' => [
                                    'required' => true,
                                ],
                            ],
                            'text' => [
                                'config' => [
                                    'enableRichtext' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
);
