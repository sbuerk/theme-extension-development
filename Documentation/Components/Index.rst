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
        -   Accordion, alert, author, badge, card, code block, description
            list - with a divided variant for key and value - divider, feature and
            feature grid, figure, file list, hero, list, media object, panel,
            quote, stats, steps, table, teaser, and the text
            roles display, lead and eyebrow
    *   -   Data display
        -   Avatar and avatar group, tag list, progress bar, meter
    *   -   Actions
        -   Button - with a link and an icon variant, pressed and busy states,
            and an attached button group - and the close button
    *   -   Icons
        -   The solid icons of Font Awesome Free, inline, see :ref:`icons`
    *   -   Interactive
        -   Tabs, dialog, tooltip
    *   -   Forms
        -   Controls, field, input group, choice group, validation
    *   -   Navigation and page
        -   Main and sub navigation, breadcrumb, pagination, content menu,
            skip link, gallery, the content element wrapper, site header and
            site footer

..  _icons:

Icons
=====

The theme ships the complete solid style of Font Awesome Free 7.3.1 - 2001
icons - below :file:`Resources/Public/Icons/FontAwesome/Solid/`, and draws every
icon of its own from it. A template renders one with the ViewHelper
:html:`<theme:icon>`, by the file name of the icon without :file:`.svg`:

..  code-block:: html

    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
          xmlns:theme="http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <theme:icon name="circle-info" />
    <theme:icon name="circle-info" label="Information" />

The icon is inlined as SVG: no webfont, no CDN and no request is involved. It is
as large as the text around it and takes the colour of that text. Without
:html:`label` it is decoration and hidden from screen readers - the right choice
next to text or inside a button that has a name of its own. With
:html:`label` it is an image with that name. An icon name that does not exist
stops the rendering with an exception rather than leaving a gap.

The optional :html:`class` argument adds classes to :css:`theme-icon`. A
component that gives icons a fixed size sets the custom property
:css:`--theme-icon-size` on the element around them.

The icons are licensed under CC BY 4.0; the npm package they come from declares
``CC-BY-4.0 AND OFL-1.1 AND MIT``, of which only the icons ship here. The
licence of the package and an attribution ship next to them, in
:file:`Resources/Public/Icons/FontAwesome/LICENSE.txt` and
:file:`Resources/Public/Icons/FontAwesome/ATTRIBUTION.txt`. Every icon file
keeps the attribution comment it comes with, and so does every icon on a
rendered page. The extension itself stays GPL-2.0-or-later.

In the backend, an editor picks an icon from a select list grouped by the
categories of Font Awesome, with a grid of the icons under it; page TSconfig
:typoscript:`keepItems` narrows the list per field - see
:ref:`feature-icon-picker`.

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
                <button class="theme-close" value="cancel" aria-label="Close"><theme:icon name="xmark" /></button>
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
            title. Three sizes: :css:`--1`, :css:`--2` - the size of the bare
            class - and :css:`--3`. All three are the size of a page title on a
            phone and grow to 68, 54 and 43 pixels on a wide window.
    *   -   :css:`.theme-lead`
        -   The paragraph that opens a page.
    *   -   :css:`.theme-eyebrow`
        -   The short label above a heading.

Rich text needs no class for the rest of the typography:

*   A :html:`<small>` inside a heading is a secondary line, set smaller and
    lighter than the heading: :html:`<h2>Release notes <small>for 2.0</small></h2>`.
*   Headings balance their lines when they wrap, paragraphs avoid a single word
    on the last line.
*   A key combination is a :html:`<kbd>` holding one :html:`<kbd>` per key;
    only the keys are framed.
*   Quotation marks follow the language of the quotation: English by default,
    the German and the French pairs for :html:`lang="de"` and
    :html:`lang="fr"`.
*   German text is hyphenated, where the browser has a German dictionary.
    Other languages are not.
*   Text set right to left with :html:`dir="rtl"` mirrors its indents, list
    markers and quotation rule.

Layouts of content elements
===========================

Several content elements offer a choice of how they look. Each value maps onto
a modifier of the component the element renders, and a value the theme does
not know renders the element without one.

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Field
        -   Values
    *   -   :guilabel:`Text`
        -   :guilabel:`Layout`
        -   :guilabel:`Running text`; :guilabel:`Columns` - :css:`.theme-text--columns`,
            two columns of at least 30 characters, one on a phone - see
            :ref:`feature-text-columns`
    *   -   :guilabel:`File Links`
        -   :guilabel:`Display file/icon/thumbnail`
        -   The file names alone - :css:`.theme-file-list`; with the icon of
            the file type - :css:`--icon`; with a thumbnail, or the icon where
            there is none - :css:`--preview`. See
            :ref:`feature-file-list-display-types`
    *   -   :guilabel:`Hero`, :guilabel:`Hero, small`,
            :guilabel:`Hero, text only`
        -   :guilabel:`Layout`, :guilabel:`Eyebrow`
        -   The image at the start - the default; at the end -
            :css:`.theme-hero--image-end`; centred - :css:`--centred`; a
            screenshot cut off at the bottom - :css:`--screenshot`; cropped at
            two edges - :css:`--bordered`. The eyebrow is
            :css:`.theme-hero__eyebrow`. See :ref:`feature-hero-layouts`
    *   -   :guilabel:`Testimonial`
        -   :guilabel:`Style`
        -   A rule at the start - the default; a pull quote -
            :css:`.theme-quote--pull`; centred - :css:`--centred`, both with a
            quotation mark. See :ref:`feature-quotation-styles`

Tables
======

The :guilabel:`Table class` of the table content element chooses the look of
the table. The core offers :guilabel:`Striped` and :guilabel:`Bordered`; the
theme adds the rest of what its table component draws:

..  list-table::
    :header-rows: 1

    *   -   Table class
        -   Look
    *   -   Default
        -   Rows separated by a hairline, the header row on a tint
    *   -   :css:`striped`
        -   Every other row on the tint
    *   -   :css:`bordered`
        -   A hairline between the columns as well
    *   -   :css:`striped-columns`
        -   Every other column on the tint
    *   -   :css:`hover`
        -   The row under the pointer is highlighted
    *   -   :css:`borderless`
        -   No rules at all
    *   -   :css:`compact`
        -   Half the cell padding
    *   -   :css:`sticky-header`
        -   The header row stays in view while a tall table scrolls

Each value becomes the modifier :css:`.theme-table--<value>`. A site package
adds its own value with page TSconfig -
:typoscript:`TCEFORM.tt_content.table_class.addItems` - and styles the class
it produces; :typoscript:`removeItems` hides one of the theme's.

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
