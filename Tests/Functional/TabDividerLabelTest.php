<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Every tab divider of the theme's own content types carries a label that the
 * running core can actually translate.
 *
 * A `--div--` whose label does not resolve is the quietest kind of defect this
 * extension can ship: TCA stays valid, no exception is thrown, every other test
 * stays green, and the only symptom is a tab in the backend titled with its own
 * label reference, or with nothing at all. It is also a trap that tends to
 * spring on one core version only. The shorthand `core.form.tabs:images` is
 * the translation domain mapping of TYPO3 v14 (Feature #93334); v13.4 does not
 * read it, which is why the overrides of this extension spell out the
 * `LLL:EXT:` reference, and what this test notices when one of them does not.
 * Branch `1` shipped the same kind of defect the other way round:
 * `locallang_tabs.xlf:images` of EXT:core is a label v13.4 has and v12.4 does
 * not. This test came with the fix there, and fails on v12.4 against the old
 * label.
 *
 * What `LanguageService::sL()` hands back for a label it cannot resolve
 * depends on the form of the label, on v13.4 and v14.3 alike: an `LLL:`
 * reference to a missing key yields an empty string, and a string without the
 * prefix - the shorthand on v13.4, or a shorthand naming a missing key on
 * v14.3 - comes back unchanged. So a resolved label is one that is neither
 * empty nor the reference that went in, and the test asserts both.
 */
final class TabDividerLabelTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function everyTabDividerOfTheThemeElementsResolvesToATranslation(): void
    {
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');

        $dividers = [];
        foreach ($GLOBALS['TCA']['tt_content']['types'] ?? [] as $type => $configuration) {
            if (!str_starts_with((string)$type, 'theme_')) {
                continue;
            }
            foreach (explode(',', (string)($configuration['showitem'] ?? '')) as $item) {
                $item = trim($item);
                if (!str_starts_with($item, '--div--;')) {
                    continue;
                }
                $dividers[$type . ' / ' . $item] = substr($item, strlen('--div--;'));
            }
        }

        $this->assertNotSame(
            [],
            $dividers,
            'No theme content type declares a tab divider at all. Either the TCA did not load, or the elements '
            . 'lost their tabs - both of which this test exists to notice.',
        );

        foreach ($dividers as $where => $label) {
            $translation = $languageService->sL($label);

            $this->assertNotSame(
                $label,
                $translation,
                'The tab divider label "' . $label . '" of "' . $where . '" does not resolve on this core version, '
                . 'so the backend renders the reference itself as the tab title. Check that the key exists in the '
                . 'XLIFF file it names, and that the reference is one every supported core version reads - the '
                . '"core.form.tabs:" shorthand is TYPO3 v14 only.',
            );
            $this->assertNotSame(
                '',
                $translation,
                'The tab divider label "' . $label . '" of "' . $where . '" resolves to an empty string, so the '
                . 'backend renders a tab with no title.',
            );
        }
    }
}
