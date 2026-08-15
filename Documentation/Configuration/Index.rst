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

Content elements
================

The theme renders the content elements TYPO3 provides without
:file:`fluid_styled_content`, on which it deliberately does not depend:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Rendered
    *   -   :guilabel:`Header`
        -   yes, honouring the heading level and the "do not display" setting
    *   -   :guilabel:`Text`
        -   yes, including rich text

..  warning::

    Those are the only two content elements that exist in an installation
    without :file:`fluid_styled_content`. Everything a TYPO3 installation
    usually offers — :guilabel:`Text & Media`, :guilabel:`Images`,
    :guilabel:`Bullet List`, :guilabel:`Table`, :guilabel:`File Links` and the
    menu elements — is registered by that extension, not by the TYPO3 core, and
    is therefore not available here at all.

    An element that has no rendering definition renders a TYPO3 notice saying
    so, rather than nothing.
