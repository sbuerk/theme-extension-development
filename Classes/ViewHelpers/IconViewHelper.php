<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\ViewHelpers;

use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders one icon of the shipped Font Awesome Free solid set as inline SVG.
 *
 *     <html xmlns:theme="http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers"
 *           data-namespace-typo3-fluid="true">
 *
 *     <theme:icon name="circle-info" />
 *     <theme:icon name="gear" class="theme-settings__icon" />
 *     <theme:icon name="magnifying-glass" label="Search" />
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
 * --- Plain Fluid, for the standalone renderer ------------------------------
 *
 * This is a "typo3fluid/fluid" ViewHelper with no TYPO3 API in it, and it has
 * to stay one: the styleguide partials use it, and the visual suite renders
 * them with standalone Fluid and no TYPO3 bootstrap
 * ("Build/Scripts/renderStyleguideFixtures.php"). The namespace is declared
 * per template with the "http://typo3.org/ns/<PHP namespace>" form, which
 * both Fluid 2 (TYPO3 v12) and Fluid 4 (TYPO3 v13) resolve by themselves, so
 * neither TYPO3 nor the fixture script has to register it.
 *
 * The same class works on both: "initializeArguments(): void" and
 * "render(): string" are compatible with the untyped declarations of Fluid 2
 * and Fluid 4, and "$escapeOutput" is untyped in both.
 *
 * Not "readonly": the parent keeps the arguments of the current call in
 * mutable properties. TYPO3 creates a new instance for every use (the
 * "fluid.viewhelper" tag makes ViewHelpers non-shared), and without a
 * container the constructor default does the same.
 */
final class IconViewHelper extends AbstractViewHelper
{
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
        $this->registerArgument('name', 'string', 'The icon: a file name below "Resources/Public/Icons/FontAwesome/Solid/", without ".svg".', true);
        $this->registerArgument('label', 'string', 'An accessible name. Without one the icon is decoration and hidden from assistive technology.', false, '');
        $this->registerArgument('class', 'string', 'Classes added to "theme-icon".', false, '');
    }

    public function render(): string
    {
        $markup = $this->iconSet->markup((string)$this->arguments['name']);

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
