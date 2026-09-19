..  include:: /Includes.rst.txt

..  _feature-bullet-list-layouts:

====================================
Feature: Layouts for the bullet list
====================================

Description
===========

The :guilabel:`Bullet List` content element renders the list components of
the theme. Its :guilabel:`Layout` field, in the :guilabel:`Appearance` tab, is
offered for this element again and picks the look of an unordered or an
ordered list:

..  list-table::
    :header-rows: 1

    *   -   Layout
        -   Renders
    *   -   :guilabel:`Bullets or numbers`
        -   The list with the markers of the element baseline.
    *   -   :guilabel:`Check marks`
        -   A check mark in front of every item.
    *   -   :guilabel:`Icons`
        -   The icon picked in the new field :guilabel:`Icon` in front of every
            item. The field offers the icons of the theme's icon picker; with
            no icon picked the list keeps its markers.
    *   -   :guilabel:`Inline`
        -   The items on one line that wraps, with a hairline between them.

A definition list is rendered with its terms and descriptions side by side on
a wide screen, stacked on a narrow one, whatever the layout says. See
:ref:`important-definition-list-terms-and-descriptions` for how its lines are
read.

:guilabel:`Layout` stays hidden for every other content element: the theme
renders it for the bullet list only.

Impact
======

Every bullet list renders :html:`<ul class="theme-list">` or
:html:`<ol class="theme-list">`, with the modifier of the chosen layout. A site
package that styles the bare list of a bullet list element gets the component
instead; its own rules for :css:`.theme-content-element--bullets ul` still
apply, and win where they are more specific.

The field :guilabel:`Icon` is the new column :sql:`tt_content.tx_theme_icon`,
declared in :file:`ext_tables.sql`; run the database analyser after the
update.

A site package can relabel or remove the layouts in page TSconfig, below
:typoscript:`TCEFORM.tt_content.layout.types.bullets`.
