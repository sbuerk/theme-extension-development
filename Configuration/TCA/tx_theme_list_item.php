<?php

declare(strict_types=1);

// The child table of the "tx_theme_list_items" inline relation registered in
// Configuration/TCA/Overrides/tt_content.php, shared by theme_linklist,
// theme_sociallinks, theme_media_teaser_grid and theme_author. Ships no
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
    ],
    'palettes' => [
        'theme_link' => [
            'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.palette.theme_link',
            'showitem' => 'link, link_label',
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
                text,
                --linebreak--,
                image,
                --linebreak--,
                --palette--;;theme_link,
            ',
        ],
    ],
];
