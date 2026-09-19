<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// Cards in a grid or in one row that scrolls sideways, rendered through
// ".theme-card-grid" of ".theme-card" items (components/_card.scss). The
// cards are rows of the shared child table "tx_theme_list_item": an image, a
// title, a subtitle, a text and a link with its label, icon and style.
//
// The arrangement is the core column "layout" - the grid (0) or the scroller
// (1) - and "tx_theme_columns", the number of cards side by side. "layout" is
// disabled for every CType by the page TSconfig of the theme and enabled for
// this one, with its two values relabelled and the two it does not render
// removed ("ContentElementAppearance.tsconfig"). Both are the palette
// "theme_grid" of the features, on the tab of the cards, next to what they
// arrange.
//
// Unlike "theme_media_teaser_grid", a card here has a style for its link,
// "link_variant", and renders the link as a button: the palette
// "theme_link_style" of the child ("Overrides/tx_theme_list_item.php") takes
// the place of "theme_link".
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_card_group.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_card_group.description',
        'value' => 'theme_card_group',
        'icon' => 'content-card-group',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.cards,
        --palette--;;theme_grid,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_card_group.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    header,
                                    --linebreak--,
                                    subheader,
                                    --linebreak--,
                                    text,
                                    --linebreak--,
                                    image,
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
