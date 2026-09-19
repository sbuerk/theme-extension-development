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
}
