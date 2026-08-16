<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Renders the classic content types `EXT:frontend` registers but supplies no
 * rendering for.
 *
 * `fluid_styled_content` is not a dependency of this theme, and on TYPO3 v14 it
 * is not even installed. What it would have supplied is the *rendering*, never
 * the TCA: every one of these types can be created in the backend of an
 * installation using this theme whether or not anything renders it. Without a
 * definition the core prints its own notice instead, so a type nobody covered
 * looks broken to an editor rather than absent.
 *
 * The sweep below is therefore the important assertion: it fails for any type
 * that is creatable but unrendered, which is the state the whole set was in
 * before this change.
 */
final class CoreContentElementRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const NO_RENDERING_DEFINITION = 'has no rendering definition';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithCoreContentElements.csv');
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
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        )->getBody();
    }

    #[Test]
    public function noRenderedElementFallsBackToTheCoreNotice(): void
    {
        $this->assertStringNotContainsString(self::NO_RENDERING_DEFINITION, $this->render());
    }

    /**
     * @return \Generator<string, array{ctype: string}>
     */
    public static function coveredContentTypes(): \Generator
    {
        foreach (['bullets', 'table', 'div', 'html', 'textmedia', 'textpic', 'uploads', 'shortcut'] as $ctype) {
            yield $ctype => ['ctype' => $ctype];
        }
    }

    /**
     * The wrapper carries the CType, so this also proves each element actually
     * went through `lib.contentElement` rather than being emitted some other
     * way.
     */
    #[DataProvider('coveredContentTypes')]
    #[Test]
    public function everyCoveredTypeIsRenderedThroughTheContentElementWrapper(string $ctype): void
    {
        $this->assertStringContainsString(
            sprintf('data-ctype="%s"', $ctype),
            $this->render(),
            sprintf('The "%s" element did not render through the shared wrapper.', $ctype),
        );
    }

    #[Test]
    public function aBulletListBecomesAList(): void
    {
        $body = $this->render();

        $this->assertMatchesRegularExpression('#<ul[^>]*>.*?<li[^>]*>\s*First item\s*</li>#s', $body);
        $this->assertStringContainsString('Third item', $body);
    }

    #[Test]
    public function aTableBecomesATableWithItsCellsSplit(): void
    {
        $body = $this->render();

        $this->assertMatchesRegularExpression('#<table\b#', $body);
        foreach (['Name', 'Role', 'Ada', 'Analyst', 'Grace', 'Compiler'] as $cell) {
            $this->assertStringContainsString($cell, $body, sprintf('The cell "%s" is missing.', $cell));
        }

        // Split into cells, not printed as one delimited string.
        $this->assertStringNotContainsString('Ada|Analyst', $body);
    }

    #[Test]
    public function aDividerBecomesARule(): void
    {
        $this->assertMatchesRegularExpression('#<hr\b[^>]*>#', $this->render());
    }

    /**
     * The one element that deliberately does not escape. The core restricts it
     * to admin users for exactly that reason, and running it through
     * `f:format.html` instead would hand it to the RTE parser and rewrite it.
     */
    #[Test]
    public function anHtmlElementIsEmittedUntouched(): void
    {
        $body = $this->render();

        $this->assertStringContainsString('<em data-raw="yes">Unescaped on purpose</em>', $body);
        $this->assertStringNotContainsString('&lt;em data-raw=', $body);
    }

    /**
     * A shortcut renders another record. The reference here points at the
     * bullet list, so its content has to appear twice on the page - once in
     * its own place and once through the shortcut.
     */
    #[Test]
    public function aShortcutRendersTheRecordItPointsAt(): void
    {
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($this->render(), 'First item'),
            'The shortcut did not render the record it references.',
        );
    }

    /**
     * The fixture contains a shortcut pointing at itself and a pair pointing
     * at each other. Rendering the page at all is the assertion.
     *
     * This is not hypothetical on TYPO3 v14. The guard that made it safe lived
     * on `TypoScriptFrontendController::$recordRegister`, and v14 removed that
     * class outright (#107831); `RecordsContentObject::render()` there renders
     * every reference unconditionally, and the older `cObjectDepthCounter` was
     * dropped back in v11.4 with a note that PHP's own nesting limit is the
     * expected outcome. So on v14 an editor pointing a shortcut at itself took
     * the request down until this theme broke the cycle itself.
     *
     * The break is structural: inside a shortcut, the shortcut branch renders
     * nothing, so no chain of references can return to its start. That needs
     * no per-request state and behaves the same on both core versions.
     */
    #[Test]
    public function aCircularShortcutDoesNotTakeTheRequestDown(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/'),
        );

        $this->assertSame(200, $response->getStatusCode());

        $body = (string)$response->getBody();

        // The page still rendered everything else around the cycle.
        $this->assertStringContainsString('data-ctype="bullets"', $body);
        $this->assertStringNotContainsString(self::NO_RENDERING_DEFINITION, $body);
    }
}
