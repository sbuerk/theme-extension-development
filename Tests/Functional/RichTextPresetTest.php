<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The rich text preset of the theme, as the core resolves it.
 *
 * `Tests/Unit/RichTextPresetTest` reads `Configuration/RTE/Theme.yaml` as
 * written. This asks the core what it makes of it: whether `ext_localconf.php`
 * registered it, whether the page TSconfig of the theme selects it for a
 * content element, what the configuration holds once the imports are merged
 * in - which is where the Bootstrap styles of the core preset would come back
 * - and what a save keeps.
 */
final class RichTextPresetTest extends AbstractFunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithAppearanceFields.csv');
    }

    /**
     * The configuration of the "bodytext" field of a text element on the root
     * page, resolved the way DataHandler and FormEngine resolve it.
     *
     * @return array<string, mixed>
     */
    private function bodytextConfiguration(): array
    {
        $fieldConfiguration = array_replace_recursive(
            $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'],
            $GLOBALS['TCA']['tt_content']['types']['text']['columnsOverrides']['bodytext']['config'] ?? [],
        );

        return $this->get(Richtext::class)->getConfiguration('tt_content', 'bodytext', 1, 'text', $fieldConfiguration);
    }

    #[Test]
    public function thePresetIsRegisteredWhileRteCkeditorIsLoaded(): void
    {
        $this->assertSame(
            'EXT:theme_extension_development/Configuration/RTE/Theme.yaml',
            $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['theme'] ?? null,
        );
    }

    #[Test]
    public function thePageTsconfigOfTheThemeSelectsThePreset(): void
    {
        $configuration = $this->bodytextConfiguration();

        $this->assertSame('theme', $configuration['preset'] ?? null);
        $this->assertContains(
            ['name' => 'Lead', 'element' => 'p', 'classes' => ['theme-lead']],
            $configuration['editor']['config']['style']['definitions'] ?? [],
        );
    }

    /**
     * Every class of the resolved configuration, imports merged in, is a
     * selector of the compiled stylesheet. A core file among the imports that
     * carries styles appends them here, and fails this.
     *
     * The configuration is also what `CKEditor5Migrator` made of the preset,
     * on every resolution - called by `Richtext` itself on v12.4 and v13.4.
     * It writes all four alignments whenever the
     * plugin is loaded, with a Bootstrap class for any the preset does not
     * name: this test is what found the `text-justify` it added. It also
     * spells a style without a class as `classes: ['']`, because CKEditor
     * rejects an empty list, so an empty string is the absence of a class and
     * not a class of its own.
     */
    #[Test]
    public function everyClassOfTheResolvedPresetIsStyled(): void
    {
        $config = $this->bodytextConfiguration()['editor']['config'] ?? [];
        $classes = [];
        foreach ($config['style']['definitions'] ?? [] as $definition) {
            array_push($classes, ...array_filter((array)($definition['classes'] ?? []), static fn(string $class): bool => $class !== ''));
        }
        foreach ($config['alignment']['options'] ?? [] as $option) {
            $classes[] = $option['className'];
        }
        foreach ($config['heading']['options'] ?? [] as $option) {
            array_push($classes, ...(array)($option['class'] ?? []));
        }

        $this->assertNotSame([], $classes);
        $stylesheet = (string)file_get_contents(dirname(__DIR__, 2) . '/Resources/Public/Css/theme.css');
        $unstyled = array_values(array_filter(
            $classes,
            static fn(string $class): bool => preg_match(sprintf('/\.%s(?![\w-])/', preg_quote($class, '/')), $stylesheet) !== 1,
        ));
        $this->assertSame([], $unstyled, 'The resolved preset offers classes no rule matches.');
    }

    /**
     * What an editor chooses survives the save. DataHandler runs the
     * "processing" of the preset over a rich text field - the core
     * "Processing.yaml" the preset imports - and a class or an element it
     * dropped would be a style that works in the editor and is gone from the
     * page.
     */
    #[Test]
    public function aSaveKeepsTheStylesAndAlignmentsOfThePreset(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $bodytext = '<p class="theme-lead">A lead.</p>'
            . '<p class="theme-eyebrow">An eyebrow</p>'
            . '<p class="theme-text--center">Centred, with <small>small print</small>, <mark>a mark</mark>, <kbd>Ctrl</kbd> and <code>code</code>.</p>'
            . '<p class="theme-text--end">At the end.</p>';

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['tt_content' => ['NEW1' => ['pid' => 1, 'CType' => 'text', 'bodytext' => $bodytext]]], []);
        $dataHandler->process_datamap();
        $this->assertSame([], $dataHandler->errorLog);

        $uid = (int)$dataHandler->substNEWwithIDs['NEW1'];
        $stored = (string)$this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['bodytext'], 'tt_content', ['uid' => $uid])->fetchOne();

        foreach ([
            '<p class="theme-lead">',
            '<p class="theme-eyebrow">',
            '<p class="theme-text--center">',
            '<p class="theme-text--end">',
            '<small>small print</small>',
            '<mark>a mark</mark>',
            '<kbd>Ctrl</kbd>',
            '<code>code</code>',
        ] as $expected) {
            $this->assertStringContainsString($expected, $stored);
        }
    }
}
