<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Asserts that a test run happens against a TYPO3 version this extension
 * supports, and that both supported versions are actually exercised.
 *
 * **Never drop the tests using this trait.** They are the cheapest guard this
 * repository has against a whole class of silent failure: a gate that runs, is
 * green, and proves nothing because it ran against the wrong core version. The
 * dependency set is installed separately from the version a suite is run for —
 * `-t 12` selects a version but installs nothing — so a stale `.Build/`, a
 * misconfigured CI matrix entry or a `composerUpdate` that was skipped all
 * produce a run that looks exactly like a real one.
 *
 * The three assertions cover that from both sides:
 *
 * - The running major version is one of the supported ones at all.
 * - Running with `-t 12` really is TYPO3 v12, and `-t 13` really is v13. This
 *   works through the group exclusion `Build/Scripts/runTests.sh` passes:
 *   `--exclude-group not-core-<version>` removes the assertion for the version
 *   that is *not* selected, so exactly one of the two remains — and it fails
 *   when what is installed disagrees with what was asked for.
 *
 * The version numbers are written out rather than kept in constants, and so are
 * the group names. An attribute argument has to be a constant expression, and a
 * trait cannot carry constants at all before PHP 8.1 is out of the supported
 * range — constants in traits are a PHP 8.2 feature, and this branch supports
 * 8.1 for TYPO3 v12.
 *
 * @todo Reintroduce the constants when PHP 8.1 support is dropped.
 *
 * @see \SBUERK\ThemeExtensionDevelopment\Tests\Unit\VersionCompatTest
 * @see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\ExtensionLoadedTest
 */
trait ExtensionCoreVersionCompatTestsTrait
{
    #[Test]
    public function runsAgainstASupportedMajorVersion(): void
    {
        $this->assertContains((new Typo3Version())->getMajorVersion(), [12, 13]);
    }

    #[Group('not-core-13')]
    #[Test]
    public function runsAgainstTheLowestSupportedMajorVersion(): void
    {
        $this->assertSame(12, (new Typo3Version())->getMajorVersion());
    }

    #[Group('not-core-12')]
    #[Test]
    public function runsAgainstTheHighestSupportedMajorVersion(): void
    {
        $this->assertSame(13, (new Typo3Version())->getMajorVersion());
    }
}
