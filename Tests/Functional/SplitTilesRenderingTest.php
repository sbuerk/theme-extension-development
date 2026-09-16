<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The split tiles render a column of featurettes whose markup does not say
 * which side anything is on.
 *
 * The assertion that matters most here is a negative one: **no tile carries a
 * modifier for its picture side**. The alternation is the stylesheet's, with
 * `:nth-child(even)`, and a template that started writing a per-tile class
 * would still look right on the page while breaking the moment an editor
 * reorders or deletes a tile. That is precisely the kind of defect that
 * survives review and every visual check, so it is asserted.
 *
 * Rendered through both delivery paths, like every other element.
 */
final class SplitTilesRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithSlides.csv');

        $storage = $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        GeneralUtility::writeFile(
            $this->instancePath . '/fileadmin/slide.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 90" width="160" height="90"><rect width="160" height="90" fill="#d6dce6"/></svg>',
        );
        $this->get(StorageRepository::class)->findByUid($storage)?->getFile('slide.svg');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SlideMedia.csv');
    }

    /**
     * @return \Generator<string, array{path: string}>
     */
    public static function deliveryPaths(): \Generator
    {
        yield 'site set' => ['path' => 'set'];
        yield 'static include' => ['path' => 'static'];
    }

    private function render(string $path): string
    {
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + ($path === 'set' ? ['dependencies' => ['sbuerk/theme-extension-development']] : []),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        if ($path === 'set') {
            $this->setUpFrontendRootPage(1, [], [], false);
        } else {
            $this->setUpFrontendRootPage(1, [], [
                'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
            ]);
        }

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'))->getBody();
        $this->assertStringContainsString('data-theme-page-layout=', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);

        return $body;
    }

    private function element(string $body, int $uid): string
    {
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No element c%d was rendered.', $uid));

        return $matches[0];
    }

    /**
     * `layout` picks the foot the alternation starts on, and nothing else:
     * 1 writes the modifier on the list, 0 writes none, and a value the form
     * does not offer starts on the default foot rather than writing a class
     * no rule matches.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theListCarriesTheRhythmAndTheTilesCarryNone(string $path): void
    {
        $body = $this->render($path);

        $this->assertStringContainsString('<ul class="theme-split-tiles">', $this->element($body, 30));
        $this->assertStringContainsString('<ul class="theme-split-tiles theme-split-tiles--reversed">', $this->element($body, 40));
        // A layout from an import: the default rhythm, not a modifier nothing
        // styles.
        $this->assertStringContainsString('<ul class="theme-split-tiles">', $this->element($body, 50));
    }

    /**
     * **No tile says which side its picture is on.**
     *
     * The stylesheet alternates with `:nth-child(even)`, so the markup of
     * every tile is identical and reordering one in the backend keeps the
     * rhythm right. A per-tile modifier would render identically today and
     * break silently on the first insert.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function noTileCarriesAModifierForItsPictureSide(string $path): void
    {
        $tiles = $this->element($this->render($path), 30);

        $this->assertSame(3, substr_count($tiles, '<li class="theme-split-tiles__item'));
        $this->assertStringNotContainsString('theme-split-tiles__item--reversed', $tiles);
        $this->assertStringNotContainsString('theme-split-tiles__item--start', $tiles);
        $this->assertStringNotContainsString('theme-split-tiles__item--end', $tiles);

        // The media comes before the body on every tile, whichever side it is
        // painted on, so the reading and the focus order are the same on all
        // of them.
        $this->assertSame(
            substr_count($tiles, '<div class="theme-split-tiles__media">'),
            preg_match_all('#<div class="theme-split-tiles__media">.*?</div>\s*<div class="theme-split-tiles__body">#s', $tiles),
            'A tile renders its body before its media.',
        );
    }

    /**
     * Each tile carries its own tone, matched value by value, and a value the
     * form does not offer renders the surface fill and no modifier.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function everyTileCarriesItsOwnTone(string $path): void
    {
        $body = $this->render($path);
        $tiles = $this->element($body, 30);

        $this->assertStringContainsString('<li class="theme-split-tiles__item">', $tiles);
        $this->assertStringContainsString('<li class="theme-split-tiles__item theme-split-tiles__item--accent">', $tiles);
        $this->assertStringContainsString('<li class="theme-split-tiles__item theme-split-tiles__item--inverse">', $tiles);
        $this->assertStringContainsString(
            '<li class="theme-split-tiles__item theme-split-tiles__item--placeholder">',
            $this->element($body, 40),
        );

        // A tone from an import renders no modifier at all.
        $unknown = $this->element($body, 50);
        $this->assertStringContainsString('<li class="theme-split-tiles__item">', $unknown);
        $this->assertStringNotContainsString('no-such-tone', $unknown);
    }

    /**
     * The form offers exactly the tones the template renders.
     *
     * Every value maps onto one case of the `f:switch`, and a value with no
     * case renders no modifier at all - an editor picks a tone and nothing
     * happens, with nothing to report it. Held to the column rather than to a
     * list written out here, so a fifth tone added to the configuration fails
     * this instead of reaching the form silently.
     */
    #[Test]
    public function theFormOffersExactlyTheTonesTheTemplateRenders(): void
    {
        $items = $GLOBALS['TCA']['tx_theme_list_item']['columns']['tone']['config']['items'] ?? [];

        $this->assertSame(['', 'accent', 'inverse', 'placeholder'], array_column($items, 'value'));
    }

    /**
     * The tone of a tile is the tone of a call to action: the column of the
     * child carries the configuration of the element's, so the two lists
     * cannot drift apart.
     *
     * The comment on the column promises exactly this, and a promise in a
     * comment is not a guard - the two components mix the same three tones
     * from the same tokens, and one of them gaining a fourth without the other
     * is the failure this rules out.
     */
    #[Test]
    public function aTileToneIsTheToneOfACallToAction(): void
    {
        $this->assertSame(
            $GLOBALS['TCA']['tt_content']['columns']['tx_theme_cta_tone']['config'],
            $GLOBALS['TCA']['tx_theme_list_item']['columns']['tone']['config'] ?? null,
        );
    }

    /**
     * A tile renders its title one level below the heading of the element, its
     * text as plain text, and its link as a button of the style the editor
     * picked.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTileRendersItsTitleTextAndLink(string $path): void
    {
        $tiles = $this->element($this->render($path), 30);

        $this->assertStringContainsString('<h3 class="theme-split-tiles__title">Write the content first</h3>', $tiles);
        $this->assertStringContainsString('<p class="theme-split-tiles__text">A tile without a tone.</p>', $tiles);
        $this->assertMatchesRegularExpression('#<a [^>]*class="theme-button"[^>]*>\s*Read the guide\s*</a>#', $tiles);
        $this->assertSame(1, substr_count($tiles, 'theme-button'), 'A tile without a link got a button.');
        $this->assertStringNotContainsString('t3://', $tiles);
    }
}
