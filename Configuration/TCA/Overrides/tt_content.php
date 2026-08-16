<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// The theme's own content elements (Configuration/TCA/Overrides/tt_content_theme_*.php)
// are grouped separately in the "new content element" wizard, so an editor can
// tell them apart from the core set at a glance. This file has to load before
// those, which alphabetic loading of Configuration/TCA/Overrides/*.php already
// guarantees: "tt_content.php" sorts before "tt_content_theme_*.php" ('.' is
// before '_' in ASCII), so the columns, the palette and the group below always
// exist by the time a record type references them.
ExtensionManagementUtility::addTcaSelectItemGroup(
    'tt_content',
    'CType',
    'theme',
    'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.group.theme',
    'before:default',
);

// Fields are prefixed "tx_theme_", not the full extension key: a
// "tx_themeextensiondevelopment_link" column is unusable in a "showitem"
// string and in TypoScript. This follows camino's own "camino_" precedent
// (see .agent/tmp/theme_camino/Configuration/TCA/Overrides/10_tt_content.php)
// and carries the same deliberate collision risk against another extension
// that happens to prefix its own fields "theme_" - accepted for the same
// reason camino accepts it.
//
// Note this deliberately does *not* reuse camino's own field names 1:1:
// camino ships a "link_icon" field backed by its own icon font. This theme
// ships no icon assets (see Configuration/TCA/tx_theme_list_item.php and the
// step-5c contract), so there is no equivalent field here.
$additionalColumns = [
    // The call-to-action link shared by the hero and teaser variants.
    'tx_theme_link' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link',
        'config' => [
            'type' => 'link',
            'size' => 30,
        ],
    ],
    'tx_theme_link_label' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_label',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 255,
        ],
    ],
    // Maps directly onto the button modifiers the component library actually
    // ships (Resources/Private/Scss/components/_button.scss: --secondary,
    // --ghost, ... - see docs/development/component-library.md). No option is
    // offered here that the CSS does not implement.
    'tx_theme_link_variant' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_variant',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => '',
            'items' => [
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_variant.I.default',
                    'value' => '',
                ],
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_variant.I.secondary',
                    'value' => 'secondary',
                ],
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_variant.I.ghost',
                    'value' => 'ghost',
                ],
            ],
        ],
    ],
    // One inline (IRRE) relation to tx_theme_list_item, shared by
    // theme_linklist, theme_sociallinks, theme_media_teaser_grid and
    // theme_author - the alternative is three near-identical child tables,
    // which the step-5c contract explicitly rejects. "foreign_match_fields"
    // tells the four record types apart on the same child table.
    //
    // "foreign_sortby" is set explicitly rather than left to the child
    // table's own 'sortby' ctrl option: TcaPreparation::migrateFileType()
    // sets exactly the same "'foreign_sortby' => 'sorting_foreign'" as part
    // of expanding TCA type=file into its underlying inline configuration
    // (.Build/vendor/typo3/cms-core/Classes/Configuration/Tca/TcaPreparation.php),
    // and RelationHandler reads ordering from this key on the *parent* side,
    // not from the child ctrl. Camino's equivalent field
    // (tx_themecamino_list_elements) omits it and relies on the child ctrl
    // alone - copied here would be relying on unverified behaviour, so this
    // sets it explicitly instead.
    'tx_theme_list_items' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_theme_list_item',
            'foreign_field' => 'uid_foreign',
            'foreign_table_field' => 'tablename',
            'foreign_sortby' => 'sorting_foreign',
            'foreign_match_fields' => [
                'fieldname' => 'tx_theme_list_items',
            ],
            'appearance' => [
                'showSynchronizationLink' => false,
                'showAllLocalizationLink' => true,
                'showPossibleLocalizationRecords' => true,
                'expandSingle' => true,
                'useSortable' => true,
                'newRecordLinkTitle' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.appearance.newRecordLinkTitle',
            ],
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('tt_content', $additionalColumns);

$GLOBALS['TCA']['tt_content']['palettes']['theme_link'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.palette.theme_link',
    'showitem' => 'tx_theme_link, tx_theme_link_label, --linebreak--, tx_theme_link_variant',
];
