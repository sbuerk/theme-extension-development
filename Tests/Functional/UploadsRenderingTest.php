<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The "File Links" element renders its files as a `.theme-file-list`.
 *
 * `uploads_type` - the display type of the core - picks the modifier: the
 * bare list for the file names alone, `--icon` for an icon of the file type in
 * front of each, `--preview` for a thumbnail, or the icon where no thumbnail
 * can be made. `filelink_size` and `uploads_description` add the size and
 * the description in any of them. See `Templates/ContentElements/Uploads.html`
 * and `Partials/ContentElement/FileIcon.html`.
 *
 * The files are real files of a real storage, indexed in a fixed order so the
 * references of the fixture can name them by uid: 1 `report.pdf`,
 * 2 `archive.zip`, 3 `notes.txt`, 4 `data.xyz` - an extension without an icon
 * of its own - and 5 `placeholder.svg`, the one image among them.
 */
final class UploadsRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    private const FILES = [
        'report.pdf' => "%PDF-1.4\n%%EOF\n",
        'archive.zip' => "PK\x05\x06" . "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0",
        'notes.txt' => "The notes of the release.\n",
        'data.xyz' => "An extension without an icon of its own.\n",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $storageUid = $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        foreach (self::FILES as $name => $content) {
            GeneralUtility::writeFile($this->instancePath . '/fileadmin/' . $name, $content);
        }
        copy(
            dirname(__DIR__, 2) . '/Configuration/DataFactory/theme-demo/Files/placeholder.svg',
            $this->instancePath . '/fileadmin/placeholder.svg',
        );
        // Indexed in this order, so the files get the uids 1 to 5 the
        // references of the fixture name.
        $storage = $this->get(StorageRepository::class)->findByUid($storageUid);
        $this->assertNotNull($storage);
        foreach ([...array_keys(self::FILES), 'placeholder.svg'] as $expectedUid => $name) {
            // "getFile()" is declared to return a "FileInterface" on v12.4 and
            // v13.4, which has no uid; the storage hands out a "File".
            $file = $storage->getFile('/' . $name);
            $this->assertInstanceOf(File::class, $file);
            $this->assertSame($expectedUid + 1, $file->getUid(), sprintf('%s was indexed out of order.', $name));
        }

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithUploads.csv');
    }

    private function render(string $path = 'theme'): string
    {
        $delivery = $this->themeDelivery();
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + ($path === 'theme' ? $delivery->siteConfiguration() : []),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        if ($path === 'theme') {
            $this->setUpFrontendRootPage(1, [], $delivery->templateValues(), $delivery->createsSysTemplateRecord());
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
     * @return list<string> The items of the file list of an element, in order.
     */
    private function items(string $element): array
    {
        preg_match_all('#<li class="theme-file-list__item">(.*?)</li>#s', $element, $matches);

        return $matches[1];
    }

    /**
     * The path of an icon of the set, which tells two icons apart where the
     * attribution comment and the attributes do not.
     */
    private function pathOf(string $icon): string
    {
        preg_match('# d="([^"]+)"#', (new IconSet())->markup($icon), $matches);
        $this->assertNotSame('', $matches[1] ?? '', sprintf('The icon "%s" has no path.', $icon));

        return $matches[1];
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, tag: string}>
     */
    public static function displayTypes(): \Generator
    {
        foreach (['theme' => 'theme delivery', 'static' => 'static include'] as $path => $label) {
            yield 'only the file name, ' . $label => ['path' => $path, 'uid' => 10, 'tag' => '<ul class="theme-file-list">'];
            yield 'file name and icon, ' . $label => ['path' => $path, 'uid' => 20, 'tag' => '<ul class="theme-file-list theme-file-list--icon">'];
            yield 'file name and thumbnail, ' . $label => ['path' => $path, 'uid' => 30, 'tag' => '<ul class="theme-file-list theme-file-list--preview">'];
            yield 'a display type nothing offers, ' . $label => ['path' => $path, 'uid' => 40, 'tag' => '<ul class="theme-file-list">'];
        }
    }

    #[DataProvider('displayTypes')]
    #[Test]
    public function theDisplayTypePicksTheModifierOfTheList(string $path, int $uid, string $tag): void
    {
        $element = $this->element($this->render($path), $uid);

        $this->assertStringContainsString($tag, $element);
        $this->assertSame(1, substr_count($element, '<ul'), 'The element renders more or less than one list.');
    }

    /**
     * The file name alone: no icon, no thumbnail, and the name is the link.
     */
    #[Test]
    public function theFileNameAloneIsTheLinkAndNothingElse(): void
    {
        $element = $this->element($this->render(), 10);

        $this->assertSame(2, count($this->items($element)));
        $this->assertMatchesRegularExpression('#<a class="theme-file-list__link" href="[^"]*fileadmin/report\.pdf"[^>]*>report\.pdf</a>#', $element);
        $this->assertStringNotContainsString('theme-file-list__icon', $element);
        $this->assertStringNotContainsString('<img', $element);
        $this->assertStringNotContainsString('<svg', $element);
    }

    /**
     * One icon per file, the one of its type by the extension, hidden from
     * assistive technology and outside the link, in the order of the files.
     */
    #[Test]
    public function everyFileShowsTheIconOfItsType(): void
    {
        $items = $this->items($this->element($this->render(), 20));

        $this->assertCount(5, $items);
        foreach (['file-pdf', 'file-zipper', 'file-lines', 'file', 'file-image'] as $position => $icon) {
            $this->assertMatchesRegularExpression(
                '#^\s*<span class="theme-file-list__icon" aria-hidden="true">\s*<svg class="theme-icon" aria-hidden="true"[^>]*>.*?</svg>\s*</span>\s*<div class="theme-file-list__body">\s*<a class="theme-file-list__link"#s',
                $items[$position],
                sprintf('The item %d does not start with the icon slot, outside the link.', $position + 1),
            );
            $this->assertStringContainsString($this->pathOf($icon), $items[$position], sprintf('The item %d does not show "%s".', $position + 1, $icon));
        }
    }

    /**
     * A thumbnail where FAL can make one - an image - with an empty "alt":
     * the name next to it is the text. A PDF gets the icon of its type in
     * the thumbnail's place instead.
     */
    #[Test]
    public function aThumbnailIsShownForAnImageAndTheIconForAnythingElse(): void
    {
        $items = $this->items($this->element($this->render(), 30));

        $this->assertCount(2, $items);
        $this->assertMatchesRegularExpression('#^\s*<img class="theme-file-list__preview" [^>]*\balt=""#', $items[0]);
        $this->assertStringNotContainsString('theme-file-list__icon', $items[0]);

        $this->assertStringNotContainsString('<img', $items[1]);
        $this->assertStringContainsString('<span class="theme-file-list__icon" aria-hidden="true">', $items[1]);
        $this->assertStringContainsString($this->pathOf('file-pdf'), $items[1]);
    }

    #[Test]
    public function theSizeAndTheDescriptionAreShownWhereTheyAreSwitchedOn(): void
    {
        $body = $this->render();

        $with = $this->element($body, 50);
        $this->assertMatchesRegularExpression('#<span class="theme-file-list__size">\d+ B</span>#', $with);
        $this->assertStringContainsString('<p class="theme-file-list__description">The notes of the release.</p>', $with);

        $without = $this->element($body, 60);
        $this->assertStringNotContainsString('theme-file-list__size', $without);
        $this->assertStringNotContainsString('A description nobody asked for.', $without);
    }
}
