..  include:: /Includes.rst.txt

..  _components:

==========
Components
==========

The stylesheet the theme ships is a component library. Every class carries a
:html:`theme-` prefix, and every component states the markup it expects in the
header comment of its own SCSS file below :file:`Resources/Private/Scss/`. That
comment is the contract a template is written against; this page is the
overview. The :file:`/styleguide` page of the seeded demo tree renders every
component, in the appearance and palette currently selected - see
:ref:`feature-styleguide`.

Every component reads its values through custom properties with a fallback, so
a site package re-themes one component or all of them from its own CSS, without
rebuilding the SCSS - see :ref:`feature-component-library`.

Every state the library draws in colour - a selected tab, a pressed toggle -
also survives forced colours, the high contrast themes of the operating system,
where it is drawn in the system highlight colour instead.

What the library contains
=========================

..  list-table::
    :header-rows: 1

    *   -   Group
        -   Components
    *   -   Content
        -   Accordion, alert, author, badge, card, hero, panel, quote, table,
            teaser, and the text roles display, lead and eyebrow
    *   -   Actions
        -   Button - with a link and an icon variant, pressed and busy states,
            and an attached button group - and the close button
    *   -   Interactive
        -   Tabs, dialog, tooltip
    *   -   Forms
        -   Controls, field, input group, choice group, validation
    *   -   Navigation and page
        -   Main and sub navigation, breadcrumb, pagination, content menu,
            skip link, gallery, the content element wrapper, site header and
            site footer

Components that need JavaScript
===============================

Three components need the theme's script,
:file:`Resources/Public/JavaScript/theme.js`, which the page includes as a
module. Each of them stays usable without it - with JavaScript switched off,
and with JavaScript on but the script failing to load. The dialog opener
follows the :html:`data-js` attribute that the theme's inline head script sets
on the :html:`<html>` element before the page is painted, the same marker the
main navigation's toggle depends on. The tabs follow a marker of their own,
:html:`data-theme-tabs-bound`, which only the theme's script sets on the tab
group once it has bound it.

..  list-table::
    :header-rows: 1

    *   -   Component
        -   With JavaScript
        -   Without JavaScript
    *   -   Tabs
        -   The WAI-ARIA tabs pattern: the arrow keys, Home and End move between
            the tabs, one panel is shown at a time.
        -   No tab list. Every panel is shown, each under its own heading, and
            none of them claims to be a tab panel.
    *   -   Dialog
        -   A button carrying :html:`data-theme-dialog-open` opens the
            :html:`<dialog>` of that id as a modal. A click on the backdrop
            closes it, and focus returns to the button.
        -   The button is not shown, and the dialog stays closed.
    *   -   Tooltip
        -   Escape hides the tooltip without moving focus.
        -   Shown on hover and on focus, but Escape does not hide it.

Tabs
----

The first tab is the selected one, and a panel carries nothing but its class
and its id in the markup. Hiding the panels that are not selected is the
script's job, and so are the tab panel role, the label and the tab stop of each
panel, so a page without the script shows every panel as plain content. Each
panel repeats the label of its tab in a :html:`theme-tabs__heading`, which
labels the panel when there are no tabs to do it.

..  code-block:: html

    <div class="theme-tabs">
        <div class="theme-tabs__list" role="tablist" aria-label="Delivery">
            <button class="theme-tabs__tab" type="button" role="tab" id="t-set"
                    aria-selected="true" aria-controls="t-set-panel">Site set</button>
            <button class="theme-tabs__tab" type="button" role="tab" id="t-static"
                    aria-selected="false" aria-controls="t-static-panel" tabindex="-1">Static include</button>
        </div>
        <div class="theme-tabs__panel" id="t-set-panel">
            <h3 class="theme-tabs__heading">Site set</h3>
            …
        </div>
        <div class="theme-tabs__panel" id="t-static-panel">
            <h3 class="theme-tabs__heading">Static include</h3>
            …
        </div>
    </div>

Dialog
------

A native :html:`<dialog>`: the browser keeps focus inside it, makes the page
behind it inert, and closes it on Escape. The :html:`<form method="dialog">`
around its content closes it with no script at all. Because nothing can open it
without JavaScript, a dialog is for content a page can do without - a
confirmation for an action that needs the script anyway.

..  code-block:: html

    <button class="theme-button" type="button" aria-haspopup="dialog"
            data-theme-dialog-open="d-reseed">Reseed</button>

    <dialog class="theme-dialog" id="d-reseed" aria-labelledby="d-reseed-title">
        <form method="dialog">
            <div class="theme-dialog__header">
                <h2 class="theme-dialog__title" id="d-reseed-title">Reseed the instance?</h2>
                <button class="theme-close" value="cancel" aria-label="Close"></button>
            </div>
            <div class="theme-dialog__body">…</div>
            <div class="theme-dialog__footer">
                <button class="theme-button theme-button--ghost" value="cancel">Cancel</button>
                <button class="theme-button theme-button--danger" value="confirm">Reseed</button>
            </div>
        </form>
    </dialog>

Tooltip
-------

A short description of a control that already has a name. The trigger points at
the bubble with :html:`aria-describedby`, which is what a screen reader
announces. The bubble sits above its trigger and is not moved to stay inside
the viewport, so a trigger at the very edge of the viewport is a placement to
avoid.

..  code-block:: html

    <span class="theme-tooltip">
        <button class="theme-button theme-button--ghost theme-button--icon" type="button"
                aria-label="Element outlines" aria-describedby="tt-outline">…</button>
        <span class="theme-tooltip__bubble" role="tooltip" id="tt-outline">…</span>
    </span>

Headings and text roles
=======================

Heading levels four to six have a step of their own. :html:`<h4>` is the body
size at the bold weight; :html:`<h5>` and :html:`<h6>` are smaller, in capitals
and with a wider letter spacing, and :html:`<h6>` is in the secondary text
colour. The titles of the hero and the teaser, which follow the heading level
an editor picks, look the same on every level.

Three text roles are classes rather than elements, so they go on whatever
element the document outline asks for:

..  list-table::
    :header-rows: 1

    *   -   Class
        -   For
    *   -   :css:`.theme-display`
        -   The one heading on a landing page that is set larger than a page
            title.
    *   -   :css:`.theme-lead`
        -   The paragraph that opens a page.
    *   -   :css:`.theme-eyebrow`
        -   The short label above a heading.

Alerts
======

Six kinds, as a modifier of :css:`.theme-alert`. The :html:`role` belongs in
the markup, and it is not the same for every kind:

..  list-table::
    :header-rows: 1

    *   -   Modifier
        -   For
        -   :html:`role`
    *   -   :css:`--info`
        -   Information; also the look of the bare class
        -   :html:`status`
    *   -   :css:`--success`
        -   Something finished
        -   :html:`status`
    *   -   :css:`--warning`
        -   Something needs attention
        -   :html:`alert`
    *   -   :css:`--danger`
        -   Something failed
        -   :html:`alert`
    *   -   :css:`--note`
        -   An aside to the text around it
        -   :html:`note`, or none
    *   -   :css:`--tip`
        -   A recommendation. Its colour follows the selected palette.
        -   :html:`note`, or none

:html:`status` and :html:`alert` are live regions, which announce a change. A
note or a tip does not change, so it is never one.
