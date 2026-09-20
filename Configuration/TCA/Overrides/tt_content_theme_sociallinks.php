<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// The same list of links as theme_linklist, rendered as a row of platform
// logos: the child's "brand_icon" picks one of the fifteen brand logos the
// extension vendors (see "docs/development/icons.md"), and "link_label" -
// the platform's name, "Mastodon", "LinkedIn", ... - names the link for
// assistive technology while the logo carries it visually. Both are shown,
// because a link whose only content is a logo has no accessible name.
// "link" is restricted to the link types that make sense for a social or
// contact entry, same as camino's camino_sociallinks
// (.agent/tmp/theme_camino/Configuration/TCA/Overrides/20_tt_content_sociallinks.php).
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_sociallinks.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_sociallinks.description',
        'value' => 'theme_sociallinks',
        'icon' => 'content-listgroup',
        'group' => 'theme',
    ],
    '
        header,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'header' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.header.types.theme_sociallinks.label',
            ],
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_sociallinks.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                // "brand_icon" ahead of the link palette: the
                                // platform is what an editor picks first, and
                                // the palette's own "link_icon" (the solid
                                // set) stays out of this relation - see the
                                // column's comment in
                                // "Configuration/TCA/tx_theme_list_item.php".
                                'showitem' => 'brand_icon, --linebreak--, link, link_label',
                            ],
                        ],
                        'columns' => [
                            'link' => [
                                'config' => [
                                    'allowedTypes' => ['url', 'email', 'telephone'],
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
