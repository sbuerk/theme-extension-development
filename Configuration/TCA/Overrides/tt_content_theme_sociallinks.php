<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// The same list of links as theme_linklist, rendered as labelled text
// entries rather than icons: this theme ships no icon assets and no icon
// component (see the step-5c contract), so the "link_label" field the child
// table already carries is what stands in for the icon - a platform name
// ("Mastodon", "LinkedIn", ...) rather than a symbol. "link" is restricted to
// the link types that make sense for a social/contact entry, same as
// camino's camino_sociallinks
// (.agent/tmp/theme_camino/Configuration/TCA/Overrides/20_tt_content_sociallinks.php).
ContentTypeRegistration::addRecordType(
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
                                'showitem' => '--palette--;;theme_link',
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
