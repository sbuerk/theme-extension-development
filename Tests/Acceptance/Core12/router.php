<?php

declare(strict_types=1);

/**
 * Router of the PHP built-in server the acceptance suite serves its TYPO3 v12
 * instance with ("Build/Scripts/runTests.sh -t 12 -s acceptance").
 *
 * Without a router the built-in server falls back to "index.php" for a path
 * without a dot in its last segment. A path whose last segment has one is
 * taken for a file and answered with 404 when there is none. So: an existing
 * file is served as it is, everything else goes to a front controller, as the
 * rewrite rules of the "root-htaccess" template of TYPO3 v12's EXT:install do.
 *
 * Those rules pick the front controller too. TYPO3 v12 has a backend entry
 * point of its own, "typo3/index.php", and a missing path below "/typo3/" goes
 * there; everything else goes to "index.php". Sent to "index.php", "/typo3/"
 * reached the frontend of the v12 instance and answered 404.
 *
 * The TYPO3 v13 counterpart is "../Core13/router.php"; see
 * "docs/testing/acceptance-tests.md".
 */
$documentRoot = (string)$_SERVER['DOCUMENT_ROOT'];
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if (is_file($documentRoot . $path)) {
    return false;
}

$frontController = preg_match('#^/typo3(?:/|$)#', $path) === 1 ? '/typo3/index.php' : '/index.php';

$_SERVER['SCRIPT_NAME'] = $frontController;
$_SERVER['SCRIPT_FILENAME'] = $documentRoot . $frontController;
$_SERVER['PHP_SELF'] = $frontController;
chdir(dirname($documentRoot . $frontController));

require $documentRoot . $frontController;
