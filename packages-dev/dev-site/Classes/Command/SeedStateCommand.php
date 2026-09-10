<?php

declare(strict_types=1);

namespace TESTS\DevSite\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Registry;

/**
 * Answers whether a development instance has been seeded, and records that it
 * has.
 *
 *   vendor/bin/typo3 dev-site:seed-state                         # exit 0 seeded, 3 not
 *   vendor/bin/typo3 dev-site:seed-state --mark=theme-instance   # record it
 *
 * `Build/Scripts/instance.php` runs the first form after it made sure TYPO3 is
 * installed, and the second one after `data-factory:import` succeeded. The
 * answer is a `sys_registry` entry rather than something derived from the page
 * tree, because "is page 1 there" does not tell a completed import from one
 * that failed halfway: data-factory writes files before records and file
 * references after them, and an import that stopped in between left a page
 * tree behind. The entry is written last, so it exists exactly when every step
 * before it succeeded - and it goes with the database, so a rebuilt database
 * is an unseeded one without anything having to be cleared.
 *
 * Not a service anything else uses: the registry is the state, the command is
 * the only reader and writer of it.
 */
#[AsCommand(
    name: 'dev-site:seed-state',
    description: 'Tell whether this development instance was seeded, or record that it was.',
)]
final class SeedStateCommand extends Command
{
    public const REGISTRY_NAMESPACE = 'tests_dev_site';

    public const REGISTRY_KEY = 'seed';

    /**
     * The instance has not been seeded yet.
     *
     * Not 1: that is `Command::FAILURE`, the code of a command that failed.
     * A caller has to be able to tell "not seeded" from "could not tell", or
     * a broken installation is taken for an empty one and seeded again. An
     * uncaught exception exits with its own code, clamped to 1-255 - TYPO3's
     * timestamp codes come out as 255 - and a boot failure with 255, so a
     * collision with 3 takes an exception carrying exactly that code.
     */
    public const EXIT_NOT_SEEDED = 3;

    public function __construct(
        private readonly Registry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'mark',
            null,
            InputOption::VALUE_REQUIRED,
            'Record that this instance was seeded with the seed set of this identifier.',
        );
        $this->setHelp(
            'Without "--mark" the command exits 0 when the instance was seeded and ' . self::EXIT_NOT_SEEDED
            . ' when it was not. The state is the sys_registry entry "' . self::REGISTRY_NAMESPACE . '/'
            . self::REGISTRY_KEY . '", written by "--mark" after a successful import.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mark = $input->getOption('mark');
        if (is_string($mark) && $mark !== '') {
            $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, [
                'set' => $mark,
                'seededAt' => time(),
                'typo3' => (new Typo3Version())->getVersion(),
            ]);
            $output->writeln(sprintf('Recorded: this instance is seeded with "%s".', $mark));

            return Command::SUCCESS;
        }

        $state = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY);
        if (!is_array($state) || !is_string($state['set'] ?? null)) {
            $output->writeln('This instance has not been seeded.');

            return self::EXIT_NOT_SEEDED;
        }

        $output->writeln(sprintf(
            'Seeded with "%s" on %s, TYPO3 %s.',
            $state['set'],
            date('Y-m-d H:i:s', (int)($state['seededAt'] ?? 0)),
            (string)($state['typo3'] ?? '?'),
        ));

        return Command::SUCCESS;
    }
}
