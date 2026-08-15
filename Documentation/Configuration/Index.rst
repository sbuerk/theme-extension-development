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

..  warning::

    Content elements are not rendered by the theme yet. The extension
    deliberately does not depend on :file:`fluid_styled_content`, and its own
    content element rendering is still to come — until then a content element on
    a page renders the TYPO3 notice that it has no rendering definition.
