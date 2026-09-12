<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Without rte_ckeditor the preset is not registered, and a rich text field
 * still saves.
 *
 * `Configuration/RTE/Theme.yaml` imports files of rte_ckeditor. An import that
 * does not resolve is caught by the YAML loader and logged as an error, and the
 * preset loads without the core processing rules. DataHandler resolves the
 * preset for every save of a rich text field, editor or not - so a preset
 * registered unconditionally would log an error on every save of a text element
 * in an installation without that extension, and process it with no rules of
 * its own. The theme does not require the extension.
 *
 * The save goes through either way, which is why the registration is asserted
 * directly: removing the guard of `ext_localconf.php` fails the first test and
 * leaves the second green.
 */
final class RichTextPresetWithoutRteCkeditorTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithAppearanceFields.csv');
    }

    #[Test]
    public function thePresetIsNotRegistered(): void
    {
        $this->assertArrayNotHasKey('theme', $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets'] ?? []);
    }

    #[Test]
    public function aRichTextFieldStillSaves(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['tt_content' => ['NEW1' => ['pid' => 1, 'CType' => 'text', 'bodytext' => '<p>Saved.</p>']]], []);
        $dataHandler->process_datamap();

        $this->assertSame([], $dataHandler->errorLog);
        $this->assertArrayHasKey('NEW1', $dataHandler->substNEWwithIDs);
    }
}
