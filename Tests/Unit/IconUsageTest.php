<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Holds the templates to the icon rule: every icon comes from the vendored
 * Font Awesome Free solid set, through "<theme:icon>", and the set is the
 * version "package.json" pins.
 *
 * "checkIconsBuild" proves that the committed files equal the pinned package.
 * What it cannot see is what refers to the set - a template naming an icon a
 * version bump removed, an SVG drawn into a template by hand, an attribution
 * still naming the old version - and that is what is asserted here.
 */
final class IconUsageTest extends UnitTestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const PACKAGE = '@fortawesome/fontawesome-free';

    /**
     * @return array<string, string> The source of every Fluid file below "Resources/Private/", by path.
     */
    private function templates(): array
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::ROOT . '/Resources/Private', \FilesystemIterator::SKIP_DOTS),
        );
        $templates = [];
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'html') {
                $path = substr($file->getPathname(), strlen(self::ROOT) + 1);
                $templates[$path] = (string)file_get_contents($file->getPathname());
            }
        }
        ksort($templates);
        $this->assertNotSame([], $templates, 'No template was found - the path is wrong.');

        return $templates;
    }

    /**
     * @return array<string, list<string>> The icon names each template renders, by path.
     */
    private function iconsByTemplate(): array
    {
        $icons = [];
        foreach ($this->templates() as $path => $source) {
            preg_match_all('#<theme:icon\b[^>]*?\bname="([^"]*)"#', $source, $matches);
            if ($matches[1] !== []) {
                $icons[$path] = $matches[1];
            }
        }

        return $icons;
    }

    /**
     * An icon Font Awesome renamed or dropped in a new version fails here,
     * with the template that names it, rather than as an exception on the
     * page that renders it.
     */
    #[Test]
    public function everyIconATemplateNamesIsShipped(): void
    {
        $shipped = (new IconSet())->names();
        $missing = [];
        foreach ($this->iconsByTemplate() as $path => $names) {
            foreach ($names as $name) {
                if (!in_array($name, $shipped, true)) {
                    $missing[] = $path . ': ' . $name;
                }
            }
        }

        $this->assertNotSame([], $this->iconsByTemplate(), 'No template renders an icon - the pattern is wrong.');
        $this->assertSame([], $missing, 'These icons are not in the shipped set: ' . implode(', ', $missing));
    }

    /**
     * Icons come from the set, never drawn by hand. A data URI image is an
     * "img", not an "svg" element, and is not affected.
     */
    #[Test]
    public function noTemplateDrawsAnSvgOfItsOwn(): void
    {
        $drawing = array_keys(array_filter(
            $this->templates(),
            static fn(string $source): bool => preg_match('#<svg\b#i', $source) === 1,
        ));

        $this->assertSame([], $drawing, 'These templates draw an SVG instead of using <theme:icon>: ' . implode(', ', $drawing));
    }

    /**
     * No stylesheet draws a glyph as generated content: a character in
     * "content" is drawn by whichever installed font covers it, and an icon
     * is markup from the set. The only strings "content" holds are the empty
     * one of a shape the stylesheet draws, and the "/" separator of the
     * breadcrumb, which is text.
     */
    #[Test]
    public function noStylesheetDrawsAGlyphAsGeneratedContent(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::ROOT . '/Resources/Private/Scss', \FilesystemIterator::SKIP_DOTS),
        );
        $found = 0;
        $glyphs = [];
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'scss') {
                continue;
            }
            // Comments first: the files explain in prose what they no longer do.
            $source = (string)preg_replace('#//.*$#m', '', (string)file_get_contents($file->getPathname()));
            preg_match_all('#(?<![-\w])content\s*:\s*([\'"])(.*?)\1#', $source, $matches);
            $found += count($matches[2]);
            foreach ($matches[2] as $value) {
                if (!in_array($value, ['', '/'], true)) {
                    $glyphs[] = $file->getFilename() . ': ' . $value;
                }
            }
        }

        $this->assertGreaterThan(0, $found, 'No quoted "content" was found - the pattern is wrong.');
        $this->assertSame([], $glyphs, 'These stylesheets draw a glyph as generated content: ' . implode(', ', $glyphs));
    }

    /**
     * The icon table of the styleguide lists exactly the icons the templates
     * render, and states the size of the set - both literals in a partial the
     * visual suite renders without TYPO3, so both are checked here.
     */
    #[Test]
    public function theStyleguideListsTheIconsTheTemplatesUseAndTheSizeOfTheSet(): void
    {
        $path = 'Resources/Private/Partials/Styleguide/Icons.html';
        $icons = $this->iconsByTemplate();
        $source = $this->templates()[$path] ?? '';

        preg_match_all('#<td><code>([a-z0-9-]+)</code></td>#', $source, $listed);
        $listed = array_unique($listed[1]);
        sort($listed);

        unset($icons[$path]);
        $used = array_unique(array_merge(...array_values($icons)));
        sort($used);

        $this->assertSame($used, $listed, 'The icon table of the styleguide does not list the icons the templates use.');

        $this->assertMatchesRegularExpression('#<strong>(\d+)</strong> icons#', $source);
        preg_match('#<strong>(\d+)</strong> icons#', $source, $count);
        $this->assertSame(count((new IconSet())->names()), (int)($count[1] ?? 0), 'The styleguide states a different number of icons than ship.');
    }

    /**
     * The version is pinned exactly, the lockfile agrees, and the
     * attribution and every shipped file name that version.
     */
    #[Test]
    public function theShippedSetIsThePinnedVersion(): void
    {
        $package = json_decode((string)file_get_contents(self::ROOT . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
        $lock = json_decode((string)file_get_contents(self::ROOT . '/package-lock.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($package);
        $this->assertIsArray($lock);

        $pinned = (string)($package['devDependencies'][self::PACKAGE] ?? '');
        $this->assertMatchesRegularExpression('/\A\d+\.\d+\.\d+\z/', $pinned, 'The icon package has to be pinned to an exact version, not a range.');
        $this->assertSame($pinned, $lock['packages']['node_modules/' . self::PACKAGE]['version'] ?? null, 'package-lock.json installs another version.');

        $attribution = (string)file_get_contents(self::ROOT . '/Resources/Public/Icons/FontAwesome/ATTRIBUTION.txt');
        $this->assertStringContainsString('Font Awesome Free ' . $pinned . ' ', $attribution);

        $stale = [];
        foreach (glob(self::ROOT . '/Resources/Public/Icons/FontAwesome/Solid/*.svg') ?: [] as $file) {
            if (!str_contains((string)file_get_contents($file), '<!--! Font Awesome Free ' . $pinned . ' ')) {
                $stale[] = basename($file);
            }
        }
        $this->assertSame([], $stale, 'These files do not come from ' . $pinned . ' - run "runTests.sh -s buildIcons".');
    }
}
