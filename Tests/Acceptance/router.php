<?php

declare(strict_types=1);

/**
 * Router of the PHP built-in server the acceptance suite serves its instance
 * with ("Build/Scripts/runTests.sh -s acceptance").
 *
 * Without a router the built-in server falls back to "index.php" for a path
 * without a dot in its last segment. A path whose last segment has one is
 * taken for a file and answered with 404 when there is none - and TYPO3 v14
 * routes such paths: the backend loads its labels from
 * "/typo3/language/domain/en/<hash>/backend.messages", and without them no
 * backend module renders. So: an existing file is served as it is, everything
 * else goes to the front controller, as a web server rewrite would.
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
