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
 * The form offers the appearance values the theme renders, and no field that
 * does nothing.
 *
 * `Configuration/PageTsConfig/ContentElementAppearance.tsconfig` removes the
 * frames the theme does not draw from `frame_class`, adds its four bands, and
 * disables `layout`, `sectionIndex` and `linkToTop`, and `header_position` and
 * `tx_theme_header_style` for the CTypes that render their title outside the
 * shared header partial. A page TSconfig file that is not loaded, or a key
 * that is misspelt, raises nothing - the form simply looks as before.
 *
 * So this compiles the form data the way the backend does when an editor opens
 * a content element, and reads what the form works with: the processed items
 * of the select fields, and the page TSconfig of the field after the
 * type-specific part was merged over it (`PageTsConfigMerged`), whose
 * `disabled` makes `SingleFieldContainer` skip the field. The disabled fields
 * are also looked for in the form FormEngine renders from that data, where a
 * disabled field has no input at all.
 */
final class ContentElementAppearanceFormEngineTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithAppearanceFields.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/ThemeHeroOnAppearancePage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/BulletsOnAppearancePage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/HeaderOnAppearancePage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/HeroesOnAppearancePage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/TestimonialOnAppearancePage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/CtaOnAppearancePage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/CollectionsOnAppearancePage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/MenusOnAppearancePage.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @return array<string, mixed>
     */
    private function compile(int $uid): array
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
     * @param array<string, mixed> $result
     */
    private static function isDisabled(array $result, string $field): bool
    {
        return (bool)($result['pageTsConfig']['TCEFORM.']['tt_content.'][$field . '.']['disabled'] ?? false);
    }

    /**
     * The form of a record as FormEngine renders it: the compiled data through
     * "fullRecordContainer", the container that renders the tabs and palettes
     * of a record on v13.4 and v14.3 alike. "SingleFieldContainer" skips a
     * field whose merged page TSconfig says "disabled", so a disabled field
     * has no input name in this markup.
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
     * The frames the theme draws, and only those. The added items come after
     * the two the core list keeps - "addItems" appends.
     */
    #[Test]
    public function theFrameSelectOffersTheBandsAndNoFrameTheThemeDoesNotDraw(): void
    {
        $this->assertSame(
            ['default', 'none', 'surface', 'raised', 'accent', 'inverse'],
            self::itemValues($this->compile(10), 'frame_class'),
        );
    }

    #[Test]
    public function theHeaderStyleOffersTheLooksTheStylesheetImplements(): void
    {
        $result = $this->compile(10);

        $this->assertSame(['', 'display', 'h1', 'h2', 'h3', 'h4', 'h5'], self::itemValues($result, 'tx_theme_header_style'));
        // Next to the level, in the palette every CType with a heading uses.
        // Compared by field name: v13.4 writes the palette items with their
        // label ("header_layout;LLL:…"), v14.3 without.
        $fields = array_map(
            static fn(string $item): string => trim(explode(';', $item)[0]),
            explode(',', (string)$GLOBALS['TCA']['tt_content']['palettes']['headers']['showitem']),
        );
        $level = array_search('header_layout', $fields, true);
        $this->assertIsInt($level, 'The "headers" palette has no "header_layout".');
        $this->assertSame('tx_theme_header_style', $fields[$level + 1] ?? null);
    }

    /**
     * `layout` is asserted on the header element: the text element, the
     * bullet list, the card group and the two page menus render it, and
     * re-enable it for their own type, below.
     *
     * @return \Generator<string, array{field: string, uid: int}>
     */
    public static function fieldsWithoutRendering(): \Generator
    {
        yield 'layout' => ['field' => 'layout', 'uid' => 920];
    }

    /**
     * `sectionIndex` and `linkToTop` were disabled for as long as the theme
     * rendered neither. It renders both now - the table of contents of the
     * aside is an `HMENU` with `sectionIndex = 1`
     * (`Configuration/TypoScript/Navigation.typoscript`), and the link back to
     * the top is `Layouts/ContentElement.html` - so both are offered again.
     *
     * Asserted as a pair with the negative case above: a `disabled` left in
     * the page TSconfig, or a value misspelt there, raises nothing at all and
     * simply leaves the two switches out of the form, which is exactly the
     * state this change ends.
     *
     * @return \Generator<string, array{field: string}>
     */
    public static function fieldsTheThemeRenders(): \Generator
    {
        yield 'sectionIndex' => ['field' => 'sectionIndex'];
        yield 'linkToTop' => ['field' => 'linkToTop'];
    }

    #[DataProvider('fieldsTheThemeRenders')]
    #[Test]
    public function aFieldTheThemeRendersIsOfferedAgain(string $field): void
    {
        $this->assertFalse(self::isDisabled($this->compile(10), $field), sprintf('"%s" is not offered.', $field));
        $this->assertStringContainsString(self::inputName(10, $field), $this->renderedForm(10), sprintf('"%s" is not in the form.', $field));
    }

    #[DataProvider('fieldsWithoutRendering')]
    #[Test]
    public function aFieldTheThemeDoesNotRenderIsNotInTheForm(string $field, int $uid): void
    {
        $this->assertTrue(self::isDisabled($this->compile($uid), $field), sprintf('"%s" is still offered.', $field));

        // And the rendered form carries no input for it - next to a field of
        // the same palette that it does carry, so an empty rendering cannot
        // pass for a missing field.
        $form = $this->renderedForm($uid);
        $this->assertStringContainsString(self::inputName($uid, 'frame_class'), $form);
        $this->assertStringNotContainsString(self::inputName($uid, $field), $form, sprintf('"%s" is rendered.', $field));
    }

    /**
     * `layout` is re-enabled for the text element, which renders "1" as
     * columns (`Templates/ContentElements/Text.html`), and narrowed to the two
     * values it renders: "2" and "3" would be layouts that look like the
     * default.
     */
    #[Test]
    public function theTextElementOffersItsTwoLayoutsUnderTheirOwnNames(): void
    {
        $result = $this->compile(10);

        $this->assertFalse(self::isDisabled($result, 'layout'), '"layout" is missing from the text element.');
        $this->assertSame(['0', '1'], self::itemValues($result, 'layout'));

        $labels = array_values(array_map(
            static fn(array $item): string => (string)$item['label'],
            $result['processedTca']['columns']['layout']['config']['items'] ?? [],
        ));
        $expected = array_map(
            static fn(int $value): string => $GLOBALS['LANG']->sL(
                'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.layout.text.I.' . $value,
            ),
            [0, 1],
        );
        $this->assertNotContains('', $expected, 'A label of the text layouts is not translated.');
        $this->assertSame($expected, $labels);

        $this->assertStringContainsString(self::inputName(10, 'layout'), $this->renderedForm(10));
        // The bullet list keeps its four: the narrowing is the text element's.
        $this->assertSame(['0', '1', '2', '3'], self::itemValues($this->compile(910), 'layout'));
    }

    /**
     * `layout` is disabled for every CType and re-enabled for the bullet list,
     * which renders it (`Templates/ContentElements/Bullets.html`). The four
     * core values keep their numbers and carry labels that describe a list,
     * not the core's "Layout 1" to "Layout 3".
     */
    #[Test]
    public function theBulletListOffersItsLayoutsUnderTheirOwnNames(): void
    {
        $result = $this->compile(910);

        $this->assertFalse(self::isDisabled($result, 'layout'), '"layout" is missing from the bullet list.');
        $this->assertSame(['0', '1', '2', '3'], self::itemValues($result, 'layout'));

        $labels = array_values(array_map(
            static fn(array $item): string => (string)$item['label'],
            $result['processedTca']['columns']['layout']['config']['items'] ?? [],
        ));
        $expected = array_map(
            static fn(int $value): string => $GLOBALS['LANG']->sL(
                'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.layout.bullets.I.' . $value,
            ),
            [0, 1, 2, 3],
        );
        $this->assertNotContains('', $expected, 'A label of the bullet list layouts is not translated.');
        $this->assertSame($expected, $labels);

        $this->assertStringContainsString(self::inputName(910, 'layout'), $this->renderedForm(910));
        // Still disabled on the header element beside it: the type specific
        // part applies to its type only.
        $this->assertTrue(self::isDisabled($this->compile(920), 'layout'));
    }

    /**
     * `layout` is the arrangement of a card group - the grid, the scroller or
     * the wall - and the form offers exactly those three, under names that say
     * so. The core value 3 is removed for this type, and the field stays
     * disabled for every type that does not render it, the timeline beside
     * it included.
     */
    #[Test]
    public function theCardGroupOffersItsThreeArrangementsUnderTheirOwnNames(): void
    {
        $result = $this->compile(970);

        $this->assertFalse(self::isDisabled($result, 'layout'), '"layout" is missing from the card group.');
        $this->assertSame(['0', '1', '2'], self::itemValues($result, 'layout'));
        $labels = array_values(array_map(
            static fn(array $item): string => (string)$item['label'],
            $result['processedTca']['columns']['layout']['config']['items'] ?? [],
        ));
        $expected = array_map(
            static fn(int $value): string => $GLOBALS['LANG']->sL(
                'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.layout.theme_card_group.I.' . $value,
            ),
            [0, 1, 2],
        );
        $this->assertNotContains('', $expected, 'A label of the card group layouts is not translated.');
        $this->assertSame($expected, $labels);
        $this->assertSame(['2', '3', '4'], self::itemValues($result, 'tx_theme_columns'));

        $form = $this->renderedForm(970);
        $this->assertStringContainsString(self::inputName(970, 'layout'), $form);
        $this->assertStringContainsString(self::inputName(970, 'tx_theme_columns'), $form);

        $this->assertTrue(self::isDisabled($this->compile(980), 'layout'), '"layout" is offered on the timeline, which ignores it.');
        $timeline = $this->renderedForm(980);
        $this->assertStringContainsString(self::inputName(980, 'tx_theme_sort_direction'), $timeline);
        $this->assertStringNotContainsString(self::inputName(980, 'layout'), $timeline);
    }

    /**
     * `layout` is the foot the alternation of the split tiles starts on, and
     * the form offers exactly those two, under names that say so. The two core
     * values the element does not render are removed for this type.
     *
     * Which side an individual tile's picture is on is never a field - the
     * stylesheet alternates it - so the two values here are about the group,
     * not about a tile.
     */
    #[Test]
    public function theSplitTilesOfferTheTwoFeetOfTheirAlternation(): void
    {
        $result = $this->compile(990);

        $this->assertFalse(self::isDisabled($result, 'layout'), '"layout" is missing from the split tiles.');
        $this->assertSame(['0', '1'], self::itemValues($result, 'layout'));
        $labels = array_values(array_map(
            static fn(array $item): string => (string)$item['label'],
            $result['processedTca']['columns']['layout']['config']['items'] ?? [],
        ));
        $expected = array_map(
            static fn(int $value): string => $GLOBALS['LANG']->sL(
                'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.layout.theme_split_tiles.I.' . $value,
            ),
            [0, 1],
        );
        $this->assertNotContains('', $expected, 'A label of the split tiles layouts is not translated.');
        $this->assertSame($expected, $labels);

        $this->assertStringContainsString(self::inputName(990, 'layout'), $this->renderedForm(990));
    }

    /**
     * @return \Generator<string, array{uid: int}>
     */
    public static function pageMenus(): \Generator
    {
        yield 'menu_pages' => ['uid' => 940];
        yield 'menu_subpages' => ['uid' => 950];
    }

    /**
     * The two page menus offer the list, the cards and the thumbnails, under
     * names that say so, and not the core value 3, which renders nothing of
     * its own. Every other menu keeps the field out of the form.
     */
    #[DataProvider('pageMenus')]
    #[Test]
    public function aPageMenuOffersTheListTheCardsAndTheThumbnails(int $uid): void
    {
        $result = $this->compile($uid);

        $this->assertFalse(self::isDisabled($result, 'layout'), '"layout" is missing from the page menu.');
        $this->assertSame(['0', '1', '2'], self::itemValues($result, 'layout'));
        $labels = array_values(array_map(
            static fn(array $item): string => (string)$item['label'],
            $result['processedTca']['columns']['layout']['config']['items'] ?? [],
        ));
        $expected = array_map(
            static fn(int $value): string => $GLOBALS['LANG']->sL(
                'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.layout.menu.I.' . $value,
            ),
            [0, 1, 2],
        );
        $this->assertNotContains('', $expected, 'A label of the menu layouts is not translated.');
        $this->assertSame($expected, $labels);
        $this->assertStringContainsString(self::inputName($uid, 'layout'), $this->renderedForm($uid));
    }

    #[Test]
    public function aMenuThatRendersNoLayoutOffersNone(): void
    {
        $this->assertTrue(self::isDisabled($this->compile(960), 'layout'), '"layout" is offered on the section menu.');
        $form = $this->renderedForm(960);
        $this->assertStringContainsString(self::inputName(960, 'frame_class'), $form);
        $this->assertStringNotContainsString(self::inputName(960, 'layout'), $form);
    }

    /**
     * The icon of the layout "Icons" is picked in the bullet list's own form
     * and in no other: the column is added to that CType alone, and it is the
     * picker of the theme, the icon grid switched on.
     */
    #[Test]
    public function theIconOfTheListIsPickedInTheBulletListOnly(): void
    {
        $bullets = $this->renderedForm(910);
        $this->assertStringContainsString(self::inputName(910, 'tx_theme_icon'), $bullets);
        $this->assertFalse(
            $this->compile(910)['processedTca']['columns']['tx_theme_icon']['config']['fieldWizard']['selectIcons']['disabled'] ?? true,
            'The icon field of the bullet list shows no icon grid.',
        );

        $text = $this->renderedForm(10);
        $this->assertStringContainsString(self::inputName(10, 'frame_class'), $text);
        $this->assertStringNotContainsString(self::inputName(10, 'tx_theme_icon'), $text);
    }

    /**
     * @return \Generator<string, array{uid: int, values: list<string>}>
     */
    public static function heroLayouts(): \Generator
    {
        yield 'the full hero, every layout' => ['uid' => 900, 'values' => ['', 'image-end', 'centred', 'screenshot', 'bordered']];
        yield 'the reduced hero, no image cut off at an edge' => ['uid' => 901, 'values' => ['', 'image-end', 'centred']];
        yield 'the hero without media, nothing to place' => ['uid' => 902, 'values' => ['', 'centred']];
    }

    /**
     * Each hero offers the layouts it can show, and the eyebrow, in its own
     * form: `tx_theme_hero_layout.types.<CType>.removeItems` narrows the
     * select per type, and a key that is misspelt raises nothing.
     *
     * @param list<string> $values
     */
    #[DataProvider('heroLayouts')]
    #[Test]
    public function aHeroOffersTheLayoutsItCanShow(int $uid, array $values): void
    {
        $this->assertSame($values, self::itemValues($this->compile($uid), 'tx_theme_hero_layout'));

        $form = $this->renderedForm($uid);
        $this->assertStringContainsString(self::inputName($uid, 'tx_theme_hero_layout'), $form);
        $this->assertStringContainsString(self::inputName($uid, 'tx_theme_eyebrow'), $form);
    }

    /**
     * The testimonial offers its three styles in a select of its own, and not
     * the core "layout", which stays disabled for it.
     */
    #[Test]
    public function theTestimonialOffersItsStyles(): void
    {
        $result = $this->compile(903);

        $this->assertSame(['', 'pull', 'centred'], self::itemValues($result, 'tx_theme_quote_style'));
        $this->assertTrue(self::isDisabled($result, 'layout'));

        $form = $this->renderedForm(903);
        $this->assertStringContainsString(self::inputName(903, 'tx_theme_quote_style'), $form);
        // The portrait, one file at most.
        $this->assertStringContainsString(self::inputName(903, 'image'), $form);
        $this->assertSame(1, (int)($this->compile(903)['processedTca']['columns']['image']['config']['maxitems'] ?? 0));
        $this->assertStringNotContainsString(self::inputName(903, 'layout'), $form);
    }

    /**
     * The call to action offers its tones and widths, the icon picker of the
     * theme and both link palettes, and not the position and the look of the
     * header: its title is its own, like the hero's.
     */
    #[Test]
    public function theCallToActionOffersItsTonesWidthsIconAndTwoLinks(): void
    {
        $result = $this->compile(904);

        $this->assertSame(['', 'accent', 'inverse', 'placeholder'], self::itemValues($result, 'tx_theme_cta_tone'));
        $this->assertSame(['boxed', 'band'], self::itemValues($result, 'tx_theme_cta_width'));
        $this->assertFalse(
            $result['processedTca']['columns']['tx_theme_icon']['config']['fieldWizard']['selectIcons']['disabled'] ?? true,
            'The icon field of the call to action shows no icon grid.',
        );
        $this->assertTrue(self::isDisabled($result, 'header_position'));
        $this->assertTrue(self::isDisabled($result, 'tx_theme_header_style'));

        $form = $this->renderedForm(904);
        foreach (['tx_theme_cta_tone', 'tx_theme_cta_width', 'tx_theme_icon', 'tx_theme_link', 'tx_theme_secondary_link', 'tx_theme_secondary_link_variant', 'tx_theme_secondary_link_icon'] as $field) {
            $this->assertStringContainsString(self::inputName(904, $field), $form, sprintf('"%s" is missing from the form.', $field));
        }
        $this->assertStringNotContainsString(self::inputName(904, 'header_position'), $form);
    }

    /**
     * @return \Generator<string, array{field: string}>
     */
    public static function headerFields(): \Generator
    {
        yield 'header_position' => ['field' => 'header_position'];
        yield 'tx_theme_header_style' => ['field' => 'tx_theme_header_style'];
    }

    /**
     * Disabled for the hero, whose title is its own component, and for no
     * CType that renders the shared header partial.
     */
    #[DataProvider('headerFields')]
    #[Test]
    public function aHeaderFieldIsOfferedWhereTheSharedHeaderRendersIt(string $field): void
    {
        $this->assertFalse(self::isDisabled($this->compile(10), $field), sprintf('"%s" is missing from the text element.', $field));
        $this->assertTrue(self::isDisabled($this->compile(900), $field), sprintf('"%s" is offered on the hero, which ignores it.', $field));

        $this->assertStringContainsString(self::inputName(10, $field), $this->renderedForm(10));
        $hero = $this->renderedForm(900);
        $this->assertStringContainsString(self::inputName(900, 'header_layout'), $hero);
        $this->assertStringNotContainsString(self::inputName(900, $field), $hero, sprintf('"%s" is rendered on the hero.', $field));
    }
}
