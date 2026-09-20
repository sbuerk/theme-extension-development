..  include:: /Includes.rst.txt

..  _feature-column-page-layouts:

============================================
Feature: Two and three column page layouts
============================================

Description
===========

Three backend layouts place content side by side instead of in one stack. They
are selected on a page like every other layout — see
:ref:`feature-backend-layouts` for how a layout reaches the template — and they
are inherited by sub pages through :sql:`backend_layout_next_level` in exactly
the same way.

..  list-table::
    :header-rows: 1

    *   -   Layout
        -   Template
        -   Content areas
    *   -   :guilabel:`Two columns (50/50)`
        -   :file:`Page/TwoColumns.html`
        -   stage (2), main (0), second column (3)
    *   -   :guilabel:`Two columns (66/33)`
        -   :file:`Page/TwoColumnsWide.html`
        -   stage (2), main (0), second column (3)
    *   -   :guilabel:`Three columns`
        -   :file:`Page/ThreeColumns.html`
        -   stage (2), main (0), second column (3), third column (4)

Two new content areas, and only two: the second column is :sql:`colPos` 3 and
the third is :sql:`colPos` 4 in **all three** layouts. Moving a page between
them therefore moves no content. A page switched from :guilabel:`Three columns`
to :guilabel:`Two columns` keeps its first and second column where they are and
leaves the third unrendered — the page module shows it under
:guilabel:`unused`, from where an editor can recover it. Content silently
rendered in a different column could not be recovered, because nothing would
report it.

Each column is a TypoScript object of its own, like the columns the theme
already had, so a site package can replace one without touching the rest:

..  code-block:: typoscript

    lib.content.secondary = CONTENT
    lib.content.tertiary = CONTENT

The column layouts carry **no footer columns**. Footer content slides down the
rootline from the site root, which uses the :guilabel:`Start page` layout, so
repeating those five columns here would offer an editor cells with no reason to
be filled.

Impact
======

The grid is a :html:`.theme-page__columns` element **inside**
:html:`.theme-page__main`, not :html:`.theme-page__main` itself: the breadcrumb
and the stage render into :html:`__main` ahead of the columns, and a grid on
that element would turn both of them into columns. Each slot is wrapped in a
:html:`.theme-page__column`, because a content area renders one element per
record straight into its parent and every one of them would otherwise be a
column of its own.

..  code-block:: html

    <main class="theme-page__main" id="content">
        <div class="theme-page__columns theme-page__columns--halves">
            <div class="theme-page__column">…</div>
            <div class="theme-page__column">…</div>
        </div>
    </main>

The modifier sizes the tracks — :html:`--halves`, :html:`--wide-start`,
:html:`--thirds` — while the number of columns is the number of children the
template wrote. Every track is :css:`minmax(0, …)`, so a wide child such as a
table, a code block or a long unbroken URL shrinks its track instead of pushing
the row past the edge of the page.

Below 768 pixels every column layout is one stacked column: two columns of body
text in that width are two columns of five words.

Demo pages
==========

The seed set ``theme-demo`` gains a :guilabel:`Layouts` section with one page
per layout, each carrying a labelled box in every column it declares — so the
rendered page says which :sql:`colPos` is which without opening the page
module. The boxes are ordinary content elements: the dashed outline and the
type chip around them are the theme's own development affordance, which the
display settings in the header switch off.
