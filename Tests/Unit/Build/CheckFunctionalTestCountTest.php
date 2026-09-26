<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Build;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * "Build/Scripts/checkFunctionalTestCount.php", the guard of "runTests.sh -j"
 * against a chunked run that is green because tests went missing.
 *
 * The script is run rather than included: it ends in `exit(main($argv))`.
 */
final class CheckFunctionalTestCountTest extends UnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = Environment::getPublicPath() . '/typo3temp/var/tests/check-functional-test-count-' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0777, true);
        $this->testFilesToDelete[] = $this->directory;
    }

    #[Test]
    public function passesWhenTheChunksExecutedEveryListedTest(): void
    {
        $this->list(['A' => 3, 'B' => 2]);
        $this->junit('junit-1.xml', 3);
        $this->junit('junit-2.xml', 2);

        [$status, $output] = $this->check('junit-1.xml', 'junit-2.xml');

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('All 5 listed tests executed, in 2 chunks', $output);
    }

    #[Test]
    public function failsWhenTheChunksExecutedFewerTestsThanListed(): void
    {
        $this->list(['A' => 3, 'B' => 2]);
        $this->junit('junit-1.xml', 3);
        $this->junit('junit-2.xml', 0);

        [$status, $output] = $this->check('junit-1.xml', 'junit-2.xml');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('The chunks executed 3 tests, but phpunit listed 5', $output);
    }

    #[Test]
    public function failsWhenTheChunksExecutedMoreTestsThanListed(): void
    {
        $this->list(['A' => 2]);
        $this->junit('junit-1.xml', 2);
        $this->junit('junit-2.xml', 2);

        [$status, $output] = $this->check('junit-1.xml', 'junit-2.xml');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('The chunks executed 4 tests, but phpunit listed 2', $output);
    }

    #[Test]
    public function failsWhenAChunkLeftNoLog(): void
    {
        $this->list(['A' => 1]);

        [$status, $output] = $this->check('missing.xml');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Cannot read', $output);
    }

    /**
     * @param array<string, int> $testsPerClass
     */
    private function list(array $testsPerClass): void
    {
        $xml = '<?xml version="1.0"?>' . "\n" . '<testSuite xmlns="https://xml.phpunit.de/testSuite"><tests>';
        foreach ($testsPerClass as $class => $numberOfTests) {
            $xml .= sprintf('<testClass name="Fixture\\%s" file="/checkout/Tests/Functional/%sTest.php">', $class, $class);
            for ($test = 1; $test <= $numberOfTests; $test++) {
                $xml .= sprintf('<testMethod id="Fixture\\%s::test%d" name="test%d"/>', $class, $test, $test);
            }
            $xml .= '</testClass>';
        }
        file_put_contents($this->directory . '/tests.xml', $xml . '</tests></testSuite>');
    }

    /**
     * The shape phpunit writes: an outer suite counting every test of the run,
     * holding one suite per test class.
     */
    private function junit(string $name, int $numberOfTests): void
    {
        file_put_contents(
            $this->directory . '/' . $name,
            sprintf(
                '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<testsuites><testsuite name="Functional tests" tests="%d" time="1">'
                . '<testsuite name="Fixture" file="/checkout/Tests/Functional/ATest.php" tests="%d" time="1"/>'
                . '</testsuite></testsuites>',
                $numberOfTests,
                $numberOfTests,
            ),
        );
    }

    /**
     * @return array{int, string}
     */
    private function check(string ...$logs): array
    {
        $arguments = [PHP_BINARY, dirname(__DIR__, 3) . '/Build/Scripts/checkFunctionalTestCount.php', $this->directory . '/tests.xml'];
        foreach ($logs as $log) {
            $arguments[] = $this->directory . '/' . $log;
        }
        $output = [];
        $status = 0;
        exec(implode(' ', array_map(escapeshellarg(...), $arguments)) . ' 2>&1', $output, $status);
        return [$status, implode("\n", $output)];
    }
}
