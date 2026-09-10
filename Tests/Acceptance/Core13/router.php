<?php

declare(strict_types=1);

/**
 * Router of the PHP built-in server the acceptance suite serves its TYPO3 v13
 * instance with ("Build/Scripts/runTests.sh -t 13 -s acceptance").
 *
 * Without a router the built-in server falls back to "index.php" for a path
 * without a dot in its last segment. A path whose last segment has one is
 * taken for a file and answered with 404 when there is none. So: an existing
 * file is served as it is, everything else goes to "index.php" - the backend
 * below "/typo3/" included. TYPO3 v13 deprecated the backend entry point
 * "typo3/index.php" (#87889, "TYPO3 backend entry point script deprecated")
 * in favour of handling backend and frontend requests with "index.php", and
 * keeps the file only as a wrapper for web servers not yet adapted.
 *
 * The TYPO3 v12 counterpart is "../Core12/router.php"; see
 * "docs/testing/acceptance-tests.md".
 */
$documentRoot = (string)$_SERVER['DOCUMENT_ROOT'];
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if (is_file($documentRoot . $path)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $documentRoot . '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
chdir($documentRoot);

require $documentRoot . '/index.php';
