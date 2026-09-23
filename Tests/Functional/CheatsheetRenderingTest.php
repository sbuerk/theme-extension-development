<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The cheatsheet, `/elements/cheatsheet`: every content element the theme
 * renders, and every variant of one, on a single page.
 *
 * The page holds no element of its own. Each family on it is an *Insert
 * records* element naming the elements of one page of the showcase, so the
 * page cannot drift from what it shows - and the element it is built out of is
 * the one it cannot demonstrate, because a shortcut reached through a shortcut
 * renders nothing on purpose.
 *
 * Which makes two things worth asserting, and neither is about markup:
 *
 * - That the page is **complete**. The expectation is read from the TypoScript
 *   rather than listed here, the way `ShowcaseTreeTest` reads it: a content
 *   type the theme starts to render and nobody adds to the cheatsheet fails
 *   here instead of quietly not being on it.
 * - That every reference **resolves**. A `shortcut` whose records were deleted
 *   or renumbered renders an empty wrapper, which looks exactly like a family
 *   that is simply short - the page would lose ten elements and still be a
 *   page of elements.
 */
final class CheatsheetRenderingTest extends AbstractFunctionalTestCase
{
    use DataFactoryImportTrait;
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    private const SCENARIO = 'Configuration/DataFactory/theme-demo/Scenario.yaml';

    /**
     * `list` is the only rendering definition of the theme that no record can
     * carry: it is the CType of an Extbase plugin, and the showcase seeds
     * none - `ShowcaseTreeTest` excludes it from the same expectation for the
     * same reason.
     */
    private const NOT_ON_THE_CHEATSHEET = ['list'];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'sbuerk/theme-extension-development',
        'sbuerk/data-factory',
    ];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->createDefaultFileStorage();
        $this->importSeedSet('theme-demo');

        $this->setUpThemeSite(identifier: 'demo', websiteTitle: 'Theme demo');
    }

    private function cheatsheet(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/elements/cheatsheet'),
        )->getBody();
    }

    private static function extensionPath(string $relative): string
    {
        return dirname(__DIR__, 2) . '/' . ltrim($relative, '/');
    }

    /**
     * @return list<string> Every `CType` the theme's TypoScript renders.
     */
    private static function renderedContentTypes(): array
    {
        preg_match_all(
            '/^tt_content\.([a-z_0-9]+)\s*=</m',
            (string)file_get_contents(self::extensionPath('Configuration/TypoScript/ContentElements.typoscript')),
            $matched,
        );

        $types = array_values(array_unique(array_diff($matched[1], self::NOT_ON_THE_CHEATSHEET)));
        sort($types);

        return $types;
    }

    #[Test]
    public function everyContentTypeTheThemeRendersIsOnTheCheatsheet(): void
    {
        $expected = self::renderedContentTypes();
        $this->assertNotEmpty($expected, 'No content type was found at all - the path is wrong.');

        preg_match_all('/data-ctype="([a-z_0-9]+)"/', $this->cheatsheet(), $rendered);
        $found = array_values(array_unique($rendered[1]));
        sort($found);

        $missing = array_values(array_diff($expected, $found));

        $this->assertSame(
            [],
            $missing,
            'These content types are rendered by the theme but not on the cheatsheet: ' . implode(', ', $missing),
        );
    }

    /**
     * Every uid the *Insert records* elements of the page name renders on it.
     *
     * The list is read out of the scenario, so the assertion moves with the
     * page rather than restating it. The anchor of a content element is
     * `c<uid>` (`Layouts/ContentElement.html`), so an element that was
     * rendered is one whose anchor is on the page.
     */
    #[Test]
    public function everyReferenceOfTheCheatsheetResolves(): void
    {
        $referenced = self::referencedUids();
        $this->assertGreaterThan(
            100,
            count($referenced),
            'The cheatsheet references fewer elements than it has families - the scenario was not read.',
        );

        $body = $this->cheatsheet();
        $missing = [];
        foreach ($referenced as $uid) {
            if (!str_contains($body, sprintf('id="c%d"', $uid))) {
                $missing[] = $uid;
            }
        }

        $this->assertSame([], $missing, 'These referenced elements did not render: ' . implode(', ', $missing));
    }

    /**
     * Nothing is referenced twice. Two families naming one element would put
     * the same `id` on the page twice, and the second anchor of that id is
     * unreachable - the defect `StyleguideRenderingTest` guards the styleguide
     * against, on the one other page of the tree that gathers everything.
     */
    #[Test]
    public function noElementIsGatheredTwice(): void
    {
        $referenced = self::referencedUids();
        $duplicates = array_values(array_unique(array_diff_assoc($referenced, array_unique($referenced))));

        $this->assertSame([], $duplicates, 'These elements are referenced by two families: ' . implode(', ', $duplicates));
    }

    /**
     * @return list<int> The uids the `records` fields of page 153 name.
     */
    private static function referencedUids(): array
    {
        $scenario = Yaml::parseFile(self::extensionPath(self::SCENARIO));

        $page = null;
        $walk = static function (array $pages) use (&$walk, &$page): void {
            foreach ($pages as $candidate) {
                if ((int)($candidate['self']['id'] ?? 0) === 153) {
                    $page = $candidate;
                }
                $walk(is_array($candidate['children'] ?? null) ? $candidate['children'] : []);
            }
        };
        $walk($scenario['entities']['page'] ?? []);

        if (!is_array($page)) {
            self::fail('The scenario declares no page 153.');
        }

        $uids = [];
        foreach ($page['entities']['content'] ?? [] as $element) {
            preg_match_all('/tt_content_(\d+)/', (string)($element['self']['records'] ?? ''), $matched);
            foreach ($matched[1] as $uid) {
                $uids[] = (int)$uid;
            }
        }

        return $uids;
    }
}
