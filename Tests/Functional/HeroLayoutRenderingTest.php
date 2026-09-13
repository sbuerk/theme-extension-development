<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The layouts and the eyebrow of the three heroes.
 *
 * `tx_theme_hero_layout` becomes a modifier of `.theme-hero` in
 * `Partials/ContentElement/Hero.html`: `--image-end`, `--centred`,
 * `--screenshot` and `--bordered`. A layout that arranges an image needs one:
 * without it "image-end" and "bordered" render the default and "screenshot"
 * renders "centred". The default and a value nothing offers render no
 * modifier - which keeps every hero seeded before the field existed exactly
 * as it was.
 *
 * The image is one real file of a storage, indexed as uid 1, so `f:image`
 * renders it the way it renders a seeded image.
 */
final class HeroLayoutRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $storageUid = $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        copy(
            dirname(__DIR__, 2) . '/Configuration/DataFactory/theme-demo/Files/placeholder.svg',
            $this->instancePath . '/fileadmin/placeholder.svg',
        );
        $storage = $this->get(StorageRepository::class)->findByUid($storageUid);
        $this->assertNotNull($storage);
        // "getFile()" is declared to return a "FileInterface" on v13.4, which
        // has no uid; the storage hands out a "File".
        $file = $storage->getFile('/placeholder.svg');
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame(1, $file->getUid());

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithHeroLayouts.csv');
    }

    private function render(string $path = 'set'): string
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
        // The page is the theme's, or none of the assertions below means anything.
        $this->assertStringContainsString('data-theme-page-layout=', $body);

        return $body;
    }

    /**
     * The markup of one element, by its uid: from its wrapper to the wrapper
     * of the next element, or the end of the main column.
     */
    private function element(string $body, int $uid): string
    {
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('The element %d was not rendered.', $uid));

        return $matches[1];
    }

    /**
     * @return list<string> The classes of the hero of one element.
     */
    private function heroClasses(string $element): array
    {
        $this->assertSame(1, preg_match('#<section class="(theme-hero[^"]*)">#', $element, $matches), 'No hero was rendered.');

        return preg_split('/\s+/', trim($matches[1])) ?: [];
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
     */
    public static function layouts(): \Generator
    {
        foreach (['set' => 'site set', 'static' => 'static include'] as $path => $label) {
            foreach ([
                'the default' => [10, ['theme-hero', 'theme-hero--media']],
                'centred' => [20, ['theme-hero', 'theme-hero--media', 'theme-hero--centred']],
                'the image at the end' => [30, ['theme-hero', 'theme-hero--media', 'theme-hero--image-end']],
                'screenshot' => [40, ['theme-hero', 'theme-hero--media', 'theme-hero--screenshot']],
                'cropped' => [50, ['theme-hero', 'theme-hero--media', 'theme-hero--bordered']],
                'the image at the end without an image' => [60, ['theme-hero']],
                'screenshot without an image is centred' => [70, ['theme-hero', 'theme-hero--centred']],
                'cropped without an image' => [80, ['theme-hero']],
                'a layout nothing offers' => [90, ['theme-hero', 'theme-hero--media']],
                'the reduced hero' => [100, ['theme-hero', 'theme-hero--media', 'theme-hero--compact', 'theme-hero--centred']],
                'the hero without media' => [110, ['theme-hero', 'theme-hero--centred']],
            ] as $name => [$uid, $expected]) {
                yield $name . ', ' . $label => ['path' => $path, 'uid' => $uid, 'expected' => $expected];
            }
        }
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('layouts')]
    #[Test]
    public function theLayoutPicksTheModifierOfTheHero(string $path, int $uid, array $expected): void
    {
        $this->assertSame($expected, $this->heroClasses($this->element($this->render($path), $uid)));
    }

    /**
     * The screenshot is the one layout that moves the image in the markup:
     * it follows the text, so the order read is the order shown. Every other
     * layout keeps the image first.
     */
    #[Test]
    public function theScreenshotFollowsTheTextAndEveryOtherImageLeadsIt(): void
    {
        $body = $this->render();

        $screenshot = $this->element($body, 40);
        $this->assertSame(1, substr_count($screenshot, 'theme-hero__media'));
        $this->assertLessThan(strpos($screenshot, 'theme-hero__media'), strpos($screenshot, 'theme-hero__body'));

        foreach ([10, 20, 30, 50] as $uid) {
            $element = $this->element($body, $uid);
            $this->assertSame(1, substr_count($element, 'theme-hero__media'), sprintf('Element %d renders its image more or less than once.', $uid));
            $this->assertLessThan(strpos($element, 'theme-hero__body'), strpos($element, 'theme-hero__media'), sprintf('Element %d does not lead with its image.', $uid));
        }
    }

    /**
     * The eyebrow is a paragraph before the heading, inside the body, and
     * text rather than markup. A hero without one renders none.
     */
    #[Test]
    public function theEyebrowIsAParagraphBeforeTheTitle(): void
    {
        $body = $this->render();

        $this->assertMatchesRegularExpression(
            '#<div class="theme-hero__body">\s*<p class="theme-hero__eyebrow">Release 2\.0</p>\s*<h2 class="theme-hero__title">The default</h2>#',
            $this->element($body, 10),
        );
        $this->assertStringNotContainsString('theme-hero__eyebrow', $this->element($body, 20));
        $this->assertStringContainsString('<p class="theme-hero__eyebrow">&lt;b&gt;Typed&lt;/b&gt;</p>', $this->element($body, 120));
    }
}
