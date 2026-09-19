<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The position of the gallery of `textpic` and `textmedia` reaches the markup
 * as a float.
 *
 * "In text" means the text flows around the gallery, and nothing but a float
 * does that. `GalleryProcessor` reports the position as `intext` plus `left`
 * or `right` and a `noWrap` flag; `Partials/ContentElement/Gallery.html`
 * translates that into `theme-gallery--float-start`, `--float-end` and
 * `--nowrap`. Without the translation the element still renders every image
 * and every word, with the text under the gallery instead of beside it -
 * nothing reports it, which is why it is asserted.
 */
final class TextPicElementRenderingTest extends AbstractFunctionalTestCase
{
    use DataFactoryImportTrait;
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'sbuerk/data-factory',
        'tests/data-factory-fixture',
    ];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->createDefaultFileStorage();
        $this->importSeedSet('tests-textpic-element');

        $this->setUpThemeSite();
    }

    /**
     * The opening tag of the gallery of one content element, by its uid.
     */
    private function galleryOf(int $uid): string
    {
        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();

        $matched = preg_match(
            sprintf('#id="c%d".*?(<div class="theme-gallery [^"]*")#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('The element %d rendered no gallery.', $uid));

        return $matches[1];
    }

    /**
     * @return \Generator<string, array{uid: int, modifiers: list<string>}>
     */
    public static function galleryPositions(): \Generator
    {
        yield 'textpic in text, left' => ['uid' => 21, 'modifiers' => ['theme-gallery--intext', 'theme-gallery--left', 'theme-gallery--float-start']];
        yield 'textpic in text, right' => ['uid' => 22, 'modifiers' => ['theme-gallery--intext', 'theme-gallery--right', 'theme-gallery--float-end']];
        yield 'textpic in text, right, no wrap' => ['uid' => 23, 'modifiers' => ['theme-gallery--float-end', 'theme-gallery--nowrap']];
        yield 'textmedia in text, left' => ['uid' => 25, 'modifiers' => ['theme-gallery--intext', 'theme-gallery--float-start']];
    }

    /**
     * @param list<string> $modifiers
     */
    #[DataProvider('galleryPositions')]
    #[Test]
    public function aGalleryInTextFloatsToItsSide(int $uid, array $modifiers): void
    {
        $gallery = $this->galleryOf($uid);

        foreach ($modifiers as $modifier) {
            $this->assertMatchesRegularExpression(
                sprintf('#[" ]%s[" ]#', preg_quote($modifier, '#')),
                $gallery,
                sprintf('The gallery of element %d does not carry "%s".', $uid, $modifier),
            );
        }
    }

    #[Test]
    public function aGalleryInTextThatWrapsDoesNotClaimNoWrap(): void
    {
        $this->assertStringNotContainsString('theme-gallery--nowrap', $this->galleryOf(21));
    }

    #[Test]
    public function aGalleryAboveTheTextDoesNotFloat(): void
    {
        $gallery = $this->galleryOf(24);

        $this->assertStringContainsString('theme-gallery--above', $gallery);
        $this->assertStringNotContainsString('theme-gallery--float', $gallery);
        $this->assertStringNotContainsString('theme-gallery--nowrap', $gallery);
    }

    #[Test]
    public function everyGalleryItemIsAFigure(): void
    {
        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();

        $this->assertSame(5, substr_count($body, '<figure class="theme-gallery__item theme-figure">'));
    }
}
