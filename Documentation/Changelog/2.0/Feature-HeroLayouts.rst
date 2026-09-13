..  include:: /Includes.rst.txt

..  _feature-hero-layouts:

==============================================
Feature: Layouts and an eyebrow for the heroes
==============================================

Description
===========

The three hero content elements - :guilabel:`Hero`, :guilabel:`Hero, small`
and :guilabel:`Hero, text only` - have two new fields above their header.

:guilabel:`Eyebrow` is a short label above the title, such as a category or a
date.

:guilabel:`Layout` arranges the text and the image:

..  list-table::
    :header-rows: 1

    *   -   Layout
        -   Renders
        -   Offered on
    *   -   :guilabel:`Image beside the text, at the start`
        -   The hero as before. The default.
        -   All three
    *   -   :guilabel:`Image beside the text, at the end`
        -   The image at the end of the row on a wide screen.
        -   Hero; hero, small
    *   -   :guilabel:`Centred`
        -   Title, text, links and image centred.
        -   All three
    *   -   :guilabel:`Screenshot`
        -   Centred, with the image below the text, cut off by the bottom edge
            of the hero - for a screenshot of an application.
        -   Hero
    *   -   :guilabel:`Cropped`
        -   The image at the end of the row, cut off by the end and the bottom
            edges of the hero.
        -   Hero

A layout that arranges an image renders like the default without one;
:guilabel:`Screenshot` renders centred.

Impact
======

Existing heroes keep their look: the default layout is the one they always
had. The layouts are the modifiers :css:`--image-end`, :css:`--centred`,
:css:`--screenshot` and :css:`--bordered` of :css:`.theme-hero`, and the
eyebrow is :html:`<p class="theme-hero__eyebrow">` before the title.

A site package can remove layouts per hero in page TSconfig, for example
:typoscript:`TCEFORM.tt_content.tx_theme_hero_layout.types.theme_hero.removeItems = bordered`.
