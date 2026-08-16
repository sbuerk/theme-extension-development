<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Core12\Example;

use SBUERK\ThemeExtensionDevelopment\Example\AbstractExample;
use SBUERK\ThemeExtensionDevelopment\Example\ExampleInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * TYPO3 v12 implementation of {@see ExampleInterface}.
 *
 * Only the `Core12/` directory is registered in the dependency
 * injection container when running on TYPO3 v12, see
 * `Configuration/Services.php`. `#[AsAlias]` makes this class the default
 * implementation of the interface, so consumers type hint the interface and
 * receive the implementation matching the running TYPO3 version.
 *
 * The class is plain `final`. Readonly classes are PHP 8.2, and this branch
 * supports PHP 8.1 for TYPO3 v12, so immutability is declared per property —
 * here on the base class' injected property. See
 * `docs/architecture/class-design.md`.
 *
 * @todo Remove along with the interface and its tests as soon as the first real
 *       implementation is added.
 */
#[AsAlias(id: ExampleInterface::class, public: true)]
final class Example extends AbstractExample
{
    public function example(): string
    {
        return sprintf('Example implementation for TYPO3 v%d', $this->coreMajorVersion());
    }
}
