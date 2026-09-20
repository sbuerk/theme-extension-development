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
    // The one item of a list singled out from the others - the recommended
    // plan of "theme_pricing", rendered as ".theme-pricing__plan--highlighted".
    //
    // Named for what it does to any item rather than for the one element that
    // shows it so far, like "subheader" and "date" before it: a later element
    // that emphasises one of its items offers the same column. Only the
    // relation of "theme_pricing" puts it in its "showitem", so no editor is
    // offered a switch nothing renders.
    //
    // "type => check" with the default 0: "DefaultTcaSchema" derives an
    // unsigned "SMALLINT NOT NULL DEFAULT 0" column from it on v13.4 and
    // v14.3 - the default comes from the TCA, the rest is fixed - so it needs
    // no "ext_tables.sql" like every other column here.
    'highlighted' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.highlighted',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.highlighted.description',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
        ],
    ],
]);

$GLOBALS['TCA']['tx_theme_list_item']['palettes']['theme_link_style'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.palette.theme_link',
    'showitem' => 'link, link_label, --linebreak--, link_variant, link_icon',
];

// The tone of one tile of "theme_split_tiles": the fill and the frame of
// ".theme-split-tiles__item" (components/_split-tiles.scss).
//
// The configuration is the one of "tt_content.tx_theme_cta_tone", taken from it
// rather than written out again, exactly as "link_variant" above takes the one
// of "tt_content.tx_theme_link_variant" and for the same reason: the two
// components mix the same three tones from the same tokens, one set of cases
// renders them, and a copy of the items would be a second list to keep in step.
// "SplitTilesRenderingTest" holds the values of the column to those cases.
//
// A column of the child rather than of the parent, because the tone is a
// property of the individual tile: a column of tiles that all carry one tone is
// a list, and setting each one apart from the one above it is what the element
// is for.
ExtensionManagementUtility::addTCAcolumns('tx_theme_list_item', [
    'tone' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.tone',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.tone.description',
        'config' => $GLOBALS['TCA']['tt_content']['columns']['tx_theme_cta_tone']['config'],
    ],
]);
