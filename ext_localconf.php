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
