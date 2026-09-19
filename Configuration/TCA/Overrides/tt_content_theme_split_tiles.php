<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Alternating featurettes, rendered through ".theme-split-tiles"
// (components/_split-tiles.scss): a column of tiles, each a picture on one side
// and words on the other, with the side changing from tile to tile. The tiles
// are rows of the shared child table "tx_theme_list_item": an image, a heading,
// a text, a link and a tone.
//
// The alternation is the stylesheet's, through ":nth-child(even)", so no field
// and no per-tile markup decides which side a picture is on - see the header
// comment of the component. What the element carries is the core column
// "layout", offered here with two values only: the default rhythm and
// "--reversed", which starts it on the other side so a second group of tiles
// further down a page carries the rhythm on instead of repeating it. It is
// disabled for every CType by the page TSconfig of the theme and enabled for
// this one, with its two values relabelled and the two it does not render
// removed ("ContentElementAppearance.tsconfig").
//
// The tone is a column of the child, not of the element: every tile carries its
// own - see "Overrides/tx_theme_list_item.php".
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_split_tiles.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_split_tiles.description',
        'value' => 'theme_split_tiles',
        'icon' => 'content-beside-text-img-left',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.tiles,
        layout,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_split_tiles.label',
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
                                    tone,
                                    --linebreak--,
                                    --palette--;;theme_link_style,
                                ',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
);
