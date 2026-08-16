..  include:: /Includes.rst.txt

..  _feature-seeded-showcase-tree:

===========================================
Feature: A seeded showcase of every element
===========================================

Description
===========

:ref:`feature-seeding` writes a page tree from a YAML definition. The shipped
definition :file:`EXT:theme_extension_development/Configuration/Seeds/Demo.yaml`
now describes a tree that demonstrates the whole theme rather than a handful of
pages:

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
        -   All ten elements the extension registers itself, with their inline
            children filled in.
    *   -   :guilabel:`Styleguide` (``/styleguide``)
        -   :guilabel:`Styleguide`
        -   The component library, rendered from Fluid rather than from content.

Between them the pages use every backend layout the extension registers and
carry every content type it renders, so a single seeded instance answers what
the theme does with each. Two of the pages are deliberate special cases:
:guilabel:`Empty page` selects no backend layout at all, which is the only way
to see the default fallback, and :guilabel:`Styleguide` is set to
:guilabel:`Page not enabled in menus` rather than disabled - a disabled page
returns 404 in the frontend and is only reachable through a preview link, which
defeats the point of seeding a page that exists to be opened.

Inline children in the seed format
==================================

Four of the theme's own content elements - :guilabel:`Author`,
:guilabel:`Link list`, :guilabel:`Social links` and :guilabel:`Media teaser
grid` - read their entries from an inline child table. A seed definition can
now describe those entries with a new structural key, ``inline``: a map of the
field on the parent record to the child records declared for it.

..  code-block:: yaml

    content:
      - identifier: showcase-linklist
        CType: theme_linklist
        header: 'Where to read more'
        inline:
          tx_theme_list_items:
            - identifier: showcase-docs
              table: tx_theme_list_item
              link: 't3://page?uid=2'
              link_label: 'Typography'
            - identifier: showcase-media
              table: tx_theme_list_item
              link: 't3://page?uid=3'
              link_label: 'Media'

Each child names the ``table`` it belongs to. That is never inferred from the
TCA of the parent's field, so a definition stays readable on its own and a
mistyped field name is reported rather than dereferenced. The children come out
in the order they are declared, and they may carry ``uid`` and ``files`` like
any other record.

The structural keys of the format are therefore ``identifier``, ``uid``,
``children``, ``content``, ``files`` and ``inline``, plus ``table`` on an inline
child. Everything else is a field of the record and is written as it stands -
which is why the backend layout and the "hide in menus" flag of the pages above
need nothing from the seeding at all.

Identifiers may no longer contain an underscore
===============================================

An ``identifier`` in a seed definition may contain letters, digits and dashes,
and has to start with a letter or a digit. A definition using anything else is
now rejected with an exception naming the identifier.

This is not a style rule. The identifier ends up inside the placeholder
DataHandler is given for the record, and a placeholder used as the value of a
relation field is read as the ``<table>_<uid>`` form when it contains an
underscore - so ``NEWtt_content_home`` is split into a table ``NEWtt_content``
and an id ``home``, neither of which resolves. The relation is then written
**empty, with nothing logged**. Rejecting the identifier is what turns a seed
that silently loses its relations into one that refuses to run.

Two fixes come with it
======================

Both failed silently, and both are now covered by a regression test:

*   **A declared** ``uid`` **was not honoured.** DataHandler reads a suggested
    uid from the data map row and looks it up under a ``<table>:<uid>`` key;
    the seeding supplied neither, so the next free uid was assigned and the
    command reported whatever it got. That looked correct only for as long as
    the declaration order of a definition happened to match its insertion
    order.

*   **File references were not ordered.** The placeholder of a reference had
    the same underscore problem, so ``sorting_foreign`` stayed at 0 on every
    seeded reference and the order of a gallery with more than one image was
    left to the database.

Known limitation
================

:guilabel:`Categorized pages` and :guilabel:`Categorized content` are part of
the seeded tree but select nothing: the format expresses neither
:sql:`sys_category` records nor the MM rows relating them to a page or a
content element. Both elements render an empty menu, which is the correct
rendering of "no category chosen". Supporting this needs a way to declare
records outside the page tree and a relation between two of them, which is out
of proportion to demonstrating two elements.

Impact
======

``vendor/bin/typo3 theme:seed`` produces a frontend that exercises the theme
end to end, so a development or test instance no longer needs pages built by
hand to see what an element looks like. Definitions of your own can describe
inline relations, and have to use identifiers without underscores.
