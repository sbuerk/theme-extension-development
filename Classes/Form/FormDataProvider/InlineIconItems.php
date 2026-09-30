<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Form\FormDataProvider;

use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\ThemeExtensionDevelopment\Imaging\IconProvider\ShippedIconProvider;
use SBUERK\ThemeExtensionDevelopment\Tca\IconItems;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Core\Imaging\IconRegistry;

/**
 * Shows the icons of an icon picker in the colour of the text around them.
 *
 * The items of a picker carry the icon file as an "EXT:" path, see
 * "IconItems". "FormEngineUtility::getIconHtml()" renders such a path as an
 * "<img>", for the grid of the "selectIcons" wizard and for the selected item
 * in front of the select alike, and an "<img>" inherits no "currentColor":
 * the icons are drawn in black, which is invisible on the dark backend scheme.
 * An icon identifier is asked from the icon registry instead, and rendered
 * with the markup of its provider ("getIconHtml()", read on v13.4.35 and
 * v14.3.7). "ShippedIconProvider" makes that the SVG itself, drawn in the
 * colour of the text, in either scheme.
 *
 * So this provider runs after "TcaSelectItems", which has resolved the
 * "itemsProcFunc" and applied "keepItems", "addItems" and "removeItems", and
 * swaps the path of every item that is still offered for an identifier it
 * registers on the spot. Registered there and not in "Configuration/Icons.php",
 * because the registry is built on every backend request: the 2001 icons of
 * the solid set cost 12 ms and 1.5 MB there, measured with the CLI of both
 * cores, while a field narrowed by the shipped page TSconfig shows about a
 * hundred, and only in a form. Registered at runtime, an identifier exists for
 * the request that renders it and nowhere else. It is not meant for anything
 * but the picker.
 *
 * Every select column is looked at, not only those of the theme: a column of
 * another extension that takes its config from "IconItems" has the same
 * paths. An item whose icon is anything else is left as it is, and so is the
 * path of a name that is not one of an icon, which "IconSet::NAME_PATTERN"
 * decides before the name becomes part of an identifier. An item keeps the
 * path until this provider has run, so a form data group without it renders
 * the image, as before.
 *
 * Stateless: the registry is the core's, and it keeps what is registered for
 * the rest of the request.
 */
#[Autoconfigure(public: true)]
final readonly class InlineIconItems implements FormDataProviderInterface
{
    /**
     * The identifier of an icon is this, its set and its name:
     * "theme-extension-development-solid-arrow-right",
     * "theme-extension-development-brands-mastodon".
     */
    public const IDENTIFIER_PREFIX = 'theme-extension-development-';

    public function __construct(
        private IconRegistry $iconRegistry,
    ) {}

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function addData(array $result): array
    {
        if (!is_array($result['processedTca']['columns'] ?? null)) {
            return $result;
        }
        foreach ($result['processedTca']['columns'] as $fieldName => $column) {
            if (($column['config']['type'] ?? '') !== 'select' || !is_array($column['config']['items'] ?? null)) {
                continue;
            }
            foreach ($column['config']['items'] as $index => $item) {
                $icon = $this->shippedIcon(is_array($item) ? ($item['icon'] ?? null) : null);
                if ($icon === null) {
                    continue;
                }
                $identifier = self::IDENTIFIER_PREFIX . $icon['set'] . '-' . $icon['name'];
                if (!$this->iconRegistry->isRegistered($identifier)) {
                    $this->iconRegistry->registerIcon($identifier, ShippedIconProvider::class, $icon);
                }
                $result['processedTca']['columns'][$fieldName]['config']['items'][$index]['icon'] = $identifier;
            }
        }

        return $result;
    }

    /**
     * The set and the name of the icon of an item, or null for an icon that
     * is not a file of the shipped sets.
     *
     * @return array{set: 'solid'|'brands', name: string}|null
     */
    private function shippedIcon(mixed $icon): ?array
    {
        if (!is_string($icon)) {
            return null;
        }
        foreach ([IconItems::ICON_PATH => 'solid', IconItems::BRAND_ICON_PATH => 'brands'] as $path => $set) {
            if (!str_starts_with($icon, $path) || !str_ends_with($icon, '.svg')) {
                continue;
            }
            $name = substr($icon, strlen($path), -strlen('.svg'));

            return preg_match(IconSet::NAME_PATTERN, $name) === 1 ? ['set' => $set, 'name' => $name] : null;
        }

        return null;
    }
}
