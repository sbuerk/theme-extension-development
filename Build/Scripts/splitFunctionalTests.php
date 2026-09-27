<?php

declare(strict_types=1);

/**
 * Splits the functional test suite into chunks that take about the same time.
 *
 *   php Build/Scripts/splitFunctionalTests.php [--auto] <list-tests.xml> <number-of-chunks> <output-directory> [<timings.json>]
 *
 * "Build/Scripts/runTests.sh -j" calls it, in the PHP container. It writes
 * "FunctionalTests-Job-<n>.xml" for n = 1 .. <number-of-chunks> into the output
 * directory: each is a copy of "Build/phpunit/FunctionalTests.xml" whose test
 * suite lists the files of that chunk, with the bootstrap and the schema as
 * absolute paths, so the copy works from any directory.
 *
 * Modelled on "Build/Scripts/splitFunctionalTests.php" of TYPO3 Core, with two
 * differences. The tests are not found by parsing files but read from the list
 * phpunit writes with "--list-tests-xml": that list is built with the same
 * "--exclude-group" options as the run ("not-core-NN", "not-<dbms>") and every
 * data set is already resolved, so a chunk holds the tests that actually run on
 * this core version and DBMS. And a test class weighs its recorded duration
 * rather than its number of tests, when a timings file is given.
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
 * The test count is a poor predictor. A handful of classes import a seed set
 * once and restore it for every later test, while tests below
 * "Tests/Functional/DevelopmentInstance/" render every page of the
 * development seed in both of its trees, so a test costs anywhere from a
 * fraction of one of a rendering test to many times one. The timings file,
 * "Build/phpunit/FunctionalTestTimes-<dbms>.json" written by
 * "recordFunctionalTestTimes.php", maps a file path relative to the repository
 * root to seconds. A class it does not know yet weighs its number of tests
 * times the average recorded duration of one test, so a stale file costs
 * balance and never a test.
 *
 * WHY "--auto" WRITES FEWER CHUNKS
 *
 * "runTests.sh -j auto" passes the most chunks the machine carries and
 * "--auto". The heaviest chunk of that many is the floor of the run. The split
 * then writes the fewest chunks of which every chunk but the heaviest weighs
 * at most 80 % of that floor, and the most if none does. Once one class
 * outweighs an even share, more chunks only make the other classes finish
 * earlier, while the run still waits for that class, and every chunk costs its
 * containers. The 20 % are headroom: the same chunk took up to 10 % more or
 * less from one run to the next, and a chunk predicted just below the floor
 * then became the longest.
 */
exit(main($argv));

/**
 * @param list<string> $argv
 */
function main(array $argv): int
{
    $auto = ($argv[1] ?? '') === '--auto';
    if ($auto) {
        array_splice($argv, 1, 1);
    }
    if (count($argv) < 4 || count($argv) > 5 || !is_file($argv[1]) || (int)$argv[2] < 1) {
        fwrite(STDERR, 'Usage: php ' . $argv[0] . ' [--auto] <list-tests.xml> <number-of-chunks> <output-directory> [<timings.json>]' . PHP_EOL);
        return 1;
    }
    $numberOfChunks = (int)$argv[2];
    $outputDirectory = $argv[3];
    $timingsFile = $argv[4] ?? '';
    $rootDirectory = dirname(__DIR__, 2);

    $testsPerFile = readTestsPerFile($argv[1]);
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
    if ($auto) {
        $most = $numberOfChunks;
        $floor = chunkWeights(distribute($weightPerFile, $most), $weightPerFile)[0];
        $numberOfChunks = 1;
        while ($numberOfChunks < $most && (chunkWeights(distribute($weightPerFile, $numberOfChunks), $weightPerFile)[1] ?? $floor) > $floor * 0.8) {
            $numberOfChunks++;
        }
        $weights = chunkWeights(distribute($weightPerFile, $numberOfChunks), $weightPerFile);
        echo sprintf(
            'auto: %d of at most %d chunks, the heaviest weighs %.0f %s, the next %.0f %s, the floor is %.0f %s',
            $numberOfChunks,
            $most,
            $weights[0],
            $unit,
            $weights[1] ?? 0.0,
            $unit,
            $floor,
            $unit,
        ) . PHP_EOL;
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
 * The phpunit 11 list: <testClass file="…"> holding one <testMethod> per test and data set.
 *
 * @return array<string, int> absolute file path => number of tests
 */
function readTestsPerFile(string $listFile): array
{
    $list = new DOMDocument();
    if (!@$list->load($listFile)) {
        return [];
    }
    $xpath = new DOMXPath($list);
    $xpath->registerNamespace('t', 'https://xml.phpunit.de/testSuite');
    $testsPerFile = [];
    foreach ($xpath->query('//t:testClass') ?: [] as $testClass) {
        if (!$testClass instanceof DOMElement) {
            continue;
        }
        $file = $testClass->getAttribute('file');
        $methods = $xpath->query('t:testMethod', $testClass);
        $testsPerFile[$file] = ($testsPerFile[$file] ?? 0) + ($methods === false ? 0 : $methods->length);
    }
    return $testsPerFile;
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
 * @param array<int, list<string>> $chunks
 * @param array<string, int|float> $weightPerFile
 * @return non-empty-list<float> the weight of every chunk, heaviest first
 */
function chunkWeights(array $chunks, array $weightPerFile): array
{
    $weights = [];
    foreach ($chunks as $files) {
        $weights[] = (float)array_sum(array_intersect_key($weightPerFile, array_flip($files)));
    }
    rsort($weights);
    return $weights === [] ? [0.0] : $weights;
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
