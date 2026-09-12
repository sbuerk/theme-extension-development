<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tca;

use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Every shipped icon as an item of a TCA select field, for an editor to pick
 * an icon by name.
 *
 * No field uses it yet. A content element that lets an editor choose an icon
 * declares a "select" column with this as its "itemsProcFunc" and renders the
 * stored name with "<theme:icon name="{data.…}" />" - see
 * "docs/development/icons.md".
 *
 * The items are appended after whatever the field declares itself, so a field
 * can offer an empty "no icon" item first. Label and value are both the name:
 * the names are English words and are what an integrator writes in a
 * template, so a translated label would be one more thing to keep in step with
 * a version bump of the set.
 *
 * Public, because TYPO3 fetches an "itemsProcFunc" from the container by its
 * class name.
 */
#[Autoconfigure(public: true)]
final readonly class IconItems
{
    public function __construct(
        private IconSet $iconSet,
    ) {}

    /**
     * @param array{items?: list<array<string, mixed>>} $parameters
     * @param-out array{items: list<array<string, mixed>>} $parameters
     */
    public function addItems(array &$parameters): void
    {
        $items = $parameters['items'] ?? [];
        foreach ($this->iconSet->names() as $name) {
            $items[] = ['label' => $name, 'value' => $name];
        }
        $parameters['items'] = $items;
    }
}
