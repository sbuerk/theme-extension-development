<?php

// ---------------------------------------------------------------------------
// Resolve the database of this instance.
//
// The path is recomputed from __DIR__ instead of being taken from
// "settings.php", so the instance resolves its database the same way inside a
// DDEV container and on a host stack, no matter where the repository is
// checked out.
// ---------------------------------------------------------------------------
$sqliteDatabaseTemplateFile = __DIR__ . '/../../../sqlite-databases/core-14.sqlite';
$sqliteDatabasePath = __DIR__ . '/../../var/sqlite';
$sqliteDatabaseFile = $sqliteDatabasePath . '/core-14.sqlite';
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['path'] = $sqliteDatabaseFile;

// Seed the instance from the committed template on first start-up. The template
// is optional: until one has been committed, the instance starts empty and is
// set up with "vendor/bin/typo3 setup".
if (!file_exists($sqliteDatabaseFile) && file_exists($sqliteDatabaseTemplateFile)) {
    @mkdir($sqliteDatabasePath, 0775, true);
    @copy($sqliteDatabaseTemplateFile, $sqliteDatabaseFile);
}

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

// The instance is reached under several host names (DDEV, host stack), so no
// host name is pinned here.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';
