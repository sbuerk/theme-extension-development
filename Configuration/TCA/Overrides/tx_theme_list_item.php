<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// The style of the link of an inline list item - a card of "theme_card_group",
// rendered as a button through "Partials/ContentElement/LinkButton.html" like
// the link of the heroes and teasers.
//
// The configuration is the one of "tt_content.tx_theme_link_variant", taken
// from it rather than written out again: the partial renders one case per
// value, "ThemeLinkRenderingTest" holds the values of both columns to those
// cases, and a copy of the items would be a second list to keep in step. So
// this file is an override rather than part of "Configuration/TCA/
// tx_theme_list_item.php" - the base TCA of the child table is loaded before
// any override, and "Overrides/tt_content.php" before this one ("tt" sorts
// before "tx").
//
// A palette of its own, "theme_link_style", rather than the column added to
// "theme_link": that palette is part of the form of every relation showing a
// link, and a style is only rendered by the card group.
ExtensionManagementUtility::addTCAcolumns('tx_theme_list_item', [
    'link_variant' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.link_variant',
        'config' => $GLOBALS['TCA']['tt_content']['columns']['tx_theme_link_variant']['config'],
    ],
]);

$GLOBALS['TCA']['tx_theme_list_item']['palettes']['theme_link_style'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.palette.theme_link',
    'showitem' => 'link, link_label, --linebreak--, link_variant, link_icon',
];
