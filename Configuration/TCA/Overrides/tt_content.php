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
    // The icon of an element as a whole, from the same picker. A column of its
    // own rather than "tx_theme_link_icon", which belongs to the link and is
    // shown with it: the bullet list renders this one in front of every item
    // in its layout "Icons" ("Templates/ContentElements/Bullets.html"). Added
    // to "bullets" only, below; a later core layout that shows one icon for
    // the element adds it to its CType the same way.
    'tx_theme_icon' => [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon.description',
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

// Where, on what and how large the icon of an element is drawn - the three
// axes of ".theme-media-object" (components/_media-object.scss), one column
// each, every value one modifier. The text and icon element offers them in the
// "theme_icon" palette below, next to "tx_theme_icon"; they are named for the
// icon rather than for that element, so a later element that shows one icon
// the same way offers the same palette.
//
// "selectSingle" of string values: "DefaultTcaSchema" derives a VARCHAR(255)
// from each on v13.4 and v14.3. The template maps every value it does not know
// - an empty one included - to the default of its axis.
$additionalColumns['tx_theme_icon_position'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_position',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 'start',
        'items' => [
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_position.I.start', 'value' => 'start'],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_position.I.end', 'value' => 'end'],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_position.I.top', 'value' => 'top'],
        ],
    ],
];
$additionalColumns['tx_theme_icon_shape'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_shape',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 'plain',
        'items' => [
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_shape.I.plain', 'value' => 'plain'],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_shape.I.square', 'value' => 'square'],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_shape.I.circle', 'value' => 'circle'],
        ],
    ],
];
$additionalColumns['tx_theme_icon_size'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_size',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 'md',
        'items' => [
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_size.I.md', 'value' => 'md'],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_size.I.lg', 'value' => 'lg'],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon_size.I.xl', 'value' => 'xl'],
        ],
    ],
];

// The most columns an element lays its items out in - the modifiers
// "--columns-2" to "--columns-4" of ".theme-feature-grid". Named for the grid,
// not for the one element that has it so far, so a later element with a grid
// of items offers the same column: the card group lays out its cards by it,
// and its scroller reads it as the number of cards in view.
//
// Integer values: "DefaultTcaSchema" derives an INT column. v14.3 gives it
// the TCA default, 3, as its database default; v13.4 gives every integer
// select 0 ("DefaultTcaSchema::enrichSingleTableFieldsFromTcaColumns()", read
// on v13.4.35 and v14.3.7). A record written through the form carries the TCA
// default on both, and the template renders 0 - and every other value it does
// not know - as three columns, so the difference never reaches the page.
//
// @todo Drop the v13.4 half of this note, and the mapping of 0 in
//       "Templates/ContentElements/ThemeFeatures.html" and
//       "Templates/ContentElements/ThemeCardGroup.html" with it, once
//       v13.4 support is dropped. No core changelog entry documents the
//       change of the default: #105441 ("TCA select fields with null item
//       values create nullable columns", 14.2) covers items with a null
//       value only, and nothing else was found in the 13.4.x and 14.*
//       changelogs.
$additionalColumns['tx_theme_columns'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_columns',
    'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_columns.description',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 3,
        'items' => [
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_columns.I.2', 'value' => 2],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_columns.I.3', 'value' => 3],
            ['label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_columns.I.4', 'value' => 4],
        ],
    ],
];

// The short label above the title of a hero, rendered as
// ".theme-hero__eyebrow" by "Partials/ContentElement/Hero.html" - the class
// the hero's markup contract always had and no field filled.
$additionalColumns['tx_theme_eyebrow'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_eyebrow',
    'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_eyebrow.description',
    'config' => [
        'type' => 'input',
        'size' => 30,
        'max' => 255,
    ],
];

// How a hero arranges its text and its image. Every value but the default is
// a modifier of ".theme-hero" (components/_hero.scss), mapped by
// "Partials/ContentElement/Hero.html". The default is the hero as it always
// rendered: the image beside the text, at the start of the line - which is why
// there is no "image-start" value, it would be a second name for the default.
// The three heroes offer what fits them, narrowed per type in
// "Configuration/PageTsConfig/ContentElementAppearance.tsconfig".
$additionalColumns['tx_theme_hero_layout'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_hero_layout',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => '',
        'items' => [
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_hero_layout.I.default',
                'value' => '',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_hero_layout.I.image-end',
                'value' => 'image-end',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_hero_layout.I.centred',
                'value' => 'centred',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_hero_layout.I.screenshot',
                'value' => 'screenshot',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_hero_layout.I.bordered',
                'value' => 'bordered',
            ],
        ],
    ],
];

// The order of the entries of a "theme_timeline", by their date. The order of
// the inline list decides only between entries of one date - see
// "tt_content.theme_timeline" in ContentElements.typoscript.
$additionalColumns['tx_theme_sort_direction'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_sort_direction',
    'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_sort_direction.description',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 'asc',
        'items' => [
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_sort_direction.I.asc',
                'value' => 'asc',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_sort_direction.I.desc',
                'value' => 'desc',
            ],
        ],
    ],
];

// How a testimonial sets its quotation: a modifier of ".theme-quote"
// (components/_quote.scss), mapped by
// "Templates/ContentElements/ThemeTestimonial.html".
//
// A select of its own rather than the core "layout": that column holds the
// numbers 0 to 3 under the labels "Layout 1" to "Layout 3", is disabled for
// every CType by the theme's page TSconfig, and is an "exclude" field an editor
// group has to be granted. The theme's own elements carry their choices in
// columns of their own - the kind of a notice, the layout of a hero - whose
// values are the names of the modifiers they select.
$additionalColumns['tx_theme_quote_style'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_quote_style',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => '',
        'items' => [
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_quote_style.I.default',
                'value' => '',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_quote_style.I.pull',
                'value' => 'pull',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_quote_style.I.centred',
                'value' => 'centred',
            ],
        ],
    ],
];

// The tone of a call to action ("theme_cta"): the fill and the frame of
// ".theme-cta" (components/_cta.scss), mapped by
// "Templates/ContentElements/ThemeCta.html". "accent" and "inverse" are the
// bands of "frame_class" applied to the component - the same 5% tint, and the
// same turned colour scheme - so the contrast DESIGN.md computed for the bands
// holds here. "placeholder" is no fill and a dashed frame, for a call to
// action standing in for content that does not exist yet.
$additionalColumns['tx_theme_cta_tone'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_tone',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => '',
        'items' => [
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_tone.I.surface',
                'value' => '',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_tone.I.accent',
                'value' => 'accent',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_tone.I.inverse',
                'value' => 'inverse',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_tone.I.placeholder',
                'value' => 'placeholder',
            ],
        ],
    ],
];

// The width of a call to action: a box narrower than the column, centred in
// it, or a band across the whole column.
$additionalColumns['tx_theme_cta_width'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_width',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'default' => 'boxed',
        'items' => [
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_width.I.boxed',
                'value' => 'boxed',
            ],
            [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_cta_width.I.band',
                'value' => 'band',
            ],
        ],
    ],
];

// The second link of a call to action: the "theme_link" palette once more,
// in columns of its own. A call to action offers a main action and an
// alternative to it - "Start now" and "Read the guide" - and a second button
// next to the first is what the component lays out. An inline relation of
// list items, as the link list has, would be a list where the element has
// two fixed places. Rendered by the same "Partials/ContentElement/LinkButton.html",
// handed these four columns as the four arguments the first link fills.
$additionalColumns['tx_theme_secondary_link'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_secondary_link',
    'config' => [
        'type' => 'link',
        'size' => 30,
    ],
];
$additionalColumns['tx_theme_secondary_link_label'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_label',
    'config' => [
        'type' => 'input',
        'size' => 30,
        'max' => 255,
    ],
];
// The items of the first link's style, so the partial renders both with the
// same cases; the second link defaults to the outlined button.
$additionalColumns['tx_theme_secondary_link_variant'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_variant',
    'config' => array_replace($additionalColumns['tx_theme_link_variant']['config'], ['default' => 'secondary']),
];
$additionalColumns['tx_theme_secondary_link_icon'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_link_icon',
    'config' => IconItems::selectConfig(),
];

ExtensionManagementUtility::addTCAcolumns('tt_content', $additionalColumns);

// The icon of the layout "Icons" of the bullet list, next to the list type.
// "bullets" is registered by EXT:frontend, whose TCA overrides run before
// this extension's, so its type exists here.
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_theme_icon', 'bullets', 'after:bullets_type');

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

// The icon of an element and how it is drawn, for an element that renders one
// icon beside its text - see the three columns above.
$GLOBALS['TCA']['tt_content']['palettes']['theme_icon'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.palette.theme_icon',
    'showitem' => 'tx_theme_icon, --linebreak--, tx_theme_icon_position, tx_theme_icon_shape, tx_theme_icon_size',
];

// The layout of a grid of items: the core "layout", which the page TSconfig
// re-enables and relabels per CType, and how many columns the grid takes.
$GLOBALS['TCA']['tt_content']['palettes']['theme_grid'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.palette.theme_grid',
    'showitem' => 'layout, tx_theme_columns',
];

$GLOBALS['TCA']['tt_content']['palettes']['theme_secondary_link'] = [
    'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.palette.theme_secondary_link',
    'showitem' => 'tx_theme_secondary_link, tx_theme_secondary_link_label, --linebreak--, tx_theme_secondary_link_variant, tx_theme_secondary_link_icon',
];
