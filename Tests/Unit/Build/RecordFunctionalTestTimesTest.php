<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Build;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * "Build/Scripts/recordFunctionalTestTimes.php", which writes the durations
 * "splitFunctionalTests.php" balances the chunks by.
 *
 * The keys have to be the same whatever checkout the logs come from - a CI
 * runner records "/home/runner/work/…" - or the committed file would never
 * match a local run. The script is run rather than included: it ends in
 * `exit(main($argv))`.
 */
final class RecordFunctionalTestTimesTest extends UnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = Environment::getPublicPath() . '/typo3temp/var/tests/record-functional-test-times-' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0777, true);
        $this->testFilesToDelete[] = $this->directory;
    }

    #[Test]
    public function durationsAreKeyedRelativeToTheRepositoryRootAndAddedUpAcrossLogs(): void
    {
        $this->junit('chunk-1.xml', [
            '/home/runner/work/theme-extension-development/theme-extension-development/Tests/Functional/ATest.php' => 12.3456,
            '/var/www/checkout/Tests/Functional/Core12/SeedTest.php' => 80.0,
        ]);
        $this->junit('chunk-2.xml', [
            '/elsewhere/Tests/Functional/ATest.php' => 1.0,
        ]);

        [$status, $output] = $this->record('chunk-1.xml', 'chunk-2.xml');

        $this->assertSame(0, $status, $output);
        $this->assertSame(
            '{' . "\n"
            . '    "Tests/Functional/ATest.php": 13.35,' . "\n"
            . '    "Tests/Functional/Core12/SeedTest.php": 80.0' . "\n"
            . '}' . "\n",
            file_get_contents($this->directory . '/timings.json'),
        );
    }

    #[Test]
    public function onlyTheSuitesOfTestClassesAreCounted(): void
    {
        // phpunit nests a suite per data provider method in the suite of its class,
        // without a "file" attribute, wraps all of them in the suite of the run, and
        // gives every test case the "file" of its class as well.
        file_put_contents(
            $this->directory . '/nested.xml',
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<testsuites><testsuite name="Functional tests" tests="2" time="5.000000">'
            . '<testsuite name="Fixture\\ATest" file="/checkout/Tests/Functional/ATest.php" tests="2" time="5.000000">'
            . '<testsuite name="Fixture\\ATest::rendersEach" tests="2" time="5.000000">'
            . '<testcase name="rendersEach with data set &quot;one&quot;" file="/checkout/Tests/Functional/ATest.php" time="2.500000"/>'
            . '<testcase name="rendersEach with data set &quot;two&quot;" file="/checkout/Tests/Functional/ATest.php" time="2.500000"/>'
            . '</testsuite></testsuite></testsuite></testsuites>',
        );

        [$status, $output] = $this->record('nested.xml');

        $this->assertSame(0, $status, $output);
        $this->assertSame(
            '{' . "\n" . '    "Tests/Functional/ATest.php": 5.0' . "\n" . '}' . "\n",
            file_get_contents($this->directory . '/timings.json'),
        );
    }

    #[Test]
    public function aLogWithoutAnyTestClassFailsInsteadOfWritingAnEmptyFile(): void
    {
        $this->junit('empty.xml', []);

        [$status, $output] = $this->record('empty.xml');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('No test class durations found', $output);
        $this->assertFileDoesNotExist($this->directory . '/timings.json');
    }

    #[Test]
    public function aMissingLogFails(): void
    {
        [$status, $output] = $this->record('missing.xml');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Cannot read', $output);
    }

    /**
     * The shape phpunit writes: a suite for the run, holding one per test class.
     *
     * @param array<string, float> $secondsPerFile
     */
    private function junit(string $name, array $secondsPerFile): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<testsuites><testsuite name="Functional tests" tests="1" time="1">';
        foreach ($secondsPerFile as $file => $seconds) {
            $xml .= sprintf('<testsuite name="Fixture" file="%s" tests="1" time="%F"><testcase name="test" time="%F"/></testsuite>', $file, $seconds, $seconds);
        }
        file_put_contents($this->directory . '/' . $name, $xml . '</testsuite></testsuites>');
    }

    /**
     * @return array{int, string}
     */
    private function record(string ...$logs): array
    {
        $arguments = [PHP_BINARY, dirname(__DIR__, 3) . '/Build/Scripts/recordFunctionalTestTimes.php', $this->directory . '/timings.json'];
        foreach ($logs as $log) {
            $arguments[] = $this->directory . '/' . $log;
        }
        $output = [];
        $status = 0;
        exec(implode(' ', array_map(escapeshellarg(...), $arguments)) . ' 2>&1', $output, $status);
        return [$status, implode("\n", $output)];
    }
}
