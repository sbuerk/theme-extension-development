<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// The full hero: heading, text, media and call-to-action links, rendered
// through the ".theme-hero" component (docs/development/component-library.md).
//
// The "images" tab divider below deliberately spells out the traditional
// "LLL:EXT:.../Form/locallang_tabs.xlf:images" reference rather than the
// shorter "core.form.tabs:images" seen in camino's own hero
// (.agent/tmp/theme_camino/Configuration/TCA/Overrides/20_tt_content_hero.php):
// that shorthand is TYPO3 v14's Translation Domain Mapping (Feature #93334,
// .Build/vendor/typo3/cms-core/Documentation/Changelog/14.0/Feature-93334-TranslationDomainMapping.rst).
// LanguageService::sL() on v13.4 only translates a string carrying the "LLL:"
// prefix and otherwise returns it unchanged - so on v13 that tab would render
// its own untranslated identifier instead of "Images". The traditional form
// works unchanged on both versions.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero.description',
        'value' => 'theme_hero',
        'icon' => 'content-header',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        bodytext,
        --palette--;;theme_link,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:images,
        image,
    ',
);
