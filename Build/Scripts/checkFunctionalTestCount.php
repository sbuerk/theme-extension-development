<?php

declare(strict_types=1);

/**
 * Fails a chunked functional run that executed another number of tests than
 * phpunit listed for it.
 *
 *   php Build/Scripts/checkFunctionalTestCount.php [--exclude-group <group,...>] <list-tests.xml> <junit.xml> [<junit.xml> ...]
 *
 * "Build/Scripts/runTests.sh -j" splits the list phpunit writes with
 * "--list-tests-xml" into chunks and runs them in parallel; each chunk writes a
 * JUnit log. Every chunk can be green while tests went missing on the way - a
 * class the split left out, a chunk configuration that points at the wrong
 * file, a chunk that filtered more than it should - and a green run shows none
 * of that. This compares the number of tests the list holds with the number
 * the JUnit logs report, skipped ones included on both sides, and fails on any
 * difference.
 *
 * PHPUnit 10.5 lists the tests of every group, "--exclude-group" or not, so the
 * tests of the groups given with "--exclude-group" are not counted as listed -
 * the same the split drops, see "splitFunctionalTests.php".
 */
exit(main($argv));

/**
 * @param list<string> $argv
 */
function main(array $argv): int
{
    $excludedGroups = [];
    if (($argv[1] ?? '') === '--exclude-group') {
        $excludedGroups = array_values(array_filter(explode(',', $argv[2] ?? ''), static fn(string $group): bool => $group !== ''));
        array_splice($argv, 1, 2);
    }
    if (count($argv) < 3) {
        fwrite(STDERR, 'Usage: php ' . $argv[0] . ' [--exclude-group <group,...>] <list-tests.xml> <junit.xml> [<junit.xml> ...]' . PHP_EOL);
        return 1;
    }
    $list = new DOMDocument();
    if (!is_file($argv[1]) || !@$list->load($argv[1])) {
        fwrite(STDERR, 'Cannot read ' . $argv[1] . PHP_EOL);
        return 1;
    }
    // The phpunit 10.5 list: <testCaseClass> holding one <testCaseMethod groups="…"> per test.
    $listed = 0;
    foreach ((new DOMXPath($list))->query('//testCaseClass/testCaseMethod') ?: [] as $testMethod) {
        if ($testMethod instanceof DOMElement
            && array_intersect(explode(',', $testMethod->getAttribute('groups')), $excludedGroups) === []
        ) {
            $listed++;
        }
    }

    $executed = 0;
    foreach (array_slice($argv, 2) as $junitFile) {
        $junit = new DOMDocument();
        if (!is_file($junitFile) || !@$junit->load($junitFile)) {
            fwrite(STDERR, 'Cannot read ' . $junitFile . PHP_EOL);
            return 1;
        }
        // The outermost suite of a log counts every test of its run once.
        $suites = (new DOMXPath($junit))->query('/testsuites/testsuite');
        $suite = $suites === false ? null : $suites->item(0);
        $executed += $suite instanceof DOMElement ? (int)$suite->getAttribute('tests') : 0;
    }

    if ($executed !== $listed) {
        fwrite(STDERR, sprintf(
            'The chunks executed %d tests, but phpunit listed %d for this run. Tests were lost or run twice on the way through the split.',
            $executed,
            $listed,
        ) . PHP_EOL);
        return 1;
    }
    echo sprintf('All %d listed tests executed, in %d chunks', $listed, count($argv) - 2) . PHP_EOL;
    return 0;
}
