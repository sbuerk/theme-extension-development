<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Example;

use Symfony\Contracts\Service\Attribute\Required;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Shared implementation of all core version aware {@see ExampleInterface}
 * implementations.
 *
 * Abstract classes must not use constructor injection: the constructor belongs
 * to the extending classes. Dependencies are injected with `inject*()` methods
 * carrying the `#[Required]` attribute instead, so adding a dependency here
 * never changes the constructor signature of the extending classes and never
 * breaks their API.
 *
 * The class is **not** `readonly` — readonly classes are PHP 8.2 and this
 * branch supports PHP 8.1 for TYPO3 v12 — so the property below carries the
 * keyword itself, which is what keeps the state immutable
 * (`docs/architecture/class-design.md`). PHP allows a readonly property to be
 * initialized by any method of its declaring class, which is exactly what the
 * injection method below does; only PHPStan insists on the constructor, which
 * is why the two findings are ignored by their identifier. That combination is
 * the documented way of writing an injected abstract base class here — see the
 * "Class design" section of CONTRIBUTING.md.
 */
abstract class AbstractExample implements ExampleInterface
{
    /** @phpstan-ignore property.uninitializedReadonly */
    protected readonly Typo3Version $typo3Version;

    #[Required]
    public function injectTypo3Version(Typo3Version $typo3Version): void
    {
        /** @phpstan-ignore property.readOnlyAssignNotInConstructor */
        $this->typo3Version = $typo3Version;
    }

    protected function coreMajorVersion(): int
    {
        return $this->typo3Version->getMajorVersion();
    }
}
