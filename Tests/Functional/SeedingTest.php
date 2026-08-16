<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Seeding\DataMapFactory;
use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use SBUERK\ThemeExtensionDevelopment\Seeding\FileImporterInterface;
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
    use ThemeSiteTrait;

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
        // The file importer is fetched from the container rather than
        // constructed: it is the core version aware half of the seeding, and
        // only the container knows which of "Core12/" and "Core13/" the running
        // core version registers.
        return new Seeder(
            new DataMapFactory(),
            new FileSeeder(
                GeneralUtility::makeInstance(StorageRepository::class),
                $this->get(FileImporterInterface::class),
            ),
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
        $this->assertSame(5, $uids['elements']);
        $this->assertSame(6, $uids['elements-core']);
        $this->assertSame(7, $uids['elements-menu']);
        $this->assertSame(8, $uids['elements-theme']);
        $this->assertSame(9, $uids['styleguide']);
    }

    /**
     * A suggested uid is honoured even when it is nowhere near the uid the
     * record would have got anyway.
     *
     * `seedWritesTheDeclaredUids` above cannot prove this. The demo definition
     * declares 1 to 9 in declaration order, which is exactly what DataHandler
     * assigns when it ignores the suggestion entirely - so that test passed for
     * years' worth of the wrong reason, and would have gone on passing if the
     * suggestion had never worked at all.
     *
     * This one declares a single page with a uid it cannot reach by counting.
     */
    #[Test]
    public function aSuggestedUidIsHonouredRatherThanTheNextFreeOne(): void
    {
        $definition = (new YamlSeedParser())->parse([
            'identifier' => 'suggested',
            'pages' => [
                [
                    'identifier' => 'home',
                    'uid' => 4711,
                    'title' => 'Home',
                    'slug' => '/',
                    'is_siteroot' => 1,
                    'doktype' => 1,
                ],
            ],
        ]);

        $uids = $this->createSeeder()->seed($definition, $this->setUpBackendUser(1));

        $this->assertSame(4711, $uids['home']);
        $this->assertSame(
            [4711],
            array_map('intval', array_column($this->queryTable('pages', ['uid'], 'uid'), 'uid')),
        );
    }

    /**
     * A multi file relation comes out in the order the definition declares it.
     *
     * The reference rows themselves were always written, and the images always
     * appeared, which is why this went unnoticed: `FileRepository` finds them
     * by `uid_foreign` and never reads the parent's counter column. It orders
     * by `sorting_foreign` though, and that column is written by
     * `RelationHandler::writeForeignField()` - which only runs once DataHandler
     * has resolved the placeholders in the parent's field.
     *
     * It could not resolve them: the placeholders contained underscores, and a
     * relation value with one is read as the `<table>_<uid>` form and split
     * there. Every seeded reference kept a `sorting_foreign` of 0, and the
     * order of the gallery was whatever the database felt like returning.
     */
    #[Test]
    public function aMultiFileRelationIsNumberedInDeclarationOrder(): void
    {
        $this->seedDemo();

        $element = $this->contentElementUid('Two images, two columns');
        $gallery = array_values(array_filter(
            $this->queryTable(
                'sys_file_reference',
                ['uid', 'uid_foreign', 'tablenames', 'fieldname', 'sorting_foreign', 'description'],
                'uid',
            ),
            static fn(array $row): bool => $row['tablenames'] === 'tt_content'
                && $row['fieldname'] === 'image'
                && (int)$row['uid_foreign'] === $element,
        ));
        $this->assertCount(2, $gallery, 'The two gallery references were not written.');

        // Numbered, not left at zero, and in the order the definition declares.
        $this->assertSame('Landscape', $gallery[0]['description']);
        $this->assertSame(1, (int)$gallery[0]['sorting_foreign']);
        $this->assertSame('Portrait', $gallery[1]['description']);
        $this->assertSame(2, (int)$gallery[1]['sorting_foreign']);

        // The parent's counter column is the other half DataHandler only writes
        // once the relation resolved. It is not read when rendering, so it is
        // the honest tell that the relation was understood rather than merely
        // that rows exist.
        $counters = array_column($this->queryTable('tt_content', ['uid', 'image'], 'uid'), 'image', 'uid');
        $this->assertSame(2, (int)$counters[$element]);
    }

    /**
     * The uid of the one seeded content element carrying this header.
     *
     * The showcase pages deliberately reuse copy - two elements can carry the
     * same file with the same description - so a reference has to be selected
     * through the element that declares it rather than through its own values.
     */
    private function contentElementUid(string $header): int
    {
        $matching = array_values(array_filter(
            $this->queryTable('tt_content', ['uid', 'header'], 'uid'),
            static fn(array $row): bool => $row['header'] === $header,
        ));

        $this->assertCount(1, $matching, sprintf('"%s" does not identify exactly one content element.', $header));

        return (int)$matching[0]['uid'];
    }

    /**
     * `backend_layout` and `nav_hide` are ordinary fields of a page, and the
     * seeder has no code for either: `DataMapFactory` copies every key it does
     * not recognise as structure into the data map untouched.
     *
     * That is a design decision - a seeder that special-cases a field it does
     * not have to is a seeder that will special-case the next one too - and it
     * is invisible in the code, because it consists of the absence of a branch.
     * This is what keeps it true.
     */
    #[Test]
    public function fieldsTheSeederKnowsNothingAboutAreWrittenAsDeclared(): void
    {
        $this->seedDemo();

        $rows = $this->queryTable('pages', ['uid', 'backend_layout', 'nav_hide'], 'uid');
        $byUid = array_column($rows, null, 'uid');

        $this->assertSame('start', $byUid[1]['backend_layout']);
        $this->assertSame('content_sidebar', $byUid[6]['backend_layout']);
        // Declared on no page at all, so it stays at the column default and the
        // layout is resolved by the fallback rather than by the field.
        $this->assertSame('', (string)$byUid[4]['backend_layout']);

        // "nav_hide", never "hidden": a hidden page returns 404 in the frontend
        // and needs a backend preview link, which defeats seeding it.
        $this->assertSame(1, (int)$byUid[9]['nav_hide']);
        $this->assertSame(0, (int)$byUid[1]['nav_hide']);
    }

    /**
     * An inline child is related to its parent by DataHandler, from the comma
     * separated list of placeholders the parent's field is written with.
     *
     * The columns asserted here are precisely the ones the definition never
     * writes. A seeder filling them in itself would produce the same rows and
     * would be wrong for the first relation whose TCA names them differently.
     */
    #[Test]
    public function inlineChildrenAreRelatedToTheRecordThatDeclaresThem(): void
    {
        $this->seedDemo();

        // "tablename" is singular here. It comes from "foreign_table_field" of
        // this relation, and is not the "tablenames" of "sys_file_reference" -
        // the two tables answer the same question with different column names,
        // and the seeder may hard-code neither.
        $children = $this->queryTable(
            'tx_theme_list_item',
            ['uid', 'pid', 'uid_foreign', 'tablename', 'fieldname', 'sorting_foreign'],
            'uid',
        );

        $this->assertNotEmpty($children, 'No inline child was written at all.');

        // Record 0 is where a child lands when the parent's placeholder went
        // unresolved - the whole failure mode this mechanism has to avoid.
        $this->assertSame([], array_values(array_filter(
            $children,
            static fn(array $row): bool => (int)$row['uid_foreign'] === 0,
        )));

        foreach ($children as $child) {
            $this->assertSame('tt_content', $child['tablename']);
            // Written from "foreign_match_fields", which is what tells the four
            // relations sharing this child table apart.
            $this->assertSame('tx_theme_list_items', $child['fieldname']);
            // A child lives on the page its parent sits on, never on the
            // parent record - "pid" is a page id, not a relation.
            $this->assertGreaterThan(0, (int)$child['pid']);
        }
    }

    /**
     * Nesting in the definition becomes a `pid` in the database, at every
     * depth.
     *
     * Asserted as a parent map rather than as a row count: the demo tree is a
     * showcase and grows whenever the theme gains something to show, and a
     * count assertion would fail on every such change while proving nothing
     * about the nesting it is here for.
     */
    #[Test]
    public function seedBuildsThePageTree(): void
    {
        $this->seedDemo();

        $parents = array_column($this->queryTable('pages', ['uid', 'pid'], 'uid'), 'pid', 'uid');

        // The root page sits at the top level the seed was written to.
        $this->assertSame(0, (int)$parents[1]);
        // The first level hangs below the root page, not below each other.
        $this->assertSame(1, (int)$parents[2]);
        $this->assertSame(1, (int)$parents[5]);
        // And the second level below its own parent, which is what proves the
        // nesting is recursive rather than one level of special case.
        $this->assertSame(5, (int)$parents[6]);
        $this->assertSame(5, (int)$parents[8]);
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
        // negative pid convention this would come back reversed.
        $this->assertSame([2, 3, 4, 5, 9], array_map('intval', $sorted));
    }

    #[Test]
    public function contentIsWrittenBelowThePageThatCarriesIt(): void
    {
        $this->seedDemo();

        $rows = $this->queryTable('tt_content', ['pid', 'CType', 'header'], 'uid');

        $this->assertSame(1, (int)$rows[0]['pid']);
        $this->assertSame('header', $rows[0]['CType']);
        $this->assertSame('A frontend to look at', $rows[0]['header']);
        // No element was written to the top level the pages were seeded at,
        // which is where one would land if a "pid" went unresolved.
        $this->assertSame([], array_values(array_filter(
            $rows,
            static fn(array $row): bool => (int)$row['pid'] === 0,
        )));
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

        $this->assertNotEmpty($references, 'No file reference was written at all.');

        // Not one of them may point at record 0, which is where a reference
        // lands when the "NEW..." placeholder of its parent went unresolved -
        // the failure this second pass exists to avoid.
        $this->assertSame([], array_values(array_filter(
            $references,
            static fn(array $row): bool => (int)$row['uid_foreign'] === 0,
        )));

        // The reference on the root page names the table and the field it was
        // declared under, which is what the rendering looks the images up by.
        $onRootPage = array_values(array_filter(
            $references,
            static fn(array $row): bool => $row['tablenames'] === 'pages',
        ));
        $this->assertCount(1, $onRootPage);
        $this->assertSame('media', $onRootPage[0]['fieldname']);
        $this->assertSame(1, (int)$onRootPage[0]['uid_foreign']);

        $onContent = array_values(array_filter(
            $references,
            static fn(array $row): bool => $row['tablenames'] === 'tt_content' && $row['fieldname'] === 'image',
        ));
        $this->assertNotEmpty($onContent, 'No image was attached to a content element.');
    }

    #[Test]
    public function fileReferencesCarryTheFieldsTheDefinitionDeclaresOnThem(): void
    {
        $this->seedDemo();

        $references = $this->queryTable(
            'sys_file_reference',
            ['uid', 'uid_foreign', 'tablenames', 'alternative', 'title', 'description'],
            'uid',
        );

        // Selected by the record it belongs to rather than by position: the
        // demo tree grows, and an index would silently start asserting against
        // a different reference than the one this test is about.
        $onRootPage = array_values(array_filter(
            $references,
            static fn(array $row): bool => $row['tablenames'] === 'pages',
        ))[0] ?? null;
        $this->assertNotNull($onRootPage, 'The root page carries no file reference.');

        // It declares an alternative text and a title, and no description.
        $this->assertSame('A placeholder graphic', $onRootPage['alternative']);
        $this->assertSame('Placeholder', $onRootPage['title']);
        $this->assertSame('', (string)$onRootPage['description']);

        // The single image element declares a description, which is what the
        // theme renders as the caption.
        $element = $this->contentElementUid('A single image');
        $withCaption = array_values(array_filter(
            $references,
            static fn(array $row): bool => $row['tablenames'] === 'tt_content'
                && (int)$row['uid_foreign'] === $element,
        ));
        $this->assertCount(1, $withCaption, 'The captioned image reference was not written.');
        $this->assertSame('A placeholder graphic in landscape format', $withCaption[0]['alternative']);
        $this->assertSame(
            'The description of a file reference is rendered as the caption.',
            $withCaption[0]['description'],
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

        $this->setUpThemeSite(identifier: 'demo', websiteTitle: 'Theme demo');

        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/typography'),
        )->getBody();

        $this->assertStringContainsString('<h1 class="theme-content-element__heading">Typography</h1>', $body);
        $this->assertStringContainsString('three inline cases', $body);
    }
}
