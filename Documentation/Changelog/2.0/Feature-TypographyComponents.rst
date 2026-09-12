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
*   **List**, :css:`.theme-list`: unstyled, inline, check marks, an icon per
    item, two or three columns, and divided rows.
*   **Description list**, :css:`.theme-dl`: stacked, or terms and
    descriptions side by side with :css:`--horizontal`, optionally with
    truncated terms.
*   **Figure**, :css:`.theme-figure`: an image with a caption and a credit
    line, floated to either side of the running text.
*   **Code block**, :css:`.theme-code`: a code block with its file name, which
    can be scrolled with the keyboard.
*   **Divider**, :css:`.theme-divider`: a separator with a label, and a
    section break.
*   **Text and image in text**: the gallery of the text and image and the text
    and media elements now floats beside the text when its position is "In
    text", and keeps the text beside it for "In text, no wrap".

See :ref:`components`.

Impact
======

Paragraphs may wrap differently wherever they occur, rich text included: the
browser now avoids a single word on the last line of a paragraph. German text
is hyphenated where it used to overflow or leave a gap. Table captions and
cells in a right-to-left page align to the right, as they should have before.

The gallery of a text and image or text and media element positioned "In text"
used to sit above the text; it now floats beside it on a viewport of the
medium breakpoint and wider. Every gallery item carries the additional class
:html:`theme-figure`, and its caption :html:`theme-figure__caption`.
