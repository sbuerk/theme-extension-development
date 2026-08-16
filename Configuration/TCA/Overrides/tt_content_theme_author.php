<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A person: portrait, name, role and links. "header"/"subheader" hold name
// and role, same relabelling as theme_testimonial; "tx_theme_list_items" is
// reused here for the person's own profile/contact links, the same child
// table theme_linklist and theme_sociallinks use.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_author.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_author.description',
        'value' => 'theme_author',
        'icon' => 'content-user',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        bodytext,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:images,
        image,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.links,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'header' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.header.types.theme_author.label',
                'config' => [
                    'required' => true,
                ],
            ],
            'subheader' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.subheader.types.theme_author.label',
            ],
            'bodytext' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.bodytext.types.theme_author.label',
                'config' => [
                    'rows' => 4,
                ],
            ],
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_author.label',
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
