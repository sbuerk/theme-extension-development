..  include:: /Includes.rst.txt

..  _feature-backend-layouts:

===================================================
Feature: Backend layouts decide the page template
===================================================

Description
===========

The theme ships **five backend layouts**, and the layout selected on a page now
decides which template renders it. Until now every page rendered the same file.

..  list-table::
    :header-rows: 1

    *   -   Layout
        -   Template
        -   Content areas
    *   -   Default
        -   :file:`Page/Default.html`
        -   `main`
    *   -   Content page
        -   :file:`Page/Content.html`
        -   `stage`, `main`, four footer columns, `footermeta`
    *   -   Content page with sidebar
        -   :file:`Page/ContentSidebar.html`
        -   the same, plus `sidebar` beside `main`
    *   -   Start page
        -   :file:`Page/Start.html`
        -   the same as Content page
    *   -   Styleguide
        -   :file:`Page/Styleguide.html`
        -   none - it renders components directly

The column numbers are the ones :composer:`typo3/theme-camino` uses, so content
is portable between the two themes.

Registration
============

The layouts are **page TSconfig**, in :file:`Configuration/page.tsconfig`, which
TYPO3 auto-loads from every package since v12.0 (:issue:`96614`). No
registration call and no database record is involved, and it applies whether the
theme is delivered through its site set or through the classic
:sql:`sys_template` static include - the alternatives would each only work for
one of those.

Overriding one layout means overriding one file:

..  code-block:: typoscript

    mod.web_layout.BackendLayouts.content.config.backend_layout.rows.2.columns.1.colPos = 5

Content areas
=============

Each column is its own TypoScript object, so a site package can replace one
without touching the rest:

..  code-block:: html

    <f:cObject typoscriptObjectPath="lib.content.main" />

The four footer columns and the footer meta row carry :typoscript:`slide = -1`.
Footer content is therefore edited **once on the site root** and inherited by
every page below it - what :composer:`typo3/theme-camino` gets from
:typoscript:`slideMode = slide`, without depending on
:composer:`typo3/cms-fluid-styled-content`.

Impact
======

..  note::

    :typoscript:`FLUIDTEMPLATE` is used rather than :typoscript:`PAGEVIEW`,
    deliberately. :typoscript:`PAGEVIEW` exists since v13.1
    (:issue:`103504`), but the content area layer it is normally used with -
    :php:`ContentAreaCollection` and :html:`<f:render.contentArea>` - arrived in
    v14.2 (:issue:`104974`) and does not exist on v13.4 at all, so templates
    written against it do not compile there. :typoscript:`FLUIDTEMPLATE` is not
    deprecated on either version; the v14.2 changelog calls
    :typoscript:`PAGEVIEW` "a powerful alternative", explicitly not a
    replacement.

The template is resolved with :typoscript:`data = pagelayout`, **not**
:typoscript:`field = backend_layout`. The getter resolves through
:php:`PageLayoutResolver`, which falls back to the first ancestor's
:sql:`backend_layout_next_level` when a page carries no layout of its own.
Reading the field directly would ignore that, and every sub-page of a configured
parent would silently render the wrong template.

Two edges of that inheritance are worth knowing, and both are covered by
:file:`Tests/Functional/BackendLayoutRenderingTest.php`:

*   A page's own :sql:`backend_layout_next_level` applies to its children and
    **never to itself** - the resolver removes the current page from the
    rootline before searching.
*   Choosing TYPO3's built-in :guilabel:`[None]` option in the page properties
    resolves to the literal identifier `none`, which is not empty. It is mapped
    back to `default`; without that it would ask for a
    :file:`Page/None.html` that no theme ships and end the request in an
    exception.

Every column declares an :typoscript:`identifier` as well as a
:typoscript:`name` and a :typoscript:`colPos`. TYPO3 v14 raises a deprecation
for a column without one and will throw in v15; v13 ignores it, so one spelling
serves both versions.

..  note::

    :html:`.theme-page` carries a :html:`data-theme-page-layout` attribute
    naming the layout the page resolved to. Which template rendered a page -
    and in particular whether it was inherited - is otherwise invisible in the
    frontend, and inheritance is exactly the part that goes wrong quietly.

    Only :file:`Page/Default.html` renders the page title itself. It has no
    stage slot, so nothing editorial is guaranteed to carry a heading and an
    empty page would render nothing at all. The layouts that do have a stage
    leave the heading to the content placed in it, so that those pages do not
    end up with two first-level headings.
