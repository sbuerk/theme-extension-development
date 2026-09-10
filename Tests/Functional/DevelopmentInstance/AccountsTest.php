<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The accounts the seed set "theme-instance" brings, and what they may do.
 *
 * The editor account is only worth having if its group lets it work on the
 * showcase. "CType" carries "authMode = explicitAllow", so a content type the
 * group does not name is a content type the editor cannot pick - and nothing
 * reports that until somebody logs in as the editor and misses it. The list is
 * therefore held to the TypoScript, the way `ShowcaseTreeTest` holds the demo
 * tree to it: a content type added to the theme without being granted fails
 * here.
 */
final class AccountsTest extends AbstractInstanceSeedTestCase
{
    /**
     * Types the TypoScript renders that the editor deliberately does not get.
     *
     * "html" is raw markup, which an editor should not get by default. "list"
     * is the Extbase plugin container, not a type anybody picks.
     */
    private const NOT_GRANTED = ['html', 'list'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSeedSet($this->instanceSeedSet());
    }

    #[Test]
    public function theEditorGroupGrantsEveryContentTypeTheThemeRendersButRawHtml(): void
    {
        $granted = [];
        foreach (explode(',', (string)$this->row('be_groups', 10)['explicit_allowdeny']) as $entry) {
            if (str_starts_with($entry, 'tt_content:CType:')) {
                $granted[] = substr($entry, strlen('tt_content:CType:'));
            }
        }

        preg_match_all(
            '/^tt_content\.([a-z_0-9]+)\s*=</m',
            (string)file_get_contents(dirname(__DIR__, 3) . '/Configuration/TypoScript/ContentElements.typoscript'),
            $matched,
        );
        $rendered = array_values(array_unique($matched[1]));
        $this->assertNotSame([], $rendered, 'No content type was found at all - the path is wrong.');

        $missing = array_values(array_diff($rendered, $granted, self::NOT_GRANTED));
        sort($missing);
        $this->assertSame([], $missing, 'The editor group does not grant: ' . implode(', ', $missing));
        $this->assertNotContains('html', $granted, 'The editor group grants raw HTML.');
        // Not a type of the theme, and the one element the seeded login page uses.
        $this->assertContains('felogin_login', $granted);
    }

    /**
     * A field marked "exclude" is invisible to a non-admin whose groups do not
     * name it - silently, the form simply has one field less. So every such
     * field in the form of a content type the group grants has to be granted
     * as well, or the editor may create an element and not fill it in: the
     * page selection of every menu element was one of them.
     *
     * Content elements only. The page form carries fields no editor group
     * gets by default - "is_siteroot", "php_tree_stop", the cache settings -
     * so the page fields of the group are a curated list, see `Accounts.yaml`.
     *
     * Derived from the TCA of the running core, form by form, rather than
     * listed here: a field added to a form fails this test instead of quietly
     * missing for editors.
     */
    #[Test]
    public function theEditorGroupGrantsEveryExcludedFieldOfTheFormsItGrants(): void
    {
        $group = $this->row('be_groups', 10);
        $granted = GeneralUtility::trimExplode(',', (string)$group['non_exclude_fields'], true);

        $required = [];
        foreach (explode(',', (string)$group['explicit_allowdeny']) as $entry) {
            if (str_starts_with($entry, 'tt_content:CType:')) {
                $required = array_merge($required, $this->excludedFieldsOfForm('tt_content', substr($entry, strlen('tt_content:CType:'))));
            }
        }

        $missing = array_values(array_diff(array_unique($required), $granted));
        sort($missing);
        $this->assertSame([], $missing, 'The editor group does not grant these excluded fields: ' . implode(',', $missing));
    }

    /**
     * The "exclude" fields of the form of one type of a table, as
     * "<table>:<field>".
     *
     * @return list<string>
     */
    private function excludedFieldsOfForm(string $table, string $type): array
    {
        $tca = $GLOBALS['TCA'][$table];
        $showitem = (string)($tca['types'][$type]['showitem'] ?? '');
        $this->assertNotSame('', $showitem, sprintf('%s has no form for type "%s".', $table, $type));

        $fields = [];
        foreach (GeneralUtility::trimExplode(',', $showitem, true) as $item) {
            $parts = GeneralUtility::trimExplode(';', $item);
            if ($parts[0] === '--palette--') {
                $palette = (string)($tca['palettes'][$parts[2] ?? '']['showitem'] ?? '');
                foreach (GeneralUtility::trimExplode(',', $palette, true) as $paletteItem) {
                    $fields[] = GeneralUtility::trimExplode(';', $paletteItem)[0];
                }
            } elseif (!str_starts_with($parts[0], '--')) {
                $fields[] = $parts[0];
            }
        }

        $excluded = [];
        foreach ($fields as $field) {
            if (($tca['columns'][$field]['exclude'] ?? false) === true) {
                $excluded[] = $table . ':' . $field;
            }
        }

        return $excluded;
    }

    #[Test]
    public function theEditorIsAnEnabledNonAdminMemberOfTheEditorGroupWithBothTreesMounted(): void
    {
        $user = $this->row('be_users', 10);
        $this->assertSame('erika-editor', $user['username']);
        $this->assertSame(0, (int)$user['admin']);
        // EXT:core defaults "disable" to 1 for a new backend user.
        $this->assertSame(0, (int)$user['disable'], 'The editor account is disabled.');
        $this->assertSame('10', (string)$user['usergroup']);
        // Page tree and file mounts come from the group.
        $this->assertSame(3, (int)$user['options']);

        $group = $this->row('be_groups', 10);
        $this->assertSame('1,1001', (string)$group['db_mountpoints']);
        $this->assertSame('10', (string)$group['file_mountpoints']);
    }

    /**
     * Every page of the composed seed belongs to the editor group, with full
     * rights - the showcase, its mirror and the account pages alike.
     */
    #[Test]
    public function everySeededPageBelongsToTheEditorGroup(): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        /** @var list<array<string, mixed>> $rows */
        $rows = $queryBuilder->select('uid', 'perms_groupid', 'perms_group')->from('pages')->executeQuery()->fetchAllAssociative();

        $this->assertNotSame([], $rows);
        $foreign = array_filter(
            $rows,
            static fn(array $row): bool => (int)$row['perms_groupid'] !== 10 || (int)$row['perms_group'] !== 31,
        );
        $this->assertSame([], array_column($foreign, 'uid'), 'Pages outside the editor group.');
    }

    /**
     * The passwords are declared in plain text and have to arrive hashed:
     * DataHandler hashes them on save. A plain text password in the database
     * would still let nobody log in - and would be a plain text password in a
     * database.
     */
    #[Test]
    public function passwordsAreStoredHashedAndMatchTheDocumentedOnes(): void
    {
        $factory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        foreach ([
            ['be_users', 10, 'BE', 'Erika-Editor-1701D.'],
            ['fe_users', 1, 'FE', 'Frontend-User-1701D.'],
            ['fe_users', 2, 'FE', 'Frontend-User-1701D.'],
        ] as [$table, $uid, $mode, $password]) {
            $hash = (string)$this->row($table, $uid)['password'];
            $this->assertNotSame($password, $hash, sprintf('%s:%d stores its password in plain text.', $table, $uid));
            $this->assertTrue(
                $factory->get($hash, $mode)->checkPassword($password, $hash),
                sprintf('%s:%d does not accept its documented password.', $table, $uid),
            );
        }
    }

    #[Test]
    public function onlyTheMemberBelongsToTheGroupTheMembersPageIsRestrictedTo(): void
    {
        $this->assertSame('1', (string)$this->row('pages', 22)['fe_group']);
        $this->assertSame('1', (string)$this->row('fe_users', 1)['usergroup']);
        $this->assertSame('', (string)$this->row('fe_users', 2)['usergroup']);
        $this->assertSame(20, (int)$this->row('fe_users', 1)['pid']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $table, int $uid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->executeQuery()
            ->fetchAssociative();
        $this->assertIsArray($row, sprintf('%s:%d was not seeded.', $table, $uid));

        return $row;
    }
}
