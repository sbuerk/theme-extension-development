<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders a page through the `include_static_file` field of a `sys_template`
 * record - the field an editor fills by picking "Theme Extension Development"
 * under "Include static (from extensions)".
 *
 * Nothing else covered that field. {@see StaticTypoScriptIncludeTest} asserts
 * that the item is *registered* in the TCA, and
 * {@see StaticTypoScriptFallbackRenderingTest} renders the static directory by
 * writing `@import` lines into `sys_template.config`, which is a different code
 * path: `SysTemplateTreeBuilder::handleSingleIncludeStaticFile()` resolves the
 * registered directory, appends `constants.typoscript` and `setup.typoscript`
 * to it and reads an `include_static_file.txt` beside them, none of which an
 * `@import` of a single file touches. So the production path was registered and
 * never rendered.
 *
 * The static include is not a leftover. Both supported versions have site
 * sets, but an installation is free not to use them, and the extension keeps
 * shipping the directory and registering it for exactly that case - so the
 * field is a supported delivery path, and a supported path that nothing
 * renders is a path nobody would notice breaking.
 *
 * The assertions are deliberately the ones {@see SiteSetRenderingTest} makes,
 * so the two delivery paths are held to delivering the same thing rather than
 * merely to each working somehow.
 *
 * The site declares no `dependencies`, which also makes this the direct test
 * of the condition guarding `Configuration/TypoScript/Static/setup.typoscript`:
 * the branch taken when the theme is *not* delivered as a set.
 */
final class StaticFileIncludeRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    /**
     * The registered directory, not a file: the core appends the file names
     * itself. This is the value `ExtensionManagementUtility::addStaticFile()`
     * builds in `Configuration/TCA/Overrides/sys_template.php`.
     */
    private const STATIC_INCLUDE = 'EXT:theme_extension_development/Configuration/TypoScript/Static';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
        // No "dependencies": the record is the only delivery, on both versions.
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        $this->setUpFrontendRootPage(1, [], ['include_static_file' => self::STATIC_INCLUDE]);
    }

    /**
     * @return \Generator<string, array{url: string, expectedContent: string}>
     */
    public static function renderedPages(): \Generator
    {
        yield 'root page renders its title' => [
            'url' => 'https://theme.example.com/',
            'expectedContent' => 'Theme root',
        ];
        yield 'sub page renders its title' => [
            'url' => 'https://theme.example.com/a-page',
            'expectedContent' => 'A page',
        ];
    }

    #[DataProvider('renderedPages')]
    #[Test]
    public function theStaticIncludeRendersThePageTemplate(string $url, string $expectedContent): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest($url));
        $body = (string)$response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('class="theme-page__main"', $body);
        $this->assertStringContainsString($expectedContent, $body);
    }

    #[Test]
    public function theStaticIncludeIncludesTheCompiledStylesheet(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));

        $this->assertStringContainsString(
            'theme_extension_development/Resources/Public/Css/theme.css',
            (string)$response->getBody(),
        );
    }

    #[Test]
    public function theStaticIncludeRendersTheThemePartials(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));
        $body = (string)$response->getBody();

        $this->assertStringContainsString('class="theme-site-header"', $body);
        $this->assertStringContainsString('data-theme="theme_extension_development"', $body);
    }

    /**
     * A constant of the theme reaches the output, not merely a path.
     *
     * The assertions above already fail if `constants.typoscript` is missing
     * from the directory - measured, not assumed: without it
     * `{$theme.templateRootPath}` stays unresolved and Fluid cannot find a
     * single template. What they do *not* notice is a constants file that
     * resolves the paths and loses a value on the way, which lands in the
     * attribute as an empty string and looks like a rendered page.
     */
    #[Test]
    public function theStaticIncludeAppliesTheConstantsOfTheTheme(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));

        $this->assertStringContainsString('data-palette="neutral"', (string)$response->getBody());
    }
}
