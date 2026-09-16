..  include:: /Includes.rst.txt

..  _feature-card-wall:

==========================================
Feature: A wall layout for the card group
==========================================

Description
===========

The :guilabel:`Card group` content element offers a third arrangement beside
the grid and the scroller: :guilabel:`Wall: columns the cards stack into,
filled top to bottom`.

In the grid the cards sit in rows, and every card in a row is as tall as the
tallest one in it, which leaves a ragged edge at the foot of the group when the
texts are of different lengths. In the wall each card keeps its own height and
the next one starts where it ends, so the columns fill evenly.

:guilabel:`Columns` works as it does for the grid: it is the most columns the
wall takes, fewer where there is no room for them and one on a phone.

..  important::

    **A wall fills column by column, not row by row.** Six cards in three
    columns are laid out first and second down the left column, third and
    fourth down the middle one, fifth and sixth down the right one — so read
    across the top of the group they are the first, the third and the fifth.
    The same six in the grid read first, second, third across the top.

    Ordering the cards in the backend therefore orders them **down the
    columns**. That is inherent to the layout and cannot be configured away.

    It is why only the card group offers a wall. An element whose items are a
    sequence — the :guilabel:`Timeline`, the :guilabel:`Steps` — does not, and
    will not: there the order the eye reads is part of the meaning. The cards
    of a card group are an unordered set, so nothing is lost.

    The order in the page source is unchanged in every case, so a screen reader
    announces the cards, and the keyboard reaches them, in exactly the order
    the editor arranged them.

Impact
======

A card group with the new layout renders
:html:`<ul class="theme-card-grid theme-card-grid--wall">` with the column
modifier it already carried. The modifier is new in the component library, on
:css:`.theme-card-grid`, and joins :css:`--wide`, :css:`--narrow`,
:css:`--columns-2` to :css:`--columns-4` and :css:`--scroller`; all of those
are unchanged, and :css:`--wide` and :css:`--narrow` move the column width of a
wall exactly as they move it for a grid.

Existing card groups are untouched: the grid remains the default, and no stored
record changes meaning.

A site package can relabel or remove the arrangements in page TSconfig, below
:typoscript:`TCEFORM.tt_content.layout.types.theme_card_group`.
