<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tca;

use SBUERK\ThemeExtensionDevelopment\Icon\IconCatalogue;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;

/**
 * A select field an editor picks an icon of the shipped set with.
 *
 * A column that stores an icon name declares
 *
 *     'config' => \SBUERK\ThemeExtensionDevelopment\Tca\IconItems::selectConfig(),
 *
 * and renders the stored name with "<theme:icon name="{…}" optional="1" />".
 *
 * The TCA holds the field, the "No icon" item, the groups and the wizard - a
 * few kilobytes. The icons themselves, two thousand items with an image path
 * each, are added by "addItems()" as the "itemsProcFunc" when a form is built,
 * rather than written into the TCA, which every request of an installation
 * loads: static items cost several hundred kilobytes of cached TCA per
 * column. Page TSconfig "keepItems", "addItems" and "removeItems" still narrow
 * the list, because the core resolves the "itemsProcFunc" first
 * ("TcaSelectItems::addData()", line 59 against 72-74 on v12.4.45, line 65
 * against 78-80 on v13.4.35).
 *
 * The list is built once by "IconCatalogue" - which reads every file of the
 * set to drop the aliases - and kept in the "core" cache as a PHP file, under
 * the fingerprint of the set and its categories, so a different set is a
 * different entry. Nothing is kept in the object: the cache is the state, and
 * it is flushed with every other cache.
 *
 * Each item's "icon" is the SVG file as an "EXT:" path, which FormEngine
 * renders as an "<img>" - it asks the icon registry only for something that
 * is not a file ("FormEngineUtility::getIconHtml()", read on v12.4 and v13.4)
 * - and the "selectIcons" field wizard shows those images as a grid.
 *
 * Public, because TYPO3 fetches an "itemsProcFunc" from the container by its
 * class name.
 */
#[Autoconfigure(public: true)]
final class IconItems
{
    /**
     * Where the icon of an item is found. An "EXT:" path, which both cores
     * resolve to the public file.
     */
    public const ICON_PATH = 'EXT:theme_extension_development/Resources/Public/Icons/FontAwesome/Solid/';

    public const NONE_LABEL = 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:icon.I.none';

    private const CACHE_PREFIX = 'theme_icon_items_';

    public function __construct(
        private readonly IconCatalogue $catalogue,
        #[Autowire(service: 'cache.core')]
        private readonly PhpFrontend $cache,
    ) {}

    /**
     * The "config" of a TCA column an editor picks an icon with.
     *
     * Static, because a TCA file has no container to take a service from; it
     * builds nothing but this array.
     *
     * @return array{
     *     type: 'select',
     *     renderType: 'selectSingle',
     *     default: '',
     *     items: list<array{label: string, value: string}>,
     *     itemGroups: array<string, string>,
     *     itemsProcFunc: string,
     *     fieldWizard: array{selectIcons: array{disabled: false}},
     * }
     */
    public static function selectConfig(): array
    {
        return [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => '',
            'items' => [
                ['label' => self::NONE_LABEL, 'value' => ''],
            ],
            'itemGroups' => (new IconCatalogue())->groups(),
            'itemsProcFunc' => self::class . '->addItems',
            'fieldWizard' => [
                'selectIcons' => [
                    'disabled' => false,
                ],
            ],
        ];
    }

    /**
     * Appends every icon of the set to the items the field declares.
     *
     * @param array{items?: list<array<string, mixed>>} $parameters
     * @param-out array{items: list<array<string, mixed>>} $parameters
     */
    public function addItems(array &$parameters): void
    {
        $items = $parameters['items'] ?? [];
        foreach ($this->icons() as $icon) {
            $items[] = [
                'label' => $icon['name'],
                'value' => $icon['name'],
                'icon' => self::ICON_PATH . $icon['name'] . '.svg',
                'group' => $icon['group'],
            ];
        }
        $parameters['items'] = $items;
    }

    /**
     * @return list<array{name: string, group: string}>
     */
    private function icons(): array
    {
        $identifier = self::CACHE_PREFIX . $this->catalogue->fingerprint();
        if ($this->cache->has($identifier)) {
            $cached = $this->cache->require($identifier);
            if (is_array($cached)) {
                /** @var list<array{name: string, group: string}> $cached */
                return $cached;
            }
        }

        $icons = $this->catalogue->icons();
        $this->cache->set($identifier, 'return ' . var_export($icons, true) . ';');

        return $icons;
    }
}
