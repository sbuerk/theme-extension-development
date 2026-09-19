..  include:: /Includes.rst.txt

..  _feature-card-group-timeline-teaser-list:

==================================================
Feature: Card group, timeline and teaser list
==================================================

Description
===========

Three more content elements of the theme's own, in the :guilabel:`Theme` group
of the :guilabel:`Create new content element` wizard. All three take their
items from the inline list the other list based elements of the theme use:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Renders
    *   -   :guilabel:`Card group`
        -   Cards with an image, a title, a subtitle, a text and a button, in
            a grid or in one row that scrolls sideways.
    *   -   :guilabel:`Timeline`
        -   Dated entries on a line, oldest or newest first.
    *   -   :guilabel:`Teaser list`
        -   Rows with a small round image, a title, a text, a date and meta
            data. The whole row is one link.

Card group
----------

:guilabel:`Layout` offers :guilabel:`Grid` and
:guilabel:`Scroller: one row that scrolls sideways`, and
:guilabel:`Columns` the most cards side by side: two, three or four. A narrower
screen shows fewer, a phone one; the scroller keeps the number as the cards in
view and always shows the edge of the next one. The scroller needs no
JavaScript: it scrolls by touch, trackpad, wheel and scroll bar, and with the
arrow keys once it has focus - it is a region with a tab stop, named after the
heading of the element.

Each card has a link with a label, an icon and a :guilabel:`Link style` - the
same four styles the link of a hero or a teaser offers - and renders it as a
button.

Timeline
--------

Every entry has a date, a title, a text, and an optional icon and image;
an entry with an icon shows it on the line in place of the dot. The entries are
sorted by their date - :guilabel:`Oldest first` or :guilabel:`Newest first`
in :guilabel:`Order` - and the order of the list in the backend decides only
between entries of the same date. The date is written out in the language of
the site, "12 January 2026" in English, and marked up as a machine readable
:html:`<time>`.

Teaser list
-----------

Every row has a title, which is its link, a text, an image, a date and a line
of meta data such as a reading time. A row without a link is shown without
one.

Impact
======

Integrators get three new content types to grant to editor groups:
``theme_card_group``, ``theme_timeline`` and ``theme_teaser_list``. The
database analyzer adds one column to ``tt_content``,
``tx_theme_sort_direction``, and three to ``tx_theme_list_item``: ``date``,
``meta`` and ``link_variant``. The card group arranges its cards by the
``tx_theme_columns`` of the features, and shows the ``subheader`` of an item;
the timeline shows its ``icon``. The field :guilabel:`Layout` of the :guilabel:`Appearance`
tab stays disabled for every other content type; the card group shows it on
its own tab.

A site package overriding :file:`Templates/ContentElements/` finds the three
templates there as :file:`ThemeCardGroup.html`, :file:`ThemeTimeline.html`
and :file:`ThemeTeaserList.html`. The stylesheet gains the timeline and the
list group component, and the card grid its column counts and the scroller -
see :ref:`components`.
