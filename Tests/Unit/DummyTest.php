<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Dummy;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DummyTest extends UnitTestCase
{
    #[Test]
    public function getExtensionKeyReturnsExtensionKey(): void
    {
        $this->assertSame('theme_extension_development', (new Dummy())->getExtensionKey());
    }
}
