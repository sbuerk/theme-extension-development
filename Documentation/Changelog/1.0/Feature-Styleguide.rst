..  include:: /Includes.rst.txt

..  _feature-styleguide:

==========================
Feature: A styleguide page
==========================

Description
===========

The extension now ships a **styleguide**: one page that renders every component
of the :ref:`component library <feature-component-library>` from its own Fluid
templates, so the whole theme can be looked at in one place, in the appearance
and colour palette currently selected.

The seeded demo tree carries it at ``/styleguide``
(:ref:`feature-seeded-showcase-tree`), reachable by URL and hidden from every
menu. It is set to :guilabel:`Page not enabled in menus` rather than disabled: a
disabled page answers 404 in the frontend and needs a backend preview link,
which defeats the point of a page that exists to be opened.

Seven sections, each its own partial:

..  list-table::
    :header-rows: 1

    *   -   Partial
        -   Section
        -   What it shows
    *   -   :file:`Styleguide/Tokens.html`
        -   Tokens
        -   All 27 colour tokens as swatches, plus the type scale, weight and
            family, the spacing scale, radius, shadow and the focus ring.
    *   -   :file:`Styleguide/Typography.html`
        -   Typography
        -   The element baseline every tag gets without a class - headings,
            running text, inline elements, lists, quotations, preformatted
            text, rules - and the table component.
    *   -   :file:`Styleguide/Buttons.html`
        -   Buttons
        -   :css:`.theme-button` with every modifier, size and state it ships,
            the button group, and :css:`.theme-badge` in both severities and
            fills.
    *   -   :file:`Styleguide/Boxes.html`
        -   Boxes
        -   Card, teaser, hero, quote, alert, accordion and author, each with
            every modifier its stylesheet defines.
    *   -   :file:`Styleguide/Forms.html`
        -   Forms
        -   The complete form contract: the field wrapper, every input type,
            select and textarea, fieldsets, checkboxes and radios, the
            validation states, disabled and read-only.
    *   -   :file:`Styleguide/Navigation.html`
        -   Navigation
        -   Main and sub navigation, breadcrumb, pagination and the content
            menu.
    *   -   :file:`Styleguide/Media.html`
        -   Media
        -   The gallery in one, two and three columns, and the content element
            wrapper with its outline switch.

The page ignores its own content
================================

Nothing on this page comes from a content element, a record or a data
processor. That is deliberate: a styleguide shows the *contract*, and a
specimen assembled from a record would show whatever that record happened to
contain and would break for reasons that have nothing to do with the component.

Two things implement it. The :guilabel:`Styleguide` backend layout offers a
single column with ``colPos 999``, which no TypoScript object in the extension
reads - anything an editor places there is stored and never rendered, inert
rather than broken. And the template contains no ``f:cObject`` at all, not even
for the main column.

..  note::

    The layout cannot simply declare no column. TYPO3 registers a backend
    layout only when its ``config.backend_layout.`` block is non-empty, and a
    ``rows`` key that is present but empty renders a page module grid with no
    rows and therefore no way to add content at all. One column that nothing
    renders is the outcome that keeps the page module usable.

Overriding a section
====================

Each section is a partial of its own under
:file:`EXT:theme_extension_development/Resources/Private/Partials/Styleguide/`,
so a site package that wants its own forms section overrides
:file:`Styleguide/Forms.html` and keeps the other six. That is the same
fine-grained override the rest of the theme follows.

Specimen copy is literal English and is not routed through
:file:`locallang.xlf`. Specimen text exists to be set in a typeface; it has
nothing to localise, and roughly 150 translated labels would make the specimens
unreadable in the source, which is the one place they have to be readable.

Impact
======

Running :bash:`vendor/bin/typo3 theme:seed` gives a frontend where
``/styleguide`` answers with the whole component library. It is the only place
the design tokens can be seen *resolved* rather than read as values, and
because of that it doubles as a live test of the appearance and palette
switchers: flip either control in the header, and every swatch and every
specimen on the page has to move together. One that does not is a colour that
escaped the token layer.

The :guilabel:`Elements` pages of the demo tree remain complementary rather
than redundant. Those render the same components from real content records, so
they prove the wiring; this page proves the library. A component can break in
one without the other noticing - most of the form contract, for instance, is
reached by no content element at all.

Three fixes come with it
========================

All three were found by putting a second instance of a component on one page,
which nothing before this did:

*   **Only the first main navigation toggle was bound.** The stylesheet
    collapses *every* :css:`.theme-nav-main` whose toggle is not expanded,
    while the script wired up only the first toggle on the page. A second
    navigation - a footer menu repeating the main one - was collapsed below the
    breakpoint by a control that did nothing, with no way left to open it.
    Every toggle is now bound, scoped to the navigation it belongs to.

*   :css:`.theme-form-summary--error` **matched no rule.** It was part of the
    published markup contract and of the documentation but had never been
    declared. The base rule already carries the danger palette, so it looked
    correct; it is now declared explicitly, symmetric with
    :css:`.theme-form-summary--success`.

*   **The alert markup contract showed the wrong role.** It illustrated
    :html:`role="status"` on a :css:`.theme-alert--warning`, which invites
    copying an assertive severity as a polite one. :css:`--info` and
    :css:`--success` take :html:`role="status"`; :css:`--warning` and
    :css:`--danger` take :html:`role="alert"`.
