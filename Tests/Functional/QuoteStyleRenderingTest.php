<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The styles of the testimonial.
 *
 * `tx_theme_quote_style` becomes `.theme-quote--pull` or `.theme-quote--centred`
 * in `Templates/ContentElements/ThemeTestimonial.html`, and both carry the
 * quotation mark of the set, hidden from assistive technology. The default -
 * and a value nothing offers - renders the quotation as it always did: no
 * modifier and no mark.
 */
final class QuoteStyleRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithQuoteStyles.csv');
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
     * @return \Generator<string, array{path: string, uid: int, tag: string, mark: bool}>
     */
    public static function styles(): \Generator
    {
        foreach (['theme' => 'theme delivery', 'static' => 'static include'] as $path => $label) {
            yield 'the default, ' . $label => ['path' => $path, 'uid' => 10, 'tag' => '<figure class="theme-quote">', 'mark' => false];
            yield 'a pull quote, ' . $label => ['path' => $path, 'uid' => 20, 'tag' => '<figure class="theme-quote theme-quote--pull">', 'mark' => true];
            yield 'centred, ' . $label => ['path' => $path, 'uid' => 30, 'tag' => '<figure class="theme-quote theme-quote--centred">', 'mark' => true];
            yield 'a style nothing offers, ' . $label => ['path' => $path, 'uid' => 40, 'tag' => '<figure class="theme-quote">', 'mark' => false];
        }
    }

    #[DataProvider('styles')]
    #[Test]
    public function theStylePicksTheModifierAndTheMark(string $path, int $uid, string $tag, bool $mark): void
    {
        $element = $this->element($this->render($path), $uid);

        $this->assertStringContainsString($tag, $element);
        $this->assertSame($mark, str_contains($element, 'theme-quote__mark'));
        $this->assertStringContainsString('<blockquote class="theme-quote__text">', $element);
    }

    /**
     * The mark is the "quote-left" icon of the set, in a slot hidden from
     * assistive technology, before the quotation - the "blockquote" is what
     * says it is one.
     */
    #[Test]
    public function theMarkIsTheQuotationMarkOfTheSetBeforeTheQuotation(): void
    {
        $element = $this->element($this->render(), 20);

        $this->assertMatchesRegularExpression(
            '#<figure class="theme-quote theme-quote--pull">\s*<span class="theme-quote__mark" aria-hidden="true"><svg class="theme-icon" aria-hidden="true"[^>]*>.*?</svg></span>\s*<blockquote class="theme-quote__text">#s',
            $element,
        );
        preg_match('# d="([^"]+)"#', (new IconSet())->markup('quote-left'), $path);
        $this->assertNotSame('', $path[1] ?? '');
        $this->assertStringContainsString($path[1], $element);
    }
}
