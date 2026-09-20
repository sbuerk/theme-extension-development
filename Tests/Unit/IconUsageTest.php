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
     * The icon names each template renders, by path, with the set each one is
     * from - "solid" unless the tag says "brands".
     *
     * A name that is a variable - "{data.tx_theme_link_icon}" - is not one of
     * them: it is whatever an editor picked, from a field that offers only
     * names of the set. It is held to "optional" by
     * "aNameAnEditorPickedIsRenderedAsOptional()" instead.
     *
     * @return array<string, list<array{set: string, name: string}>>
     */
    private function iconsByTemplate(): array
    {
        $icons = [];
        foreach ($this->templates() as $path => $source) {
            preg_match_all('#<theme:icon\b[^>]*?/?>#', $source, $tags);
            foreach ($tags[0] as $tag) {
                if (preg_match('#\bname="([^"{]*)"#', $tag, $name) !== 1) {
                    continue;
                }
                $set = preg_match('#\bset="([a-z]+)"#', $tag, $matched) === 1 ? $matched[1] : 'solid';
                $icons[$path][] = ['set' => $set, 'name' => $name[1]];
            }
        }

        return $icons;
    }

    /**
     * The names of one set, as the templates use it.
     *
     * @param array<string, list<array{set: string, name: string}>> $icons
     * @return list<string>
     */
    private function namesOfSet(array $icons, string $set): array
    {
        $names = [];
        foreach ($icons as $used) {
            foreach ($used as $icon) {
                if ($icon['set'] === $set) {
                    $names[] = $icon['name'];
                }
            }
        }
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * The icons a stylesheet paints as a mask - the check list marker of
     * "components/_list.scss", the markers of a decorated link in
     * "components/_link.scss" - by the file name they reference. That each is
     * a file of the set, by its relative path, is
     * "aStylesheetReferencesOnlyFilesOfTheIconSet()"; this only names them.
     *
     * @return list<string>
     */
    private function iconsTheStylesheetsMask(): array
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::ROOT . '/Resources/Private/Scss', \FilesystemIterator::SKIP_DOTS),
        );
        $icons = [];
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'scss') {
                continue;
            }
            $source = (string)preg_replace('#//.*$#m', '', (string)file_get_contents($file->getPathname()));
            preg_match_all('#url\(\s*([\'"]?)[^)\'"]*Icons/FontAwesome/Solid/([^)\'"/]+)\.svg\1\s*\)#', $source, $matches);
            array_push($icons, ...$matches[2]);
        }
        $icons = array_values(array_unique($icons));
        sort($icons);

        return $icons;
    }

    /**
     * A name read from a record is rendered with "optional": the set of a later
     * version may no longer have the name an editor picked, and that has to
     * cost the icon, not the page.
     */
    #[Test]
    public function aNameAnEditorPickedIsRenderedAsOptional(): void
    {
        $found = 0;
        $strict = [];
        foreach ($this->templates() as $path => $source) {
            preg_match_all('#<theme:icon\b[^>]*?\bname="[^"]*\{[^"]*"[^>]*>#', $source, $matches);
            foreach ($matches[0] as $tag) {
                $found++;
                if (preg_match('#\boptional="(1|true)"#', $tag) !== 1) {
                    $strict[] = $path . ': ' . $tag;
                }
            }
        }

        $this->assertGreaterThan(0, $found, 'No template renders an icon an editor picked - the pattern is wrong.');
        $this->assertSame([], $strict, 'These icons come from a record and are not optional: ' . implode(', ', $strict));
    }

    /**
     * An icon Font Awesome renamed or dropped in a new version fails here,
     * with the template that names it, rather than as an exception on the
     * page that renders it.
     */
    #[Test]
    public function everyIconATemplateNamesIsShipped(): void
    {
        $shipped = [
            'solid' => (new IconSet())->names(),
            'brands' => IconSet::brands()->names(),
        ];
        $missing = [];
        foreach ($this->iconsByTemplate() as $path => $icons) {
            foreach ($icons as $icon) {
                if (!in_array($icon['name'], $shipped[$icon['set']] ?? [], true)) {
                    $missing[] = $path . ': ' . $icon['set'] . '/' . $icon['name'];
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
     * A stylesheet may reference an icon only as a file of the vendored set,
     * the way the check list masks "check.svg" - never a "data:" URI, never an
     * image of its own, never a file that is not shipped. The path is relative
     * to the compiled stylesheet in "Resources/Public/Css/".
     */
    #[Test]
    public function aStylesheetReferencesOnlyFilesOfTheIconSet(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::ROOT . '/Resources/Private/Scss', \FilesystemIterator::SKIP_DOTS),
        );
        $found = 0;
        $foreign = [];
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'scss') {
                continue;
            }
            // Comments first, as above: prose may name a URL it does not use.
            $source = (string)preg_replace('#//.*$#m', '', (string)file_get_contents($file->getPathname()));
            preg_match_all('#url\(\s*([\'"]?)(.*?)\1\s*\)#', $source, $matches);
            foreach ($matches[2] as $url) {
                $found++;
                $isShippedSetFile = preg_match('#^\.\./Icons/FontAwesome/Solid/[a-z0-9-]+\.svg$#', $url) === 1
                    && is_file(self::ROOT . '/Resources/Public/Icons/FontAwesome/Solid/' . basename($url));
                if (!$isShippedSetFile) {
                    $foreign[] = $file->getFilename() . ': ' . $url;
                }
            }
        }

        $this->assertGreaterThan(0, $found, 'No "url()" was found - the pattern is wrong, or the check list lost its mask.');
        $this->assertSame([], $foreign, 'These stylesheets reference something other than a file of the vendored icon set: ' . implode(', ', $foreign));
    }

    /**
     * The icon table of the styleguide lists exactly the icons the templates
     * render, and states the size of the set - both literals in a partial the
     * visual suite renders without TYPO3, so both are checked here.
     *
     * The brand logos are a table of their own, and the whole allowlist is in
     * it rather than only the names a template writes out: the set exists to
     * be picked from in the backend, so the styleguide shows what an editor
     * can pick, the way the solid table shows what the theme itself draws.
     */
    #[Test]
    public function theStyleguideListsTheIconsTheTemplatesUseAndTheSizeOfTheSet(): void
    {
        $path = 'Resources/Private/Partials/Styleguide/Icons.html';
        $icons = $this->iconsByTemplate();
        $source = $this->templates()[$path] ?? '';
        [$solidTable, $brandsTable] = $this->iconTablesOfTheStyleguide($source);

        unset($icons[$path]);
        $used = array_unique(array_merge($this->iconsTheStylesheetsMask(), $this->namesOfSet($icons, 'solid')));
        sort($used);

        $this->assertSame($used, $solidTable, 'The icon table of the styleguide does not list the icons the templates use.');
        $this->assertSame(IconSet::brands()->names(), $brandsTable, 'The brand table of the styleguide does not list the shipped brand logos.');

        $this->assertMatchesRegularExpression('#<strong>(\d+)</strong> icons#', $source);
        preg_match('#<strong>(\d+)</strong> icons#', $source, $count);
        $this->assertSame(count((new IconSet())->names()), (int)($count[1] ?? 0), 'The styleguide states a different number of icons than ship.');
    }

    /**
     * The names listed in the two icon tables of the styleguide section, in
     * the order the section renders them: the solid one first, the brands one
     * after it.
     *
     * @return array{list<string>, list<string>}
     */
    private function iconTablesOfTheStyleguide(string $source): array
    {
        $tables = [];
        preg_match_all('#<table\b.*?</table>#s', $source, $matches);
        foreach ($matches[0] as $table) {
            preg_match_all('#<td><code>([a-z0-9-]+)</code></td>#', $table, $names);
            if ($names[1] === []) {
                continue;
            }
            $listed = array_values(array_unique($names[1]));
            sort($listed);
            $tables[] = $listed;
        }
        $this->assertCount(2, $tables, 'The icons section does not render one table of solid names and one of brand names.');

        return [$tables[0], $tables[1]];
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
        foreach (['Solid', 'Brands'] as $set) {
            foreach (glob(self::ROOT . '/Resources/Public/Icons/FontAwesome/' . $set . '/*.svg') ?: [] as $file) {
                if (!str_contains((string)file_get_contents($file), '<!--! Font Awesome Free ' . $pinned . ' ')) {
                    $stale[] = $set . '/' . basename($file);
                }
            }
        }
        $this->assertSame([], $stale, 'These files do not come from ' . $pinned . ' - run "runTests.sh -s buildIcons".');
    }

    /**
     * The brand logos are an allowlist, not a style copied whole: 609 brand
     * files ship with the package and fifteen of them are here, named one by
     * one in "package.json" so adding a platform is a decision somebody wrote
     * down rather than a side effect of a version bump.
     *
     * "checkIconsBuild" proves the committed directory equals that allowlist
     * applied to the pinned package. What it cannot see is the list itself
     * drifting into an unsorted, duplicated heap, which is what this holds.
     */
    #[Test]
    public function theBrandLogosAreTheAllowlistOfThePackageManifest(): void
    {
        $package = json_decode((string)file_get_contents(self::ROOT . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($package);
        $allowed = $package['fontAwesomeBrands'] ?? null;

        $this->assertIsArray($allowed);
        $this->assertNotSame([], $allowed, 'The brand allowlist is empty.');
        $this->assertSame(array_values(array_unique($allowed)), $allowed, 'The brand allowlist names a platform twice.');
        $sorted = $allowed;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $allowed, 'The brand allowlist is not sorted, which makes every addition a diff of its own.');
        $this->assertLessThanOrEqual(15, count($allowed), 'The brand set is a curated subset, not a copy of the brands style.');

        $this->assertSame($sorted, IconSet::brands()->names(), 'The committed brand logos are not the allowlist - run "runTests.sh -s buildIcons".');
    }

    /**
     * A brand logo is a trademark of its owner, and Font Awesome's licence
     * asks that it is used only to refer to the platform it names. The
     * attribution that ships beside the files has to say so: it is the file
     * that travels into the composer dist archive and the TER artifact.
     */
    #[Test]
    public function theAttributionNamesTheTrademarkRestrictionOfTheBrandLogos(): void
    {
        $attribution = (string)file_get_contents(self::ROOT . '/Resources/Public/Icons/FontAwesome/ATTRIBUTION.txt');

        $this->assertStringContainsString('Brands/', $attribution);
        $this->assertStringContainsString('trademarks of their respective owners', $attribution);
    }
}
