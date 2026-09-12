<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// An accordion, rendered through ".theme-accordion": one native "details" per
// row of the shared child table, all of one element sharing a "name", so
// opening one closes the others. The child is narrowed to its title - the
// "summary" - and its text, which is rich text here for the reason and with
// the caveat given in "tt_content_theme_tabs.php".
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_accordion.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_accordion.description',
        'value' => 'theme_accordion',
        'icon' => 'content-accordion',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.items,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_accordion.label',
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
