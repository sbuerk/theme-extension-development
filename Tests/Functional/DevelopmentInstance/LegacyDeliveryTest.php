<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The two page trees of a development instance render the same pages.
 *
 * This is the reason the `/legacy/` tree is a mirror rather than a smoke test.
 * On TYPO3 v13 the `/` tree is delivered through the site set of the theme, the
 * `/legacy/` tree through the `include_static_file` of a root `sys_template`
 * record - and the second mechanism fails silently. On TYPO3 v12, which has no
 * site sets, both trees are delivered through a root `sys_template` record, and
 * the comparison holds the mirror to the showcase. The column is a comma separated list
 * read with `trimExplode`, an entry that resolves to nothing contributes
 * nothing, and the page still answers 200 with a piece of its configuration
 * missing. No assertion on one tree can see that; only the other tree can.
 *
 * `DeliveryRegistrationTest` checks the entries. This checks the outcome: every
 * mirrored page has to come out of the two trees as the same markup once the
 * things that legitimately differ are normalised away - and each rule of
 * `normalise()` says why it is one of those.
 */
final class LegacyDeliveryTest extends AbstractInstanceSeedTestCase
{
    private const BASE = 'https://theme.example.com';

    private const LEGACY_SEGMENT = '/legacy';

    /**
     * The uid offset of the mirror, as `Build/Scripts/generateLegacyScenario.php`
     * writes it.
     */
    private const OFFSET = 1000;

    /**
     * What the two sites are *supposed* to disagree about, as
     * `search => replacement`, longest first: the `websiteTitle` of the two
     * site configurations and the titles of the two root pages.
     *
     * @var array<string, string>
     */
    private array $expectedDifferences = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sites = [];

    /**
     * The uids of the mirrored content elements, keyed by themselves.
     *
     * @var array<int, true>
     */
    private array $mirroredContentUids = [];

    /**
     * The uids of the content elements of the "/" tree that have a mirror,
     * keyed by themselves.
     *
     * @var array<int, true>
     */
    private array $originalContentUids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSeedSet($this->instanceSeedSet());
        $this->sites = $this->adoptCommittedSiteConfigurations();
        $this->expectedDifferences = $this->expectedDifferences($this->sites);
        $this->mirroredContentUids = $this->mirroredContentUids();
        foreach (array_keys($this->mirroredContentUids) as $uid) {
            $this->originalContentUids[$uid - self::OFFSET] = true;
        }
    }

    #[Test]
    public function bothTreesDeliverTheSameMarkupForEveryMirroredPage(): void
    {
        $differences = [];
        $pages = $this->mirroredPages();

        foreach ($pages as $uid => $path) {
            $original = $this->render($path);
            $mirror = $this->render(self::LEGACY_SEGMENT . $path, true);

            if ($original['body'] !== $mirror['body']) {
                $differences[] = sprintf(
                    'page %d ("%s"): the markup differs%s',
                    $uid,
                    $path,
                    self::firstDifferingLine($original['body'], $mirror['body']),
                );
            }
        }

        // Every page the showcase declares has to be compared. Fewer means the
        // mirror or the lookup lost some, and an empty comparison would pass.
        $this->assertGreaterThan(0, count($pages));
        $this->assertCount(self::showcasePageCount(), $pages, 'The mirror does not cover every page of the showcase.');
        $this->assertSame(
            [],
            $differences,
            sprintf(
                "%d of %d pages differ between the two delivery mechanisms:\n  %s\n\n"
                . 'The two trees are supposed to render the same pages, on TYPO3 v13 through two delivery'
                . ' mechanisms. A difference here is usually an entry of "include_static_file" that resolves to'
                . ' nothing, which raises nothing at runtime.',
                count($differences),
                count($pages),
                implode("\n  ", $differences),
            ),
        );
    }

    /**
     * Every page answers 200 in both trees, and is rendered by the theme.
     *
     * The equality check above passes for a page that fails identically on
     * both sides, and it should: it compares delivery mechanisms. This is the
     * assertion that says the pages render at all, and it is separate so that
     * the two failures read differently.
     */
    #[Test]
    public function everyMirroredPageIsRenderedByTheThemeInBothTrees(): void
    {
        $failures = [];

        foreach ($this->mirroredPages() as $uid => $path) {
            foreach ([$path, self::LEGACY_SEGMENT . $path] as $requested) {
                $response = $this->render($requested);
                if ($response['status'] !== 200) {
                    $failures[] = sprintf('page %d, "%s": status %d', $uid, $requested, $response['status']);
                } elseif (!str_contains($response['body'], 'data-theme-page-layout=')) {
                    $failures[] = sprintf('page %d, "%s": not rendered by the theme', $uid, $requested);
                }
            }
        }

        $this->assertSame([], $failures, "Pages of the instance do not render:\n  " . implode("\n  ", $failures));
    }

    /**
     * No page link and no content anchor of the `/legacy/` tree leads out of
     * it.
     *
     * The comparison above cannot see this: it maps `/legacy/` to `/` before
     * comparing, so a link that leaves the mirror and a link that stays in it
     * look the same. A link leaving it is a pointer the generator did not
     * rewrite, or markup that hard-codes the root of the host rather than the
     * root of the site - both have happened. Asserted on the raw markup.
     */
    #[Test]
    public function noPageLinkOfTheLegacyTreeLeavesIt(): void
    {
        $leaks = [];

        foreach ($this->mirroredPages() as $path) {
            $requested = self::LEGACY_SEGMENT . $path;
            $body = (string)$this->executeFrontendSubRequest(new InternalRequest(self::BASE . $requested))->getBody();
            preg_match_all('#<a\s[^>]*\bhref="([^"]*)"#', $body, $matches);

            foreach ($matches[1] as $href) {
                $href = html_entity_decode($href);
                $target = str_starts_with($href, self::BASE) ? substr($href, strlen(self::BASE)) : $href;
                // A content element anchor of the "/" tree: the mirror names
                // its own elements at their uid plus the offset. Checked first,
                // because a link to an anchor on the current page is rendered
                // as the bare fragment "#c<uid>".
                if (preg_match('/#c(\d+)$/', $target, $anchor) === 1 && isset($this->originalContentUids[(int)$anchor[1]])) {
                    $leaks[] = sprintf('"%s" links to "%s", an element of the "/" tree', $requested, $href);
                }
                if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
                    // Relative, a fragment, a mail or telephone link, or another host.
                    continue;
                }
                if (preg_match('#^/(fileadmin|_assets|typo3temp|typo3conf|typo3)/#', $target) === 1) {
                    // A file, not a page.
                    continue;
                }
                if ($target !== self::LEGACY_SEGMENT && !str_starts_with($target, self::LEGACY_SEGMENT . '/')) {
                    $leaks[] = sprintf('"%s" links to "%s"', $requested, $href);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($leaks)), "Links of the legacy tree leave it:\n  " . implode("\n  ", array_unique($leaks)));
    }

    /**
     * On TYPO3 v13 there is exactly one TypoScript template record, and it is
     * the root of the legacy tree. TYPO3 v12 delivers both trees through one -
     * `Core12/DevelopmentInstance/InstanceDeliveryTest`.
     *
     * A record anywhere in the `/` tree would feed TypoScript into the tree
     * that is supposed to be delivered by the site set alone - a record's
     * TypoScript is appended after the set's, see
     * `Configuration/TypoScript/Static/setup.typoscript` - and the two trees
     * would then compare two mixtures rather than two mechanisms.
     */
    #[Group('not-core-12')]
    #[Test]
    public function onlyTheLegacyRootCarriesATypoScriptRecord(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_template');
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('pid', 'root', 'include_static_file')
            ->from('sys_template')
            ->executeQuery()
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame(1 + self::OFFSET, (int)$rows[0]['pid']);
        $this->assertSame(1, (int)$rows[0]['root']);
    }

    /**
     * The legacy site depends on no set, and the `/` site on the theme's -
     * among others: it depends on "typo3/felogin" too, for its login page.
     * TYPO3 v12 has no site sets - `Core12/DevelopmentInstance/InstanceDeliveryTest`.
     *
     * The static include is guarded by a condition on `site('sets')`, so a
     * legacy site that names the theme set by mistake is rendered by the set
     * and never reaches the include - and renders exactly the markup the
     * comparison above expects. Only the site configuration can tell, so it is
     * asserted on directly.
     */
    #[Group('not-core-12')]
    #[Test]
    public function onlyTheSiteSetTreeDependsOnTheThemeSet(): void
    {
        $this->assertSame(
            ['sbuerk/theme-extension-development', 'typo3/felogin'],
            $this->sites['demo']['dependencies'] ?? null,
            'The "demo" site does not deliver the theme through its site set, or depends on more than it should.',
        );
        $this->assertArrayHasKey('demo-legacy', $this->sites, 'The committed "demo-legacy" site is missing.');
        $this->assertSame(
            [],
            $this->sites['demo-legacy']['dependencies'] ?? [],
            'The "demo-legacy" site declares a set, which suppresses the static include it exists to deliver.',
        );
    }

    /**
     * The pages of the `/` tree that have a counterpart in `/legacy/`, keyed by
     * uid, with their path.
     *
     * Read from the database rather than listed: the mirror is generated, and a
     * page added to the showcase is part of this test as soon as the generator
     * has run.
     *
     * @return array<int, string>
     */
    private function mirroredPages(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder
            ->select('uid', 'slug')
            ->from('pages')
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $slugs = [];
        foreach ($rows as $row) {
            $slugs[(int)$row['uid']] = (string)$row['slug'];
        }

        $pages = [];
        foreach ($slugs as $uid => $slug) {
            if ($uid < self::OFFSET && ($slugs[$uid + self::OFFSET] ?? null) === $slug) {
                $pages[$uid] = $slug;
            }
        }

        return $pages;
    }

    /**
     * A content element is mirrored when it sits on a page of the mirror - page
     * uids move by the offset as well - and its original exists. The page is
     * part of the test: the uid alone is ambiguous once the showcase uses two
     * decades ten pages apart. The mirror of the content of page 70 is 8001 and
     * up, the content of page 90 is 9001 and up, and 9001 less the offset then
     * exists although 9001 is an original - and a specimen of the styleguide
     * that writes "c9001" into its markup was translated on the mirror side.
     *
     * @return array<int, true>
     */
    private function mirroredContentUids(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        /** @var list<array{uid: int|string, pid: int|string}> $rows */
        $rows = $queryBuilder->select('uid', 'pid')->from('tt_content')->executeQuery()->fetchAllAssociative();
        $pages = [];
        foreach ($rows as $row) {
            $pages[(int)$row['uid']] = (int)$row['pid'];
        }

        $mirrored = [];
        foreach ($pages as $uid => $pid) {
            if ($uid > self::OFFSET && $pid > self::OFFSET && isset($pages[$uid - self::OFFSET])) {
                $mirrored[$uid] = true;
            }
        }

        return $mirrored;
    }

    /**
     * The number of pages the showcase declares, read from its scenario.
     */
    private static function showcasePageCount(): int
    {
        $scenario = Yaml::parseFile(dirname(__DIR__, 3) . '/Configuration/DataFactory/theme-demo/Scenario.yaml');
        $count = 0;
        $walk = static function (array $items) use (&$walk, &$count): void {
            foreach ($items as $item) {
                $count++;
                $walk($item['children'] ?? []);
            }
        };
        $walk($scenario['entities']['page'] ?? []);

        return $count;
    }

    /**
     * @return array{status: int, body: string}
     */
    private function render(string $path, bool $isMirror = false): array
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest(self::BASE . $path));

        return [
            'status' => $response->getStatusCode(),
            'body' => $this->normalise((string)$response->getBody(), $isMirror),
        ];
    }

    /**
     * What the two trees are allowed to differ in.
     *
     * Every rule here is something that differs because the mirror *is* a
     * mirror, and nothing else is touched - a normalisation that removed more
     * would remove the difference the test exists to find.
     */
    private function normalise(string $markup, bool $isMirror): string
    {
        // The path segment that makes the mirror a second site. Every link of
        // the legacy tree carries it.
        $markup = str_replace(self::LEGACY_SEGMENT . '/', '/', $markup);
        $markup = str_replace('"' . self::LEGACY_SEGMENT . '"', '"/"', $markup);
        // The site titles and the root page titles. The two sites have two
        // titles by definition, and the root page title is in the breadcrumb
        // and the main navigation of every page.
        $markup = str_replace(array_keys($this->expectedDifferences), array_values($this->expectedDifferences), $markup);
        // The nonce of a Content Security Policy, drawn per request.
        $markup = (string)preg_replace('#nonce="[^"]*"#', 'nonce="*"', $markup);
        // The anchor of a content element, "c<uid>" in its "id" and in every
        // link to it: a mirrored element carries the uid of its original plus
        // the offset. Translated back on the mirror side only, and only this
        // pattern - an image width or a column count that differed would be a
        // real difference, and stays one.
        //
        // The same anchor also starts the identifiers an element derives from
        // it - "c801-tab-1" and its panel "c801-tab-1-panel" of the tabs, in
        // "id" and "aria-controls", and "c801-accordion", the "name" grouping
        // an accordion's items - so a "c<uid>" followed by "-" is translated
        // back as well, in those two attributes too.
        if ($isMirror) {
            $markup = (string)preg_replace_callback(
                '/(id="|#|aria-controls="|name=")c(\d+)(["-])/',
                fn(array $match): string => isset($this->mirroredContentUids[(int)$match[2]])
                    ? $match[1] . 'c' . ((int)$match[2] - self::OFFSET) . $match[3]
                    : $match[0],
                $markup,
            );
        }

        return trim($markup);
    }

    /**
     * @param array<string, array<string, mixed>> $sites
     * @return array<string, string>
     */
    private function expectedDifferences(array $sites): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $titles = [];
        foreach ([1, 1 + self::OFFSET] as $uid) {
            $titles[] = (string)$queryBuilder
                ->select('title')
                ->from('pages')
                ->where($queryBuilder->expr()->eq('uid', $uid))
                ->executeQuery()
                ->fetchOne();
        }

        $siteTitles = array_map(
            static fn(array $site): string => (string)($site['websiteTitle'] ?? ''),
            array_values($sites),
        );

        $differences = [];
        foreach ([$titles, $siteTitles] as $pair) {
            [$first, $second] = $pair + ['', ''];
            if ($first !== '' && $second !== '' && $first !== $second) {
                $differences[$first] = '[title]';
                $differences[$second] = '[title]';
            }
        }
        uksort($differences, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $differences;
    }

    private static function firstDifferingLine(string $first, string $second): string
    {
        $firstLines = explode("\n", $first);
        $secondLines = explode("\n", $second);
        foreach ($firstLines as $number => $line) {
            if ($line !== ($secondLines[$number] ?? null)) {
                return sprintf(
                    ", first at line %d:\n      /        %s\n      /legacy/ %s",
                    $number + 1,
                    trim($line),
                    trim($secondLines[$number] ?? '(no such line)'),
                );
            }
        }

        return '';
    }
}
