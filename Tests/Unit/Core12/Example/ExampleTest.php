<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Core12\Example;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Core12\Example\Example;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests of the TYPO3 v12 implementation living in `Core12/`.
 *
 * Core version aware tests live below `Tests/Unit/Core12/`, mirroring the
 * `Core12/` source directory they cover.
 *
 * The `not-core-13` group is what keeps them out of a run against the other
 * supported version: `Build/Scripts/runTests.sh` passes
 * `--exclude-group not-core-<version>`, so this class runs on v12 and is
 * excluded on v13 — where `Core12/` is not registered in the container at
 * all and the class under test is not the implementation the interface
 * resolves to.
 */
#[Group('not-core-13')]
final class ExampleTest extends UnitTestCase
{
    #[Test]
    public function exampleReturnsCoreVersionAwareValue(): void
    {
        $subject = new Example();
        $subject->injectTypo3Version(new Typo3Version());

        $this->assertSame('Example implementation for TYPO3 v12', $subject->example());
    }
}
