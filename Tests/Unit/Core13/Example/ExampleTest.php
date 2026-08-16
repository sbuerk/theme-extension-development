<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Core13\Example;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Core13\Example\Example;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests of the TYPO3 v13 implementation living in `Core13/`.
 *
 * Core version aware tests live below `Tests/Unit/Core13/`, mirroring the
 * `Core13/` source directory they cover.
 *
 * They carry no PHPUnit group: `Build/Scripts/runTests.sh` passes
 * `--exclude-group not-core-<version>` so a test can be kept out of a run
 * against a core version it does not apply to, and with a single supported
 * version there is no such run to exclude it from.
 */
final class ExampleTest extends UnitTestCase
{
    #[Test]
    public function exampleReturnsCoreVersionAwareValue(): void
    {
        $subject = new Example();
        $subject->injectTypo3Version(new Typo3Version());

        $this->assertSame('Example implementation for TYPO3 v13', $subject->example());
    }
}
