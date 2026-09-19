<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The committed `/legacy/` tree is what the showcase produces today.
 *
 * The tree is a mirror, and a mirror maintained by hand drifts. It is generated
 * instead - and a generated file that is committed has the same problem one
 * level up: somebody edits the showcase, does not re-run the script, and the
 * two trees quietly stop being the same tree.
 *
 * `LegacyDeliveryTest` sees part of that: a page whose copy changed in one tree
 * and not in the other renders differently, and a page added to the showcase
 * leaves the mirror one page short. It does not see a changed file
 * reference field that no page renders, nor the exact place a difference comes
 * from. This names the stale file.
 *
 * The script is run rather than included: it ends in `exit(main($argv))`,
 * which is right for a script and unusable from a test.
 */
final class GeneratedLegacyScenarioTest extends UnitTestCase
{
    /**
     * The descriptor of the set the TYPO3 v12 development instance imports.
     * It imports "theme-instance", the set of the v13 instance, and adds the
     * root "sys_template" record of the "/" tree, so walking it walks every
     * scenario file of both sets.
     */
    private const INSTANCE_SET = 'packages-dev/dev-site/Configuration/DataFactory/theme-instance-core12/config.yml';

    /**
     * The "EXT:" prefixes a descriptor of the two sets uses, with the
     * directory below the repository root each resolves to.
     */
    private const EXTENSION_PREFIXES = [
        'EXT:theme_extension_development/' => '',
        'EXT:tests_dev_site/' => 'packages-dev/dev-site/',
    ];

    /**
     * The scenario files a set descriptor composes, in the order they are
     * composed: those of its imports first, then its own.
     *
     * A path is resolved like data-factory resolves it: "EXT:" of this
     * extension or of the development site package against its directory in
     * the repository, anything else against the directory of the descriptor
     * that names it. An import is a descriptor
     * as well - a fragment without "scenarios" adds none.
     *
     * @return list<string>
     */
    private static function scenarioFiles(string $descriptor): array
    {
        $configuration = Yaml::parseFile($descriptor);
        if (!is_array($configuration)) {
            return [];
        }
        $resolve = static function (string $path) use ($descriptor): string {
            foreach (self::EXTENSION_PREFIXES as $prefix => $directory) {
                if (str_starts_with($path, $prefix)) {
                    return dirname(__DIR__, 2) . '/' . $directory . substr($path, strlen($prefix));
                }
            }

            return dirname($descriptor) . '/' . $path;
        };

        $files = [];
        foreach ($configuration['imports'] ?? [] as $import) {
            $resource = is_array($import) ? (string)($import['resource'] ?? '') : '';
            if ($resource !== '') {
                array_push($files, ...self::scenarioFiles($resolve($resource)));
            }
        }
        foreach ($configuration['scenarios'] ?? [] as $scenario) {
            $files[] = $resolve((string)$scenario);
        }

        return array_values(array_unique($files));
    }

    #[Test]
    public function theCommittedMirrorIsUpToDate(): void
    {
        $script = dirname(__DIR__, 2) . '/Build/Scripts/generateLegacyScenario.php';
        $this->assertFileExists($script);

        $output = [];
        $status = 0;
        exec(
            sprintf('%s %s --check 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script)),
            $output,
            $status,
        );

        $this->assertSame(
            0,
            $status,
            sprintf(
                "The committed \"/legacy/\" tree is not what the showcase produces:\n  %s\n\n"
                . 'Run "php Build/Scripts/generateLegacyScenario.php" and commit the result. The mirror is'
                . ' generated, so an edit to it is lost on the next run.',
                implode("\n  ", $output),
            ),
        );
    }

    /**
     * The instance set composes the showcase, its mirror and the accounts
     * into one scenario, and no table of it may declare a uid twice.
     *
     * The mirror moves every uid by 1000, and a content element's uid is its
     * page times 100 plus its position: the mirror of the content of page p
     * takes the uids of the content of page p + 10. A page added ten uids
     * from another page with content - or in the range of the account pages
     * - therefore produces a set that cannot be imported as declared, and
     * nothing about the showcase alone shows it. See the uid rule at the top
     * of "Scenario.yaml".
     *
     * The scenario files are read from the set descriptor, not listed here:
     * the "scenarios" of "theme-instance-core12/config.yml" and of every
     * descriptor it imports - "theme-instance/config.yml" and the showcase's
     * "theme-demo/config.yml" among them - so a scenario file added to any of
     * the sets is part of the walk, the root template of the v12 instance
     * included.
     */
    #[Test]
    public function theInstanceSetDeclaresNoUidTwice(): void
    {
        $root = dirname(__DIR__, 2);
        $files = self::scenarioFiles($root . '/' . self::INSTANCE_SET);
        $this->assertContains(
            $root . '/Configuration/DataFactory/theme-demo/Scenario.yaml',
            $files,
            'The showcase scenario is not reached through the descriptors - the imports are read wrong.',
        );

        $tables = [];
        $scenarios = [];
        foreach ($files as $file) {
            $this->assertFileExists($file, 'A set descriptor names a scenario file that does not exist.');
            $scenario = Yaml::parseFile($file);
            $this->assertIsArray($scenario, $file);
            // The settings of all the files apply to all of them, as the
            // scenario composer merges them.
            foreach ($scenario['entitySettings'] ?? [] as $entity => $settings) {
                if (is_array($settings) && isset($settings['tableName'])) {
                    $tables[(string)$entity] = (string)$settings['tableName'];
                }
            }
            $scenarios[] = $scenario;
        }

        $seen = [];
        $duplicates = [];
        $walk = static function (string $entity, array $items) use (&$walk, &$seen, &$duplicates, $tables): void {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (isset($item['self']['id'])) {
                    $key = ($tables[$entity] ?? $entity) . ' ' . (int)$item['self']['id'];
                    if (isset($seen[$key])) {
                        $duplicates[] = $key;
                    }
                    $seen[$key] = true;
                }
                foreach ($item['entities'] ?? [] as $nested => $nestedItems) {
                    if (is_array($nestedItems)) {
                        $walk((string)$nested, $nestedItems);
                    }
                }
                if (is_array($item['children'] ?? null)) {
                    $walk($entity, $item['children']);
                }
            }
        };
        foreach ($scenarios as $scenario) {
            foreach ($scenario['entities'] ?? [] as $entity => $items) {
                if (is_array($items)) {
                    $walk((string)$entity, $items);
                }
            }
        }

        // Pages and content of both trees and the accounts: far more than
        // a hundred records, so an empty walk cannot pass.
        $this->assertGreaterThan(100, count($seen), 'The walk found almost no record - the structure is read wrong.');
        $this->assertSame([], $duplicates, 'These uids are declared twice in the instance set: ' . implode(', ', $duplicates));
    }
}
