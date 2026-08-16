<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\DataMapFactory;
use SBUERK\ThemeExtensionDevelopment\Seeding\FileSeeder;
use SBUERK\ThemeExtensionDevelopment\Seeding\Seeder;
use SBUERK\ThemeExtensionDevelopment\Seeding\YamlSeedParser;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders the `image` content element.
 *
 * The element itself is registered in TCA by **EXT:frontend**, not by
 * `fluid_styled_content` and not by this extension: EXT:frontend ships
 * `Configuration/TCA/Overrides/225-tt_content-content_type-image.php` on v13.4,
 * while the FSC TCA folder holds nothing but a
 * `sys_template.php`. What FSC supplies, and what this theme therefore has to
 * bring itself, is the rendering.
 *
 * What is asserted is the part that is easy to get wrong: that the *backend
 * fields* reach the output. `imagecols` has to decide the number of columns and
 * `image_zoom` the link, because a template iterating the files directly would
 * render the images perfectly and ignore both - and nothing would report it.
 */
final class ImageElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const SEED = 'EXT:theme_extension_development/Tests/Functional/Fixtures/Seeds/ImageElement.yaml';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        // A functional instance has no "sys_file_storage" record - the testing
        // framework creates the folders, "typo3 setup" creates the record.
        GeneralUtility::makeInstance(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Image element test storage', true);

        $seeder = new Seeder(
            new DataMapFactory(),
            new FileSeeder(GeneralUtility::makeInstance(StorageRepository::class)),
        );
        $seeder->seed((new YamlSeedParser())->parseFile(self::SEED), $this->setUpBackendUser(1));

        $this->writeSiteConfiguration(
            'theme',
            // See SiteSetRenderingTest for why this is not the "additional"
            // argument: https://github.com/sbuerk/typo3-site-based-test-trait/issues/25
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

    private function renderRootPage(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    #[Test]
    public function imageElementIsRenderedRatherThanTheCoreNotice(): void
    {
        $this->assertStringNotContainsString('has no rendering definition', $this->renderRootPage());
    }

    #[Test]
    public function everyReferencedImageIsRenderedInAFigure(): void
    {
        $body = $this->renderRootPage();

        // One, two, one and none: the fourth element references no file at all.
        $this->assertSame(4, substr_count($body, '<figure class="theme-gallery__item">'));
    }

    #[Test]
    public function anImageIsRenderedWithTheDimensionsTheGalleryComputed(): void
    {
        $body = $this->renderRootPage();

        // The SVG declares a 480x270 viewBox and the gallery is computed for a
        // width of 1200, so it is rendered at its own size rather than scaled
        // up - "min(maxMediaWidth, croppedWidth)".
        $this->assertStringContainsString('width="480"', $body);
        $this->assertStringContainsString('height="270"', $body);
        // The portrait file of the second element, unchanged for the same
        // reason.
        $this->assertStringContainsString('width="270"', $body);
        $this->assertStringContainsString('height="480"', $body);
    }

    #[Test]
    public function theColumnCountComesFromTheRecordRatherThanFromTheTemplate(): void
    {
        $body = $this->renderRootPage();

        // "imagecols" is 2 on the second element and unset on the others.
        $this->assertSame(1, substr_count($body, 'data-theme-gallery-columns="2"'));
        $this->assertSame(2, substr_count($body, 'data-theme-gallery-columns="1"'));
    }

    #[Test]
    public function twoImagesInTwoColumnsShareOneRow(): void
    {
        $body = $this->renderRootPage();

        // Three galleries, three rows: the two column element puts both of its
        // images into the same row rather than into one row each.
        $this->assertSame(3, substr_count($body, '<div class="theme-gallery__row">'));
    }

    #[Test]
    public function anElementWithoutAnImageRendersNoGallery(): void
    {
        $body = $this->renderRootPage();

        // The element itself is rendered - its heading proves it - but it
        // contributes no empty gallery markup.
        $this->assertStringContainsString('An element without an image', $body);
        $this->assertSame(3, substr_count($body, '<div class="theme-gallery '));
    }

    #[Test]
    public function imageZoomLinksTheImageToTheOriginalFile(): void
    {
        $body = $this->renderRootPage();

        // Set on the third element only.
        $this->assertSame(1, substr_count($body, '<a class="theme-gallery__zoom"'));
        $this->assertMatchesRegularExpression(
            '#<a class="theme-gallery__zoom" href="[^"]+/placeholder\.svg">#',
            $body,
        );
    }

    #[Test]
    public function alternativeAndDescriptionOfTheReferenceReachTheOutput(): void
    {
        // Both are declared on the reference in the seed definition, so this
        // asserts the whole chain: the definition writes them onto the
        // "sys_file_reference" record and the template reads them back off it.
        $body = $this->renderRootPage();

        $this->assertStringContainsString('alt="A placeholder image"', $body);
        $this->assertStringContainsString(
            '<figcaption class="theme-gallery__caption">The caption of the image</figcaption>',
            $body,
        );
    }
}
