<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Every static include a seeded root TypoScript record names exists and can be
 * selected - the one of the `/legacy/` tree, and on TYPO3 v12 the one of `/`.
 *
 * `include_static_file` is a comma separated list read with `trimExplode`. An
 * entry naming a folder that does not exist contributes nothing and raises
 * nothing, so the seed can drift away from the extension without a single
 * error: rename `Configuration/TypoScript/Static/`, and the legacy tree renders
 * unthemed while the page answers 200.
 *
 * The second half is the one that is easy to leave out. A folder that is still
 * on disk but is no longer offered by `addStaticFile()` is a folder no
 * integrator could select any more - the seed would describe an installation
 * nobody could build by hand, and the site set would have taken the delivery
 * over without anybody saying so.
 *
 * `LegacyDeliveryTest` catches the outcome, a tree that renders differently.
 * This names the entry.
 */
final class DeliveryRegistrationTest extends AbstractInstanceSeedTestCase
{
    #[Test]
    public function everyStaticIncludeOfASeededRootTemplateResolvesAndIsRegistered(): void
    {
        $this->importSeedSet($this->instanceSeedSet());

        $entries = $this->staticIncludesOfTheRootTemplates();
        $this->assertNotSame([], $entries, 'No seeded root TypoScript record carries a static include.');

        $registered = array_map(
            static fn(array $item): string => (string)($item['value'] ?? ''),
            $GLOBALS['TCA']['sys_template']['columns']['include_static_file']['config']['items'] ?? [],
        );

        $problems = [];
        foreach ($entries as $entry) {
            $path = GeneralUtility::getFileAbsFileName($entry);
            if ($path === '' || (!is_file($path . '/setup.typoscript') && !is_file($path . '/constants.typoscript'))) {
                $problems[] = sprintf('"%s" does not resolve to a folder holding TypoScript', $entry);
            }
            if (!in_array($entry, $registered, true)) {
                $problems[] = sprintf('"%s" is not offered by addStaticFile()', $entry);
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * The static includes of every root TypoScript record the seed writes -
     * the one of the legacy tree, and on TYPO3 v12 the one of the "/" tree.
     *
     * @return list<string>
     */
    private function staticIncludesOfTheRootTemplates(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_template');
        $queryBuilder->getRestrictions()->removeAll();

        $values = $queryBuilder
            ->select('include_static_file')
            ->from('sys_template')
            ->where($queryBuilder->expr()->eq('root', 1))
            ->executeQuery()
            ->fetchFirstColumn();

        $entries = [];
        foreach ($values as $value) {
            $entries = array_merge($entries, GeneralUtility::trimExplode(',', (string)$value, true));
        }

        return array_values(array_unique($entries));
    }
}
