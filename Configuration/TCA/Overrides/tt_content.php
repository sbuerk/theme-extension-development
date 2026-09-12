<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Tca\IconItems;
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
// camino ships a "link_icon" field backed by an icon font of its own. The
// equivalent here is "tx_theme_link_icon", an icon of the Font Awesome Free
// solid set the theme ships - picked by name, rendered inline as SVG.
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
    // --ghost, --link - see docs/development/component-library.md). No option
    // is offered here that the CSS does not implement, and "--danger" is left
    // out on purpose: a call to action is not a destructive action.
    //
    // The column keeps its name, although the form calls it "Link style":
    // release 1.x ships it with the three values before "link", and a rename
    // would lose what installations stored.
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
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_variant.I.link',
                    'value' => 'link',
                ],
            ],
        ],
    ],
    // An icon before the label of the link, from the shipped set - the picker
    // is "Classes/Tca/IconItems.php", the icons it offers by default are
    // "Configuration/PageTsConfig/IconPicker.tsconfig". Rendered by
    // "Partials/ContentElement/LinkButton.html".
    'tx_theme_link_icon' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_icon',
        'config' => IconItems::selectConfig(),
    ],
    // The kind of a theme_notice. Maps directly onto the six ".theme-alert"
    // modifiers (Resources/Private/Scss/components/_alert.scss), and the
    // template derives the "role" from it - see
    // docs/development/component-library.md for which kind takes which.
    //
    // The default is "note", not "info", although "info" is what the bare
    // class looks like: "info" is a live region ("role=status"), and a notice
    // whose kind nobody chose should be the one kind that never is.
    'tx_theme_notice_kind' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_notice_kind',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => 'note',
            'items' => [
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_notice_kind.I.note',
                    'value' => 'note',
                ],
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_notice_kind.I.info',
                    'value' => 'info',
                ],
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_notice_kind.I.tip',
                    'value' => 'tip',
                ],
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_notice_kind.I.success',
                    'value' => 'success',
                ],
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_notice_kind.I.warning',
                    'value' => 'warning',
                ],
                [
                    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_notice_kind.I.danger',
                    'value' => 'danger',
                ],
            ],
        ],
    ],
    // One inline (IRRE) relation to tx_theme_list_item, shared by
    // theme_linklist, theme_sociallinks, theme_media_teaser_grid,
    // theme_author, theme_tabs and theme_accordion - the alternative is
    // several near-identical child tables,
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

// How loud the heading of a content element is, independent of its level:
// "header_layout" says where the heading sits in the outline, this how it
// looks. Rendered by "Partials/ContentElement/Header.html" - "display" as the
// text role ".theme-display", "h1" to "h5" as modifiers of
// ".theme-content-element__heading" (components/_content-element.scss). No
// value is offered that the stylesheet does not implement, the same rule as
// "tx_theme_link_variant" above.
//
// Not "exclude": like every column of this extension it is a presentation
// choice of the element, and an editor who may edit the header may choose
// how it looks.
$additionalColumns['tx_theme_header_style'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style',
    'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.description',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => '',
        'items' => [
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.I.default',
                'value' => '',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.I.display',
                'value' => 'display',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.I.h1',
                'value' => 'h1',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.I.h2',
                'value' => 'h2',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.I.h3',
                'value' => 'h3',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.I.h4',
                'value' => 'h4',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_header_style.I.h5',
                'value' => 'h5',
            ],
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('tt_content', $additionalColumns);

// Next to "header_layout" in both header palettes of the core, so every CType
// offering a heading level also offers its look. The CTypes of this extension
// that render their title outside the shared header partial - the heroes, the
// media teaser, the testimonial - hide it again per type, in
// "Configuration/PageTsConfig/ContentElementAppearance.tsconfig", rather than
// offering a field that does nothing there.
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', 'tx_theme_header_style', 'after:header_layout');
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'header', 'tx_theme_header_style', 'after:header_layout');

$GLOBALS['TCA']['tt_content']['palettes']['theme_link'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.palette.theme_link',
    'showitem' => 'tx_theme_link, tx_theme_link_label, --linebreak--, tx_theme_link_variant, tx_theme_link_icon',
];
