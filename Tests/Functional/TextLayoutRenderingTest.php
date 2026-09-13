<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The text element sets its text in columns for the layout "Columns".
 *
 * `layout` 1 becomes `.theme-text--columns` on the block holding the rich
 * text (`Templates/ContentElements/Text.html`), every other value no modifier:
 * the default, the two values the page TSconfig took out of the select for
 * this CType, which an older record may still carry, and a value nothing ever
 * offered. `ContentElementContractTest` holds the one modifier to the compiled
 * stylesheet.
 *
 * Rendered through both delivery paths, like the appearance fields: the
 * template is the same file either way, but the path decides whether the
 * theme renders the element at all.
 */
final class TextLayoutRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithTextLayouts.csv');
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
        // The page is the theme's, or none of the assertions below means anything.
        $this->assertStringContainsString('data-theme-page-layout=', $body);

        return $body;
    }

    /**
     * The classes of the block holding the rich text of one element.
     *
     * @return list<string>
     */
    private function bodyClasses(string $body, int $uid): array
    {
        $this->assertSame(
            1,
            preg_match(sprintf('#<div id="c%d"\s[^>]*>.*?<div class="(theme-content-element__body[^"]*)">#s', $uid), $body, $matches),
            sprintf('Element c%d rendered no text body.', $uid),
        );

        return preg_split('/\s+/', trim($matches[1])) ?: [];
    }

    /**
     * @return \Generator<string, array{path: string, uid: int, expected: list<string>}>
     */
    public static function layouts(): \Generator
    {
        foreach (['set' => 'site set', 'static' => 'static include'] as $path => $label) {
            yield 'running text, ' . $label => ['path' => $path, 'uid' => 10, 'expected' => ['theme-content-element__body']];
            yield 'columns, ' . $label => ['path' => $path, 'uid' => 20, 'expected' => ['theme-content-element__body', 'theme-text--columns']];
            yield 'a layout the page TSconfig removed, ' . $label => ['path' => $path, 'uid' => 30, 'expected' => ['theme-content-element__body']];
            yield 'a layout nothing offers, ' . $label => ['path' => $path, 'uid' => 40, 'expected' => ['theme-content-element__body']];
        }
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('layouts')]
    #[Test]
    public function theLayoutPicksTheModifierOfTheText(string $path, int $uid, array $expected): void
    {
        $this->assertSame($expected, $this->bodyClasses($this->render($path), $uid));
    }

    /**
     * The columns hold the rich text as it was written: the modifier changes
     * how the block is set, not what is in it.
     */
    #[Test]
    public function theColumnsHoldTheRichTextUnchanged(): void
    {
        $this->assertMatchesRegularExpression(
            '#<div class="theme-content-element__body theme-text--columns">(?:(?!</div>).)*<h3>A heading inside</h3><p>Two columns\.</p>\s*</div>#s',
            $this->render('set'),
        );
    }
}
