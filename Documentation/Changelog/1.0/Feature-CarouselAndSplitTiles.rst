..  include:: /Includes.rst.txt

..  _feature-carousel-and-split-tiles:

=========================================
Feature: Carousel and split tiles
=========================================

Description
===========

Two content elements join the :guilabel:`Theme` group, both built on the shared
list of items the card group and the teaser list already use.

Carousel
--------

:guilabel:`Carousel` shows slides in one track that scrolls sideways and snaps
each slide into place. A slide has an image, a heading, a text and a link, and
:guilabel:`Caption` decides where its words sit against its picture:

..  list-table::
    :header-rows: 1

    *   -   Caption
        -   Renders
    *   -   :guilabel:`Below the image`
        -   The default: the words under the picture, on the surface fill.
    *   -   :guilabel:`Above the image`
        -   The words before the picture.
    *   -   :guilabel:`Over the foot of the image`
        -   The words over the bottom of the picture on a wide screen, and
            below it on a narrow one, where they would cover what they caption.
            A slide with **no picture** puts them below as well: there is
            nothing to lie over, and a caption laid over nothing would be
            clipped away with the text in it.

..  important::

    **Nothing moves on its own, and there is no setting that makes it.** A
    carousel that advanced by itself would have to carry a control that stops
    it (WCAG 2.2.2, *Pause, Stop, Hide*); not starting is the simpler way to
    satisfy that rule, and it means no timer ever races a reader who is halfway
    through a caption.

The element is usable with JavaScript switched off, which is the property the
whole design is arranged around. The track is an ordinary scroll container, so
it answers touch, trackpad, wheel and scroll bar, and the arrow keys once it
has focus. The indicators below it are **links to the slides**, so pressing one
moves the reader to that slide with no script involved. Only the previous and
next buttons need JavaScript, and they are not shown until it has run — there
is never a visible control that does nothing.

Impact
======

The element appears in the :guilabel:`Theme` group of the
:guilabel:`New content element` wizard and renders
:html:`<section class="theme-carousel">`. The component :css:`.theme-carousel`
is new in the library and is shown on the styleguide page.

The shared list item record gains a :guilabel:`Caption` field, offered only in
the relation that renders it. No existing element changes.

A site package that styles the theme's components itself gets one more to
style. The carousel's buttons stay hidden until the theme's script sets
:html:`data-theme-carousel-bound` on the carousel — a site package replacing
the script has to set that attribute, or the buttons stay hidden.
