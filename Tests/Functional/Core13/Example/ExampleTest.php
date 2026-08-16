<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13\Example;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Core13\Example\Example;
use SBUERK\ThemeExtensionDevelopment\Example\ExampleInterface;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;

/**
 * Tests of the TYPO3 v13 implementation living in `Core13/`.
 *
 * Core version aware tests live below `Tests/Functional/Core13/`, mirroring the
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

    #[Test]
    public function implementationOfTheOtherCoreVersionIsNotRegistered(): void
    {
        $this->assertFalse(
            $this->getContainer()->has(
                'SBUERK\\ThemeExtensionDevelopment\\Core12\\Example\\Example',
            ),
            'The other core version\'s implementation must not be registered on TYPO3 v13.',
        );
    }
}
