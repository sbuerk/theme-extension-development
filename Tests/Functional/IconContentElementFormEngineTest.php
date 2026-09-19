<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The form of the icon content elements offers what their templates render,
 * where they render it, and nothing else.
 *
 * Which fields a type shows, which values a select offers and which
 * description a field carries on one type only are decided by the TCA of the
 * type - its "showitem" and "columnsOverrides". A field that is missing, or
 * that shows up on a type that ignores it, raises nothing. So this compiles
 * the form data the way the backend does when an editor opens an element, and
 * renders the form from it.
 */
final class IconContentElementFormEngineTest extends AbstractFunctionalTestCase
{
    private const TEXT_ICON = 1010;

    private const FEATURES = 1110;

    private const STATS = 1210;

    private const STEPS = 1310;

    /**
     * A notice of the fixture of the other theme elements: a theme element
     * that shows no icon.
     */
    private const NOTICE = 110;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithThemeContentElements.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/IconContentElements.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @param array<string, list<int>> $expandCollapseState The inline children
     *        shown open, by table - the state an editor's clicks store in the
     *        user settings, and what `TcaInlineExpandCollapseState` reads.
     * @return array<string, mixed>
     */
    private function compile(int $uid, array $expandCollapseState = []): array
    {
        // The route of the record editor: rendering the form, the containers
        // resolve their Fluid templates through "BackendViewFactory", which
        // reads the package name from the route of the request.
        $request = (new ServerRequest('https://theme.example.com/typo3/record/edit', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', new Route('/record/edit', ['packageName' => 'typo3/cms-backend']));
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        return GeneralUtility::makeInstance(FormDataCompiler::class)->compile(
            [
                'request' => $request,
                'tableName' => 'tt_content',
                'vanillaUid' => $uid,
                'command' => 'edit',
                'inlineExpandCollapseStateArray' => $expandCollapseState,
            ],
            GeneralUtility::makeInstance(TcaDatabaseRecord::class),
        );
    }

    /**
     * @param array<string, mixed> $result
     * @return list<string>
     */
    private static function itemValues(array $result, string $field): array
    {
        return array_values(array_map(
            static fn(array $item): string => (string)$item['value'],
            $result['processedTca']['columns'][$field]['config']['items'] ?? [],
        ));
    }

    /**
     * The form of a record as FormEngine renders it, through
     * "fullRecordContainer" - see "ContentElementAppearanceFormEngineTest".
     */
    private function renderedForm(int $uid): string
    {
        $formData = $this->compile($uid);
        $formData['renderType'] = 'fullRecordContainer';

        return (string)(GeneralUtility::makeInstance(NodeFactory::class)->create($formData)->render()['html'] ?? '');
    }

    private static function inputName(int $uid, string $field): string
    {
        return sprintf('name="data[tt_content][%d][%s]"', $uid, $field);
    }

    /**
     * @return \Generator<string, array{field: string, values: list<string>}>
     */
    public static function iconAxes(): \Generator
    {
        yield 'position' => ['field' => 'tx_theme_icon_position', 'values' => ['start', 'end', 'top']];
        yield 'shape' => ['field' => 'tx_theme_icon_shape', 'values' => ['plain', 'square', 'circle']];
        yield 'size' => ['field' => 'tx_theme_icon_size', 'values' => ['md', 'lg', 'xl']];
    }

    /**
     * Every value is one modifier of ".theme-media-object", and there is no
     * value the component does not style.
     *
     * @param list<string> $values
     */
    #[DataProvider('iconAxes')]
    #[Test]
    public function anAxisOfTheIconOffersTheModifiersOfTheComponent(string $field, array $values): void
    {
        $this->assertSame($values, self::itemValues($this->compile(self::TEXT_ICON), $field));
    }

    /**
     * The icon and its three axes are in the form of the text and icon
     * element, and in the form of no theme element that renders no icon.
     */
    #[Test]
    public function theIconAndItsAxesArePickedInTheTextAndIconElementOnly(): void
    {
        $fields = ['tx_theme_icon', 'tx_theme_icon_position', 'tx_theme_icon_shape', 'tx_theme_icon_size'];

        $textIcon = $this->renderedForm(self::TEXT_ICON);
        $this->assertStringContainsString(self::inputName(self::TEXT_ICON, 'bodytext'), $textIcon);
        foreach ($fields as $field) {
            $this->assertStringContainsString(self::inputName(self::TEXT_ICON, $field), $textIcon, sprintf('"%s" is missing from the text and icon element.', $field));
        }

        $notice = $this->renderedForm(self::NOTICE);
        $this->assertStringContainsString(self::inputName(self::NOTICE, 'header'), $notice);
        foreach ($fields as $field) {
            $this->assertStringNotContainsString(self::inputName(self::NOTICE, $field), $notice, sprintf('"%s" is offered on the notice, which ignores it.', $field));
        }
    }

    /**
     * The icon column describes the icon list of the bullet list. On this type
     * it says what it does here instead. FormEngine hands the form the
     * translated description, so that is what is compared.
     */
    #[Test]
    public function theIconIsDescribedForTheTextAndIconElement(): void
    {
        $prefix = 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_icon';
        $own = $GLOBALS['LANG']->sL($prefix . '.types.theme_text_icon.description');
        $this->assertNotSame('', $own, 'The description of the text and icon element is not translated.');
        $this->assertNotSame($GLOBALS['LANG']->sL($prefix . '.description'), $own);

        $this->assertSame(
            $own,
            $this->compile(self::TEXT_ICON)['processedTca']['columns']['tx_theme_icon']['description'] ?? null,
        );
    }

    /**
     * `layout` is disabled for every CType and re-enabled for the features,
     * whose template maps it onto the item layout. The four core values keep
     * their numbers and carry labels that describe a layout of features; the
     * columns offer the three modifiers of the grid.
     */
    #[Test]
    public function theFeaturesOfferTheirLayoutsAndColumnsUnderTheirOwnNames(): void
    {
        $result = $this->compile(self::FEATURES);

        $this->assertFalse((bool)($result['pageTsConfig']['TCEFORM.']['tt_content.']['layout.']['disabled'] ?? false), '"layout" is missing from the features.');
        $this->assertSame(['0', '1', '2', '3'], self::itemValues($result, 'layout'));
        $labels = array_values(array_map(
            static fn(array $item): string => (string)$item['label'],
            $result['processedTca']['columns']['layout']['config']['items'] ?? [],
        ));
        $expected = array_map(
            static fn(int $value): string => $GLOBALS['LANG']->sL(
                'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.layout.theme_features.I.' . $value,
            ),
            [0, 1, 2, 3],
        );
        $this->assertNotContains('', $expected, 'A label of the feature layouts is not translated.');
        $this->assertSame($expected, $labels);
        $this->assertSame(['2', '3', '4'], self::itemValues($result, 'tx_theme_columns'));

        $form = $this->renderedForm(self::FEATURES);
        $this->assertStringContainsString(self::inputName(self::FEATURES, 'layout'), $form);
        $this->assertStringContainsString(self::inputName(self::FEATURES, 'tx_theme_columns'), $form);

        // Still disabled on the text and icon element: the type specific part
        // applies to its type only.
        $this->assertTrue((bool)($this->compile(self::TEXT_ICON)['pageTsConfig']['TCEFORM.']['tt_content.']['layout.']['disabled'] ?? false));
        $this->assertStringNotContainsString(self::inputName(self::TEXT_ICON, 'layout'), $this->renderedForm(self::TEXT_ICON));
    }

    /**
     * The compiled form of the first inline child of a content element, the
     * way `TcaInline` compiles it with the configuration of its parent.
     *
     * The child is compiled open. A collapsed child is compiled with the
     * columns of its title only (`TcaColumnsProcessShowitem`, "isInlineChild"
     * without "isInlineChildExpanded", read on v12.4 and v13.4), which says
     * nothing about the form an editor fills in; the state lists every uid of
     * the fixtures, as an editor who opened them all would store it.
     *
     * @return array<string, mixed>
     */
    private function firstInlineChild(int $contentElementUid): array
    {
        $children = $this->compile($contentElementUid, ['tx_theme_list_item' => range(1, 200)])['processedTca']['columns']['tx_theme_list_items']['children'] ?? [];
        $this->assertNotEmpty($children, sprintf('Content element %d compiled no inline child.', $contentElementUid));
        $this->assertSame('tx_theme_list_item', $children[0]['tableName']);
        $this->assertTrue(
            (bool)($children[0]['isInlineChildExpanded'] ?? false),
            sprintf('The child of c%d was compiled collapsed, with the columns of its title only.', $contentElementUid),
        );

        return $children[0];
    }

    /**
     * @return \Generator<string, array{uid: int, icon: bool, subheader: bool}>
     */
    public static function listItemRelations(): \Generator
    {
        yield 'features' => ['uid' => self::FEATURES, 'icon' => true, 'subheader' => false];
        yield 'figures' => ['uid' => self::STATS, 'icon' => true, 'subheader' => true];
        yield 'steps' => ['uid' => self::STEPS, 'icon' => true, 'subheader' => false];
        // The timeline shows the icon of an entry on its line.
        yield 'timeline' => ['uid' => 250, 'icon' => true, 'subheader' => false];
        // Relations of the other theme elements, which render no item icon;
        // the card group shows the subheader below the title of a card.
        yield 'card group' => ['uid' => 240, 'icon' => false, 'subheader' => true];
        yield 'teaser list' => ['uid' => 260, 'icon' => false, 'subheader' => false];
        yield 'link list' => ['uid' => 80, 'icon' => false, 'subheader' => false];
        yield 'teaser grid' => ['uid' => 100, 'icon' => false, 'subheader' => false];
    }

    /**
     * The icon of an item is offered where the parent renders one per item,
     * and nowhere else; so is the subheader, which only the figures render.
     */
    #[DataProvider('listItemRelations')]
    #[Test]
    public function theListItemOffersAnIconWhereItsParentRendersOne(int $uid, bool $icon, bool $subheader): void
    {
        $columns = $this->firstInlineChild($uid)['processedTca']['columns'] ?? [];

        $this->assertSame($icon, array_key_exists('icon', $columns), sprintf('The child of c%d %s the icon.', $uid, $icon ? 'does not offer' : 'offers'));
        $this->assertSame($subheader, array_key_exists('subheader', $columns), sprintf('The child of c%d %s the subheader.', $uid, $subheader ? 'does not offer' : 'offers'));
        if ($icon) {
            $this->assertFalse($columns['icon']['config']['fieldWizard']['selectIcons']['disabled'] ?? true, 'The icon of the item shows no icon grid.');
        }
    }

    /**
     * The title of a feature and of a step, and the figure and what it counts,
     * are required; the figures call the two fields by what they hold.
     */
    #[Test]
    public function theItemsRequireTheirTitleAndTheFiguresNameTheirFields(): void
    {
        foreach ([self::FEATURES, self::STATS, self::STEPS] as $uid) {
            $this->assertTrue(
                (bool)($this->firstInlineChild($uid)['processedTca']['columns']['header']['config']['required'] ?? false),
                sprintf('The title of an item of c%d is not required.', $uid),
            );
        }

        $columns = $this->firstInlineChild(self::STATS)['processedTca']['columns'];
        $this->assertTrue((bool)($columns['subheader']['config']['required'] ?? false));
        foreach (['header' => 'tx_theme_list_item.header.types.theme_stats.label', 'subheader' => 'tx_theme_list_item.subheader.types.theme_stats.label'] as $field => $key) {
            $label = $GLOBALS['LANG']->sL('LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:' . $key);
            $this->assertNotSame('', $label);
            $this->assertSame($label, $columns[$field]['label'] ?? null, sprintf('The "%s" of a figure keeps the label of the table.', $field));
        }
    }
}
