..  include:: /Includes.rst.txt

..  _feature-typography-components:

=======================================
Feature: Typography and text components
=======================================

Description
===========

The typography of the theme covers what running text and rich text actually
contain, and the :file:`/styleguide` page shows all of it.

*   **Display sizes**: :css:`.theme-display` gains three sizes,
    :css:`--1`, :css:`--2` and :css:`--3`. Two new design tokens carry the
    added ones, :css:`--theme-font-size-display-1` and
    :css:`--theme-font-size-display-3`.
*   **Element typography**, without a class: a secondary line in a heading,
    balanced headings and paragraphs without a lone last word, the defining
    instance of a term, key combinations, quotation marks per language,
    hyphenation of German text, long links that wrap, and captions and table
    cells that align to the start of the line in right-to-left text as well.

See :ref:`components`.

Impact
======

Paragraphs may wrap differently wherever they occur, rich text included: the
browser now avoids a single word on the last line of a paragraph. German text
is hyphenated where it used to overflow or leave a gap. Table captions and
cells in a right-to-left page align to the right, as they should have before.
