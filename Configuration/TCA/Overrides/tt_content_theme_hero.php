<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// The full hero: heading, text, media and call-to-action links, rendered
// through the ".theme-hero" component (docs/development/component-library.md).
//
// The "images" tab divider below deliberately spells out the traditional
// "LLL:EXT:.../Form/locallang_tabs.xlf:images" reference rather than the
// shorter "core.form.tabs:images" seen in camino's own hero
// (.agent/tmp/theme_camino/Configuration/TCA/Overrides/20_tt_content_hero.php):
// that shorthand needs a translation domain mapping this core version does not
// have. LanguageService::sL() on v13.4 only translates a string carrying the
// "LLL:" prefix and returns anything else unchanged, so the tab would render
// its own untranslated identifier instead of "Images". The traditional form is
// what works here.
ContentTypeRegistration::addRecordType(
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
