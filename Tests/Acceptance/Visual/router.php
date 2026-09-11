<?php

declare(strict_types=1);

/**
 * Router of the PHP built-in server the visual suite serves its fixtures with
 * ("Build/Scripts/runTests.sh -s visual").
 *
 * The document root is the repository root, because a fixture links the
 * committed stylesheet where it is rather than a copy of it. Two trees are
 * served, and nothing else:
 *
 *   /.Build/visual/      the fixtures, the manifest and the index
 *   /Resources/Public/   the stylesheet and whatever it references
 *
 * Everything else is answered with 404 - in particular the self referencing
 * "theme" symlink at the root, which would otherwise make the whole repository
 * reachable below itself, again and again.
 */
$documentRoot = (string)realpath((string)$_SERVER['DOCUMENT_ROOT']);
$path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));

foreach (['/.Build/visual/', '/Resources/Public/'] as $tree) {
    if (!str_starts_with($path, $tree)) {
        continue;
    }
    // Resolved and compared again, so neither a ".." segment nor a symlink
    // below the tree leads out of it.
    $file = realpath($documentRoot . $path);
    if ($file !== false && is_file($file) && str_starts_with($file, $documentRoot . $tree)) {
        return false;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not a fixture of the visual suite.\n";
return true;
