..  include:: /Includes.rst.txt

..  _feature-navigation:

====================
Feature: Navigation
====================

Description
===========

The theme now renders its **three navigations**: a main menu, a left-hand sub
navigation for the current section, and a breadcrumb trail. All three are the
same core :php:`MenuProcessor`, configured three different ways, in
:file:`Configuration/TypoScript/Navigation.typoscript`.

..  list-table::
    :header-rows: 1

    *   -   Navigation
        -   Source
        -   Rendered on
    *   -   Main menu
        -   Site root, two levels
        -   every page, in the header
    *   -   Sub navigation
        -   The current page's **section**, two levels
        -   the :guilabel:`Content page with sidebar` layout only
    *   -   Breadcrumb
        -   The rootline, root to current page
        -   :guilabel:`Content page` and :guilabel:`Content page with sidebar`

The sub navigation fixes a rootline position, not the current page
=====================================================================

The sub navigation is configured against the current page's **first-level
ancestor**, not the current page itself, using
:typoscript:`special.value.data = leveluid:1` rather than
:typoscript:`entryLevel`.

The difference matters because it is easy to get backwards, and the wrong
choice looks correct in the one place it is checked first. :typoscript:`entryLevel`
is relative to the current page's *depth* - the same value resolves to a
*different* ancestor on a second-level page than on a third-level page.
:typoscript:`leveluid:1` instead indexes the current page's rootline directly:
index `0` is always the site root and index `1` is always the first page below
it, on every page regardless of how deep it is.

That is what keeps the sidebar showing the same section three levels down as
it does on the section's own landing page, rather than emptying out
underneath it. A sub navigation built from the current page's own children
looks entirely correct on a first-level page - there, the section root *is*
the current page - and only fails on the pages below it, exactly where a
reader needs the navigation most. :file:`Tests/Functional/NavigationRenderingTest.php`
therefore runs against a fixture three levels deep and asserts the same
section content at all three, rather than stopping at the depth where the bug
would already be invisible.

Placement follows the backend layout
=====================================

Each navigation is its own numbered TypoScript key
(:typoscript:`page.10.dataProcessing.10/.20/.30`), so a site package can
remove exactly one without touching the others:

..  code-block:: typoscript

    page.10.dataProcessing.20 >

The sub navigation is rendered only on the :guilabel:`Content page with
sidebar` layout - the one layout with a left column, per
:ref:`Backend layouts decide the page template <feature-backend-layouts>`.
Choosing that layout in the page module is how an editor asks for the left
navigation; there is no separate flag. The breadcrumb renders on
:guilabel:`Content page` and :guilabel:`Content page with sidebar`, and
deliberately not on :guilabel:`Start page` (no trail is worth showing there)
or :guilabel:`Default` (the bare layout, which is also the one layout that
already renders the page title itself).

Accessibility
==============

*   Every :html:`<nav>` carries a translated :html:`aria-label` - three
    navigation landmarks on one page are indistinguishable to a screen reader
    without one.
*   The current page carries :html:`aria-current="page"`, and the stylesheet
    styles that attribute directly rather than a modifier class kept in sync
    with it - the visual state and the announced state cannot disagree.
*   The breadcrumb's last item is plain text with :html:`aria-current="page"`
    on the list item, **not a link**: the destination of a breadcrumb is not
    somewhere it still points to.
*   The breadcrumb separator is generated content on a :css:`::before`, never
    a character in the markup, so it is not part of the accessible name and is
    not announced.

Impact
======

The main menu's toggle button ships without its script. The markup - the
:html:`<button aria-expanded="false" aria-controls="nav-main">` and the
matching :html:`id` on the list - is in place, but the script that flips
:html:`aria-expanded` and the :html:`data-js` marker the stylesheet gates
collapsing behind both arrive with the appearance switcher. Until then the
button is inert and hidden by CSS, and the menu is simply always expanded -
the intended, working state without JavaScript, not a degraded one. See
:ref:`Feature: Component library <feature-component-library>` for the
:html:`data-js` switch itself.

..  note::

    There is no language navigation. Nothing in this release asks for one, and
    the theme has no multi-language story yet.
