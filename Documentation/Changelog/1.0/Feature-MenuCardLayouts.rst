..  include:: /Includes.rst.txt

..  _feature-menu-card-layouts:

==============================================
Feature: Cards and thumbnails for page menus
==============================================

Description
===========

The two page menus :guilabel:`Menu of selected pages` and
:guilabel:`Menu of subpages of selected pages` gain a :guilabel:`Layout`:

..  list-table::
    :header-rows: 1

    *   -   Layout
        -   Renders
    *   -   :guilabel:`List`
        -   The list of links it rendered before, and the default.
    *   -   :guilabel:`Cards`
        -   One card per page: its first page media, its title and its
            abstract. The whole card is the link.
    *   -   :guilabel:`Thumbnails`
        -   One small card per page: its first page media and its title.

The image of a card is the first file of the page's :guilabel:`Media` field
(:guilabel:`Resources` tab of the page properties), with the alternative text
of that file reference; a page without media gets a card without an image.

The field is offered for these two menus only. Every other menu, and every
other content type the field does nothing for, keeps it out of the form.

Impact
======

No new column. The page media is fetched only for a menu in one of the two
new layouts, so a menu in the list layout costs what it did before. A record
of another menu type that still carries a layout value from an earlier
rendering renders its list as before.

A site package overriding :file:`Templates/ContentElements/MenuPages.html`
or :file:`MenuSubpages.html` finds the cards in the new partial
:file:`Partials/ContentElement/MenuCards.html`.
