<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// A video of another site, embedded without loading anything from it until the
// reader presses play - rendered through ".theme-embed"
// (components/_embed.scss) by "Templates/ContentElements/ThemeExternalMedia.html".
//
// The fields are the URL, a title, a ratio and a poster image. The poster is
// the core "image" column the other media elements of the theme use, on the
// core "images" tab: a thumbnail fetched from the provider would be a request
// to exactly the host this element exists to avoid, so the poster is a file of
// this installation.
//
// The URL is "tx_theme_embed_url", an "input" rather than a "link": a TCA link
// field stores a typolink parameter string, and what an iframe needs is the
// plain URL. What may be embedded is decided in
// "Classes/DataProcessing/ExternalMediaProcessor.php" - the field itself
// accepts any URL, and a host that is not recognised renders the link to the
// source instead of an embed.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_external_media.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_external_media.description',
        'value' => 'theme_external_media',
        'icon' => 'content-media',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.embed,
        --palette--;;theme_embed,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:images,
        image,
    ',
    [
        'columnsOverrides' => [
            'image' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.image.types.theme_external_media.label',
                'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.image.types.theme_external_media.description',
                'config' => [
                    // One poster, not a gallery: the element shows a single
                    // video and the frame has room for one picture.
                    'maxitems' => 1,
                ],
            ],
            'tx_theme_embed_url' => [
                'config' => [
                    'required' => true,
                ],
            ],
        ],
    ],
);
