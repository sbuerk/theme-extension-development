<?php

declare(strict_types=1);

/**
 * Copies the SQLite database of a development instance to or from its committed
 * template.
 *
 * Invoked through the "sqlite:backup" and "sqlite:apply" composer scripts of an
 * instance, so the working directory is the instance directory and both paths
 * are relative to it:
 *
 *   php ../theme/Build/Scripts/sqliteSnapshot.php backup  var/sqlite/x.sqlite ../sqlite-databases/x.sqlite
 *   php ../theme/Build/Scripts/sqliteSnapshot.php restore ../sqlite-databases/x.sqlite var/sqlite/x.sqlite
 *
 * Why this is not a plain "cp".
 *
 * SQLite may run in write ahead logging mode, and then the database file on
 * disk is not the database: the most recent transactions live in a "-wal"
 * sidecar until a checkpoint folds them back in. Copying the main file alone
 * produces a template that is silently missing the newest writes - the kind of
 * defect that surfaces much later as content that was "definitely saved" and is
 * not there.
 *
 * So a backup checkpoints first, and both directions remove the sidecars of the
 * target, which belong to the database being replaced and never to its
 * replacement. The checkpoint is harmless when the database is in any other
 * journal mode; the pragma then simply reports that there was nothing to do.
 *
 * The copy is verified by opening it and counting its tables, because a
 * truncated or half written template is worth less than no template at all.
 *
 * Requires nothing but PHP with pdo_sqlite: it is called from an instance whose
 * dependencies may not be installed yet.
 */
const SIDECAR_SUFFIXES = ['-wal', '-shm'];

/**
 * @param string[] $argv
 */
function main(array $argv): int
{
    $mode = $argv[1] ?? '';
    $source = $argv[2] ?? '';
    $target = $argv[3] ?? '';

    if (!in_array($mode, ['backup', 'restore'], true) || $source === '' || $target === '') {
        fwrite(STDERR, "Usage: sqliteSnapshot.php <backup|restore> <source> <target>\n");
        return 1;
    }

    if (!extension_loaded('pdo_sqlite')) {
        fwrite(STDERR, "The pdo_sqlite extension is not available.\n");
        return 1;
    }

    if (!is_file($source)) {
        fwrite(STDERR, sprintf(
            "There is no database at \"%s\".\n%s\n",
            $source,
            $mode === 'restore'
                ? 'Nothing has been committed as a template yet, so there is nothing to restore from.'
                : 'Start the instance and set it up before backing it up.',
        ));
        return 1;
    }

    // Only a backup reads a live database. A restore reads a template that
    // nothing is writing to, and checkpointing it would only touch a file that
    // is about to be copied verbatim anyway.
    if ($mode === 'backup' && !checkpoint($source)) {
        return 1;
    }

    $targetDirectory = dirname($target);
    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        fwrite(STDERR, sprintf("Could not create \"%s\".\n", $targetDirectory));
        return 1;
    }

    foreach (SIDECAR_SUFFIXES as $suffix) {
        $sidecar = $target . $suffix;
        if (is_file($sidecar) && !unlink($sidecar)) {
            fwrite(STDERR, sprintf("Could not remove the stale sidecar \"%s\".\n", $sidecar));
            return 1;
        }
    }

    if (!copy($source, $target)) {
        fwrite(STDERR, sprintf("Could not copy \"%s\" to \"%s\".\n", $source, $target));
        return 1;
    }

    $tables = countTables($target);
    if ($tables === null) {
        fwrite(STDERR, sprintf("The copy at \"%s\" could not be opened as a database.\n", $target));
        return 1;
    }

    printf(
        "%s %s -> %s (%d tables, %s)\n",
        $mode === 'backup' ? 'Backed up' : 'Restored',
        $source,
        $target,
        $tables,
        formatSize((int)filesize($target)),
    );

    return 0;
}

function checkpoint(string $database): bool
{
    try {
        $connection = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // TRUNCATE folds the write ahead log back into the database and empties
        // it, so the file that is copied afterwards is complete on its own.
        $connection->query('PRAGMA wal_checkpoint(TRUNCATE)');
        unset($connection);
    } catch (PDOException $exception) {
        fwrite(STDERR, sprintf("Could not checkpoint \"%s\": %s\n", $database, $exception->getMessage()));
        return false;
    }

    return true;
}

function countTables(string $database): ?int
{
    try {
        $connection = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $count = $connection->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")?->fetchColumn();
        unset($connection);
    } catch (PDOException) {
        return null;
    }

    return is_numeric($count) ? (int)$count : null;
}

function formatSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return sprintf('%.1f MB', $bytes / 1048576);
    }

    return sprintf('%.1f kB', $bytes / 1024);
}

exit(main($argv));
