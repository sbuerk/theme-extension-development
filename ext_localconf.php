<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// -----------------------------------------------------------------------------
// The static include of the theme is a content rendering template.
// -----------------------------------------------------------------------------
//
// "ExtensionUtility::configurePlugin()" adds the rendering of every Extbase
// plugin CType - "tt_content.<signature> =< lib.contentElement" and its
// "20 = EXTBASEPLUGIN" - with "addTypoScript(..., 'defaultContentRendering')",
// and so does every extension adding a plugin the classic way. It lands in
// $GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup.']['defaultContentRendering'].
//
// For a site using site sets that TypoScript is always added
// ("SysTemplateTreeBuilder::createSiteTemplateInclude()" calls
// "addContentRenderingFromGlobals()" with no lookup anywhere near it). For a
// "sys_template" site it is added only directly after a static include listed
// here ("addStaticMagicFromGlobals()", which asks
// "in_array($identifier, ...['FE']['contentRenderingTemplates'], true)" first).
// That array is empty by default ("EXT:core/Configuration/DefaultConfiguration.php":
// 'contentRenderingTemplates' => []) and is normally filled by
// "fluid_styled_content", which this theme deliberately does not depend on.
//
// Without this entry the theme's static include renders every classic CType
// and every "theme_*" one, while EXT:felogin's login form, and every other
// plugin, falls through to the core's "no rendering definition" notice on the
// static include path. That path is the only one on TYPO3 v12, which has no
// site sets, and the one every "sys_template" site takes on v13 - the
// "/legacy/" tree of the development instance among them. This entry was
// registered for v12 only before, on the reasoning that v13 delivers through
// the site set; that left a v13 site using the static include without plugin
// rendering. "ExtbasePluginRenderingTest" holds it on v12, where
// "ThemeSiteTrait" arranges the static include,
// "ExtbasePluginStaticIncludeRenderingTest" on both core versions, and
// "FeloginRenderingTest" the login form on both paths.
//
// The identifier is not a free label. The core builds it from the static
// include the "sys_template" record selects and compares the built string with
// the entries of this array. "handleSingleIncludeStaticFile()" splits
// "EXT:theme_extension_development/Configuration/TypoScript/Static" - the value
// registered by "Configuration/TCA/Overrides/sys_template.php" - into extension
// key and path, then looks up
// "str_replace('_', '', $extensionKey) . '/' . rtrim($path) . '/'". That is:
//
//   "theme_extension_development" -> "themeextensiondevelopment"
//   + "/" + "Configuration/TypoScript/Static" + "/"
//
// Underscores removed, trailing slash added - the same shape
// "fluid_styled_content" registers for itself as
// "fluidstyledcontent/Configuration/TypoScript/", and the shape
// "ExtensionManagementUtility::addTypoScript()" documents as
// "[reduced extension_key]/[local path]". The lookup is the same in
// "SysTemplateTreeBuilder" of v12.4 and of v13.4.
//
// Consequence worth naming: this declares the theme to be the content
// rendering definition of the installation, which it is - it defines
// "tt_content" for every element it ships plus "lib.contentElement". An
// installation that also installs "fluid_styled_content" then has two, and the
// later static include wins per object path. That is the same trade every site
// package makes.
$GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'themeextensiondevelopment/Configuration/TypoScript/Static/';

// -----------------------------------------------------------------------------
// "lib.parseFunc" and "lib.parseFunc_RTE", for TYPO3 v12 only.
// -----------------------------------------------------------------------------
//
// The block below repairs something the core does for us on v13 and not on
// v12. It cannot be solved the way this extension solves version differences
// everywhere else - one class per core version below "Core12/" and "Core13/",
// selected by the container (see "docs/architecture/core-version-aware-code.md").
// "ext_localconf.php" is loaded by TYPO3 from a fixed path, long before a
// container exists, and it is loaded for exactly one file name. It is therefore
// the documented exception to that rule: the difference is applied to the
// finished configuration, in one place, with the condition under which it goes
// away written down.
//
// ## The version test
//
// "(new Typo3Version())->getMajorVersion()" is the spelling this repository
// already uses to answer "which core is running" - "Configuration/Services.php"
// picks the container directory with it, "Tests/Functional/ThemeSiteTrait"
// picks the test delivery with it. Using the same expression here keeps it one
// recognisable mechanism instead of three ideas. It is also the only spelling
// available at this point: "Typo3Version" reads a class constant, so it needs
// no container, no configuration and no database, all of which "ext_localconf.php"
// runs before.
//
// The test is "< 13" and not "=== 12", because what is being asked is "is this
// older than the version that brought the feature", not "is this exactly v12" -
// the same reading that keeps working if this file ever has to answer for an
// older core as well.
//
// The major version alone is precise enough even though the block describes a
// v13.2 change: "composer.json" requires "typo3/cms-core: ^12.4.22 || ^13.4",
// so a running v13 is at least 13.4 and there is no reachable v13.0 or v13.1
// for the major to be wrong about.
if ((new Typo3Version())->getMajorVersion() < 13) {
    // "EXT:frontend" provides both objects from TYPO3 v13.2 on - changelog
    // "Important: #103485 - Provide lib.parseFunc via ext:frontend". Before
    // that they came from a content rendering definition, in practice
    // "fluid_styled_content", which this theme deliberately does not depend on
    // (see "Tests/Functional/ExtbasePluginRenderingTest" for what that
    // independence is worth).
    //
    // Without them "<f:format.html>" - which this theme's templates use for
    // every rich text field - throws
    // "LogicException: Invoked ContentObjectRenderer::parseFunc without any
    // configuration". Measured on the v12 leg before this file existed: 65 of
    // 245 functional tests errored with exactly that exception.
    //
    // The TypoScript is not written here, it is taken: the loaded file is a
    // byte for byte copy of the block v13.4's "EXT:frontend/ext_localconf.php"
    // passes to "addTypoScriptSetup()". Reproducing it by hand would give v12
    // something that looks right and renders differently - the point is that
    // the two versions parse rich text identically, not similarly.
    //
    // Only the "lib.parseFunc*" half of that block is copied. The rest of it -
    // "styles.content.get" and the "tt_content = CASE" default with the yellow
    // "has no rendering definition" notice - is registered by v12's own
    // "EXT:frontend/ext_localconf.php" already, and adding it again would
    // overwrite a "tt_content" that other extensions may have contributed to in
    // between.
    //
    // "addTypoScriptSetup()" appends to
    // $GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup'].
    // "SysTemplateTreeBuilder::handleSysTemplateRecordInclude()" (:188-190)
    // includes that as the very first thing of a "sys_template" record that
    // carries the "clear setup" flag - which is what the backend sets on a root
    // template, and what "setUpFrontendRootPage()" writes in the tests - so it
    // lands before the record's static includes and before its own setup, and
    // an installation overriding "lib.parseFunc" keeps overriding it. That is
    // the "loaded early in the TypoScript chain" the changelog entry describes
    // for v13.
    //
    // That our block lands after v12's own "tt_content = CASE" inside that
    // string, instead of before it as v13 orders the two in one literal, makes
    // no difference: they write disjoint object paths.
    //
    // @todo Remove this block, and delete the loaded file, as soon as support
    //       for TYPO3 v12 is dropped.
    $parseFuncTypoScriptFile = ExtensionManagementUtility::extPath(
        'theme_extension_development',
        'Configuration/TypoScript/Compatibility/Core12/ParseFunc.typoscript',
    );
    $parseFuncTypoScript = file_get_contents($parseFuncTypoScriptFile);
    if ($parseFuncTypoScript === false) {
        // Not silently skipped. A missing file here presents as "every rich
        // text field on the site throws", with nothing pointing at the cause.
        throw new \RuntimeException(
            'The TYPO3 v12 compatibility TypoScript of EXT:theme_extension_development could not be read: '
            . $parseFuncTypoScriptFile,
            1786924901
        );
    }
    ExtensionManagementUtility::addTypoScriptSetup($parseFuncTypoScript);
}

// The rich text preset of the theme, "Configuration/RTE/Theme.yaml", selected
// by "Configuration/PageTsConfig/Rte.tsconfig". The registration is the same on
// TYPO3 v12.4 and v13.4 - "Richtext::loadConfigurationFromPreset()" reads this
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
