<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Tca\IconItems;

// The child table of the "tx_theme_list_items" inline relation registered in
// Configuration/TCA/Overrides/tt_content.php, shared by theme_linklist,
// theme_sociallinks, theme_media_teaser_grid, theme_author, theme_tabs,
// theme_accordion, theme_features, theme_stats and theme_steps - each narrows
// the form to what its template renders through "overrideChildTca". "text" is
// plain text here, and rich text only through the
// "overrideChildTca" of the last two - see "tt_content_theme_tabs.php". Ships no
// ext_tables.sql - see the comment above the "fieldname" column for the one
// field that would silently fail to get a database column without it.
//
// The ctrl section mirrors what TYPO3 core's own sys_file_reference declares
// for a translatable inline child table
// (.Build/vendor/typo3/cms-core/Configuration/TCA/sys_file_reference.php):
// tstamp/crdate, soft delete, workspaces versioning, the three language
// fields, and "rootLevel" plus "security.ignore*Restriction" so a child
// record can live below any page a IRRE child ends up on. That file is the
// evidence for what a translatable inline child needs, not a guess.
return [
    'ctrl' => [
        'title' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item',
        'label' => 'header',
        'label_alt' => 'text, link_label',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        'sortby' => 'sorting_foreign',
        'hideTable' => true,
        'rootLevel' => -1,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        // No custom icon is shipped for this table (see the step-5c contract
        // on icons); "content-bullets" is an existing core identifier and
        // reused here purely as the record icon shown in the list module.
        'typeicon_classes' => [
            'default' => 'content-bullets',
        ],
        'security' => [
            'ignoreWebMountRestriction' => true,
            'ignoreRootLevelRestriction' => true,
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        // Written by "foreign_match_fields" on the parent side to record
        // which of the four inline relations a row belongs to. This must be
        // an actual persisted TCA type, not "passthrough": DefaultTcaSchema
        // (.Build/vendor/typo3/cms-core/Classes/Database/Schema/DefaultTcaSchema.php)
        // only auto-creates a database column for "foreign_field" and
        // "foreign_table_field" when the inline relation is processed - a
        // field used solely for "foreign_match_fields", as this one is, is
        // not part of that special case and only gets a column through the
        // generic per-column loop, which requires a real field type. This
        // matches core's own sys_file_reference, which declares "fieldname"
        // the same way with the same reasoning in its own "@todo" comment.
        'fieldname' => [
            'config' => [
                'type' => 'input',
            ],
        ],
        'header' => [
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.header',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
            ],
        ],
        // A second, short line that goes with the header. Named for what it
        // is on any item, not for one element: the stats element uses it for
        // what a figure counts - its "header" is the figure - and relabels
        // both through its "overrideChildTca"; the card group shows it below
        // the title of a card. No other relation shows it.
        'subheader' => [
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.subheader',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
            ],
        ],
        // The date of an entry of "theme_timeline", which is sorted by it, and
        // of a row of "theme_teaser_list". A native "DATE" column ("dbType"):
        // a timeline reaches back before 1970 as easily as forward past 2038,
        // a calendar date has no time zone to shift it across midnight, and
        // the database sorts it as it stands. "DefaultTcaSchema" derives the
        // nullable column from "dbType" on v13.4 and v14.3, and DataHandler
        // stores an empty value as NULL rather than as a date.
        'date' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.date',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'dbType' => 'date',
                'nullable' => true,
            ],
        ],
        // Meta data at the end of a row of "theme_teaser_list" - a reading
        // time, a place, a category. Plain text; the date has its own column.
        'meta' => [
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.meta',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
            ],
        ],
        'text' => [
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.text',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 5,
                'softref' => 'typolink_tag,email[subst],url',
            ],
        ],
        'image' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.image',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'appearance' => [
                    'showPossibleLocalizationRecords' => true,
                ],
            ],
        ],
        'link' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.link',
            'config' => [
                'type' => 'link',
                'size' => 50,
            ],
        ],
        'link_label' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.link_label',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
            ],
        ],
        // An icon before the label of the item's link, from the shipped set -
        // the picker is "Classes/Tca/IconItems.php", the icons it offers by
        // default are "Configuration/PageTsConfig/IconPicker.tsconfig". Part
        // of the "theme_link" palette, so every relation that shows the link
        // shows it too, and every template that renders the link renders the
        // icon: "Partials/ContentElement/LinkList.html" and
        // "Templates/ContentElements/ThemeMediaTeaserGrid.html".
        //
        // A "selectSingle" of string values: "DefaultTcaSchema" derives a
        // "VARCHAR(255) DEFAULT ''" column from it on v13.4 and v14.3, so like
        // every other column here it needs no "ext_tables.sql".
        'link_icon' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.link_icon',
            'config' => IconItems::selectConfig(),
        ],
        // The icon of the item itself, from the same picker - not the icon of
        // its link, which is "link_icon" above and is shown with the link. A
        // parent element that renders an icon per item adds this column to the
        // "showitem" of its "overrideChildTca"; every other relation leaves it
        // out, so an editor is never offered an icon nothing renders. The
        // default type below shows it, because that is the form of the record
        // edited on its own, outside any relation.
        //
        // Named for the item and not for one element, so every element with an
        // icon per item uses this one column. The icons it offers by default
        // are the curated list of "Configuration/PageTsConfig/IconPicker.tsconfig".
        // A "selectSingle" of string values, so "DefaultTcaSchema" derives a
        // "VARCHAR(255) DEFAULT ''" column on v13.4 and v14.3, as for
        // "link_icon".
        'icon' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.icon',
            'config' => IconItems::selectConfig(),
        ],
    ],
    'palettes' => [
        'theme_link' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.palette.theme_link',
            'showitem' => 'link, link_label, --linebreak--, link_icon',
        ],
    ],
    // The default shown when a child record is edited outside of one of the
    // four inline relations above (e.g. directly in the list module). Each
    // CType file below narrows this per relation via "overrideChildTca".
    'types' => [
        '0' => [
            'showitem' => '
                header,
                --linebreak--,
                subheader,
                --linebreak--,
                text,
                --linebreak--,
                icon,
                --linebreak--,
                image,
                --linebreak--,
                --palette--;;theme_link,
            ',
        ],
    ],
];
