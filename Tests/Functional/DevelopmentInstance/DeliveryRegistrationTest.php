<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Every static include the `/legacy/` tree names exists and can be selected.
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
    public function everyStaticIncludeOfTheLegacyRootResolvesAndIsRegistered(): void
    {
        $this->importSeedSet(self::SEED_SET);

        $entries = $this->staticIncludesOfTheLegacyRoot();
        $this->assertNotSame([], $entries, 'The legacy root carries no static include at all.');

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
     * @return list<string>
     */
    private function staticIncludesOfTheLegacyRoot(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_template');
        $queryBuilder->getRestrictions()->removeAll();

        $value = (string)$queryBuilder
            ->select('include_static_file')
            ->from('sys_template')
            ->where($queryBuilder->expr()->eq('root', 1))
            ->executeQuery()
            ->fetchOne();

        return GeneralUtility::trimExplode(',', $value, true);
    }
}
