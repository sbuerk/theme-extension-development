<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13\Example;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Core13\Example\Example;
use SBUERK\ThemeExtensionDevelopment\Example\ExampleInterface;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;

/**
 * Verifies the core version aware dependency injection wiring on TYPO3 v13.
 *
 * Core version aware tests live below `Tests/Functional/Core13/`, mirroring the
 * `Core13/` source directory they cover.
 *
 * They carry no PHPUnit group: `Build/Scripts/runTests.sh` passes
 * `--exclude-group not-core-<version>` so a test can be kept out of a run
 * against a core version it does not apply to, and with a single supported
 * version there is no such run to exclude it from.
 */
final class ExampleTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function interfaceIsAliasedToCoreVersionAwareImplementation(): void
    {
        $this->assertInstanceOf(Example::class, $this->get(ExampleInterface::class));
    }

    #[Test]
    public function exampleReturnsCoreVersionAwareValue(): void
    {
        $this->assertSame(
            'Example implementation for TYPO3 v13',
            $this->get(ExampleInterface::class)->example(),
        );
    }
}
