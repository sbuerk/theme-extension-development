<?php

declare(strict_types=1);

/**
 * Builds a development instance from nothing, and rebuilds it.
 *
 * Invoked through the composer scripts of "instance-core-13/" and
 * "instance-core-14/", so the working directory is the instance directory and
 * every path is resolved relative to it. There are no path arguments on
 * purpose: both instances declare the exact same commands.
 *
 *   composer system:setup    php ../theme/Build/Scripts/instance.php setup
 *   composer system:reseed   php ../theme/Build/Scripts/instance.php reseed
 *
 * "setup" is idempotent, and it is what "ddev start" runs after every
 * "composer install". It does what is missing and nothing else:
 *
 *   1. TYPO3 is not installed - no "config/system/settings.php", or a database
 *      without a "be_users" table - so it runs "typo3 setup".
 *   2. The instance is not seeded - "dev-site:seed-state" exits 3 - so it runs
 *      "extension:setup", imports the seed set and records that it did. Any
 *      other failure of that check stops the script: an instance whose state
 *      cannot be read is not taken for an empty one.
 *
 * A started instance that is both passes through in two quick checks. The
 * state lives in the database, not in a marker file next to it: removing the
 * database is all it takes to make the next start rebuild the instance.
 *
 * "reseed" removes the database, the files the seed placed and the images
 * processed from them, the caches, and "config/system/settings.php" - then
 * runs "setup". The instance comes out as a fresh clone would.
 *
 * WHY "settings.php" IS NOT COMMITTED
 *
 * "typo3 setup" writes it, and it writes it complete: the encryption key, the
 * install tool password hash, the system maintainers, the extension
 * configuration "extension:setup" adds later. A committed file would either
 * carry a shared encryption key, or be rewritten into a dirty working copy by
 * every setup. So it is generated per checkout and git-ignored, and everything
 * the repository wants an instance to be is set in the tracked
 * "config/system/additional.php", which TYPO3 applies after it.
 *
 * Requires nothing but PHP with pdo_sqlite. It runs where "vendor/bin/typo3"
 * runs - inside the DDEV web container, or on a host stack - and calls it as a
 * separate process for every step, because every step needs a TYPO3 that was
 * booted against the state the previous step left behind.
 */
const DATABASE_DIRECTORY = 'var/sqlite';
const DATABASE_SIDECARS = ['-wal', '-shm', '-journal'];
const SETTINGS_FILE = 'config/system/settings.php';
const ADDITIONAL_FILE = 'config/system/additional.php';
const TYPO3_BINARY = 'vendor/bin/typo3';
const SEED_SET = 'theme-instance';

/**
 * The exit code of "dev-site:seed-state" for an instance that is not seeded -
 * "SeedStateCommand::EXIT_NOT_SEEDED". Every other non-zero code is a failure
 * of the check itself.
 */
const EXIT_NOT_SEEDED = 3;

/**
 * What "reseed" removes besides the database and "settings.php": the folder
 * the seed places its files into, the images processed from them, and the
 * caches. Nothing else of "fileadmin/" is touched.
 */
const RESEED_REMOVES = [
    'public/fileadmin/theme-demo',
    'public/fileadmin/_processed_',
    'public/typo3temp/assets',
    'var/cache',
];

/**
 * The administrator "typo3 setup" creates. The account of the TYPO3
 * contribution guide, spelled exactly like that - see
 * "docs/development/instances.md".
 */
const ADMIN = [
    'TYPO3_SETUP_ADMIN_USERNAME' => 'john-doe',
    'TYPO3_SETUP_ADMIN_PASSWORD' => 'John-Doe-1701D.',
    'TYPO3_SETUP_ADMIN_EMAIL' => 'john.doe@example.com',
];

exit(main($argv));

/**
 * @param string[] $argv
 */
function main(array $argv): int
{
    $mode = $argv[1] ?? '';
    if (!in_array($mode, ['setup', 'reseed'], true)) {
        fwrite(STDERR, "Usage: instance.php <setup|reseed>\n");
        return 1;
    }

    // Every path below is relative to the working directory, and "reseed"
    // deletes files. Refuse to run anywhere that is not an instance.
    if (!is_file(ADDITIONAL_FILE) || !is_file('composer.json')) {
        fwrite(STDERR, sprintf(
            "\"%s\" is not a development instance directory.\n"
            . "Run this through \"composer system:%s\" in \"instance-core-13/\" or \"instance-core-14/\".\n",
            getcwd() ?: '.',
            $mode,
        ));
        return 1;
    }
    if (!is_file(TYPO3_BINARY)) {
        fwrite(STDERR, "\"vendor/bin/typo3\" is missing. Run \"composer install\" first.\n");
        return 1;
    }

    if ($mode === 'reseed' && !removeInstanceState()) {
        return 1;
    }

    return setup();
}

function setup(): int
{
    $database = databaseFile();
    $installed = false;

    if (!isInstalled($database)) {
        section('Installing TYPO3');
        if (!install($database)) {
            return 1;
        }
        $installed = true;
    }

    $state = typo3(['dev-site:seed-state']);
    if ($state === 0) {
        section('Already seeded - nothing to do');
        return 0;
    }
    if ($state !== EXIT_NOT_SEEDED) {
        fwrite(STDERR, sprintf(
            "\"typo3 dev-site:seed-state\" failed with exit code %d, so whether this instance is seeded is\n"
            . "unknown, and nothing was imported%s. \"composer system:reseed\" rebuilds it from nothing.\n",
            $state,
            $installed ? ' - TYPO3 was installed right before' : '',
        ));
        return 1;
    }

    section('Seeding "' . SEED_SET . '"');
    foreach ([
        ['extension:setup'],
        ['data-factory:import', SEED_SET, '--no-interaction'],
        ['dev-site:seed-state', '--mark=' . SEED_SET],
        ['cache:flush'],
    ] as $command) {
        $exitCode = typo3($command);
        if ($exitCode !== 0) {
            fwrite(STDERR, sprintf(
                "\"typo3 %s\" failed with exit code %d. The instance is not marked as seeded, so the next\n"
                . "\"composer system:setup\" tries again - after \"composer system:reseed\" if the import\n"
                . "wrote part of the set, because a second import collides with the first one's uids.\n",
                implode(' ', $command),
                $exitCode,
            ));
            return 1;
        }
    }

    return 0;
}

/**
 * Installed means a TYPO3 that can boot: a "settings.php" to boot with, and a
 * database that holds the tables "typo3 setup" creates.
 *
 * The database file alone says nothing. SQLite creates a missing file on the
 * first connection, so a single request to an instance that was never set up
 * leaves an empty database behind.
 */
function isInstalled(string $database): bool
{
    if (!is_file(SETTINGS_FILE) || !is_file($database) || filesize($database) === 0) {
        return false;
    }

    try {
        $connection = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $statement = $connection->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'be_users'");

        return $statement !== false && (int)$statement->fetchColumn() === 1;
    } catch (PDOException) {
        return false;
    }
}

/**
 * Runs "typo3 setup" and moves the database it creates to where the instance
 * looks for it.
 *
 * An existing database is removed first, a seeded one included: "installed"
 * was answered with no, so either "settings.php" is missing - and with it the
 * encryption key everything in that database was written with - or the
 * database is not one TYPO3 can boot against.
 *
 * "typo3 setup" names a SQLite database itself - "var/sqlite/cms-<hash>.sqlite"
 * (SetupDatabaseService of EXT:install) - and ignores any name it is given.
 * "config/system/additional.php" pins the path instead, so the generated file
 * is renamed to that path; its own path in "settings.php" is then advisory.
 */
function install(string $database): bool
{
    removeDatabase($database);

    $exitCode = typo3(
        ['setup', '--force', '--no-interaction'],
        environment: ADMIN + [
            // The connection type, "sqlite" - never the driver name
            // "pdo_sqlite", which is rejected with the list of valid keys.
            'TYPO3_DB_DRIVER' => 'sqlite',
            'TYPO3_PROJECT_NAME' => 'Theme Extension Development',
            'TYPO3_SERVER_TYPE' => 'other',
        ],
    );
    if ($exitCode !== 0) {
        fwrite(STDERR, sprintf("\"typo3 setup\" failed with exit code %d.\n", $exitCode));
        return false;
    }

    $created = createdDatabase();
    if ($created === null) {
        fwrite(STDERR, "\"typo3 setup\" succeeded, but \"settings.php\" names no database file that exists.\n");
        return false;
    }
    if (realpath($created) !== realpath($database)) {
        removeDatabase($database);
        if (!rename($created, $database)) {
            fwrite(STDERR, sprintf("Could not move \"%s\" to \"%s\".\n", $created, $database));
            return false;
        }
        foreach (DATABASE_SIDECARS as $suffix) {
            if (is_file($created . $suffix)) {
                rename($created . $suffix, $database . $suffix);
            }
        }
    }
    printf("Database: %s\n", $database);

    return true;
}

/**
 * The database file "config/system/additional.php" pins, read from the one
 * line of that file that sets it - the same file TYPO3 reads, so the two cannot
 * disagree about the name.
 */
function databaseFile(): string
{
    $matched = preg_match(
        '#\$sqliteDatabaseFile\s*=\s*\$sqliteDatabasePath\s*\.\s*[\'"]/([^\'"]+\.sqlite)[\'"]#',
        (string)file_get_contents(ADDITIONAL_FILE),
        $matches,
    );
    if ($matched !== 1) {
        fwrite(STDERR, sprintf("Cannot find the database file name in \"%s\".\n", ADDITIONAL_FILE));
        exit(1);
    }

    return DATABASE_DIRECTORY . '/' . $matches[1];
}

/**
 * The database "typo3 setup" wrote into "settings.php".
 */
function createdDatabase(): ?string
{
    /** @var array{DB?: array{Connections?: array{Default?: array{path?: string}}}} $settings */
    $settings = require(realpath(SETTINGS_FILE) ?: SETTINGS_FILE);
    $path = (string)($settings['DB']['Connections']['Default']['path'] ?? '');

    return $path !== '' && is_file($path) ? $path : null;
}

function removeInstanceState(): bool
{
    section('Removing the instance state');
    $database = databaseFile();
    removeDatabase($database);
    // A database "typo3 setup" created and nothing adopted, from an aborted run.
    foreach (glob(DATABASE_DIRECTORY . '/cms-*.sqlite') ?: [] as $orphan) {
        removeDatabase($orphan);
    }

    foreach ([SETTINGS_FILE, ...RESEED_REMOVES] as $path) {
        if (!removePath($path)) {
            fwrite(STDERR, sprintf("Could not remove \"%s\".\n", $path));
            return false;
        }
    }

    return true;
}

function removeDatabase(string $database): void
{
    foreach (['', ...DATABASE_SIDECARS] as $suffix) {
        if (is_file($database . $suffix)) {
            if (!unlink($database . $suffix)) {
                fwrite(STDERR, sprintf("Could not remove \"%s\".\n", $database . $suffix));
                exit(1);
            }
            printf("Removed %s\n", $database . $suffix);
        }
    }
}

function removePath(string $path): bool
{
    if (is_link($path) || is_file($path)) {
        printf("Removed %s\n", $path);
        return unlink($path);
    }
    if (!is_dir($path)) {
        return true;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($entries as $entry) {
        /** @var SplFileInfo $entry */
        $removed = $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        if (!$removed) {
            return false;
        }
    }
    printf("Removed %s\n", $path);

    return rmdir($path);
}

/**
 * Runs "vendor/bin/typo3" as a process of its own and returns its exit code.
 *
 * @param list<string> $arguments
 * @param array<string, string> $environment Added to the inherited environment.
 */
function typo3(array $arguments, array $environment = []): int
{
    $command = array_merge([PHP_BINARY, TYPO3_BINARY], $arguments);
    $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR];

    $process = proc_open($command, $descriptors, $pipes, null, $environment + getenv());
    if (!is_resource($process)) {
        fwrite(STDERR, sprintf("Could not start \"%s\".\n", implode(' ', $command)));
        return 1;
    }

    return proc_close($process);
}

function section(string $title): void
{
    printf("\n> %s\n", $title);
}
