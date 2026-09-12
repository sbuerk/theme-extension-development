<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// A notice, rendered through ".theme-alert". "tx_theme_notice_kind" selects
// one of the six kinds the component ships (see the column in
// "Configuration/TCA/Overrides/tt_content.php"), and the template derives the
// "role" from it - the role is a property of the kind, not a second choice an
// editor could get wrong.
//
// "header" is the notice's title, rendered as ".theme-alert__title" inside the
// component, not as a content heading: a bare field, like theme_teaser, since
// header_layout, header_position and date have no meaning for it. "bodytext" is
// rich text - the only theme element that enables the editor on it, because a
// notice is prose with the occasional link or emphasis, and it renders through
// "f:format.html" like every other bodytext of this extension.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_notice.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_notice.description',
        'value' => 'theme_notice',
        'icon' => 'content-message',
        'group' => 'theme',
    ],
    '
        tx_theme_notice_kind,
        header,
        bodytext,
    ',
    [
        'columnsOverrides' => [
            'header' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.header.types.theme_notice.label',
            ],
            'bodytext' => [
                'config' => [
                    'enableRichtext' => true,
                ],
            ],
        ],
    ],
);
