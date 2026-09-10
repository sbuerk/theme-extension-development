<?php

// ---------------------------------------------------------------------------
// Everything the repository wants this instance to be.
//
// "settings.php" next to this file is NOT committed: "typo3 setup" writes it
// per checkout, and it carries what must not be shared - the encryption key and
// the install tool password hash - along with the extension configuration
// "extension:setup" adds. TYPO3 applies this file after it, so every value set
// here wins, and it is the only place instance configuration is changed. See
// "Build/Scripts/instance.php" and "docs/development/instances.md".
// ---------------------------------------------------------------------------

// The database. The path is recomputed from __DIR__ on every request instead
// of being taken from "settings.php", so the instance resolves its database
// the same way inside a DDEV container and on a host stack, no matter where
// the repository is checked out - and "typo3 setup", which names a SQLite file
// itself, is overruled. Nothing is copied or created here: a missing database
// is built by "composer system:setup", which "ddev start" runs.
$sqliteDatabasePath = __DIR__ . '/../../var/sqlite';
$sqliteDatabaseFile = $sqliteDatabasePath . '/core-14.sqlite';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'] = 'pdo_sqlite';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['path'] = $sqliteDatabaseFile;

// A development instance: every error visible, nothing cached silently.
$GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = true;
$GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = true;
$GLOBALS['TYPO3_CONF_VARS']['FE']['disableNoCacheParameter'] = true;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = 1;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem'] = true;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['security.system.enforceAllowedFileExtensions'] = true;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = 'Theme Extension Development, TYPO3 v14';

// Warnings, recoverable errors and PHP's own deprecations become exceptions -
// but only once TYPO3 is installed. Before that, "typo3 setup" boots against the
// DefaultConfiguration of the core, which has no "SYS/encryptionKey" at all,
// and the first cache write warns about the missing key: turned into an
// exception here, it aborts the setup that would have created the key.
if (is_file(__DIR__ . '/settings.php')) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = 12290;
}

// Image processing and mail as DDEV provides them. A host stack with other
// binaries overrides these in "additional/", see below.
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'ImageMagick';
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled'] = true;
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] = '/usr/bin/';
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'sendmail';
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_sendmail_command'] = '/usr/sbin/sendmail -t -i';

// The instance is reached under several host names (DDEV, host stack), so no
// host name is pinned here.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';

// ---------------------------------------------------------------------------
// Include local-only overrides from the git-ignored "additional/" folder.
//
// That is the place for anything belonging to one machine rather than to the
// repository - different binary paths or a different mail transport when the
// instance is served by a host stack instead of DDEV.
// ---------------------------------------------------------------------------
$additionalIncludePath = __DIR__ . '/additional';
if (is_dir($additionalIncludePath)) {
    foreach (glob($additionalIncludePath . '/*.php') ?: [] as $additionalIncludeFile) {
        include $additionalIncludeFile;
    }
}
