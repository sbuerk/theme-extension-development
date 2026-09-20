<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Icon;

/**
 * The Font Awesome Free icons this extension ships.
 *
 * The set is the committed copy below "Resources/Public/Icons/FontAwesome/Solid/"
 * ("runTests.sh -s buildIcons", see "docs/development/icons.md"), and an icon's
 * name is its file name without ".svg".
 *
 * "Brands/" is the second directory this class can read: the curated handful of
 * platform logos of the brands style, named one by one in "fontAwesomeBrands"
 * of the root "package.json" and copied by the same build. It is a set of the
 * same shape - file name is icon name, "fill=currentColor", the attribution
 * comment inside - so it needs no second class, only a second directory. Use
 * "IconSet::brands()" rather than the constructor argument, which exists for
 * the tests.
 *
 * The directory is resolved relative to this file, not through a TYPO3 API:
 * "IconViewHelper" renders through this class, and the styleguide partials it is
 * used in are also rendered by plain "typo3fluid/fluid" with no TYPO3 bootstrap
 * ("Build/Scripts/renderStyleguideFixtures.php"). Nothing here may therefore
 * need more than PHP.
 *
 * Stateless: every call reads the file system. The set is not cached in the
 * object - a page renders a handful of icons, and TYPO3 caches the page.
 */
final readonly class IconSet
{
    /**
     * The shipped set.
     */
    public const DIRECTORY = __DIR__ . '/../../Resources/Public/Icons/FontAwesome/Solid';

    /**
     * The shipped brand logos, the allowlist of "package.json".
     */
    public const BRANDS_DIRECTORY = __DIR__ . '/../../Resources/Public/Icons/FontAwesome/Brands';

    /**
     * What a name may consist of. Checked before a name becomes part of a path,
     * so no name can leave the directory. "\z" rather than "$", which also
     * matches before a trailing line break.
     */
    public const NAME_PATTERN = '/\A[a-z0-9-]+\z/';

    /**
     * @param string $directory The set to read. The shipped one: the argument
     *                          exists so a unit test can point at a fixture,
     *                          nothing else has a reason to pass it.
     */
    public function __construct(
        private string $directory = self::DIRECTORY,
    ) {}

    /**
     * The brand logos, as their own set.
     *
     * A named constructor rather than a second service: the two differ in one
     * string, and the container has no reason to know about either directory -
     * the ViewHelper renders through this class with no TYPO3 API in reach
     * (see the class comment), and asking a container for the brands set would
     * be the one thing it cannot do in the standalone renderer.
     */
    public static function brands(): self
    {
        return new self(self::BRANDS_DIRECTORY);
    }

    /**
     * Every icon of the set, by name, sorted.
     *
     * Sorted here rather than trusting the order of "glob()", which depends on
     * the platform: a list an editor picks from has to come out the same
     * everywhere.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];
        foreach (glob($this->directory . '/*.svg') ?: [] as $file) {
            $names[] = basename($file, '.svg');
        }
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * The markup of one icon, ready to be placed into a page.
     *
     * That is the file as shipped, byte for byte - including the attribution
     * comment inside the root element. Font Awesome's "LICENSE.txt" asks "that
     * you do not actively work to remove them from files, especially code",
     * and the icons are CC BY 4.0: whoever shares them carries the attribution,
     * and a public page shows them. Nothing is added here either; the
     * ViewHelper adds its attributes to the root element.
     *
     * @throws \InvalidArgumentException for a name that is malformed or not in the set
     * @throws \UnexpectedValueException for a file that does not start and end as an "<svg>" element
     */
    public function markup(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not an icon name. A name consists of "a-z", "0-9" and "-" only, and has no ".svg".', $name),
                1789218001,
            );
        }
        $file = $this->directory . '/' . $name . '.svg';
        if (!is_file($file)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'There is no icon "%s" in the Font Awesome Free set this extension ships below'
                    . ' "Resources/Public/Icons/FontAwesome/%s/". The names are the file names there.',
                    $name,
                    basename($this->directory),
                ),
                1789218002,
            );
        }

        // The ViewHelper inserts its attributes right after "<svg", so a file
        // with anything in front of the root element - an XML declaration, a
        // comment - would come out broken. Such a file is refused instead.
        $markup = trim((string)file_get_contents($file));
        if (!str_starts_with($markup, '<svg ') || !str_ends_with($markup, '</svg>')) {
            throw new \UnexpectedValueException(
                sprintf('The icon file "%s" does not start and end as an <svg> element. Rebuild the set with "runTests.sh -s buildIcons".', $name . '.svg'),
                1789218003,
            );
        }

        return $markup;
    }
}
