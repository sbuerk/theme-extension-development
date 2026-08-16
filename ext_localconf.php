<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// This file exists for TYPO3 v12 only. On v13 it does nothing at all, and it is
// meant to disappear with v12 support.
//
// Both blocks below repair something the core does for us on v13 and not on
// v12. Neither can be solved the way this extension solves version differences
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
// The major version alone is precise enough for both blocks even though the
// first one describes a v13.2 change: "composer.json" requires
// "typo3/cms-core: ^12.4.22 || ^13.4", so a running v13 is at least 13.4 and
// there is no reachable v13.0 or v13.1 for the major to be wrong about.
if ((new Typo3Version())->getMajorVersion() < 13) {
    // -----------------------------------------------------------------------
    // 1) "lib.parseFunc" and "lib.parseFunc_RTE"
    // -----------------------------------------------------------------------
    //
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

    // -----------------------------------------------------------------------
    // 2) TypoScript registered as "defaultContentRendering"
    // -----------------------------------------------------------------------
    //
    // "ExtensionUtility::configurePlugin()" registers the TypoScript that makes
    // an Extbase plugin renderable - "tt_content.<signature> =< lib.contentElement"
    // - through "ExtensionManagementUtility::addTypoScript(..., 'defaultContentRendering')".
    // It lands in
    // $GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup.']['defaultContentRendering'],
    // and it is only ever included next to a static template that has declared
    // itself to be *the* content rendering definition of the installation.
    //
    // On v12 that declaration is the only way in. Every path into that array
    // goes through the same gate - "SysTemplateTreeBuilder::addStaticMagicFromGlobals()"
    // (.Build/vendor/typo3/cms-core/Classes/TypoScript/IncludeTree/SysTemplateTreeBuilder.php:462),
    // "TreeFromLineStreamBuilder" (:557), and the legacy "TemplateService" (:862)
    // - and each of them asks "in_array($identifier, ...['FE']['contentRenderingTemplates'], true)"
    // first. That array is empty by default
    // ("EXT:core/Configuration/DefaultConfiguration.php": 'contentRenderingTemplates' => []),
    // it is normally filled by "fluid_styled_content", and this theme does not
    // depend on that. So on v12, without the line below, no Extbase plugin in
    // the installation has a rendering definition: measured on the v12 leg,
    // "Tests/Functional/ExtbasePluginRenderingTest" failed all three of its
    // tests, the third one on the yellow "has no rendering definition" notice
    // the core prints for an unrendered CType.
    //
    // On v13 the line is not needed, and that is a code path difference rather
    // than a default: a site set does not go through
    // "addStaticMagicFromGlobals()" at all. "SysTemplateTreeBuilder::createSiteTemplateInclude()"
    // calls "addContentRenderingFromGlobals()" unconditionally
    // (instance-core-13/vendor/typo3/cms-core/Classes/TypoScript/IncludeTree/SysTemplateTreeBuilder.php:199),
    // with no "contentRenderingTemplates" lookup anywhere near it, so every
    // "defaultContentRendering" contribution is included for every set based
    // site. The site set is how the theme is delivered on v13.
    //
    // That path arrived with "Feature: #103437 - Introduce Site Sets" in TYPO3
    // v13.1, which is also why the difference has no changelog of its own:
    // neither #103437 nor "Feature: #103439 - TypoScript provider for sites and
    // sets" mentions "defaultContentRendering" at all. The two source positions
    // above are the whole evidence, and they were read, not inferred.
    //
    // ## The identifier
    //
    // It is not a free label. The core builds it from the static include the
    // "sys_template" record selects, and compares the built string with the
    // entries of this array, so the entry has to be the string the core will
    // build. "handleSingleIncludeStaticFile()" splits
    // "EXT:theme_extension_development/Configuration/TypoScript/Static" - the
    // value registered by "Configuration/TCA/Overrides/sys_template.php" and
    // written by "Tests/Functional/Core12/ThemeDelivery" - into extension key
    // and path, then looks up
    // "str_replace('_', '', $extensionKey) . '/' . rtrim($path) . '/'"
    // (SysTemplateTreeBuilder.php:318 and :353-354). That is:
    //
    //   "theme_extension_development" -> "themeextensiondevelopment"
    //   + "/" + "Configuration/TypoScript/Static" + "/"
    //
    // Underscores removed, trailing slash added - the same shape
    // "fluid_styled_content" registers for itself as
    // "fluidstyledcontent/Configuration/TypoScript/", and the shape
    // "ExtensionManagementUtility::addTypoScript()" documents as
    // "[reduced extension_key]/[local path]".
    //
    // Consequence worth naming: this declares the theme to be the content
    // rendering definition of the installation, which it is - it defines
    // "tt_content" for every element it ships plus "lib.contentElement". An
    // installation that also installs "fluid_styled_content" then has two, and
    // the later static include wins per object path. That is the same trade
    // every site package makes and it is not v12 specific.
    //
    // @todo Remove this block as soon as support for TYPO3 v12 is dropped.
    $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'themeextensiondevelopment/Configuration/TypoScript/Static/';
}
