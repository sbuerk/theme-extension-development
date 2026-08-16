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
the stylesheet with it, and no :guilabel:`sys_template` record is needed. The
set itself declares neither dependencies nor settings — everything an
integrator changes is a TypoScript constant, and those are described below.

In the backend the same set can be selected under
:guilabel:`Site Management > Sites` in the :guilabel:`Sets` field of the site.

Installations without site sets
===============================

For an installation that does not use site sets, the theme additionally
registers a classic static template. Create a :guilabel:`sys_template` record on
the root page and include :guilabel:`Theme Extension Development` in
:guilabel:`Include static (from extensions)`.

Both paths read the same TypoScript files, so what they deliver is identical.

..  note::

    Use one mechanism or the other. When a site depends on the set, the static
    include deactivates itself — its constants and setup are wrapped in a
    condition asking whether the set is active for the current site, so an
    installation that has both configured is not served the theme twice.

    That condition asks the site for the sets it declares **itself**. A site
    that pulls the theme in transitively, by depending on another set which in
    turn depends on this one, is not covered: there the static include has to
    stay out of the :guilabel:`sys_template` record.

Templates and stylesheet
========================

The Fluid paths and the stylesheet are TypoScript constants, so an integrator
can render their own templates without editing the theme:

..  list-table::
    :header-rows: 1

    *   -   Constant
        -   Default
    *   -   ``theme.templateRootPath``
        -   :file:`EXT:theme_extension_development/Resources/Private/Templates/`
    *   -   ``theme.partialRootPath``
        -   :file:`EXT:theme_extension_development/Resources/Private/Partials/`
    *   -   ``theme.layoutRootPath``
        -   :file:`EXT:theme_extension_development/Resources/Private/Layouts/`
    *   -   ``theme.stylesheet``
        -   :file:`EXT:theme_extension_development/Resources/Public/Css/theme.css`

..  code-block:: typoscript

    theme {
        templateRootPath = EXT:my_site_package/Resources/Private/Templates/
        partialRootPath = EXT:my_site_package/Resources/Private/Partials/
        layoutRootPath = EXT:my_site_package/Resources/Private/Layouts/
        stylesheet = EXT:my_site_package/Resources/Public/Css/my-theme.css
    }

The stylesheet is compiled from SCSS sources that ship with the extension, so
it can also be rebuilt with different design tokens instead of being replaced —
see :ref:`feature-design-tokens`.

Image width
===========

The image based elements scale their images to the width the layout gives the
content column. That width is a constant, because nothing in TypoScript can
read it out of the stylesheet:

..  code-block:: typoscript

    theme.media {
        # The width in pixels the gallery is computed for.
        maxGalleryWidth = 1200

        # The same, for an element positioned beside the text.
        maxGalleryWidthInText = 420
    }

The default matches the ``75rem`` of ``--theme-content-max-width``. Set it too
low and images are processed smaller than they are displayed, which shows. Set
it far too high and every image is processed at a size no visitor ever sees.

Appearance and palette
======================

The theme renders in a light and a dark appearance and carries five colour
palettes. What is rendered server side — before the frontend switcher can
restore a visitor's choice from ``localStorage`` — is configured with three
constants:

..  list-table::
    :header-rows: 1

    *   -   Constant
        -   Default
        -   Values
    *   -   ``theme.appearance.default``
        -   ``auto``
        -   ``auto``, ``light``, ``dark``
    *   -   ``theme.appearance.palette``
        -   ``neutral``
        -   ``neutral``, ``ember``, ``ocean``, ``moss``, ``violet``
    *   -   ``theme.appearance.contentOutline``
        -   ``on``
        -   ``on``, ``off``

They are written onto the ``<html>`` tag as ``data-theme``, ``data-palette``
and ``data-theme-content-outline``. Two of those are worth knowing exactly:

*   ``auto`` renders **no** ``data-theme`` attribute at all. The appearance is
    then left to the operating system through ``color-scheme``, which is what
    ``light-dark()`` in the stylesheet resolves against. A palette has no such
    case, so ``neutral`` is still written out.
*   ``contentOutline`` draws the labelled outline around every content element.
    Only ``off`` has a rule of its own; ``on`` is simply the absence of it. It
    is a development and staging affordance — a site package rendering for real
    visitors sets it to ``off``.

See :ref:`feature-appearance-switcher` for the switcher itself and
:ref:`feature-design-tokens` for what the palettes are built from.

Backend layouts and page templates
==================================

The theme ships five backend layouts through page TSconfig, and the layout an
editor selects picks the Fluid template the page is rendered with:

..  list-table::
    :header-rows: 1

    *   -   Identifier
        -   Backend label
        -   Columns (``colPos``)
        -   Page template
    *   -   ``default``
        -   :guilabel:`Default`
        -   main (0)
        -   :file:`Page/Default.html`
    *   -   ``content``
        -   :guilabel:`Content page`
        -   stage (2), main (0), footer 1-4 (11-14), footer meta (10)
        -   :file:`Page/Content.html`
    *   -   ``content_sidebar``
        -   :guilabel:`Content page with sidebar`
        -   stage (2), main (0), sidebar (1), footer 1-4 (11-14), footer meta
            (10)
        -   :file:`Page/ContentSidebar.html`
    *   -   ``start``
        -   :guilabel:`Start page`
        -   stage (2), main (0), footer 1-4 (11-14), footer meta (10)
        -   :file:`Page/Start.html`
    *   -   ``styleguide``
        -   :guilabel:`Styleguide`
        -   unused (999)
        -   :file:`Page/Styleguide.html`

The ``colPos`` numbers are a contract, not an implementation detail: the same
number means the same slot in every layout, which is what lets an editor change
a page's layout without content disappearing. The footer columns and the footer
meta row slide down the rootline, so they are edited once on the start page and
appear on every page below it.

The mapping is by convention rather than by configuration: the identifier is
upper-camel-cased and prefixed with :file:`Page/`, so a new layout needs a
TSconfig file and a template of the matching name and nothing else. A page
without a layout, and a page whose layout is TYPO3's built-in
:guilabel:`[None]`, both render with :file:`Page/Default.html`.

See :ref:`feature-backend-layouts`.

Content elements
================

The theme brings its own content element rendering and does **not** depend on
:file:`fluid_styled_content` — that extension is not required here, and on
TYPO3 v14 it is not installed at all. What that covers:

*   Every classic content element :file:`EXT:frontend` registers, including
    :guilabel:`Text & Media`, :guilabel:`Bullet List`, :guilabel:`Table`,
    :guilabel:`File Links`, :guilabel:`Insert Records`, :guilabel:`Divider` and
    :guilabel:`Plain HTML` — see :ref:`feature-core-content-elements`.
*   All eleven menu elements — see :ref:`feature-menu-content-elements`.
*   Ten content elements of the theme's own, in a :guilabel:`Theme` group —
    see :ref:`feature-theme-content-elements`.
*   Third-party Extbase plugins, which render through a generic template
    without any per-plugin configuration — see
    :ref:`feature-extbase-plugin-rendering`.

Nothing an editor can create is left without a rendering definition, and a test
asserts exactly that: it walks the content types registered in TCA and fails if
the core's "no rendering definition" notice appears for any of them.

Demo content
============

A page tree to look at is written by a console command rather than by hand:

..  code-block:: bash

    vendor/bin/typo3 theme:seed

..  list-table::
    :header-rows: 1

    *   -   Argument or option
        -   Default
        -   Meaning
    *   -   ``definition``
        -   :file:`EXT:theme_extension_development/Configuration/Seeds/Demo.yaml`
        -   The YAML definition to write. An ``EXT:`` path is resolved.
    *   -   ``--root-page``
        -   ``0``
        -   The page the definition is written below. ``0`` is the page tree
            root.
    *   -   ``--force``
        -   —
        -   Seed even though the page tree is not empty. A definition declaring
            uids will collide.

The shipped definition seeds a start page, pages for typography and media, one
page deliberately without a backend layout, a ``/elements`` branch carrying
every content type, and a ``/styleguide`` page rendering the whole component
library — see :ref:`feature-seeded-showcase-tree` and :ref:`feature-styleguide`.
The definition format is described in :ref:`feature-seeding`.
