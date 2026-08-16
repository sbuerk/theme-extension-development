<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Asserts that a test run happens against the TYPO3 version this extension
 * supports.
 *
 * **Never drop the tests using this trait.** They are the cheapest guard this
 * repository has against a whole class of silent failure: a gate that runs, is
 * green, and proves nothing because it ran against the wrong core version. The
 * dependency set is installed separately from the version a suite is run for —
 * `-t 13` selects a version but installs nothing — so a stale `.Build/`, a
 * misconfigured CI matrix entry or a `composerUpdate` that was skipped all
 * produce a run that looks exactly like a real one.
 *
 * The single assertion covers exactly that: running with `-t 13` really is
 * TYPO3 v13, and anything else — a `.Build/` left over from another version, a
 * CI job that installed something else than it selected — fails here rather
 * than somewhere deep in a feature test, or not at all.
 *
 * The assertion is deliberately ungrouped. `Build/Scripts/runTests.sh` passes
 * `--exclude-group not-core-<version>` so that a version specific test can be
 * skipped for the versions it does not apply to; with a single supported
 * version there is nothing to exclude, and a group would only create the
 * possibility of a guard that never executes.
 *
 * @see \SBUERK\ThemeExtensionDevelopment\Tests\Unit\VersionCompatTest
 * @see \SBUERK\ThemeExtensionDevelopment\Tests\Functional\ExtensionLoadedTest
 */
trait ExtensionCoreVersionCompatTestsTrait
{
    private const SUPPORTED_MAJOR_VERSION = 13;

    #[Test]
    public function runsAgainstTheSupportedMajorVersion(): void
    {
        $this->assertSame(self::SUPPORTED_MAJOR_VERSION, (new Typo3Version())->getMajorVersion());
    }
}
