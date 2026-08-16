..  include:: /Includes.rst.txt

..  _feature-menu-content-elements:

===============================
Feature: Menu content elements
===============================

Description
===========

The theme now renders the eleven ``menu_*`` content elements
``EXT:frontend`` registers - the last of the classic content element set left
uncovered by :ref:`feature-core-content-elements`:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Rendered
    *   -   :guilabel:`Pages`
        -   A link per page in :guilabel:`Pages`, or the site root's own
            subpages when none are selected.
    *   -   :guilabel:`Subpages`
        -   A link per child of the selected pages, or the current page's own
            children.
    *   -   :guilabel:`Section index`
        -   The same, two levels deep, rooted at the current page when
            :guilabel:`Pages` is empty.
    *   -   :guilabel:`Section index of subpages from selected pages`
        -   :guilabel:`Subpages`, two levels deep.
    *   -   :guilabel:`Sitemap`
        -   The whole site, seven levels down from the site root.
    *   -   :guilabel:`Sitemaps of selected pages`
        -   The same, rooted at the selected pages instead.
    *   -   :guilabel:`Abstracts`
        -   :guilabel:`Subpages`, each link followed by that page's own
            :guilabel:`Abstract` field.
    *   -   :guilabel:`Recently updated pages`
        -   Pages beneath the selection (or the current page), sorted by last
            change, each link followed by that date.
    *   -   :guilabel:`Related pages`
        -   Pages beneath the same entry point sharing a keyword with it.
    *   -   :guilabel:`Categorized pages`
        -   Pages carrying the selected category.
    *   -   :guilabel:`Categorized content`
        -   Content elements carrying the selected category, linked by
            heading and anchor.

No TCA of this extension's own is added for any of them, the same as every
other element :ref:`feature-core-content-elements` covered: all eleven were
already creatable in the backend before this change, on TYPO3 v13.4 and v14
alike, and only rendered TYPO3's own "no rendering definition" notice.

Nine on ``MenuProcessor``, two on a category query
====================================================

Nine of the eleven are the core's :php:`MenuProcessor`, unchanged between
v13.4 and v14.3, configured with a different ``special`` per type -
``list``, ``directory``, ``updated`` or ``keywords`` - and, for two of them,
one extra level. :guilabel:`Abstracts` and :guilabel:`Recently updated
pages` needed no data processor beyond that: :php:`MenuProcessor` already
JSON-encodes the whole page row onto every menu item, so the abstract text
and the last-changed timestamp were already there for the reading.

:guilabel:`Categorized pages` and :guilabel:`Categorized content` select by
category membership, which :php:`MenuProcessor` cannot express at all, and
are built on two different mechanisms on purpose, not by accident:

- :guilabel:`Categorized pages` uses the core's :typoscript:`RECORDS`
  cObject, which can select by category directly.
- :guilabel:`Categorized content` uses
  :php:`TYPO3\CMS\Frontend\DataProcessing\DatabaseQueryProcessor` with an
  explicit join on ``sys_category_record_mm`` instead of :typoscript:`RECORDS`,
  even though :typoscript:`RECORDS` could select the same rows. Rendering
  through :typoscript:`RECORDS` would render every matched content element
  in full, nested inside this one - the wrong shape for a menu, and exposed
  to the same reference-cycle risk documented for :guilabel:`Insert Records`
  in :ref:`feature-core-content-elements`, which TYPO3 v14 does not guard at
  all. Rendering the matched rows as links instead means nothing can nest,
  so the cycle cannot form in the first place - no structural break was
  needed here the way one was added for :guilabel:`Insert Records`.

..  note::

    :php:`DatabaseQueryProcessor` wraps every row as
    :php:`['data' => $record]`, the same shape a content element's own
    record has in its template. The :guilabel:`Categorized content` template
    reads ``item.data.header``, not ``item.header`` - the latter resolves to
    nothing and renders an empty link rather than failing.

Markup
======

A single new component, ``.theme-content-menu``, is shared by all eleven
elements - a list of links, optionally carrying a date or an abstract line,
nested one level for :guilabel:`Sitemap`'s tree. It is deliberately not the
existing sub-navigation component: a ``menu_*`` element is authored content
in the content column, not section-scoped site chrome, and reusing the
navigation component would pull navigation styling into content rendering.
See :file:`docs/development/component-library.md` for the markup contract.

Known gap
=========

Historical ``fluid_styled_content`` additionally embedded each listed page's
own content elements flagged "section index" into :guilabel:`Section index`
and :guilabel:`Section index of subpages from selected pages`, linked by
anchor. **That is not implemented here.** A second menu level - the listed
pages' own children - stands in for it instead. A site package that needs
anchor-level section navigation has to add it itself.

Impact
======

An installation using the theme no longer renders the TYPO3 "no rendering
definition" notice for any ``menu_*`` element.
:file:`Tests/Functional/CoreContentElementRenderingTest.php` sweeps all
eleven together with every other covered ``CType`` and fails if any of them
regresses to the notice; two further assertions render past the wrapper to
confirm a menu actually lists what it should, and that the two categorized
elements actually select by category rather than rendering a correct but
empty wrapper.

The markup is generated from the templates below
:file:`Resources/Private/Templates/ContentElements/`, redirected together
with every other template through the Fluid path constants under
``theme.`` - see :ref:`configuration`. See
:file:`docs/architecture/content-elements.md` in the developer documentation
for the full ``special``/level table, the category-query reasoning, and the
known gap above.
