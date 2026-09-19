<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Tca;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\ThemeExtensionDevelopment\Tca\IconItems;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class IconItemsTest extends UnitTestCase
{
    #[Test]
    public function everyIconIsAppendedInOrderAfterTheItemsTheFieldDeclares(): void
    {
        $parameters = ['items' => [['label' => 'No icon', 'value' => '']]];

        (new IconItems(new IconSet()))->addItems($parameters);

        $expected = [['label' => 'No icon', 'value' => '']];
        foreach ((new IconSet())->names() as $name) {
            $expected[] = ['label' => $name, 'value' => $name];
        }
        $this->assertGreaterThan(1, count($expected), 'No icon was found - the set is missing.');
        $this->assertSame($expected, $parameters['items']);
    }

    #[Test]
    public function aFieldWithoutItemsGetsEveryIcon(): void
    {
        $parameters = [];

        (new IconItems(new IconSet()))->addItems($parameters);

        $this->assertSame(
            (new IconSet())->names(),
            array_column($parameters['items'], 'value'),
        );
    }
}
