<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Imaging\IconProvider;

use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconProviderInterface;

/**
 * Renders an icon of the shipped Font Awesome sets as the SVG itself, so that
 * it is drawn in "currentColor" - in the colour of the text around it, in the
 * light and in the dark backend scheme alike.
 *
 * Registered by "InlineIconItems" for the items of an icon picker, with the
 * options "set" - "solid" or "brands" - and "name". Not meant to be registered
 * for anything else.
 *
 * The default markup is the SVG as well, not an "<img>" as with the core's
 * "SvgIconProvider": a caller that asks for the default markup gets an icon
 * that follows the scheme too. The markup is "IconSet::markup()", the file as
 * shipped, which is what "<theme:icon>" puts into every page of the frontend.
 * It is not run through a sanitiser, which the core's provider does on v14.3
 * for every icon, at about a millisecond each: the files are the committed
 * copy of the pinned package that "checkIconsBuild" holds byte for byte, never
 * an upload, and the name is checked against "IconSet::NAME_PATTERN" before it
 * becomes part of a path.
 *
 * An icon that cannot be read has no markup rather than failing the request:
 * an icon is decoration, and a broken one must not take the form with it.
 *
 * "IconProviderInterface" and not the core's "AbstractSvgIconProvider", which
 * is "@internal": the interface and the two setters of "Icon" it needs are the
 * same on v13.4 and v14.3.
 *
 * Stateless, and without a constructor: v13.4 creates a provider with
 * "GeneralUtility::makeInstance()", v14.3 takes it from the container when
 * the container has it.
 */
final readonly class ShippedIconProvider implements IconProviderInterface
{
    /**
     * The alternative markup "FormEngineUtility::getIconHtml()" asks for on
     * v13.4 and v14.3. The core's name for it is a constant of the internal
     * "AbstractSvgIconProvider".
     */
    private const MARKUP_INLINE = 'inline';

    /**
     * @param array<string, mixed> $options
     */
    public function prepareIconMarkup(Icon $icon, array $options = []): void
    {
        $set = ($options['set'] ?? 'solid') === 'brands' ? IconSet::brands() : new IconSet();
        try {
            $markup = $set->markup((string)($options['name'] ?? ''));
        } catch (\InvalidArgumentException|\UnexpectedValueException) {
            $markup = '';
        }
        $icon->setMarkup($markup);
        $icon->setAlternativeMarkup(self::MARKUP_INLINE, $markup);
    }
}
