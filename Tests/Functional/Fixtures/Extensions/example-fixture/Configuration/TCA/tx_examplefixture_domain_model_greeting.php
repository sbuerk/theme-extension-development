<?php

declare(strict_types=1);

/**
 * TCA of the greeting table of the fixture extension.
 *
 * The table name follows the Extbase convention, which derives it from the
 * class name of the model and not from the extension key:
 * "TESTS\ExampleFixture\Domain\Model\Greeting" resolves to
 * "tx_examplefixture_domain_model_greeting" — the vendor part is dropped, the
 * rest is lower cased and joined with underscores. See
 * \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory::resolveTableName().
 *
 * The table is deliberately both language aware and version aware: it declares
 * the language fields, so records can be translated and are overlaid on
 * retrieval, and "versioningWS", so workspace overlays apply as well.
 */
return [
    'ctrl' => [
        'title' => 'Example fixture greeting',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        // Narrows the backend record list and the live search to these two
        // fields. Without the option the core widens the search to every
        // searchable field of the schema — see the empty-list branch of
        // \TYPO3\CMS\Core\Schema\SearchableSchemaFieldsCollector::getFields(),
        // which both \TYPO3\CMS\Backend\RecordList\DatabaseRecordList and
        // \TYPO3\CMS\Backend\Search\LiveSearch\DatabaseRecordProvider call
        // without a field list of their own.
        'searchFields' => 'title,message',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_examplefixture_domain_model_greeting',
                'foreign_table_where' => 'AND {#tx_examplefixture_domain_model_greeting}.{#pid}=###CURRENT_PID###'
                    . ' AND {#tx_examplefixture_domain_model_greeting}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'label' => 'Title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'message' => [
            'label' => 'Message',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 5,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, title, message',
        ],
    ],
];
