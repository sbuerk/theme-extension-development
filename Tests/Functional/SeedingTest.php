<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\DataMapFactory;
use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use SBUERK\ThemeExtensionDevelopment\Seeding\FileSeeder;
use SBUERK\ThemeExtensionDevelopment\Seeding\Seeder;
use SBUERK\ThemeExtensionDevelopment\Seeding\YamlSeedParser;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
        // A functional instance has no file storage: the testing framework
        // creates the fileadmin folders but no sys_file_storage record, which
        // a real instance gets from "typo3 setup". Created through the API
        // rather than from a fixture, so the flexform driver configuration and
        // the capability flags are the core's business and not a hand written
        // record that is wrong in a way nothing reports.
        GeneralUtility::makeInstance(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Seeding test storage', true);
    }

    private function createSeeder(): Seeder
    {
        return new Seeder(
            new DataMapFactory(),
            new FileSeeder(GeneralUtility::makeInstance(StorageRepository::class)),
        );
    }

    /**
     * @return array<string, int> The written uids, keyed by seed identifier.
     */
    private function seedDemo(): array
    {
        $backendUser = $this->setUpBackendUser(1);

        return $this->createSeeder()->seed((new YamlSeedParser())->parseFile(self::DEMO_SEED), $backendUser);
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
        $this->assertSame(3, $uids['media']);
        $this->assertSame(4, $uids['empty']);
    }

    #[Test]
    public function seedBuildsThePageTree(): void
    {
        $this->seedDemo();

        $rows = $this->queryTable('pages', ['uid', 'pid', 'title', 'slug'], 'uid');

        $this->assertCount(4, $rows);
        $this->assertSame(0, (int)$rows[0]['pid']);
        // All sub pages hang below the root page, not below each other.
        $this->assertSame(1, (int)$rows[1]['pid']);
        $this->assertSame(1, (int)$rows[2]['pid']);
        $this->assertSame(1, (int)$rows[3]['pid']);
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
        // negative pid convention this would come back as [4, 3, 2].
        $this->assertSame([2, 3, 4], array_map('intval', $sorted));
    }

    #[Test]
    public function contentIsWrittenBelowThePageThatCarriesIt(): void
    {
        $this->seedDemo();

        $rows = $this->queryTable('tt_content', ['pid', 'CType', 'header'], 'sorting');

        $this->assertCount(7, $rows);
        $this->assertSame(1, (int)$rows[0]['pid']);
        $this->assertSame('header', $rows[0]['CType']);
        $this->assertSame('A frontend to look at', $rows[0]['header']);
    }

    #[Test]
    public function filesAreCopiedIntoTheStorageAndIndexed(): void
    {
        $this->seedDemo();

        $files = $this->queryTable('sys_file', ['uid', 'identifier', 'name'], 'uid');

        $this->assertCount(2, $files);
        $this->assertSame('/theme-demo/placeholder.svg', $files[0]['identifier']);
        $this->assertSame('/theme-demo/placeholder-portrait.svg', $files[1]['identifier']);
        // Copied, not moved: the source has to survive in the repository.
        $this->assertFileExists(dirname(__DIR__, 2) . '/Configuration/Seeds/Files/placeholder.svg');
        $this->assertFileExists(dirname(__DIR__, 2) . '/Configuration/Seeds/Files/placeholder-portrait.svg');
    }

    #[Test]
    public function fileReferencesArePointedAtTheRecordThatDeclaresThem(): void
    {
        $this->seedDemo();

        $references = $this->queryTable(
            'sys_file_reference',
            ['uid_local', 'uid_foreign', 'tablenames', 'fieldname'],
            'uid',
        );

        // One on the root page, one on the single image element and two on the
        // gallery element.
        $this->assertCount(4, $references);
        $this->assertSame('pages', $references[0]['tablenames']);
        $this->assertSame('media', $references[0]['fieldname']);
        // The root page of the demo definition.
        $this->assertSame(1, (int)$references[0]['uid_foreign']);

        // A content element reference names the field of "tt_content" it was
        // declared under, which is what the "FilesProcessor" of the rendering
        // looks the images up by.
        $this->assertSame('tt_content', $references[1]['tablenames']);
        $this->assertSame('image', $references[1]['fieldname']);
    }

    #[Test]
    public function fileReferencesCarryTheFieldsTheDefinitionDeclaresOnThem(): void
    {
        $this->seedDemo();

        $references = $this->queryTable(
            'sys_file_reference',
            ['uid', 'alternative', 'title', 'description'],
            'uid',
        );

        // The reference on the root page declares an alternative text and a
        // title, and no description.
        $this->assertSame('A placeholder graphic', $references[0]['alternative']);
        $this->assertSame('Placeholder', $references[0]['title']);
        $this->assertSame('', (string)$references[0]['description']);

        // The one on the single image element declares a description, which is
        // what the theme renders as the caption.
        $this->assertSame('A placeholder graphic in landscape format', $references[1]['alternative']);
        $this->assertSame(
            'The description of a file reference is rendered as the caption.',
            $references[1]['description'],
        );
    }

    #[Test]
    public function aReferenceCannotPointItselfSomewhereElseThroughDeclaredColumns(): void
    {
        $definition = (new YamlSeedParser())->parse([
            'identifier' => 'structural',
            'files' => [
                [
                    'identifier' => 'placeholder',
                    'source' => 'EXT:theme_extension_development/Configuration/Seeds/Files/placeholder.svg',
                    'folder' => 'structural',
                ],
            ],
            'pages' => [
                [
                    'identifier' => 'home',
                    'uid' => 1,
                    'title' => 'Home',
                    'slug' => '/',
                    'is_siteroot' => 1,
                    'doktype' => 1,
                    'files' => [
                        'media' => [
                            [
                                'identifier' => 'placeholder',
                                // The columns the seeder owns...
                                'uid_foreign' => 999,
                                'tablenames' => 'tt_content',
                                'fieldname' => 'image',
                                // ...and one it does not.
                                'alternative' => 'Kept',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->createSeeder()->seed($definition, $this->setUpBackendUser(1));

        $references = $this->queryTable(
            'sys_file_reference',
            ['uid_foreign', 'tablenames', 'fieldname', 'alternative'],
            'uid',
        );

        // Structural values win, so a definition cannot detach a reference from
        // the record that declares it - the same rule a record's "pid" follows.
        $this->assertSame(1, (int)$references[0]['uid_foreign']);
        $this->assertSame('pages', $references[0]['tablenames']);
        $this->assertSame('media', $references[0]['fieldname']);
        // Everything else is written as declared.
        $this->assertSame('Kept', $references[0]['alternative']);
    }

    #[Test]
    public function referencingAnUndeclaredFileIsRejected(): void
    {
        $this->expectException(SeedingException::class);
        $this->expectExceptionCode(1786924828);

        $definition = (new YamlSeedParser())->parse([
            'identifier' => 'broken',
            'pages' => [
                ['identifier' => 'home', 'files' => ['media' => ['nope']]],
            ],
        ]);

        (new DataMapFactory())->createFromDefinition($definition);
    }

    #[Test]
    public function seedingRefusesWithoutAnAdminUser(): void
    {
        $backendUser = new BackendUserAuthentication();
        $backendUser->user = ['uid' => 2, 'username' => 'editor', 'admin' => 0];

        $this->expectException(SeedingException::class);
        $this->expectExceptionCode(1786924814);

        $this->createSeeder()->seed((new YamlSeedParser())->parseFile(self::DEMO_SEED), $backendUser);
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
