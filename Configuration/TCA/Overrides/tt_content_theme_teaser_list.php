<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Rows of teasers, rendered through ".theme-list-group"
// (components/_list-group.scss): a small image, a title, a text, a date and
// meta data. The rows are rows of the shared child table "tx_theme_list_item".
//
// A row has one link, and the title is its label: the whole row is the
// target, so a label of its own, an icon or a style would have nowhere to go.
// The relation therefore shows "link" alone rather than the "theme_link"
// palette. The title is required, because it is the name of the link.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_teaser_list.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_teaser_list.description',
        'value' => 'theme_teaser_list',
        'icon' => 'content-listgroup',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.rows,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_teaser_list.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    header,
                                    --linebreak--,
                                    text,
                                    --linebreak--,
                                    image,
                                    --linebreak--,
                                    date,
                                    meta,
                                    --linebreak--,
                                    link,
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
