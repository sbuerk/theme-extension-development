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
 * The portrait of the testimonial.
 *
 * The one file of `image` renders as a `.theme-avatar` in the media slot
 * `.theme-quote__portrait`, first in the attribution, next to the name -
 * where it is decoration, so its `alt` is empty even though the reference
 * carries an alternative text. Without a name there is no portrait: it would
 * be the only thing saying who is quoted, and would need a name of its own.
 * See `Templates/ContentElements/ThemeTestimonial.html`.
 *
 * The image is one real file of a storage, indexed as uid 1.
 */
final class TestimonialPortraitRenderingTest extends AbstractFunctionalTestCase
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
        copy(
            dirname(__DIR__, 2) . '/Configuration/DataFactory/theme-demo/Files/placeholder-portrait.svg',
            $this->instancePath . '/fileadmin/placeholder-portrait.svg',
        );
        $storage = $this->get(StorageRepository::class)->findByUid($storageUid);
        $this->assertNotNull($storage);
        // "getFile()" is declared to return a "FileInterface" on v12.4 and
        // v13.4, which has no uid; the storage hands out a "File".
        $file = $storage->getFile('/placeholder-portrait.svg');
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame(1, $file->getUid());

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithTestimonialPortraits.csv');
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
     * @return \Generator<string, array{path: string}>
     */
    public static function deliveryPaths(): \Generator
    {
        yield 'theme delivery' => ['path' => 'theme'];
        yield 'static include' => ['path' => 'static'];
    }

    /**
     * The avatar is the first thing in the attribution, before the name, and
     * holds the picture with an empty alternative text: the name beside it
     * says who it is.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function thePortraitIsADecorativeAvatarBeforeTheName(string $path): void
    {
        $element = $this->element($this->render($path), 10);

        $this->assertMatchesRegularExpression(
            '#<figcaption class="theme-quote__attribution">\s*<span class="theme-avatar theme-avatar--large theme-quote__portrait"><img class="theme-avatar__image" [^>]*></span>\s*<span class="theme-quote__author">Ada Example</span>#',
            $element,
        );
        $this->assertSame(1, preg_match('#<img class="theme-avatar__image" [^>]*>#', $element, $image));
        $this->assertMatchesRegularExpression('#\balt=""#', $image[0]);
        $this->assertMatchesRegularExpression('#\bsrc="[^"]*placeholder-portrait[^"]*\.svg"#', $image[0]);
        $this->assertStringNotContainsString('An alternative text the portrait must not use', $element);
    }

    /**
     * No name, no portrait: the picture would be the only thing naming the
     * person. No image, no avatar - not an empty slot.
     */
    #[Test]
    public function thereIsNoPortraitWithoutANameOrAnImage(): void
    {
        $body = $this->render();

        $unnamed = $this->element($body, 20);
        $this->assertStringContainsString('A role without a name', $unnamed);
        $this->assertStringNotContainsString('<img', $unnamed);
        $this->assertStringNotContainsString('theme-avatar', $unnamed);

        $this->assertStringNotContainsString('theme-avatar', $this->element($body, 30));
    }

    #[Test]
    public function aQuotationStyleKeepsThePortrait(): void
    {
        $element = $this->element($this->render(), 40);

        $this->assertStringContainsString('<figure class="theme-quote theme-quote--pull">', $element);
        $this->assertStringContainsString('theme-quote__portrait', $element);
    }
}
