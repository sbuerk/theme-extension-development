<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// A quotation with its attribution, rendered through ".theme-quote". "header"
// and "subheader" are relabelled to what they actually hold here - the
// attributed person's name and role, not a content heading - the same
// relabelling camino applies to its own camino_testimonial
// (.agent/tmp/theme_camino/Configuration/TCA/Overrides/20_tt_content_testimonial.php).
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_testimonial.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_testimonial.description',
        'value' => 'theme_testimonial',
        'icon' => 'content-quote',
        'group' => 'theme',
    ],
    // "tx_theme_quote_style" first, as the kind comes first on a notice: it
    // decides how everything below it is set.
    //
    // "image" is the portrait of the attributed person, shown as a
    // ".theme-avatar" in the media slot ".theme-quote__portrait" next to the
    // name. One file: the slot holds one picture. The field was taken off this
    // type once, while ".theme-quote" had no slot for it and an attached
    // portrait would have silently never appeared; it is back with the slot.
    // The template renders the portrait only next to a name, the one place it
    // is decoration - see "Templates/ContentElements/ThemeTestimonial.html".
    '
        tx_theme_quote_style,
        bodytext,
        --palette--;;headers,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.images,
        image,
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
            'image' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.image.types.theme_testimonial.label',
                'config' => [
                    'maxitems' => 1,
                ],
            ],
        ],
    ],
);
