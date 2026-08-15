<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Command;

use SBUERK\ThemeExtensionDevelopment\Seeding\Exception\SeedingException;
use SBUERK\ThemeExtensionDevelopment\Seeding\Seeder;
use SBUERK\ThemeExtensionDevelopment\Seeding\YamlSeedParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Writes a seed definition into the current instance.
 *
 * Intended for a development instance: it creates the page tree and content a
 * frontend needs to be worth looking at, reproducibly, from a file that lives
 * in the repository.
 *
 * @internal Part of the seeding implementation, not public API.
 */
#[AsCommand(
    name: 'theme:seed',
    description: 'Seed a page tree and its content from a YAML definition.',
)]
final class SeedCommand extends Command
{
    public function __construct(
        private readonly YamlSeedParser $parser,
        private readonly Seeder $seeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'definition',
                InputArgument::OPTIONAL,
                'The seed definition to write. An "EXT:" path is resolved.',
                'EXT:theme_extension_development/Configuration/Seeds/Demo.yaml',
            )
            ->addOption(
                'root-page',
                null,
                InputOption::VALUE_REQUIRED,
                'The page the definition is written below. 0 is the page tree root.',
                '0',
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Seed even though the page tree is not empty. A definition declaring uids will collide.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $definitionFile = (string)$input->getArgument('definition');

        if (!$input->getOption('force') && !$this->seeder->pageTreeIsEmpty()) {
            $io->error([
                'The page tree is not empty.',
                'A seed definition declares the uids it writes, so seeding into an existing tree collides '
                . 'rather than adding to it. Reset the instance database, or pass "--force" when you know '
                . 'the definition does not overlap.',
            ]);

            return Command::FAILURE;
        }

        // The CLI application creates the "_cli_" backend user, and this makes
        // sure it is authenticated: DataHandler needs an admin user, and
        // silently ignores suggested uids without one.
        Bootstrap::initializeBackendAuthentication();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof \TYPO3\CMS\Core\Authentication\BackendUserAuthentication) {
            $io->error('No backend user is available, so nothing can be written.');

            return Command::FAILURE;
        }

        try {
            $definition = $this->parser->parseFile($definitionFile);
            $uids = $this->seeder->seed($definition, $backendUser, (int)$input->getOption('root-page'));
        } catch (SeedingException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Seeded "%s" with %d records.', $definition->identifier, count($uids)));
        if ($output->isVerbose()) {
            $io->table(
                ['Identifier', 'uid'],
                array_map(static fn(string $key, int $uid): array => [$key, $uid], array_keys($uids), $uids),
            );
        }

        return Command::SUCCESS;
    }
}
