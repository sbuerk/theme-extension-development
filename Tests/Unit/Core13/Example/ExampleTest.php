<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Core13\Example;

use PHPUnit\Framework\Attributes\Group;
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
 * The `not-core-12` group is what keeps them out of a run against the other
 * supported version: `Build/Scripts/runTests.sh` passes
 * `--exclude-group not-core-<version>`, so this class runs on v13 and is
 * excluded on v12 — where `Core13/` is not registered in the container at
 * all and the class under test is not the implementation the interface
 * resolves to.
 */
#[Group('not-core-12')]
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
