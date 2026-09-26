<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Gitignore;
use Symfony\Component\Finder\SplFileInfo;

require_once __DIR__ . '/../../.Build/vendor/autoload.php';
require_once __DIR__ . '/MarkdownTableFormatter.php';

/**
 * Quality gate over the Markdown tables of "./*.md" and "docs/": this file
 * decides which files to look at and how to report them, while the formatting
 * rules themselves live in "MarkdownTableFormatter.php".
 *
 * Run "--fix" to rewrite the files instead of only reporting them:
 *
 *   Build/Scripts/runTests.sh -s checkMarkdownTables
 *   Build/Scripts/runTests.sh -s checkMarkdownTables -- --fix
 *
 * Git-ignored files are skipped by the rules of the ".gitignore" of this
 * checkout, applied here, rather than by the "ignoreVCSIgnored()" option of the
 * Finder. That option takes as its root the nearest directory upwards that has
 * a ".git" *directory*, and a git worktree has a ".git" *file*: from a worktree
 * kept below an ignored directory of another checkout it applied the
 * ".gitignore" of that other checkout, found every file ignored and reported
 * "Checked 0 markdown files" with exit code 0. The rules of this checkout are
 * the only ones that decide which of its files are ignored, wherever it is
 * placed and however the script is started. Only the root ".gitignore" is
 * read: it is the only one the repository has.
 *
 * A set that yields no file at all fails the gate: the root always carries
 * "README.md" and "docs/" always carries "Index.md", so an empty set means the
 * files were not looked at, not that there is nothing to check.
 *
 * See the documentation conventions in "docs/Index.md".
 */
$fix = in_array('--fix', array_slice($argv, 1), true);
$output = new ConsoleOutput();
$root = dirname(__DIR__, 2);

$gitignore = $root . '/.gitignore';
$ignoredPattern = is_file($gitignore) ? Gitignore::toRegex((string)file_get_contents($gitignore)) : null;
$isNotIgnored = static function (SplFileInfo $file) use ($root, $ignoredPattern): bool {
    if ($ignoredPattern === null) {
        return true;
    }
    return preg_match($ignoredPattern, substr($file->getPathname(), strlen($root) + 1)) !== 1;
};

$finder = new Finder();
$finder->files()
    ->name('*.md')
    ->in($root)
    ->depth(0)
    ->filter($isNotIgnored);

$documentation = new Finder();
$documentation->files()
    ->name('*.md')
    ->in($root . '/docs')
    ->filter($isNotIgnored);

$formatter = new MarkdownTableFormatter();
$offenders = [];
$checked = 0;

foreach (['./*.md' => $finder, 'docs/' => $documentation] as $label => $set) {
    if (!$set->hasResults()) {
        $output->writeln(sprintf('<error>No markdown file found in "%s" of "%s", the gate would check nothing.</error>', $label, $root));
        exit(1);
    }
    foreach ($set as $file) {
        // Symlinked files are duplicates of their target, which is checked itself.
        if ($file->isLink()) {
            continue;
        }
        $checked++;
        $content = (string)file_get_contents($file->getPathname());
        [$formatted, $changed] = $formatter->format($content);
        if (!$changed) {
            continue;
        }
        $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        if ($fix) {
            file_put_contents($file->getPathname(), $formatted);
        }
    }
}

if ($offenders === []) {
    $output->writeln(sprintf('<info>Checked %d markdown files, all tables are formatted.</info>', $checked));
    exit(0);
}

if ($fix) {
    $output->writeln(sprintf('<info>Formatted tables in %d of %d markdown files:</info>', count($offenders), $checked));
    $output->writeln(array_map(static fn(string $file): string => '  ' . $file, $offenders));
    exit(0);
}

$output->writeln(sprintf('<error>Found unformatted tables in %d of %d markdown files:</error>', count($offenders), $checked));
$output->writeln(array_map(static fn(string $file): string => '  ' . $file, $offenders));
$output->writeln('');
$output->writeln('Pad every cell so the pipes line up, or run:');
$output->writeln('  Build/Scripts/runTests.sh -s checkMarkdownTables -- --fix');
exit(1);
