<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A quotation with its attribution, rendered through ".theme-quote". "header"
// and "subheader" are relabelled to what they actually hold here - the
// attributed person's name and role, not a content heading - the same
// relabelling camino applies to its own camino_testimonial
// (.agent/tmp/theme_camino/Configuration/TCA/Overrides/20_tt_content_testimonial.php).
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_testimonial.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_testimonial.description',
        'value' => 'theme_testimonial',
        'icon' => 'content-quote',
        'group' => 'theme',
    ],
    // No image field, deliberately. The element renders through
    // ".theme-quote", which has no media slot in its markup contract, and a
    // portrait was not part of what this element is for. Offering the field
    // anyway would let an editor attach an image that silently never appears -
    // worse than not offering it, because the page looks finished and the work
    // is gone. Add the slot to the component first if that changes.
    '
        bodytext,
        --palette--;;headers,
    ',
    [
        'columnsOverrides' => [
            'header' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.header.types.theme_testimonial.label',
            ],
            'subheader' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.subheader.types.theme_testimonial.label',
            ],
            'bodytext' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.bodytext.types.theme_testimonial.label',
                'config' => [
                    'rows' => 4,
                ],
            ],
        ],
    ],
);
