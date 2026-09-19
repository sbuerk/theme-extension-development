..  include:: /Includes.rst.txt

..  _feature-quotation-styles:

=================================================
Feature: Pull quote and centred testimonial style
=================================================

Description
===========

The :guilabel:`Testimonial` content element has a new field :guilabel:`Style`:

..  list-table::
    :header-rows: 1

    *   -   Style
        -   Renders
    *   -   :guilabel:`A rule at the start of the quotation`
        -   The testimonial as before. The default.
    *   -   :guilabel:`Pull quote`
        -   The quotation larger and bolder, between a rule above and a rule
            below, with a quotation mark.
    *   -   :guilabel:`Centred`
        -   The quotation and its attribution centred below a quotation mark,
            no wider than the reading measure.

The quotation mark is an icon of the theme's icon set, hidden from screen
readers - the quotation itself is marked up as one.

Impact
======

Existing testimonials keep their look. The styles are the modifiers
:css:`--pull` and :css:`--centred` of :css:`.theme-quote`, and the quotation
mark is :html:`<span class="theme-quote__mark" aria-hidden="true">` before the
:html:`<blockquote>`.

The testimonial still has no image: the portrait waits for a media slot in the
quotation component.
