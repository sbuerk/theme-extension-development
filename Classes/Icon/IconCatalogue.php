<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Icon;

use Symfony\Component\Yaml\Yaml;

/**
 * The shipped icons as an editor picks from them: each in one group, and
 * without the names Font Awesome keeps for renamed icons.
 *
 * The groups are the categories of Font Awesome's "categories.yml", which
 * "runTests.sh -s buildIcons" copies from the pinned package next to the set.
 * An icon sits in the first category that lists it, in the order of the file:
 * Font Awesome files many icons under several categories, and a select item
 * has exactly one group, so any rule is a choice, and this one needs nothing
 * but the file.
 *
 * A name no category lists is, in 7.3.1, always an alias Font Awesome keeps
 * for a renamed icon - "arrow-circle-right" for "circle-arrow-right" - and its
 * file is byte for byte the file of the icon it stands for. Such a name is
 * left out: it would only repeat a glyph already in the list. Telling an alias
 * apart by its content needs no metadata beyond what ships; the alias list of
 * "metadata/icons.yml" is 912 kB. A name no category lists that draws a glyph
 * of its own is kept, in a group of its own at the end.
 *
 * Plain PHP with no dependency on the container: the TCA takes the groups
 * from here while it is built, which the install tool does without the
 * autowired container.
 */
final readonly class IconCatalogue
{
    /**
     * The categories of the pinned Font Awesome version, copied by the build.
     */
    public const CATEGORIES = __DIR__ . '/../../Resources/Public/Icons/FontAwesome/categories.yml';

    /**
     * The group of a name no category lists and that is no alias.
     */
    public const UNCATEGORISED = 'uncategorised';

    public const UNCATEGORISED_LABEL = 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:icon.group.uncategorised';

    public function __construct(
        private IconSet $iconSet = new IconSet(),
        private string $categoriesFile = self::CATEGORIES,
    ) {}

    /**
     * Every category, in the order of the file, with Font Awesome's English
     * label, and the fallback group last.
     *
     * A category no shipped icon is placed in is listed too: the core leaves
     * a group without items out of the list (the "SelectItemProcessor" of
     * EXT:backend, on v13.4 and v14.3), so building the TCA reads no icon file.
     *
     * @return array<string, string>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->categories() as $category => $definition) {
            $groups[$category] = $definition['label'];
        }
        $groups[self::UNCATEGORISED] = self::UNCATEGORISED_LABEL;

        return $groups;
    }

    /**
     * Every icon an editor is offered, sorted by name, with its group.
     *
     * Reads every file of the set once, to find the aliases - which is why
     * "IconItems" caches the result rather than calling this per form.
     *
     * @return list<array{name: string, group: string}>
     */
    public function icons(): array
    {
        $groupOfIcon = [];
        foreach ($this->categories() as $category => $definition) {
            foreach ($definition['icons'] as $name) {
                $groupOfIcon[$name] ??= $category;
            }
        }

        $names = $this->iconSet->names();
        $listedGlyphs = [];
        foreach ($names as $name) {
            if (isset($groupOfIcon[$name])) {
                $listedGlyphs[hash('xxh128', $this->iconSet->markup($name))] = true;
            }
        }

        $icons = [];
        foreach ($names as $name) {
            $group = $groupOfIcon[$name] ?? null;
            if ($group === null) {
                if (isset($listedGlyphs[hash('xxh128', $this->iconSet->markup($name))])) {
                    continue;
                }
                $group = self::UNCATEGORISED;
            }
            $icons[] = ['name' => $name, 'group' => $group];
        }

        return $icons;
    }

    /**
     * What the result of "icons()" depends on: the names of the set and the
     * categories. A cached list keyed on it is rebuilt when either changes.
     */
    public function fingerprint(): string
    {
        return hash('xxh128', implode(',', $this->iconSet->names()) . '|' . (string)hash_file('xxh128', $this->categoriesFile));
    }

    /**
     * @return array<string, array{label: string, icons: list<string>}>
     */
    private function categories(): array
    {
        $parsed = Yaml::parseFile($this->categoriesFile);
        if (!is_array($parsed)) {
            throw new \UnexpectedValueException(
                sprintf('"%s" holds no categories. Rebuild the icon set with "runTests.sh -s buildIcons".', $this->categoriesFile),
                1789300001,
            );
        }

        $categories = [];
        foreach ($parsed as $category => $definition) {
            if (!is_array($definition) || !is_string($definition['label'] ?? null) || !is_array($definition['icons'] ?? null)) {
                continue;
            }
            $categories[(string)$category] = [
                'label' => $definition['label'],
                'icons' => array_values(array_map('strval', $definition['icons'])),
            ];
        }

        return $categories;
    }
}
