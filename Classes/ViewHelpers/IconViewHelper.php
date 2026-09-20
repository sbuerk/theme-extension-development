<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\ViewHelpers;

use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders one icon of the shipped Font Awesome Free set as inline SVG.
 *
 *     <html xmlns:theme="http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers"
 *           data-namespace-typo3-fluid="true">
 *
 *     <theme:icon name="circle-info" />
 *     <theme:icon name="gear" class="theme-settings__icon" />
 *     <theme:icon name="magnifying-glass" label="Search" />
 *     <theme:icon set="brands" name="mastodon" />
 *
 * "set" is "solid", the default and nearly every use, or "brands" - the
 * curated platform logos of "Resources/Public/Icons/FontAwesome/Brands/",
 * which exist for one job, naming the platform a social link leads to. A
 * brand logo is a trademark of its owner and may be used only to refer to
 * that platform; see "docs/development/icons.md".
 *
 * Without "label" the icon is decoration, the case of nearly every icon: it
 * sits next to text, or in a control that is named by "aria-label" or by its
 * own text. It is rendered with "aria-hidden" then. With "label" it is an
 * image of its own, "role=img" named by "aria-label" - for an icon that is
 * the only thing telling a reader something. "focusable=false" is on both.
 *
 * The class "theme-icon" is always there, and "class" adds to it. What it
 * looks like is "components/_icon.scss": one em square, filled with the text
 * colour.
 *
 * A name that is malformed or not in the set throws, rather than rendering
 * nothing: an icon that silently disappears is found by a reader, not by a
 * test.
 *
 * Except with "optional", for a name an editor picked:
 *
 *     <theme:icon name="{data.tx_theme_link_icon}" optional="1" />
 *
 * Then an empty name and a name the set does not have render nothing. The
 * field offered only names of the set when the record was saved, but a later
 * Font Awesome version may have renamed or dropped one, and that has to cost
 * the icon, not the page. So does a malformed name: a record holds whatever
 * was written into its column, by an import as well as by the picker, and
 * the name is refused before it becomes part of a path either way.
 * "Tests/Unit/IconUsageTest" requires "optional" on every name that is, or
 * contains, a variable, and a name written into a template is checked
 * against the set there instead.
 *
 * --- Plain Fluid, for the standalone renderer ------------------------------
 *
 * This is a "typo3fluid/fluid" ViewHelper with no TYPO3 API in it, and it has
 * to stay one: the styleguide partials use it, and the visual suite renders
 * them with standalone Fluid and no TYPO3 bootstrap
 * ("Build/Scripts/renderStyleguideFixtures.php"). The namespace is declared
 * per template with the "http://typo3.org/ns/<PHP namespace>" form, which
 * both Fluid 4 (TYPO3 v13) and Fluid 5 (TYPO3 v14) resolve by themselves, so
 * neither TYPO3 nor the fixture script has to register it.
 *
 * The same class works on both: "initializeArguments(): void" and
 * "render(): string" are compatible with the untyped Fluid 4 declarations and
 * the typed Fluid 5 ones, and "$escapeOutput" is untyped in both.
 *
 * Not "readonly": the parent keeps the arguments of the current call in
 * mutable properties. TYPO3 creates a new instance for every use (the
 * "fluid.viewhelper" tag makes ViewHelpers non-shared), and without a
 * container the constructor default does the same.
 */
final class IconViewHelper extends AbstractViewHelper
{
    /**
     * The two sets "set" names. A third value is a typo, not a request for the
     * default: "set=brand" would otherwise look for a platform logo among the
     * solid icons and report the name as missing, which points at the wrong
     * thing.
     */
    private const SETS = ['solid', 'brands'];

    /**
     * The output is markup. Every argument that reaches it is escaped in
     * "render()".
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function __construct(
        private readonly IconSet $iconSet = new IconSet(),
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'The icon: a file name below "Resources/Public/Icons/FontAwesome/<Set>/", without ".svg".', true);
        $this->registerArgument('set', 'string', 'Which shipped set the name is from: "solid" (the default) or "brands".', false, 'solid');
        $this->registerArgument('label', 'string', 'An accessible name. Without one the icon is decoration and hidden from assistive technology.', false, '');
        $this->registerArgument('class', 'string', 'Classes added to "theme-icon".', false, '');
        $this->registerArgument('optional', 'bool', 'Render nothing for an empty name or a name the set does not have, instead of throwing. For a name read from a record.', false, false);
    }

    public function render(): string
    {
        $name = (string)$this->arguments['name'];
        $optional = (bool)$this->arguments['optional'];
        $set = (string)$this->arguments['set'];
        if (!in_array($set, self::SETS, true)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a set this extension ships. Use "%s".', $set, implode('" or "', self::SETS)),
                1789218004,
            );
        }
        if ($optional && $name === '') {
            return '';
        }
        // A new "IconSet" per brand icon rather than a second injected one:
        // the object holds a directory string and nothing else, and two
        // constructor arguments of the same type cannot both be autowired.
        $iconSet = $set === 'brands' ? IconSet::brands() : $this->iconSet;
        try {
            $markup = $iconSet->markup($name);
        } catch (\InvalidArgumentException $exception) {
            if ($optional && in_array($exception->getCode(), [1789218001, 1789218002], true)) {
                return '';
            }
            throw $exception;
        }

        $class = trim('theme-icon ' . trim((string)$this->arguments['class']));
        $label = trim((string)$this->arguments['label']);
        $attributes = ' class="' . htmlspecialchars($class, ENT_QUOTES | ENT_HTML5) . '"';
        $attributes .= $label === ''
            ? ' aria-hidden="true"'
            : ' role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_HTML5) . '"';
        $attributes .= ' focusable="false"';

        // "IconSet::markup()" guarantees the markup starts with "<svg ".
        return '<svg' . $attributes . substr($markup, 4);
    }
}
