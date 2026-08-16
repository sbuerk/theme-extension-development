<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The theme's ten content types are registered, and creatable.
 *
 * Registration is spread over two mechanisms that differ per core version, and
 * a failure of either is silent: the element simply is not offered, and nothing
 * anywhere says why.
 *
 *  - The TCA side goes through
 *    `SBUERK\ThemeExtensionDevelopment\Compatibility\ContentTypeRegistration`,
 *    because `ExtensionManagementUtility::addRecordType()` does not exist on
 *    TYPO3 v12.4. That helper reproduces what v13's method writes; the first
 *    three tests below are what holds it to that, by asserting the three
 *    separate places a record type has to appear in.
 *  - The wizard side is TCA derived on v13 (#102834, v13.0) and page TSconfig
 *    on v12 (`Configuration/PageTsConfig/NewContentElementWizard.tsconfig`).
 *
 * This test class carries **no** core version group on purpose. Everything it
 * asserts must hold on every supported version - that is the whole point of the
 * compatibility layer it covers - and a version specific variant of an
 * assertion would only prove that the variant was written, not that the
 * behaviour is the same.
 */
final class ThemeContentTypeRegistrationTest extends AbstractFunctionalTestCase
{
    /**
     * Every content type this extension registers, spelled out rather than
     * derived from the TCA: a list read back out of the thing under test would
     * shrink silently along with it.
     *
     * @var list<string>
     */
    private const THEME_CONTENT_TYPES = [
        'theme_author',
        'theme_hero',
        'theme_hero_small',
        'theme_hero_text_only',
        'theme_linklist',
        'theme_media_teaser',
        'theme_media_teaser_grid',
        'theme_sociallinks',
        'theme_teaser',
        'theme_testimonial',
    ];

    /**
     * @return \Generator<string, array{contentType: string}>
     */
    public static function themeContentTypes(): \Generator
    {
        foreach (self::THEME_CONTENT_TYPES as $contentType) {
            yield $contentType => ['contentType' => $contentType];
        }
    }

    #[DataProvider('themeContentTypes')]
    #[Test]
    public function contentTypeIsOfferedInTheCTypeSelect(string $contentType): void
    {
        $items = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [];
        $values = array_map(
            static fn(array $item): string => (string)($item['value'] ?? ''),
            $items,
        );

        $this->assertContains(
            $contentType,
            $values,
            'The content type "' . $contentType . '" is not an item of the "CType" select field. It is '
            . 'registered in "Configuration/TCA/Overrides/tt_content_theme_' . substr($contentType, 6) . '.php".',
        );
    }

    #[DataProvider('themeContentTypes')]
    #[Test]
    public function contentTypeHasARecordTypeDefinition(string $contentType): void
    {
        $type = $GLOBALS['TCA']['tt_content']['types'][$contentType] ?? null;

        $this->assertIsArray(
            $type,
            'The content type "' . $contentType . '" has no entry in $TCA[tt_content][types], so opening a '
            . 'record of it falls back to the default type and shows the wrong fields.',
        );
        $this->assertNotSame(
            '',
            trim((string)($type['showitem'] ?? '')),
            'The record type "' . $contentType . '" has an empty "showitem", so its form has no fields.',
        );
    }

    #[DataProvider('themeContentTypes')]
    #[Test]
    public function contentTypeHasATypeIcon(string $contentType): void
    {
        $icon = $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$contentType] ?? null;

        $this->assertIsString(
            $icon,
            'The content type "' . $contentType . '" has no "ctrl.typeicon_classes" entry, so the page module '
            . 'and the record list show the generic default icon for it.',
        );
        $this->assertNotSame('', $icon);
    }

    /**
     * The ten types are creatable, not merely selectable.
     *
     * This is the assertion that covers the v12 half of the wizard problem
     * (contract item C3-D): on v13 the wizard entries are derived from the CType
     * items, on v12 they exist only if the extension ships them as page
     * TSconfig. The two sources are different, but the result is not - which is
     * why this can be asserted once for both versions instead of twice.
     *
     * The observable is `NewContentElementController::getWizards()`, reached
     * through reflection. That looks heavy handed, and the alternatives were
     * worse: asserting the page TSconfig would only ever pass on v12, and
     * asserting the rendered wizard means building a full backend route
     * request, whose shape is exactly the kind of thing that differs between
     * two core versions. `getWizards()` has the identical name, visibility and
     * (empty) signature on 12.4 and 13.4, and it is the method that merges the
     * TCA derived and the page TSconfig derived items, so it is the narrowest
     * point at which "the wizard offers this element" is true or false.
     *
     * The controller is `@internal`, so this is a deliberate reach into core
     * internals. If a future core version renames the method, this test fails
     * loudly rather than silently passing - which is the right way round.
     */
    #[Test]
    public function newContentElementWizardOffersEveryThemeContentType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/ContentTypeWizardPage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        // An admin, deliberately: "removeInvalidWizardItems()" drops an entry
        // whose default values the current user may not set, and a
        // non-privileged user would therefore make this test fail for a reason
        // that has nothing to do with the registration.
        $backendUser = $this->setUpBackendUser(1);
        // "getWizards()" resolves the group headers through the language
        // service on both versions, and a functional test has no $GLOBALS['LANG'].
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $controller = $this->get(NewContentElementController::class);
        $reflection = new \ReflectionObject($controller);
        $pageId = $reflection->getProperty('id');
        $pageId->setAccessible(true);
        $pageId->setValue($controller, 1);
        $getWizards = $reflection->getMethod('getWizards');
        $getWizards->setAccessible(true);

        /** @var array<string, mixed> $wizards */
        $wizards = $getWizards->invoke($controller);

        // The key is "<group>_<element>". The group is the "theme" item group of
        // the CType field, and the element key is the content type value on both
        // versions - v13 derives it from the select item, v12 from the element
        // key of the shipped TSconfig, which is named after the type for exactly
        // this reason.
        foreach (self::THEME_CONTENT_TYPES as $contentType) {
            $this->assertArrayHasKey(
                'theme_' . $contentType,
                $wizards,
                'The content type "' . $contentType . '" is not offered by the "new content element" wizard, so '
                . 'an editor can select it on an existing element but never create one. On TYPO3 v12 the wizard '
                . 'items come from "Configuration/PageTsConfig/NewContentElementWizard.tsconfig", on v13 they are '
                . 'derived from the CType items.',
            );
        }

        $this->assertArrayHasKey(
            'theme',
            $wizards,
            'The wizard has no "theme" group header, so the theme elements would be listed without a heading.',
        );
    }
}
