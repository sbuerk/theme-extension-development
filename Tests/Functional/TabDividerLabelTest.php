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
 * label reference, or with nothing at all. It is also a trap that only springs
 * on one core version -
 * `LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:images` is a
 * label of TYPO3 v13.4 that v12.4 does not have, and five element definitions
 * of this branch used it.
 *
 * What `LanguageService::sL()` hands back for a label it cannot resolve
 * depends on the form of the label, on v12.4 and v13.4 alike: an `LLL:`
 * reference to a missing key yields an empty string, and a string without the
 * prefix comes back unchanged. So a resolved label is one that is neither
 * empty nor the string that went in, and the test asserts both. The label
 * `locallang_tabs.xlf:images` on v12.4 is the first kind, and fails the
 * second assertion.
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
                . 'XLIFF file it names, on every supported core version - the labels of EXT:core differ between '
                . 'v12.4 and v13.4.',
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
