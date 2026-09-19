..  include:: /Includes.rst.txt

..  _feature-data-display-components:

===================================================
Feature: Avatar, tag, progress and meter components
===================================================

Description
===========

The :ref:`component library <feature-component-library>` gains the components
that show a person or a value rather than a passage of content, pure HTML and
CSS, flat and framed like the rest of the library:

*   **Avatar** - a portrait in a circle or a square, in three sizes, with the
    initials of the person where there is no portrait, and a group of avatars
    that overlap.
*   **Tag list** - keywords, plain or linked, optionally with an icon before
    the label. A linked tag is underlined, so it is told apart from a plain
    one by more than its colour.
*   **Progress** and **meter** - the native :html:`<progress>` and
    :html:`<meter>`, labelled like a control and followed by their value in
    words. The edge of the track is drawn in the strong border colour, which
    reaches 3:1 against every background. A meter draws its three regions in
    the success, the warning and the danger colour, and its value text says
    what the region means.

The description list takes a modifier, :css:`.theme-dl--divided`, for pairs of
key and value: a hairline between one term and the next.

What each of them expects is on :ref:`components`, and the styleguide shows
all of them in a section of its own, "Data display".

Impact
======

Everything is new and changes nothing that renders today. The styleguide has
a twelfth section. No design token is added.
