..  include:: /Includes.rst.txt

..  _feature-seeded-showcase-tree:

===========================================
Feature: A seeded showcase of every element
===========================================

Description
===========

The extension ships a showcase page tree as a seed set of
`sbuerk/data-factory <https://packagist.org/packages/sbuerk/data-factory>`__,
in :file:`EXT:theme_extension_development/Configuration/DataFactory/theme-demo/`.
That extension is suggested, not required: the set is inert data until it is
installed, and nothing in the theme reads it.

..  code-block:: bash

    composer require --dev sbuerk/data-factory
    vendor/bin/typo3 data-factory:import theme-demo

The tree demonstrates the whole theme rather than a handful of pages:

..  list-table::
    :header-rows: 1

    *   -   Page
        -   Backend layout
        -   What it shows
    *   -   :guilabel:`Theme demo` (``/``)
        -   :guilabel:`Start page`
        -   The site root, and the footer columns that layout adds.
    *   -   :guilabel:`Typography` (``/typography``)
        -   :guilabel:`Content page`
        -   Headings, running text and the inline cases a stylesheet has to
            answer for.
    *   -   :guilabel:`Media` (``/media``)
        -   :guilabel:`Content page`
        -   A single image, and a two column gallery.
    *   -   :guilabel:`Empty page` (``/empty``)
        -   *none*
        -   A page with no layout selected, which falls back to the default.
    *   -   :guilabel:`Elements` (``/elements``)
        -   :guilabel:`Content page`
        -   The showcase branch, and the parent of the three pages below.
    *   -   :guilabel:`Core elements` (``/elements/core``)
        -   :guilabel:`Content page with sidebar`
        -   Every classic content element the theme renders, once each.
    *   -   :guilabel:`Menu elements` (``/elements/menu``)
        -   :guilabel:`Content page with sidebar`
        -   All eleven menu elements, each pointed at a different part of the
            tree so they are told apart by what they list.
    *   -   :guilabel:`Theme elements` (``/elements/theme``)
        -   :guilabel:`Content page`
        -   Every element the extension registers itself, with their
            inline children filled in.
    *   -   :guilabel:`Styleguide` (``/styleguide``)
        -   :guilabel:`Styleguide`
        -   The component library, rendered from Fluid rather than from content.
    *   -   :guilabel:`Forms` (``/forms``)
        -   :guilabel:`Form showcase`
        -   A request form built from the form components, see
            :ref:`feature-form-showcase`.

Between them the pages carry every content type the extension renders, and the
layouts listed above; the multi column, article, cover and band layouts have a
demo page each in the :guilabel:`Layouts` section, see
:ref:`feature-column-page-layouts`. So a single seeded instance answers what
the theme does with each. Two of the pages are deliberate special cases:
:guilabel:`Empty page` selects no backend layout at all, which is the only way
to see the default fallback. :guilabel:`Styleguide` and :guilabel:`Forms` are
in the main navigation, see :ref:`feature-showcase-sections`; neither is
disabled - a disabled page returns 404 in the frontend and is only reachable
through a preview link, which defeats the point of seeding a page that exists
to be opened.

Known limitation
================

:guilabel:`Categorized pages` and :guilabel:`Categorized content` are part of
the seeded tree but select nothing: the set seeds no :sql:`sys_category`
records. Both elements render an empty menu, which is the correct rendering of
"no category chosen".

Impact
======

``vendor/bin/typo3 data-factory:import theme-demo`` produces a frontend that
exercises the theme end to end, so a development or test instance needs no
pages built by hand to see what an element looks like.

The set declares the uid of every record it writes - pages 1 to 10 and the
pages of :ref:`feature-showcase-sections`, content elements from 101 - because the records point at each other by uid: the links
name ``t3://page?uid=2``, the :guilabel:`Insert records` element names
``tt_content_601``, and a site configuration names its root page. The import
therefore needs an installation where those uids are free, and refuses rather
than overwrites when they are not. ``--root-page=<uid>`` writes the tree below
an existing page instead of at the root of the page tree, which changes where
it lands and not which uids it takes. The set declares no site configuration:
create one with root page ``1`` after importing it at the page tree root.
