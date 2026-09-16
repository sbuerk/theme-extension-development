<?php

declare(strict_types=1);

defined('TYPO3') or die();

// The static include of the theme is a content rendering template.
//
// "ExtensionUtility::configurePlugin()" adds the rendering of every Extbase
// plugin CType - "tt_content.<signature> =< lib.contentElement" and its
// "20 = EXTBASEPLUGIN" - with "addTypoScript(..., 'defaultContentRendering')",
// and so does every extension adding a plugin the classic way. For a site using
// site sets that TypoScript is always added ("SysTemplateTreeBuilder::
// createSiteTemplateInclude()"). For a "sys_template" site it is added only
// directly after a static include listed here ("addStaticMagicFromGlobals()"),
// which is the role "fluid_styled_content" plays for the installations that
// use it. Without this entry the theme's static include renders every classic
// CType and every "theme_*" one, while EXT:felogin's login form, and every other
// plugin, falls through to the core's "no rendering definition" notice on the
// static include path only. "ExtbasePluginStaticIncludeRenderingTest" holds
// it for a plugin of no extension's own, "FeloginRenderingTest" for the login
// form on both paths.
//
// The identifier is the extension key without underscores, a slash, and the
// path the static include is registered with in
// "Configuration/TCA/Overrides/sys_template.php", with a trailing slash - the
// string "SysTemplateTreeBuilder" builds from an "include_static_file" entry.
$GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'themeextensiondevelopment/Configuration/TypoScript/Static/';

// The rich text preset of the theme, "Configuration/RTE/Theme.yaml", selected
// by "Configuration/PageTsConfig/Rte.tsconfig". The registration is the same on
// TYPO3 v13.4 and v14.3 - "Richtext::loadConfigurationFromPreset()" reads this
// array on both.
//
// Only while rte_ckeditor is loaded. The preset imports the processing and
// editor files of that extension. An import that does not resolve is caught by
// "YamlFileLoader::processImports()" and logged as an error (#1485784246), so
// the preset would load without the core processing rules - and "Richtext"
// resolves it whenever DataHandler saves a rich text field, with or without an
// editor on screen: one error in the log per save, and a save processed by a
// preset that has no processing of its own. Unregistered, the page TSconfig
// names a preset that does not exist, and "Richtext" returns no configuration,
// exactly as for the core's "default" without rte_ckeditor.
//
// "??=": a preset of that name registered before this file stays.
if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('rte_ckeditor')) {
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['theme'] ??= 'EXT:theme_extension_development/Configuration/RTE/Theme.yaml';
}

// WebVTT, so a caption file can be uploaded at all.
//
// "tx_theme_captions" of "Text & Media" holds the caption track of a video or
// an audio file (see "Configuration/TCA/Overrides/tt_content.php"). A file
// reaches a storage only if its extension is in one of the three lists
// "ResourceConsistencyService::getAllowedFileExtensions()" reads -
// "textfile_ext", "mediafile_ext" and "miscfile_ext" - whenever the security
// feature "security.system.enforceAllowedFileExtensions" is on. The core ships
// "srt", the other subtitle format, in "textfile_ext", and "vtt" in none of
// the three (verified in "Configuration/DefaultConfiguration.php" of EXT:core
// on v13.4 and v14.3). Without this an editor cannot upload the file the field
// asks for, and the check fails with "Resource consistency check failed"
// rather than with anything naming the extension.
//
// "textfile_ext" rather than "mediafile_ext" on purpose: a WebVTT file is
// text - the transcript of a medium, not a medium of its own - and it is the
// list the core keeps "srt" in. It also stays out of "common-media-types",
// which resolves to "mediafile_ext" and is what the "assets" field accepts: a
// caption file is not something to put in the media field.
//
// Appended rather than replaced, and only when absent: the list belongs to the
// installation, and another extension or "additional.php" may have added to it
// first.
$themeTextFileExtensions = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
    ',',
    (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'] ?? ''),
    true,
);
if (!in_array('vtt', array_map('strtolower', $themeTextFileExtensions), true)) {
    $themeTextFileExtensions[] = 'vtt';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'] = implode(',', $themeTextFileExtensions);
}
unset($themeTextFileExtensions);
