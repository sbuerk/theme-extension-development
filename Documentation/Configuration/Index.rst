..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Enabling the theme for a site
=============================

The theme ships a **site set**. A site enables it by depending on that set in
its :file:`config/sites/<identifier>/config.yaml`:

..  code-block:: yaml

    dependencies:
      - sbuerk/theme-extension-development

Nothing else is required. The set brings the TypoScript, the page rendering and
the stylesheet with it, and no :guilabel:`sys_template` record is needed.

In the backend the same set can be selected under
:guilabel:`Site Management > Sites` in the :guilabel:`Sets` field of the site.

Installations without site sets
===============================

For an installation that does not use site sets, the theme additionally
registers a classic static template. Create a :guilabel:`sys_template` record on
the root page and include :guilabel:`Theme Extension Development
(theme_extension_development)` in :guilabel:`Include static (from extensions)`.

..  note::

    Use one mechanism or the other. When a site depends on the set, the static
    include deactivates itself — the theme detects the active set and skips its
    own import, so a site that has both configured is not served the theme
    twice.

Overriding the templates
========================

The Fluid paths are TypoScript constants, so an integrator can render their own
templates without editing the theme:

..  code-block:: typoscript

    theme {
        templateRootPath = EXT:my_site_package/Resources/Private/Templates/
        partialRootPath = EXT:my_site_package/Resources/Private/Partials/
        layoutRootPath = EXT:my_site_package/Resources/Private/Layouts/
        stylesheet = EXT:my_site_package/Resources/Public/Css/my-theme.css
    }

The stylesheet itself is compiled from SCSS sources that ship with the
extension, so it can also be rebuilt with different design tokens instead of
being overridden.

Image width
===========

The :guilabel:`Images` element scales its images to the width the layout gives
the content column. That width is a constant, because nothing in TypoScript can
read it out of the stylesheet:

..  code-block:: typoscript

    theme.media {
        # The width in pixels the gallery is computed for.
        maxGalleryWidth = 1200

        # The same, for an element positioned beside the text.
        maxGalleryWidthInText = 420
    }

Set it too low and images are processed smaller than they are displayed, which
shows. Set it far too high and every image is processed at a size no visitor
ever sees.

Content elements
================

The theme brings its own content element rendering, so that it does not depend
on :file:`fluid_styled_content`:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Rendered
    *   -   :guilabel:`Header`
        -   yes, honouring the heading level and the "do not display" setting
    *   -   :guilabel:`Text`
        -   yes, including rich text
    *   -   :guilabel:`Images`
        -   yes, honouring the column count, the position, the fixed
            dimensions and the click-enlarge setting

..  warning::

    The remaining classic content elements — :guilabel:`Text & Media`,
    :guilabel:`Bullet List`, :guilabel:`Table`, :guilabel:`File Links`, the menu
    elements and :guilabel:`HTML` — **can be created** in the backend. Their TCA
    comes from EXT:frontend and not from :file:`fluid_styled_content`, which
    contributes only the rendering.

    They have no rendering definition here yet, and an element without one
    renders a TYPO3 notice saying so, rather than nothing.
