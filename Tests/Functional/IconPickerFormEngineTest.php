<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconCatalogue;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\Utility\FormEngineUtility;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * What an editor is offered when picking an icon.
 *
 * `IconItemsTest` and `IconCatalogueTest` hold the configuration and the list.
 * Whether the form shows that is a question of the FormEngine data providers,
 * on each core: the icons come from an "itemsProcFunc", and page TSconfig
 * "keepItems" has to narrow them - it does only because the core resolves
 * the "itemsProcFunc" first - without losing the "No icon" item; the groups
 * have to come out as the headings of the list; and every item's icon has to
 * be the image of its file.
 *
 * The picker itself is compiled for the column of the fixture extension
 * `tests/icon-picker-fixture`, declared with `IconItems::selectConfig()`
 * exactly as any such column is, and narrowed by page TSconfig of the test
 * page. The icon fields of the theme are compiled with the page TSconfig the
 * theme ships, "Configuration/PageTsConfig/IconPicker.tsconfig".
 */
final class IconPickerFormEngineTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'tests/icon-picker-fixture',
    ];

    private const FIELD = 'tx_iconpickerfixture_icon';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/IconPicker.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @return array<string, mixed> The compiled form data of one record.
     */
    private function compile(string $table, int $uid): array
    {
        $request = (new ServerRequest('https://theme.example.com/typo3/record/edit', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        return GeneralUtility::makeInstance(FormDataCompiler::class)->compile(
            [
                'request' => $request,
                'tableName' => $table,
                'vanillaUid' => $uid,
                'command' => 'edit',
            ],
            GeneralUtility::makeInstance(TcaDatabaseRecord::class),
        );
    }

    /**
     * @param array<string, mixed> $result
     * @return list<array<string, mixed>> The items of a field, group headings included.
     */
    private function items(array $result, string $field): array
    {
        $items = $result['processedTca']['columns'][$field]['config']['items'] ?? null;
        $this->assertIsArray($items, sprintf('The form has no "%s" field.', $field));

        return array_values($items);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string> The values an editor can pick, without the group headings.
     */
    private function values(array $items): array
    {
        return array_values(array_filter(
            array_map(static fn(array $item): string => (string)$item['value'], $items),
            static fn(string $value): bool => $value !== '--div--',
        ));
    }

    /**
     * @return list<string> Every name of the catalogue, sorted.
     */
    private function catalogueNames(): array
    {
        $names = array_column((new IconCatalogue())->icons(), 'name');
        sort($names);

        return $names;
    }

    /**
     * "keepItems" narrows the icons the "itemsProcFunc" added, and the empty
     * entry its value starts with keeps "No icon". Were "keepItems" applied
     * before the "itemsProcFunc", every icon would still be offered.
     */
    #[Test]
    public function pageTsConfigNarrowsTheIconsAndKeepsNoIcon(): void
    {
        $values = $this->values($this->items($this->compile('tt_content', 10), self::FIELD));

        $this->assertSame('', $values[0] ?? null, 'The "No icon" item is gone, and the first icon would be stored instead.');
        $offered = array_slice($values, 1);
        sort($offered);
        $this->assertSame(['arrow-right', 'circle-info', 'envelope'], $offered);
    }

    /**
     * Without page TSconfig, every icon of the catalogue - the set without
     * the aliases of renamed icons - after "No icon".
     */
    #[Test]
    public function withoutPageTsConfigEveryIconOfTheCatalogueIsOffered(): void
    {
        $values = $this->values($this->items($this->compile('tt_content', 20), self::FIELD));

        $offered = array_slice($values, 1);
        sort($offered);
        $this->assertSame('', $values[0] ?? null);
        $this->assertSame($this->catalogueNames(), $offered);
        $this->assertNotContains('arrow-circle-right', $offered, 'The alias of a renamed icon is offered.');
    }

    /**
     * The groups arrive as headings of the list: a "--div--" item carrying the
     * label of the category, followed by its icons.
     */
    #[Test]
    public function theIconsAreListedUnderTheirFontAwesomeCategory(): void
    {
        $heading = [];
        $current = '';
        foreach ($this->items($this->compile('tt_content', 20), self::FIELD) as $item) {
            if ($item['value'] === '--div--') {
                $current = (string)$item['label'];
                continue;
            }
            $heading[(string)$item['value']] = $current;
        }

        $this->assertSame('', $heading[''] ?? null, 'The "No icon" item is not the ungrouped first entry.');
        $this->assertSame('Arrows', $heading['arrow-right'] ?? null);
        $this->assertSame('Business', $heading['envelope'] ?? null);
    }

    /**
     * The grid under the select shows each icon as the image of its file.
     *
     * Both cores check the file with "getFileAbsFileName()" and fall back to
     * the icon registry when there is none, which renders no "<img>" of the
     * file. So the assertion is on the path in the "src", which only a
     * working path produces.
     */
    #[Test]
    public function everyOfferedIconIsShownAsTheImageOfItsFile(): void
    {
        $result = $this->compile('tt_content', 10);
        $config = $result['processedTca']['columns'][self::FIELD]['config'];
        $this->assertFalse($config['fieldWizard']['selectIcons']['disabled'] ?? true, 'The icon grid is switched off.');

        $checked = 0;
        foreach ($this->items($result, self::FIELD) as $item) {
            if (($item['icon'] ?? '') === '') {
                continue;
            }
            $html = FormEngineUtility::getIconHtml($item['icon'], $item['label'], $item['label']);
            $this->assertMatchesRegularExpression(
                sprintf('#^<img [^>]*src="[^"]*Icons/FontAwesome/Solid/%s\.svg[^"]*"#', preg_quote((string)$item['value'], '#')),
                $html,
                sprintf('The icon of "%s" is not shown as its file.', $item['value']),
            );
            $checked++;
        }
        $this->assertSame(3, $checked);
    }

    /**
     * The icon fields of the theme: on page 1 with the page TSconfig the theme
     * ships, on page 3 with the curated list removed.
     *
     * @return \Generator<string, array{table: string, curated: int, every: int, field: string}>
     */
    public static function themeIconFields(): \Generator
    {
        yield 'content element link icon' => ['table' => 'tt_content', 'curated' => 30, 'every' => 40, 'field' => 'tx_theme_link_icon'];
        yield 'list item link icon' => ['table' => 'tx_theme_list_item', 'curated' => 1, 'every' => 2, 'field' => 'link_icon'];
        yield 'bullet list icon' => ['table' => 'tt_content', 'curated' => 70, 'every' => 80, 'field' => 'tx_theme_icon'];
        // The item compiled on its own, in the default type of the child
        // table, which shows the column.
        yield 'list item icon' => ['table' => 'tx_theme_list_item', 'curated' => 1, 'every' => 2, 'field' => 'icon'];
        yield 'call to action icon' => ['table' => 'tt_content', 'curated' => 90, 'every' => 100, 'field' => 'tx_theme_icon'];
        yield 'second link icon' => ['table' => 'tt_content', 'curated' => 90, 'every' => 100, 'field' => 'tx_theme_secondary_link_icon'];
    }

    /**
     * The shipped list reaches every icon field of the theme, keeps "No icon",
     * narrows rather than renames the whole set, and names only icons the
     * catalogue has - a name it does not have would silently not be offered.
     */
    #[DataProvider('themeIconFields')]
    #[Test]
    public function theIconFieldsOfTheThemeOfferTheCuratedIcons(string $table, int $curated, int $every, string $field): void
    {
        $result = $this->compile($table, $curated);
        $values = $this->values($this->items($result, $field));
        $keepItems = $result['pageTsConfig']['TCEFORM.'][$table . '.'][$field . '.']['keepItems'] ?? null;
        $this->assertIsString($keepItems, sprintf('The theme ships no "keepItems" for %s.%s.', $table, $field));
        $kept = GeneralUtility::trimExplode(',', $keepItems, true);
        sort($kept);

        $this->assertSame('', $values[0] ?? null, 'The "No icon" item is gone, and the first icon would be stored instead.');
        $offered = array_slice($values, 1);
        sort($offered);
        $this->assertSame([], array_values(array_diff($kept, $this->catalogueNames())), 'The curated list names icons the catalogue does not offer.');
        $this->assertSame($kept, $offered);
        $this->assertGreaterThan(50, count($offered));
        $this->assertLessThan(200, count($offered));
    }

    #[DataProvider('themeIconFields')]
    #[Test]
    public function withoutTheCuratedListTheIconFieldsOfTheThemeOfferTheCatalogue(string $table, int $curated, int $every, string $field): void
    {
        $values = $this->values($this->items($this->compile($table, $every), $field));

        $offered = array_slice($values, 1);
        sort($offered);
        $this->assertSame('', $values[0] ?? null);
        $this->assertSame($this->catalogueNames(), $offered);
    }
}
