<?php

declare(strict_types=1);

use SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration;

defined('TYPO3') or die();

// The full hero: heading, text, media and call-to-action links, rendered
// through the ".theme-hero" component (docs/development/component-library.md).
//
// The "images" tab divider below names the label of EXT:frontend, not the
// "LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:images" one
// that reads like the obvious choice: that key exists in v13.4 and **not** in
// v12.4, where the tab would render its own untranslated identifier. Both
// versions ship "tabs.images" in EXT:frontend's "locallang_ttc.xlf" (v12.4.45
// line 789, v13.4.35 line 459), which is also where the core's own image tab
// on "tt_content" takes its title from. The shorthand "core.form.tabs:images"
// is no option either: it needs a translation domain neither version has, and
// "LanguageService::sL()" returns a string without the "LLL:" prefix
// unchanged.
ContentTypeRegistration::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_hero.description',
        'value' => 'theme_hero',
        'icon' => 'content-header',
        'group' => 'theme',
    ],
    '
        tx_theme_hero_layout,
        tx_theme_eyebrow,
        --palette--;;headers,
        bodytext,
        --palette--;;theme_link,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.images,
        image,
    ',
);
