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

Split tiles
-----------

:guilabel:`Split tiles` is a column of featurettes: each tile an image on one
side and a heading, a text and a link on the other, with the side changing from
tile to tile down the column. Nothing is set per tile to achieve that — the
theme alternates the sides itself, so inserting, deleting or reordering a tile
keeps the rhythm correct. :guilabel:`Layout` chooses which foot the alternation
starts on, so a second group of tiles further down a page can carry the rhythm
on instead of repeating it.

Every tile carries its own :guilabel:`Tone`, the same four the call to action
offers — the surface fill, an accent tint, the other appearance, and a
placeholder with no fill and a dashed frame for a tile standing in for content
that does not exist yet.

Impact
======

Both elements appear in the :guilabel:`Theme` group of the
:guilabel:`New content element` wizard. They render
:html:`<section class="theme-carousel">` and
:html:`<ul class="theme-split-tiles">` respectively; the components are
:css:`.theme-carousel` and :css:`.theme-split-tiles`, both new in the library
and both shown on the styleguide page.

The shared list item record gains two fields, :guilabel:`Caption` and
:guilabel:`Tone`, each offered only in the relation that renders it. No
existing element changes.

A site package that styles the theme's components itself gets two more to
style. The alternation of the tiles is :css:`:nth-child(even)` on
:css:`.theme-split-tiles__item` and the carousel's buttons are hidden until the
theme's script sets :html:`data-theme-carousel-bound` on the carousel — a site
package replacing the script has to set that attribute, or the buttons stay
hidden.
