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
 * What a page does with media beyond showing an image.
 *
 * - `image_zoom` renders the lightbox dialog beside the gallery, and the zoom
 *   link keeps its `href` so it still works without a script.
 * - `theme_external_media` renders a poster and a button and **no** iframe,
 *   and only for a host the processor recognises.
 *
 * The files are real files of a real storage, so the references of the fixture
 * can name them by uid: 1 `placeholder.svg`.
 */
final class MediaElementRenderingTest extends AbstractFunctionalTestCase
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

        // Indexed here, so the file gets the uid 1 the references of the
        // fixture name.
        $storage = $this->get(StorageRepository::class)->findByUid($storageUid);
        $this->assertNotNull($storage);
        $file = $storage->getFile('/placeholder.svg');
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame(1, $file->getUid(), 'placeholder.svg was indexed out of order.');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithMediaElements.csv');
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + [
                'dependencies' => [
                    'sbuerk/theme-extension-development',
                ],
            ],
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        $this->setUpFrontendRootPage(1, [], [], false);
    }

    private function render(): string
    {
        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
        // The page is the theme's, or none of the assertions below means anything.
        $this->assertStringContainsString('data-theme-page-layout=', $body);

        return $body;
    }

    /**
     * The markup of one element, by its uid: from its wrapper to the wrapper of
     * the next element, or the end of the main column.
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
     * The zoom link keeps its `href` to the file. Turned into a
     * `data-theme-dialog-open` opener it would be hidden without a script, and
     * an enlargeable image would have no way of being enlarged at all.
     */
    #[Test]
    public function theZoomLinkStillLeadsToTheFileAndNamesItsLightboxItem(): void
    {
        $element = $this->element($this->render(), 30);

        $this->assertMatchesRegularExpression(
            '#<a class="theme-gallery__zoom" href="[^"]*placeholder[^"]*"\s+data-theme-lightbox="c30-lightbox"\s+data-theme-lightbox-item="c30-lightbox-1">#',
            $element,
        );
        $this->assertStringContainsString('data-theme-lightbox-item="c30-lightbox-2"', $element);
        $this->assertStringNotContainsString('data-theme-dialog-open', $element);
        // The badge that says the image enlarges.
        $this->assertSame(2, substr_count($element, 'theme-gallery__zoom-hint'));
    }

    #[Test]
    public function anEnlargeableGalleryRendersOneLightboxItemPerImage(): void
    {
        $element = $this->element($this->render(), 30);

        $this->assertStringContainsString('<dialog class="theme-dialog theme-lightbox" id="c30-lightbox"', $element);
        $this->assertStringContainsString('<figure class="theme-lightbox__item" id="c30-lightbox-1" data-theme-lightbox-item="">', $element);
        $this->assertStringContainsString('<figure class="theme-lightbox__item" id="c30-lightbox-2" data-theme-lightbox-item="">', $element);
        $this->assertStringContainsString('The second image', $element);
        $this->assertStringContainsString('data-theme-lightbox-previous=""', $element);
        $this->assertStringContainsString('data-theme-lightbox-next=""', $element);
        // The markup hides nothing - the script owns that, see the contract.
        $this->assertStringNotContainsString('theme-lightbox__item" id="c30-lightbox-1" hidden', $element);
    }

    /**
     * An element the editor did not tick renders no dialog at all, rather than
     * an empty one nothing can open.
     */
    #[Test]
    public function aGalleryWithoutTheZoomFieldRendersNoLightbox(): void
    {
        $element = $this->element($this->render(), 40);

        $this->assertStringNotContainsString('theme-lightbox', $element);
        $this->assertStringNotContainsString('data-theme-lightbox', $element);
        $this->assertStringNotContainsString('theme-gallery__zoom', $element);
    }

    /**
     * @return \Generator<string, array{uid: int, needle: string}>
     */
    public static function embedMarkup(): \Generator
    {
        yield 'the ratio of the record' => ['uid' => 50, 'needle' => 'theme-embed theme-embed--16-9'];
        yield 'the other ratio' => ['uid' => 60, 'needle' => 'theme-embed theme-embed--4-3'];
        yield 'the cookieless address of the recognised host' => [
            'uid' => 50,
            'needle' => 'data-theme-embed-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"',
        ];
        yield 'the name of the frame' => ['uid' => 50, 'needle' => 'data-theme-embed-title="The launch"'];
        yield 'the link to the source' => ['uid' => 50, 'needle' => 'class="theme-embed__source"'];
        yield 'the link to the source of a host that is not embedded' => [
            'uid' => 60,
            'needle' => 'href="https://media.example.org/videos/the-launch"',
        ];
    }

    #[DataProvider('embedMarkup')]
    #[Test]
    public function theExternalMediaElementRendersItsPlaceholder(int $uid, string $needle): void
    {
        $this->assertStringContainsString($needle, $this->element($this->render(), $uid));
    }

    /**
     * The whole point of the element: opening the page opens no connection to
     * the video's host. An iframe anywhere in the page - however lazy - is the
     * defect this asserts against.
     */
    #[Test]
    public function noIframeReachesThePage(): void
    {
        $body = $this->render();

        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('youtube.com/embed', $body, 'Only the cookieless host may be named at all.');
    }

    /**
     * A host the processor does not recognise is not embedded: no button, so
     * nothing can put an iframe of it in the page, and the link is what is
     * left - the same page a visitor without JavaScript gets.
     */
    #[Test]
    public function aHostThatIsNotRecognisedGetsNoPlayButton(): void
    {
        $element = $this->element($this->render(), 60);

        $this->assertStringNotContainsString('data-theme-embed-play', $element);
        $this->assertStringNotContainsString('theme-embed__button', $element);
        $this->assertStringContainsString('theme-embed__source', $element);
    }

    /**
     * The poster is a file of this installation, and it is decoration: the
     * button over it carries the name of what pressing it does.
     */
    #[Test]
    public function thePosterIsAFileOfThisInstallationAndIsDecoration(): void
    {
        $element = $this->element($this->render(), 50);

        $this->assertMatchesRegularExpression('#<img class="theme-embed__poster" [^>]*src="[^"]*placeholder#', $element);
        $this->assertMatchesRegularExpression('#<img class="theme-embed__poster" [^>]*alt=""#', $element);
    }
}
