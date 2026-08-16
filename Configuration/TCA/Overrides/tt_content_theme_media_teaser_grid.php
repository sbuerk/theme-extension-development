<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Several media teasers in a grid, rendered through ".theme-card-grid" of
// ".theme-card" items. "header"/"bodytext" are the optional lead-in above the
// grid; the items themselves are the shared tx_theme_list_item child table,
// narrowed here to header/text/image/link through "overrideChildTca" - the
// full field set that table's own default type already shows, spelled out
// again here only so a future reader does not have to cross-reference the
// child table's "types" to know what an editor sees.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_media_teaser_grid.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_media_teaser_grid.description',
        'value' => 'theme_media_teaser_grid',
        'icon' => 'content-card-group',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        bodytext,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.teasers,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_media_teaser_grid.label',
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
                                    --palette--;;theme_link,
                                ',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
);
