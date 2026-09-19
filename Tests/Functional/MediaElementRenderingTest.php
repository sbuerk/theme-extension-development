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
 * The three things a page does with media beyond showing an image.
 *
 * - A video or an audio file in the `assets` field of `textmedia` renders as a
 *   native player (`.theme-media`), with the caption track of the file of the
 *   same name.
 * - `image_zoom` renders the lightbox dialog beside the gallery, and the zoom
 *   link keeps its `href` so it still works without a script.
 * - `theme_external_media` renders a poster and a button and **no** iframe,
 *   and only for a host the processor recognises.
 *
 * The files are real files of a real storage, indexed in a fixed order so the
 * references of the fixture can name them by uid: 1 `clip.mp4`, 2 `tone.wav`,
 * 3 `clip.vtt` and 4 `placeholder.svg`. The film is a header-only stub - it is
 * detected as `video/mp4`, which is all the rendering depends on, and no
 * encoder is needed to commit it.
 */
final class MediaElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $storageUid = $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');

        // An "ftyp" box and a "free" box: the header that makes the file
        // "video/mp4" to the MIME guesser, and nothing else.
        $ftyp = 'isom' . pack('N', 512) . 'isomiso2mp41';
        GeneralUtility::writeFile(
            $this->instancePath . '/fileadmin/clip.mp4',
            pack('N', 8 + strlen($ftyp)) . 'ftyp' . $ftyp . pack('N', 8) . 'free',
        );
        // A real WAV: an eighth of a second of silence, 8 kHz, 8 bit, mono.
        $samples = str_repeat(chr(128), 1000);
        $format = pack('vvVVvv', 1, 1, 8000, 8000, 1, 8);
        GeneralUtility::writeFile(
            $this->instancePath . '/fileadmin/tone.wav',
            'RIFF' . pack('V', 4 + 8 + strlen($format) + 8 + strlen($samples)) . 'WAVE'
            . 'fmt ' . pack('V', strlen($format)) . $format
            . 'data' . pack('V', strlen($samples)) . $samples,
        );
        GeneralUtility::writeFile(
            $this->instancePath . '/fileadmin/clip.vtt',
            "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nA caption of the clip.\n",
        );
        copy(
            dirname(__DIR__, 2) . '/Configuration/DataFactory/theme-demo/Files/placeholder.svg',
            $this->instancePath . '/fileadmin/placeholder.svg',
        );

        // Indexed in this order, so the files get the uids 1 to 4 the
        // references of the fixture name.
        $storage = $this->get(StorageRepository::class)->findByUid($storageUid);
        $this->assertNotNull($storage);
        foreach (['clip.mp4', 'tone.wav', 'clip.vtt', 'placeholder.svg'] as $expectedUid => $name) {
            $file = $storage->getFile('/' . $name);
            $this->assertInstanceOf(File::class, $file);
            $this->assertSame($expectedUid + 1, $file->getUid(), sprintf('%s was indexed out of order.', $name));
        }

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithMediaElements.csv');
        $this->setUpThemeSite();
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
     * A video is a player, not an image. Rendered through `f:image` - which is
     * what this element did with every file of `assets` before - the page
     * carries an `img` whose source a browser cannot decode, and nothing says
     * so.
     */
    #[Test]
    public function aVideoInTheMediaFieldRendersAsAPlayer(): void
    {
        $element = $this->element($this->render(), 10);

        $this->assertMatchesRegularExpression(
            '#<figure class="theme-gallery__item theme-figure theme-media theme-media--video">#',
            $element,
        );
        $this->assertMatchesRegularExpression(
            '#<video class="theme-media__player" controls="controls" preload="metadata" playsinline="playsinline">#',
            $element,
        );
        $this->assertMatchesRegularExpression(
            '#<source src="[^"]*fileadmin/clip\.mp4" type="video/mp4" />#',
            $element,
        );
        // Not an image, and not the core's own renderer either.
        $this->assertStringNotContainsString('theme-gallery__image', $element);
        $this->assertStringNotContainsString('<img', $element);
    }

    #[Test]
    public function anAudioFileInTheMediaFieldRendersAsAPlayer(): void
    {
        $element = $this->element($this->render(), 20);

        $this->assertStringContainsString('theme-media theme-media--audio', $element);
        $this->assertMatchesRegularExpression(
            '#<audio class="theme-media__player" controls="controls" preload="metadata">#',
            $element,
        );
        $this->assertMatchesRegularExpression(
            '#<source src="[^"]*fileadmin/tone\.wav" type="audio/(x-)?wav" />#',
            $element,
        );
        $this->assertStringNotContainsString('<video', $element);
    }

    /**
     * The caption track is the whole reason the theme writes the tag rather
     * than using `f:media`: neither core renderer emits one.
     */
    #[Test]
    public function theCaptionFileOfTheSameNameBecomesATrack(): void
    {
        $element = $this->element($this->render(), 10);

        $this->assertMatchesRegularExpression(
            '#<track kind="captions" src="[^"]*fileadmin/clip\.vtt" label="English" />#',
            $element,
        );
    }

    /**
     * The pairing is **by name**, and this is the case that says so.
     *
     * Element 80 carries "clip.vtt" in its captions field and "tone.wav" as
     * its medium: a caption file is present, the element is configured for
     * one, and the names do not match - so no track. An element with no
     * caption file at all proves only that nothing appears out of nowhere,
     * which is why 20 and 70 are asserted beside it rather than instead of it.
     */
    #[Test]
    public function aMediumWithoutACaptionFileOfItsNameGetsNoTrack(): void
    {
        $body = $this->render();

        $paired = $this->element($body, 80);
        // The caption file did reach the element - otherwise this would pass
        // for the same reason 20 and 70 do.
        $this->assertStringContainsString('fileadmin/tone.wav', $paired);
        $this->assertStringNotContainsString('<track', $paired);
        $this->assertStringNotContainsString('clip.vtt', $paired);

        $this->assertStringNotContainsString('<track', $this->element($body, 20));
        $this->assertStringNotContainsString('<track', $this->element($body, 70));
    }

    /**
     * A gallery of mixed media renders each file as what it is - the branch is
     * per file, not per element.
     */
    #[Test]
    public function aGalleryRendersEachFileAsWhatItIs(): void
    {
        $element = $this->element($this->render(), 70);

        $this->assertStringContainsString('theme-media--video', $element);
        $this->assertStringContainsString('<video', $element);
        $this->assertStringContainsString('<img class="theme-gallery__image"', $element);
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
            '#<a class="theme-gallery__zoom" href="[^"]*placeholder[^"]*"\s+data-theme-lightbox="c30-lightbox"\s+data-theme-lightbox-item="c30-lightbox-4">#',
            $element,
        );
        $this->assertStringContainsString('data-theme-lightbox-item="c30-lightbox-5"', $element);
        $this->assertStringNotContainsString('data-theme-dialog-open', $element);
        // The badge that says the image enlarges.
        $this->assertSame(2, substr_count($element, 'theme-gallery__zoom-hint'));
    }

    #[Test]
    public function anEnlargeableGalleryRendersOneLightboxItemPerImage(): void
    {
        $element = $this->element($this->render(), 30);

        $this->assertStringContainsString('<dialog class="theme-dialog theme-lightbox" id="c30-lightbox"', $element);
        $this->assertStringContainsString('<figure class="theme-lightbox__item" id="c30-lightbox-4" data-theme-lightbox-item="">', $element);
        $this->assertStringContainsString('<figure class="theme-lightbox__item" id="c30-lightbox-5" data-theme-lightbox-item="">', $element);
        $this->assertStringContainsString('The second image', $element);
        $this->assertStringContainsString('data-theme-lightbox-previous=""', $element);
        $this->assertStringContainsString('data-theme-lightbox-next=""', $element);
        // The markup hides nothing - the script owns that, see the contract.
        $this->assertStringNotContainsString('theme-lightbox__item" id="c30-lightbox-4" hidden', $element);
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
