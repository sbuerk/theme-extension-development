<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Dummy;

final class DummyTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function dummyIsRetrievableFromDependencyInjectionContainer(): void
    {
        $this->assertInstanceOf(Dummy::class, $this->get(Dummy::class));
    }
}
