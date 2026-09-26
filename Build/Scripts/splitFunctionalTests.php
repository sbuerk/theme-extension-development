<?php

declare(strict_types=1);

/**
 * Splits the functional test suite into chunks that take about the same time.
 *
 *   php Build/Scripts/splitFunctionalTests.php [--exclude-group <group,...>] <list-tests.xml> <number-of-chunks> <output-directory> [<timings.json>]
 *
 * "Build/Scripts/runTests.sh -j" calls it, in the PHP container. It writes
 * "FunctionalTests-Job-<n>.xml" for n = 1 .. <number-of-chunks> into the output
 * directory: each is a copy of "Build/phpunit/FunctionalTests.xml" whose test
 * suite lists the files of that chunk, with the bootstrap and the schema as
 * absolute paths, so the copy works from any directory.
 *
 * Modelled on "Build/Scripts/splitFunctionalTests.php" of TYPO3 Core, with two
 * differences. The tests are not found by parsing files but read from the list
 * phpunit writes with "--list-tests-xml", in which every data set is already
 * resolved, less the tests of the groups the run excludes ("not-core-NN",
 * "not-<dbms>"), so a chunk holds the tests that actually run on this core
 * version and DBMS. And a test class weighs its recorded duration rather than
 * its number of tests, when a timings file is given.
 *
 * WHY THE GROUPS ARE EXCLUDED HERE
 *
 * This branch runs PHPUnit 10.5. It writes the list before it applies any
 * filter and ignores "--exclude-group" there, with a note in its output
 * (Application::run() and ListTestsAsXmlCommand of phpunit/phpunit 10.5), so
 * the list holds the tests of every core version and DBMS. Each listed test
 * carries its groups, those of its class included, and the ones in a group of
 * "--exclude-group" are dropped here, as the run drops them. The list does not
 * carry the file of a class either: it is derived from the PSR-4 mapping of the
 * root "composer.json", the one composer autoloads the test classes by. A file
 * that is not where the mapping puts it fails its chunk, as phpunit refuses a
 * configured test file that does not exist.
 *
 * @todo PHPUnit 11 applies "--exclude-group" to the list and writes the file of
 *       every class into it. Read that list and drop "--exclude-group" and the
 *       PSR-4 mapping together with the PHPUnit major.
 *
 * WHY A CLASS IS NEVER SPLIT
 *
 * Each functional test class gets its own TYPO3 instance, below
 * "typo3temp/var/tests/functional-<identifier>", and its own database, both set
 * up once for all of its tests. The identifier is derived from the class name
 * alone. Two chunks sharing a class would set up the same instance in the same
 * directory at the same time. The heaviest classes are placed first, each into
 * the chunk that is lightest so far; the heaviest class is therefore the floor
 * of a run, however many chunks it has.
 *
 * WHY RECORDED DURATIONS
 *
 * The test count is a poor predictor. "ShowcaseTreeTest" imports the whole
 * showcase seed for every one of its tests, and a handful of classes below
 * "Tests/Functional/DevelopmentInstance/" import the development seed, so a
 * test of theirs costs many times one of a rendering test. The timings file,
 * "Build/phpunit/FunctionalTestTimes-<dbms>.json" written by
 * "recordFunctionalTestTimes.php", maps a file path relative to the repository
 * root to seconds. A class it does not know yet weighs its number of tests
 * times the average recorded duration of one test, so a stale file costs
 * balance and never a test.
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
    if (count($argv) < 4 || count($argv) > 5 || !is_file($argv[1]) || (int)$argv[2] < 1) {
        fwrite(STDERR, 'Usage: php ' . $argv[0] . ' [--exclude-group <group,...>] <list-tests.xml> <number-of-chunks> <output-directory> [<timings.json>]' . PHP_EOL);
        return 1;
    }
    $numberOfChunks = (int)$argv[2];
    $outputDirectory = $argv[3];
    $timingsFile = $argv[4] ?? '';
    $rootDirectory = dirname(__DIR__, 2);

    try {
        $testsPerFile = readTestsPerFile($argv[1], $excludedGroups, psr4Directories($rootDirectory));
    } catch (RuntimeException $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        return 1;
    }
    // A list phpunit could not fill must not become chunks that run nothing and pass.
    if (array_sum($testsPerFile) === 0) {
        fwrite(STDERR, 'No functional tests found in ' . $argv[1] . PHP_EOL);
        return 1;
    }

    $weightPerFile = $testsPerFile;
    $unit = 'tests';
    if ($timingsFile !== '') {
        $timings = readTimings($timingsFile);
        if ($timings === null) {
            fwrite(STDERR, 'Cannot read the timings file ' . $timingsFile . PHP_EOL);
            return 1;
        }
        $weightPerFile = weighByTimings($testsPerFile, $timings, $rootDirectory);
        $unit = 's';
        echo sprintf(
            'Balanced by %s: %d of %d test classes recorded',
            $timingsFile,
            count(array_intersect_key(relativeKeys($testsPerFile, $rootDirectory), $timings)),
            count($testsPerFile),
        ) . PHP_EOL;
    }

    // More chunks than classes would leave chunks without a test, each of which would
    // still start its containers. "runTests.sh" runs as many chunks as are written here.
    if ($numberOfChunks > count($testsPerFile)) {
        echo sprintf(
            '%d chunks asked for, but only %d test classes: writing %d',
            $numberOfChunks,
            count($testsPerFile),
            count($testsPerFile),
        ) . PHP_EOL;
        $numberOfChunks = count($testsPerFile);
    }
    $chunks = distribute($weightPerFile, $numberOfChunks);

    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
        fwrite(STDERR, 'Cannot create ' . $outputDirectory . PHP_EOL);
        return 1;
    }
    foreach ($chunks as $index => $files) {
        writeConfiguration(
            $rootDirectory . '/Build/phpunit/FunctionalTests.xml',
            $outputDirectory . '/FunctionalTests-Job-' . $index . '.xml',
            $files,
        );
        echo sprintf(
            'Chunk %d/%d: %d test classes, %d tests, weight %.0f %s',
            $index,
            $numberOfChunks,
            count($files),
            array_sum(array_intersect_key($testsPerFile, array_flip($files))),
            array_sum(array_intersect_key($weightPerFile, array_flip($files))),
            $unit,
        ) . PHP_EOL;
    }
    return 0;
}

/**
 * The phpunit 10.5 list: <testCaseClass name="…"> holding one <testCaseMethod groups="…"> per
 * test and data set. A class none of whose tests is left counts no file.
 *
 * @param list<string> $excludedGroups
 * @param array<string, string> $psr4Directories namespace prefix => absolute directory
 * @return array<string, int> absolute file path => number of tests
 */
function readTestsPerFile(string $listFile, array $excludedGroups, array $psr4Directories): array
{
    $list = new DOMDocument();
    if (!@$list->load($listFile)) {
        return [];
    }
    $xpath = new DOMXPath($list);
    $testsPerFile = [];
    foreach ($xpath->query('//testCaseClass') ?: [] as $testClass) {
        if (!$testClass instanceof DOMElement) {
            continue;
        }
        $numberOfTests = 0;
        foreach ($xpath->query('testCaseMethod', $testClass) ?: [] as $testMethod) {
            if ($testMethod instanceof DOMElement
                && array_intersect(explode(',', $testMethod->getAttribute('groups')), $excludedGroups) === []
            ) {
                $numberOfTests++;
            }
        }
        if ($numberOfTests === 0) {
            continue;
        }
        $file = fileOfClass($testClass->getAttribute('name'), $psr4Directories);
        $testsPerFile[$file] = ($testsPerFile[$file] ?? 0) + $numberOfTests;
    }
    return $testsPerFile;
}

/**
 * The "psr-4" mappings of "autoload" and "autoload-dev" of the root "composer.json", longest
 * prefix first, so the most specific one wins as it does for composer.
 *
 * @return array<string, string> namespace prefix => absolute directory
 */
function psr4Directories(string $rootDirectory): array
{
    $composerFile = $rootDirectory . '/composer.json';
    try {
        $composer = json_decode(is_file($composerFile) ? (string)file_get_contents($composerFile) : '', true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('Cannot read ' . $composerFile, 1790518421);
    }
    $directories = [];
    foreach (['autoload', 'autoload-dev'] as $section) {
        $mapping = is_array($composer) && is_array($composer[$section] ?? null) ? ($composer[$section]['psr-4'] ?? []) : [];
        foreach (is_array($mapping) ? $mapping : [] as $prefix => $directory) {
            if (is_string($prefix) && is_string($directory)) {
                $directories[$prefix] = $rootDirectory . '/' . rtrim($directory, '/');
            }
        }
    }
    uksort($directories, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    return $directories;
}

/**
 * @param array<string, string> $psr4Directories namespace prefix => absolute directory
 */
function fileOfClass(string $class, array $psr4Directories): string
{
    foreach ($psr4Directories as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            return $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    }
    throw new RuntimeException('No PSR-4 mapping of the root "composer.json" covers the test class ' . $class, 1790518422);
}

/**
 * @return array<string, float>|null file relative to the repository root => seconds
 */
function readTimings(string $timingsFile): ?array
{
    if (!is_file($timingsFile)) {
        return null;
    }
    try {
        $decoded = json_decode((string)file_get_contents($timingsFile), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($decoded)) {
        return null;
    }
    $timings = [];
    foreach ($decoded as $file => $seconds) {
        if (is_string($file) && (is_float($seconds) || is_int($seconds))) {
            $timings[$file] = (float)$seconds;
        }
    }
    return $timings;
}

/**
 * @param array<string, int> $testsPerFile
 * @param array<string, float> $timings file relative to the repository root => seconds
 * @return array<string, float>
 */
function weighByTimings(array $testsPerFile, array $timings, string $rootDirectory): array
{
    $relativeFiles = relativeKeys($testsPerFile, $rootDirectory);
    $recordedSeconds = 0.0;
    $recordedTests = 0;
    foreach ($relativeFiles as $relativeFile => $file) {
        if (isset($timings[$relativeFile])) {
            $recordedSeconds += $timings[$relativeFile];
            $recordedTests += $testsPerFile[$file];
        }
    }
    $secondsPerTest = $recordedTests > 0 ? $recordedSeconds / $recordedTests : 1.0;
    $weightPerFile = [];
    foreach ($relativeFiles as $relativeFile => $file) {
        $weightPerFile[$file] = $timings[$relativeFile] ?? $testsPerFile[$file] * $secondsPerTest;
    }
    return $weightPerFile;
}

/**
 * @param array<string, int|float> $perFile
 * @return array<string, string> file relative to the repository root => absolute file
 */
function relativeKeys(array $perFile, string $rootDirectory): array
{
    $relative = [];
    foreach (array_keys($perFile) as $file) {
        $relative[str_starts_with($file, $rootDirectory . '/') ? substr($file, strlen($rootDirectory) + 1) : $file] = $file;
    }
    return $relative;
}

/**
 * Heaviest first into the lightest chunk. The file name breaks ties, so every
 * run computes the same split from the same input.
 *
 * @param array<string, int|float> $weightPerFile
 * @return array<int, list<string>> chunk number, starting at 1 => files
 */
function distribute(array $weightPerFile, int $numberOfChunks): array
{
    uksort($weightPerFile, static fn(string $a, string $b): int => [$weightPerFile[$b], $a] <=> [$weightPerFile[$a], $b]);
    $weights = array_fill(1, $numberOfChunks, 0.0);
    $chunks = array_fill(1, $numberOfChunks, []);
    foreach ($weightPerFile as $file => $weight) {
        $lightest = 1;
        foreach ($weights as $chunk => $chunkWeight) {
            if ($chunkWeight < $weights[$lightest]) {
                $lightest = $chunk;
            }
        }
        $weights[$lightest] += $weight;
        $chunks[$lightest][] = $file;
    }
    foreach ($chunks as &$files) {
        sort($files);
    }
    unset($files);
    return $chunks;
}

/**
 * @param list<string> $files
 */
function writeConfiguration(string $template, string $target, array $files): void
{
    $configuration = new DOMDocument();
    $configuration->preserveWhiteSpace = false;
    $configuration->formatOutput = true;
    $configuration->load($template);
    $phpunit = $configuration->documentElement;
    $testSuite = $configuration->getElementsByTagName('testsuite')->item(0);
    if (!$phpunit instanceof DOMElement || !$testSuite instanceof DOMElement) {
        throw new RuntimeException('No <phpunit> with a <testsuite> in ' . $template, 1790452212);
    }
    // Both are relative to the template; the copy lives elsewhere.
    $phpunit->setAttribute('bootstrap', dirname($template) . '/' . $phpunit->getAttribute('bootstrap'));
    $schemaNamespace = 'http://www.w3.org/2001/XMLSchema-instance';
    $schema = $phpunit->getAttributeNS($schemaNamespace, 'noNamespaceSchemaLocation');
    if ($schema !== '' && !str_starts_with($schema, '/')) {
        $phpunit->setAttributeNS($schemaNamespace, 'xsi:noNamespaceSchemaLocation', dirname($template) . '/' . $schema);
    }
    while ($testSuite->firstChild !== null) {
        $testSuite->removeChild($testSuite->firstChild);
    }
    foreach ($files as $file) {
        $testSuite->appendChild($configuration->createElement('file', $file));
    }
    $configuration->save($target);
}
