<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Every table class an editor can pick reaches the table as its modifier.
 *
 * The list of values is not written down here. It is read the way the
 * backend builds the select: the items of the core's `table_class` TCA plus
 * the `addItems` of the page TSconfig the theme ships
 * (`Configuration/PageTsConfig/TCEFORM/TableClass.tsconfig`). A value added to
 * either without a fixture element fails the first test, and a value whose
 * element does not render `theme-table--<value>` fails the second - both of
 * which would otherwise look like a table that simply is not striped.
 */
final class TableClassRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithTables.csv');
        $this->setUpThemeSite();
    }

    /**
     * The values of the select, as the backend offers them on page 1.
     *
     * @return list<string>
     */
    private function offeredTableClasses(): array
    {
        $values = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['table_class']['config']['items'] ?? [] as $item) {
            $values[] = (string)($item['value'] ?? '');
        }

        $pageTsConfig = BackendUtility::getPagesTSconfig(1);
        foreach (array_keys($pageTsConfig['TCEFORM.']['tt_content.']['table_class.']['addItems.'] ?? []) as $value) {
            $values[] = (string)$value;
        }

        return array_values(array_filter(array_unique($values), static fn(string $value): bool => $value !== ''));
    }

    /**
     * The opening tag of every rendered table, by the uid of its element.
     *
     * @return array<int, string>
     */
    private function renderedTables(): array
    {
        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();

        preg_match_all('#id="c(\d+)".*?(<table class="[^"]*">)#s', $body, $matches, PREG_SET_ORDER);
        $tables = [];
        foreach ($matches as [, $uid, $tag]) {
            $tables[(int)$uid] = $tag;
        }

        return $tables;
    }

    #[Test]
    public function theThemeOffersItsModifiersNextToTheCoreItems(): void
    {
        $offered = $this->offeredTableClasses();
        sort($offered);

        $this->assertSame(
            ['bordered', 'borderless', 'compact', 'hover', 'sticky-header', 'striped', 'striped-columns'],
            $offered,
            'The table classes an editor can pick changed - add a fixture element and a modifier for a new one.',
        );
    }

    #[Test]
    public function everyOfferedTableClassRendersItsModifier(): void
    {
        $tables = $this->renderedTables();
        $this->assertCount(8, $tables, 'Not every table of the fixture rendered.');

        $rendered = implode("\n", $tables);
        foreach ($this->offeredTableClasses() as $value) {
            $this->assertStringContainsString(
                sprintf('<table class="theme-table theme-table--%s">', $value),
                $rendered,
                sprintf('The table class "%s" does not reach the table.', $value),
            );
        }
    }

    #[Test]
    public function aTableWithoutAClassRendersTheBareComponent(): void
    {
        $this->assertSame('<table class="theme-table">', $this->renderedTables()[10] ?? null);
    }
}
