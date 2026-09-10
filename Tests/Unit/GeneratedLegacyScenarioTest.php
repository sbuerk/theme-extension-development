<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
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
}
