..  include:: /Includes.rst.txt

..  _feature-theme-content-elements:

================================
Feature: Theme content elements
================================

Description
===========

The theme now ships ten content elements of its own - unlike every element
covered in :ref:`feature-core-content-elements` and
:ref:`feature-menu-content-elements`, these do not exist in the core at all.
Their TCA, their own columns and a shared inline child table all belong to
this extension:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Renders through
    *   -   :guilabel:`Hero`
        -   A full hero: heading, text, media and call-to-action links.
    *   -   :guilabel:`Hero, small`
        -   The same, reduced.
    *   -   :guilabel:`Hero, text only`
        -   The same, without media.
    *   -   :guilabel:`Teaser`
        -   A short text teaser without media.
    *   -   :guilabel:`Media teaser`
        -   Text placed beside a single image.
    *   -   :guilabel:`Media teaser grid`
        -   Several media teasers arranged in a grid.
    *   -   :guilabel:`Testimonial`
        -   A quotation with its attribution.
    *   -   :guilabel:`Author`
        -   A person: portrait, name, role and links.
    *   -   :guilabel:`Link list`
        -   A list of links.
    *   -   :guilabel:`Social links`
        -   The same, labelled instead of rendered as icons.

All ten are grouped under their own :guilabel:`Theme` entry in the
:guilabel:`Create new content element` wizard, so an editor can tell them
apart from the core set at a glance. No page TSconfig registers that group -
since TYPO3 v13 the wizard is generated straight from the :php:`label`,
:php:`description`, :php:`group` and :php:`icon` already given to
:php:`ExtensionManagementUtility::addRecordType()`.

Naming
======

CTypes are prefixed ``theme_``, columns ``tx_theme_``, and the shared inline
child table is ``tx_theme_list_item`` - short rather than the full extension
key, because ``themeextensiondevelopment_hero`` is unusable in a
:php:`showitem` string and in TypoScript. This follows the reference
implementation's own equally short prefix for the identical reason, and it
accepts the same collision risk deliberately: another extension is free to
also prefix its own fields ``theme_``.

No schema of its own
=====================

This extension ships no ``ext_tables.sql``. The whole schema - the four
``tx_theme_*`` columns added to ``tt_content`` and every column of
``tx_theme_list_item`` - is derived from TCA by
:php:`TYPO3\CMS\Core\Database\Schema\DefaultTcaSchema::enrich()`, on both
supported core versions.

One column needed to be declared explicitly rather than left to that
derivation. An inline relation's :php:`foreign_field` and
:php:`foreign_table_field` are auto-created on the child table if not already
present; a field used only in :php:`foreign_match_fields` - here, the column
that records *which* of the four inline relations sharing this child table a
row belongs to - is not part of that special case and gets no column for
free. ``fieldname`` on ``tx_theme_list_item`` is therefore a real
:php:`type=input` column, the same way the core's own ``sys_file_reference``
declares its own ``fieldname`` for the identical reason.

``type=link`` fields are not URLs
==================================

``tx_theme_link`` and the child table's ``link`` column are both TCA
:php:`type=link`. Their stored value is :php:`stdWrap.typolink` syntax, not a
bare URL, so every template renders it through :html:`f:link.typolink` or
:html:`f:uri.typolink`, never as a plain ``href``. TYPO3 v14's Fluid 5
null-handling change names :html:`f:link.typolink` as an explicit exception -
it renders through the TypoScript link API rather than building a tag itself
- so no version split was needed to keep this working on both v13.4 and
v14.3.

Inline children, and the ``item.data`` trap
==============================================

No core data processor resolves a generic database relation the way
:php:`FilesProcessor` resolves FAL - that class only ever wraps
:php:`FileCollector`, which is FAL-specific by construction.
:guilabel:`Author`, :guilabel:`Link list`, :guilabel:`Social links` and
:guilabel:`Media teaser grid` all resolve their shared inline relation with
:php:`TYPO3\CMS\Frontend\DataProcessing\DatabaseQueryProcessor` instead.

That processor wraps every row as :php:`['data' => $record]` - the same
wrapping :ref:`feature-menu-content-elements` already documents for
:guilabel:`Categorized content`. Every template reads ``item.data.link``,
never ``item.link``: the latter resolves to nothing and renders an **empty
list with no error**, not a broken one. This is not a hypothetical risk here
either - it happened during this element set's own development, before the
functional test suite caught it.

``GalleryProcessor`` is deliberately not used
================================================

:guilabel:`Hero`, :guilabel:`Hero, small`, :guilabel:`Media teaser` and
:guilabel:`Author` all resolve their image with :php:`FilesProcessor` alone.
Each shows exactly one image in a fixed-shape box, and none of their forms
expose :guilabel:`Columns`, :guilabel:`Orientation` or the other gallery
fields :php:`GalleryProcessor` reads - wiring it in would bind the layout to
columns an editor can never set.

Markup
======

:guilabel:`Link list` and :guilabel:`Social links` reuse the existing
``.theme-content-menu`` component - the same one every ``menu_*`` element
uses - rather than a list component of their own: structurally the shape is
identical, and a purpose-built list component would only duplicate styling
that already exists. :guilabel:`Author`'s own profile/contact links reuse the
identical pair for the same reason.

Only one new component was needed: ``.theme-author`` - a portrait, a role
line and a bio. It does not render the person's own name; that goes through
the shared content-element heading like every other element, so
``.theme-author`` sits below it rather than repeating it.

Known gaps
==========

This theme ships no icon assets and no icon component. :guilabel:`Social
links` therefore renders the same text-label list as :guilabel:`Link list` -
``link_label`` stands in for a platform icon, not a glyph approximating one.

``.theme-hero__eyebrow`` exists in the hero component's stylesheet, but no
hero variant's TCA offers an eyebrow field to back it - omitted rather than
invented.

A field was removed
====================

:guilabel:`Testimonial` originally exposed the core :guilabel:`Images` field,
the same way :guilabel:`Hero` and :guilabel:`Author` do. The quote component
it renders through has no media slot at all, so a filled-in image would never
have appeared on the page - an editor attaches a portrait and the work is
silently gone. The field was removed from the form rather than left inert.

Impact
======

An installation using the theme can create all ten elements from its own
wizard group and gets working output for every one of them.
:file:`Tests/Functional/ThemeContentElementRenderingTest.php` renders a page
carrying one of each and asserts every element reaches the content-element
wrapper, that the inline-relation based elements actually list their
children (not merely an empty, correct-looking wrapper), that inline children
keep the order an editor gave them, and that the button variant and the
``link`` field resolve to a real, followable URL rather than a raw
:typoscript:`t3://` reference.

See :file:`docs/architecture/content-elements.md` in the developer
documentation for the full CType table, the schema derivation details, and
the reasoning behind each decision summarised above.
