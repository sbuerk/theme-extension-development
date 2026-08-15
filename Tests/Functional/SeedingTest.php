<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\DataMapFactory;
use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use SBUERK\ThemeExtensionDevelopment\Seeding\Seeder;
use SBUERK\ThemeExtensionDevelopment\Seeding\YamlSeedParser;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Writes the shipped demo seed into a real instance.
 *
 * The subject is the seeding, and the reason it goes through DataHandler rather
 * than through SQL: what is asserted here - generated slugs, computed sorting,
 * a resolved page tree - is precisely what a seeder writing rows itself would
 * have to reimplement.
 *
 * The final test closes the loop by rendering the seeded tree through the
 * theme, so the definition is proven to produce a page a browser can be pointed
 * at rather than merely rows in a table.
 */
final class SeedingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    private const DEMO_SEED = 'EXT:theme_extension_development/Configuration/Seeds/Demo.yaml';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
    }

    /**
     * @return array<string, int> The written uids, keyed by seed identifier.
     */
    private function seedDemo(): array
    {
        $backendUser = $this->setUpBackendUser(1);
        $parser = new YamlSeedParser();
        $seeder = new Seeder(new DataMapFactory());

        return $seeder->seed($parser->parseFile(self::DEMO_SEED), $backendUser);
    }

    /**
     * Reads a table through the QueryBuilder rather than through hand written
     * SQL, and without restrictions, so what is asserted is what the seeder
     * actually wrote.
     *
     * The QueryBuilder quotes identifiers, which is not cosmetic here:
     * PostgreSQL folds an unquoted identifier to lower case, so a literal
     * "SELECT CType" asks for a column "ctype" that does not exist. SQLite and
     * MySQL accept it, which is exactly how such a query passes locally and
     * fails in CI.
     *
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    private function queryTable(string $table, array $columns, string $orderBy): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select(...$columns)
            ->from($table)
            ->orderBy($orderBy)
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    #[Test]
    public function seedWritesTheDeclaredUids(): void
    {
        $uids = $this->seedDemo();

        // The site configurations of the development instances point at root
        // page 1, so this is a promise the seed definition has to keep.
        $this->assertSame(1, $uids['home']);
        $this->assertSame(2, $uids['styles']);
        $this->assertSame(3, $uids['empty']);
    }

    #[Test]
    public function seedBuildsThePageTree(): void
    {
        $this->seedDemo();

        $rows = $this->queryTable('pages', ['uid', 'pid', 'title', 'slug'], 'uid');

        $this->assertCount(3, $rows);
        $this->assertSame(0, (int)$rows[0]['pid']);
        // Both sub pages hang below the root page, not below each other.
        $this->assertSame(1, (int)$rows[1]['pid']);
        $this->assertSame(1, (int)$rows[2]['pid']);
    }

    #[Test]
    public function dataHandlerGeneratesTheSlugsRatherThanTheSeed(): void
    {
        $this->seedDemo();

        $slug = $this->queryTable('pages', ['uid', 'slug'], 'uid')[1]['slug'] ?? null;

        // Declared as "/typography" in the definition and kept by DataHandler,
        // which is what proves the slug field was evaluated at all.
        $this->assertSame('/typography', $slug);
    }

    #[Test]
    public function siblingsKeepTheirDeclarationOrder(): void
    {
        $this->seedDemo();

        $sorted = array_column(
            array_values(array_filter(
                $this->queryTable('pages', ['uid', 'pid'], 'sorting'),
                static fn(array $row): bool => (int)$row['pid'] === 1,
            )),
            'uid',
        );

        // A new record goes to the top of its parent by default, so without the
        // negative pid convention this would come back as [3, 2].
        $this->assertSame([2, 3], array_map('intval', $sorted));
    }

    #[Test]
    public function contentIsWrittenBelowThePageThatCarriesIt(): void
    {
        $this->seedDemo();

        $rows = $this->queryTable('tt_content', ['pid', 'CType', 'header'], 'sorting');

        $this->assertCount(4, $rows);
        $this->assertSame(1, (int)$rows[0]['pid']);
        $this->assertSame('header', $rows[0]['CType']);
        $this->assertSame('A frontend to look at', $rows[0]['header']);
    }

    #[Test]
    public function seedingRefusesWithoutAnAdminUser(): void
    {
        $backendUser = new BackendUserAuthentication();
        $backendUser->user = ['uid' => 2, 'username' => 'editor', 'admin' => 0];

        $this->expectException(SeedingException::class);
        $this->expectExceptionCode(1786924814);

        (new Seeder(new DataMapFactory()))
            ->seed((new YamlSeedParser())->parseFile(self::DEMO_SEED), $backendUser);
    }

    #[Test]
    public function theSeededTreeRendersThroughTheTheme(): void
    {
        $this->seedDemo();

        $this->writeSiteConfiguration(
            'demo',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme demo',
            ) + [
                'dependencies' => ['sbuerk/theme-extension-development'],
            ],
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        $this->setUpFrontendRootPage(1, [], [], false);

        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/typography'),
        )->getBody();

        $this->assertStringContainsString('<h1 class="content-element__heading">Typography</h1>', $body);
        $this->assertStringContainsString('three inline cases', $body);
    }
}
