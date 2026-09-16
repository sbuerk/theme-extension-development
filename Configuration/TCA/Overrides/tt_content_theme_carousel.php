<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Slides in one track that scrolls sideways and snaps, rendered through
// ".theme-carousel" (components/_carousel.scss). The slides are rows of the
// shared child table "tx_theme_list_item": an image, a heading, a text, a link
// and where the caption sits against the picture.
//
// No field arranges the element as a whole: a carousel is one shape, and the
// only choice is per slide. The relation therefore shows "caption_position"
// rather than the parent carrying a layout column.
//
// The link of a slide is a button of the style the editor picked, like a card
// of "theme_card_group": the palette "theme_link_style" of the child
// ("Overrides/tx_theme_list_item.php") rather than the plain "theme_link".
//
// There is deliberately no autoplay and therefore no field for one - see the
// header comment of "components/_carousel.scss" for the WCAG 2.2.2 reasoning.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_carousel.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_carousel.description',
        'value' => 'theme_carousel',
        'icon' => 'content-carousel',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.slides,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_carousel.label',
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
                                    caption_position,
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
