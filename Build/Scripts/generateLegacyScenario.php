<?php

declare(strict_types=1);

/**
 * Writes the "/legacy/" tree of the development instances from the showcase.
 *
 * The showcase set "theme-demo" of the extension is delivered through the site
 * set of the theme. The development instances carry a second, identical tree
 * below "/legacy/", delivered through a root "sys_template" record and the
 * classic static include instead - the way an installation that predates site
 * sets is configured, and the only way the two delivery mechanisms can be
 * compared page for page. A mirror maintained by hand is a mirror that drifts,
 * so it is derived instead:
 *
 *   php Build/Scripts/generateLegacyScenario.php
 *   php Build/Scripts/generateLegacyScenario.php --check   # exit 1 if it would change
 *
 * Two files are written into
 * "packages-dev/dev-site/Configuration/DataFactory/theme-instance/":
 *
 *   ScenarioLegacy.yaml     the records, from "Scenario.yaml" of the showcase
 *   ReferencesLegacy.yaml   a descriptor fragment holding the file references,
 *                           from "config.yml" of the showcase
 *
 * It needs the composer install of the repository root for the YAML parser, so
 * run "Build/Scripts/runTests.sh -s composerUpdate" first if ".Build/" is
 * empty. It writes committed artifacts and is run by hand when the showcase
 * changes, which is why it is a script and not a suite of "runTests.sh".
 * "Tests/Unit/GeneratedLegacyScenarioTest.php" runs "--check".
 *
 * WHAT IT REWRITES
 *
 * Every uid moves by 1000 - pages, content elements and list items alike - so a
 * record of the mirror is found by arithmetic: page 3 is page 1003, content
 * element 601 is 1601. The pointers at those records this script knows move
 * with them, so a link of the mirror stays inside the mirror:
 *
 *   - "t3://page?uid=N" in any string value, and a "#N"/"#cN" content
 *     element anchor ending it,
 *   - the page lists of the menu elements ("tt_content.pages"),
 *   - "tt_content_N" in "tt_content.records" of the "Insert records" element,
 *   - the inline child lists ("tt_content.tx_theme_list_items").
 *
 * The site root is mirrored like every other page, then given a title of its
 * own and the "sys_template" record that makes it a legacy tree - the one
 * record without an original, at uid 1.
 *
 * Only the pointers listed here are known. The columns listed in
 * UNSUPPORTED_POINTER_COLUMNS stop the script when they carry a value, rather
 * than reaching the mirror unrewritten and pointing out of it. That list is
 * what could be named, not everything there is - a FlexForm value or a
 * "t3://record" link is not recognised - so "LegacyDeliveryTest" checks the
 * result from the other side: no page link and no content anchor of the
 * rendered mirror may lead out of it.
 *
 * Comments are lost - the parser discards them. The prose of a mirrored record
 * is in "Scenario.yaml" of the showcase, at its uid minus 1000.
 */
$autoload = __DIR__ . '/../../.Build/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "The composer install is missing. Run \"Build/Scripts/runTests.sh -s composerUpdate\" first.\n");
    exit(1);
}
require $autoload;

use Symfony\Component\Yaml\Yaml;

const SOURCE_PATH = __DIR__ . '/../../Configuration/DataFactory/theme-demo';
const TARGET_PATH = __DIR__ . '/../../packages-dev/dev-site/Configuration/DataFactory/theme-instance';

/**
 * The offset every mirrored uid moves by, in every table.
 */
const OFFSET = 1000;

/**
 * Content element columns holding a comma separated list of page uids.
 */
const PAGE_LIST_COLUMNS = ['pages'];

/**
 * Content element columns holding a comma separated list of list item uids.
 */
const LIST_ITEM_COLUMNS = ['tx_theme_list_items'];

/**
 * Columns that point at a page or a record and that this script does not know
 * how to rewrite. A value in one of them - other than empty or "0", which
 * point at nothing - stops the script instead of reaching the mirror
 * unrewritten, pointing out of the mirror, which is the one thing the mirror
 * must not do. The showcase uses none of them with a value today; it writes
 * "selected_categories: 0" for its two categorized menus.
 */
const UNSUPPORTED_POINTER_COLUMNS = [
    'shortcut',
    'mount_pid',
    'content_from_pid',
    'l10n_parent',
    'l18n_parent',
    'l10n_source',
    'categories',
    'selected_categories',
];

/**
 * What the root page of the mirror has on top of the mirrored showcase root:
 * a title of its own, and the "sys_template" record that makes it a legacy
 * tree. Every other column is mirrored from the showcase root like any page.
 *
 * "include_static_file" names the static include the theme registers in
 * "Configuration/TCA/Overrides/sys_template.php" - nothing else. Everything
 * "Configuration/TypoScript/Static/setup.typoscript" imports is the TypoScript
 * the site set reads, so the two trees are rendered by the same text. The
 * record keeps uid 1: it is the only "sys_template" record of the set.
 */
const LEGACY_ROOT_TITLE = 'Theme demo (sys_template delivery)';
const LEGACY_TEMPLATE = [
    'self' => [
        'id' => 1,
        'title' => 'Theme demo, static include',
        'root' => 1,
        'clear' => 3,
        'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
    ],
];

const SCENARIO_HEADER = <<<'YAML'
# The "/legacy/" tree of the seed set "theme-instance".
#
# GENERATED FILE. Written by "Build/Scripts/generateLegacyScenario.php" from
# "Configuration/DataFactory/theme-demo/Scenario.yaml" of the extension - edit
# that file and run the script, never this one.
#
# The showcase is delivered through the site set of the theme. This file holds
# a full mirror of it below a second site root, delivered through a root
# "sys_template" record and the classic static include instead. The site of
# this tree, "instance-core-*/config/sites/demo-legacy/", declares no
# dependency on the set, so the condition in
# "Configuration/TypoScript/Static/setup.typoscript" lets the static include
# through for it and for nothing else.
#
# Every mirrored record carries the uid of its original plus 1000, in every
# table, and so do the pointers at one the generator knows - a link and its
# content anchor, a menu page list, the "Insert records" element, an inline
# child list - so this tree links to itself and not out of it. The
# "sys_template" record has no original and is uid 1. The prose explaining a
# record is in the showcase, at its uid minus 1000.
#
# The two scenario files are composed into one before anything is written:
# "entitySettings" of the showcase apply here, and this file adds the one
# entity it needs on top of them.

YAML;

const REFERENCES_HEADER = <<<'YAML'
# The file references of the "/legacy/" tree of the seed set "theme-instance".
#
# GENERATED FILE. Written by "Build/Scripts/generateLegacyScenario.php" from
# "Configuration/DataFactory/theme-demo/config.yml" of the extension - edit
# that file and run the script, never this one.
#
# A descriptor fragment, pulled into "config.yml" through "imports": it
# carries the same references as the showcase, at the mirrored uids. The files
# themselves are declared by the showcase and are placed once.

YAML;

exit(main($argv));

/**
 * @param string[] $argv
 */
function main(array $argv): int
{
    $arguments = array_slice($argv, 1);
    if ($arguments !== [] && $arguments !== ['--check']) {
        fwrite(STDERR, "Usage: generateLegacyScenario.php [--check]\n");
        return 1;
    }
    $check = $arguments === ['--check'];

    $scenario = Yaml::parseFile(SOURCE_PATH . '/Scenario.yaml');
    $descriptor = Yaml::parseFile(SOURCE_PATH . '/config.yml');
    if (!is_array($scenario) || !is_array($scenario['entities']['page'] ?? null) || !is_array($descriptor)) {
        fwrite(STDERR, "The showcase set declares no page tree.\n");
        return 1;
    }
    // One site root, and everything below it. A second top level page would be
    // a second tree the mirror does not know where to put.
    if (count($scenario['entities']['page']) !== 1 || array_diff(array_keys($scenario['entities']), ['page']) !== []) {
        fwrite(STDERR, "The showcase has to declare exactly one top level page and nothing else at the top level.\n");
        return 1;
    }

    $files = [
        TARGET_PATH . '/ScenarioLegacy.yaml' => SCENARIO_HEADER . dumpYaml(buildScenario($scenario)),
        TARGET_PATH . '/ReferencesLegacy.yaml' => REFERENCES_HEADER . dumpYaml(buildReferences($descriptor)),
    ];

    $stale = [];
    foreach ($files as $target => $rendered) {
        if (isCurrent($target, $rendered)) {
            continue;
        }
        $stale[] = basename($target);
        if (!$check) {
            file_put_contents($target, $rendered);
            printf("Written %s\n", realpath($target) ?: $target);
        }
    }

    if ($check && $stale !== []) {
        fwrite(STDERR, sprintf("Stale, not what the showcase produces: %s\n", implode(', ', $stale)));
        return 1;
    }
    if ($stale === []) {
        echo "Up to date.\n";
    }

    return 0;
}

/**
 * Whether the committed file says what the showcase produces: the same data,
 * under the same header.
 *
 * Compared as data rather than byte for byte, because the bytes are the
 * dumper's and the dumper is whichever symfony/yaml the installed dependency
 * set brings - the v12 and the v13 set may bring different ones, and the
 * mirror has to be up to date for both.
 */
function isCurrent(string $target, string $rendered): bool
{
    if (!is_file($target)) {
        return false;
    }
    $current = (string)file_get_contents($target);
    $header = static fn(string $yaml): string => (string)preg_replace('/^((?:#[^\n]*\n)*).*$/s', '$1', $yaml);

    try {
        return $header($current) === $header($rendered) && Yaml::parse($current) === Yaml::parse($rendered);
    } catch (\Symfony\Component\Yaml\Exception\ParseException) {
        // A committed file that does not parse is as stale as it gets.
        return false;
    }
}

/**
 * @param array<string, mixed> $scenario
 * @return array<string, mixed>
 */
function buildScenario(array $scenario): array
{
    /** @var array<string, mixed> $sourceRoot */
    $sourceRoot = $scenario['entities']['page'][0];
    $root = mirrorItem($sourceRoot);
    $root['self']['title'] = LEGACY_ROOT_TITLE;
    $root['entities'] = ['template' => [LEGACY_TEMPLATE]] + ($root['entities'] ?? []);

    return [
        'entitySettings' => [
            'template' => ['tableName' => 'sys_template'],
        ],
        'entities' => [
            'page' => [$root],
        ],
    ];
}

/**
 * @param array<string, mixed> $descriptor
 * @return array<string, mixed>
 */
function buildReferences(array $descriptor): array
{
    $references = [];
    foreach ($descriptor['references'] ?? [] as $reference) {
        $reference['uid'] = (int)$reference['uid'] + OFFSET;
        $references[] = $reference;
    }

    return ['references' => $references];
}

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function mirrorItem(array $item): array
{
    if (isset($item['self'])) {
        $item['self'] = mirrorValues($item['self']);
    }
    foreach ($item['entities'] ?? [] as $entity => $items) {
        $item['entities'][$entity] = array_map('mirrorItem', $items);
    }
    if (isset($item['children'])) {
        $item['children'] = array_map('mirrorItem', $item['children']);
    }

    return $item;
}

/**
 * @param array<string, mixed> $values
 * @return array<string, mixed>
 */
function mirrorValues(array $values): array
{
    foreach ($values as $column => $value) {
        if (in_array($column, UNSUPPORTED_POINTER_COLUMNS, true) && !in_array(is_scalar($value) ? (string)$value : '*', ['', '0'], true)) {
            fwrite(STDERR, sprintf("The showcase declares \"%s\", which this script cannot mirror yet.\n", $column));
            exit(1);
        }
        if ($column === 'id') {
            $values[$column] = (int)$value + OFFSET;
        } elseif (in_array($column, PAGE_LIST_COLUMNS, true) || in_array($column, LIST_ITEM_COLUMNS, true)) {
            $values[$column] = shiftList((string)$value);
        } elseif ($column === 'records') {
            $values[$column] = preg_replace_callback(
                '/\b(tt_content|pages)_(\d+)\b/',
                static fn(array $match): string => $match[1] . '_' . ((int)$match[2] + OFFSET),
                (string)$value,
            );
        } elseif (is_string($value)) {
            // The page uid, anything up to the fragment, and a fragment that
            // is a content element anchor - "#601" or "#c601", ending there.
            // A named anchor that merely starts with digits ("#2col") is not
            // one and stays as it is.
            $values[$column] = preg_replace_callback(
                '/t3:\/\/page\?uid=(\d+)([^#"\s]*)(?:#(c?)(\d+)(?![\w-]))?/',
                static fn(array $match): string => 't3://page?uid=' . ((int)$match[1] + OFFSET) . $match[2]
                    . (isset($match[4]) ? '#' . $match[3] . ((int)$match[4] + OFFSET) : ''),
                $value,
            );
        }
    }

    return $values;
}

function shiftList(string $list): string
{
    if (trim($list) === '') {
        return $list;
    }

    // "0" is "nothing selected", not page 0, and stays what it is.
    return implode(',', array_map(
        static fn(string $uid): string => (int)trim($uid) === 0 ? trim($uid) : (string)((int)trim($uid) + OFFSET),
        explode(',', $list),
    ));
}

/**
 * Dumps the data, and refuses to write anything that does not read back as
 * exactly that data.
 *
 * Multi-line strings are written as double quoted scalars, not as literal
 * blocks. symfony/yaml - the parser data-factory reads the file with - reads a
 * literal block ("|") back without its final line break when the next line is
 * dedented, and the plain text of a card, rendered through nl2br(), then lost
 * its trailing "<br />" in the mirror. The quoted form cannot lose anything,
 * and the check below is what makes sure of it.
 *
 * @param array<string, mixed> $data
 */
function dumpYaml(array $data): string
{
    $yaml = Yaml::dump($data, 32, 2);
    if (Yaml::parse($yaml) !== $data) {
        fwrite(STDERR, "The generated YAML does not read back as the data it was generated from.\n");
        exit(1);
    }

    return $yaml;
}
