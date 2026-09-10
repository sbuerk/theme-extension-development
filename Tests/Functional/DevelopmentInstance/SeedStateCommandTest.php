<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TESTS\DevSite\Command\SeedStateCommand;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Registry;

/**
 * `dev-site:seed-state`, which `Build/Scripts/instance.php` asks whether an
 * instance was seeded, and which records that it was.
 *
 * The exit codes are the interface: 0 for seeded, `EXIT_NOT_SEEDED` for not
 * seeded - and that one must not be 1, the code of every command failure, or
 * a broken installation is taken for an empty one and seeded again.
 */
final class SeedStateCommandTest extends AbstractInstanceSeedTestCase
{
    #[Test]
    public function aFreshInstanceIsNotSeeded(): void
    {
        $tester = $this->execute([]);

        // Asserted by value: 1 would be Command::FAILURE, see the class comment.
        $this->assertSame(3, $tester->getStatusCode());
        $this->assertSame(SeedStateCommand::EXIT_NOT_SEEDED, $tester->getStatusCode());
    }

    #[Test]
    public function markingRecordsTheSetAndMakesTheInstanceSeeded(): void
    {
        $this->assertSame(Command::SUCCESS, $this->execute(['--mark' => 'theme-instance'])->getStatusCode());

        $state = $this->get(Registry::class)->get(SeedStateCommand::REGISTRY_NAMESPACE, SeedStateCommand::REGISTRY_KEY);
        $this->assertIsArray($state);
        $this->assertSame('theme-instance', $state['set']);
        $this->assertGreaterThan(0, $state['seededAt']);

        $tester = $this->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Seeded with "theme-instance"', $tester->getDisplay());
    }

    /**
     * @param array<string, string> $input
     */
    private function execute(array $input): CommandTester
    {
        $tester = new CommandTester($this->get(CommandRegistry::class)->get('dev-site:seed-state'));
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }
}
