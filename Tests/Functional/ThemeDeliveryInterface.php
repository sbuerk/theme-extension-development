<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

/**
 * How a functional test arranges a site that delivers the theme.
 *
 * There is exactly one thing about the rendering tests that depends on the core
 * version, and this is it. TYPO3 v13 delivers the theme through the site set:
 * the site declares `dependencies: [sbuerk/theme-extension-development]` and the
 * root page carries no `sys_template` record at all. TYPO3 v12 has no site sets
 * — they arrived in v13.1 with #103437 — so there the theme is delivered the
 * classic way, through a `sys_template` record selecting the static include
 * registered by `Configuration/TCA/Overrides/sys_template.php`.
 *
 * The two are mutually exclusive rather than merely different, which is why
 * this is an interface with two implementations instead of a conditional
 * somewhere in a `setUp()`. Arranging both at once does not work on v13:
 * `setUpFrontendRootPage()` hard-codes `'clear' => 3` on the record it writes,
 * a clear-flagged `SysTemplateInclude` resets the whole AST built so far
 * (`IncludeTreeAstBuilderVisitor::visitBeforeChildren()`), and the site set node
 * is added *before* the `sys_template` rows
 * (`SysTemplateTreeBuilder::getTreeBySysTemplateRowsAndSite()`) — so the record
 * would throw away everything the set delivered, while the condition guarding
 * the static include would suppress the import because a set *is* active. The
 * page renders empty.
 *
 * ## Why this describes an arrangement instead of performing it
 *
 * `writeSiteConfiguration()` and `setUpFrontendRootPage()` are `protected`
 * members of the test case. An implementation of this interface is a plain
 * object and cannot call them, and handing it the test case so it can reach
 * through would trade one seam for a much wider one. So the implementations
 * return *what* to arrange and {@see ThemeSiteTrait} does the arranging — which
 * has the pleasant side effect that the two implementations are directly
 * assertable, see the `ThemeDeliveryTest` beside each of them.
 *
 * @see ThemeSiteTrait
 * @see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core12\ThemeDelivery
 * @see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13\ThemeDelivery
 */
interface ThemeDeliveryInterface
{
    /**
     * Keys added to the site configuration array so the site delivers the
     * theme, empty when the delivery does not go through the site.
     *
     * @return array<string, mixed>
     */
    public function siteConfiguration(): array;

    /**
     * Field values for the `sys_template` record, empty when none is written.
     *
     * Passed as the `$templateValues` argument of `setUpFrontendRootPage()`,
     * which merges them over its own defaults.
     *
     * @return array<string, mixed>
     */
    public function templateValues(): array;

    /**
     * Whether a `sys_template` record is written at all — the fourth argument
     * of `setUpFrontendRootPage()` of `sbuerk/typo3-site-based-test-trait`.
     *
     * A set based delivery must answer `false` here: a record would be written
     * with `clear = 3` and discard the TypoScript of the set.
     */
    public function createsSysTemplateRecord(): bool;
}
