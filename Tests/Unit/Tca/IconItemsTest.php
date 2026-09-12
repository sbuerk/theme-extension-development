<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Tca;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconCatalogue;
use SBUERK\ThemeExtensionDevelopment\Tca\IconItems;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class IconItemsTest extends UnitTestCase
{
    /**
     * The TCA carries the field and its groups, not the icons: two thousand
     * items per column would be cached TCA every request loads.
     */
    #[Test]
    public function theFieldDeclaresNoIconAndTheGroupsAndLeavesTheIconsToTheItemsProcFunc(): void
    {
        $config = IconItems::selectConfig();

        $this->assertSame('select', $config['type']);
        $this->assertSame('selectSingle', $config['renderType']);
        $this->assertSame('', $config['default']);
        $this->assertSame([['label' => IconItems::NONE_LABEL, 'value' => '']], $config['items']);
        $this->assertSame((new IconCatalogue())->groups(), $config['itemGroups']);
        $this->assertSame(IconItems::class . '->addItems', $config['itemsProcFunc']);
        $this->assertFalse($config['fieldWizard']['selectIcons']['disabled']);
    }

    /**
     * Built by the catalogue, written to the cache, and appended after the
     * items the field declares, each shown as its own file of the set.
     */
    #[Test]
    public function theIconsOfTheCatalogueAreAppendedAndCachedWhenNoListIsCached(): void
    {
        $catalogue = new IconCatalogue();
        $cache = $this->createMock(PhpFrontend::class);
        $cache->method('has')->willReturn(false);
        $cache->expects($this->never())->method('require');
        $cache->expects($this->once())->method('set')->with(
            'theme_icon_items_' . $catalogue->fingerprint(),
            'return ' . var_export($catalogue->icons(), true) . ';',
        );
        $parameters = ['items' => [['label' => 'No icon', 'value' => '']]];

        (new IconItems($catalogue, $cache))->addItems($parameters);

        $expected = [['label' => 'No icon', 'value' => '']];
        foreach ($catalogue->icons() as $icon) {
            $expected[] = [
                'label' => $icon['name'],
                'value' => $icon['name'],
                'icon' => IconItems::ICON_PATH . $icon['name'] . '.svg',
                'group' => $icon['group'],
            ];
        }
        $this->assertGreaterThan(1000, count($expected));
        $this->assertSame($expected, $parameters['items']);
    }

    /**
     * A cached list is used as it is: the catalogue, which reads every file
     * of the set, is not asked again.
     */
    #[Test]
    public function aCachedListIsUsedWithoutBuildingItAgain(): void
    {
        $cache = $this->createMock(PhpFrontend::class);
        $cache->method('has')->willReturn(true);
        $cache->method('require')->willReturn([['name' => 'cached-icon', 'group' => 'cached-group']]);
        $cache->expects($this->never())->method('set');
        $parameters = [];

        (new IconItems(new IconCatalogue(), $cache))->addItems($parameters);

        $this->assertSame(
            [[
                'label' => 'cached-icon',
                'value' => 'cached-icon',
                'icon' => IconItems::ICON_PATH . 'cached-icon.svg',
                'group' => 'cached-group',
            ]],
            $parameters['items'],
        );
    }
}
