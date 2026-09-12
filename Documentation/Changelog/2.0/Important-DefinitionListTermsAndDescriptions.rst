..  include:: /Includes.rst.txt

..  _important-definition-list-terms-and-descriptions:

===========================================================
Important: A definition list reads "term|description" lines
===========================================================

Description
===========

A :guilabel:`Bullet List` of the type :guilabel:`Definition list` used to
render every line as a term of its own, with no description at all.

Each line is now split at the vertical bar, the way
`fluid_styled_content` reads the same field: the part before the first
:code:`|` is the term, every further part a description of it.

..  code-block:: text

    Surface band|The element on the surface colour, with a hairline around it
    Inverse band|The element in the other appearance
    No frame

renders three terms, the first two with a description. A line without a
vertical bar is a term without a description. An empty line, a line with an
empty term - ``|description`` - and an empty description are skipped: a
description is not rendered without its term.

A line is read as a line of CSV with the double quote as the enclosure, so a
part may be enclosed in double quotes to hold a vertical bar itself.

Impact
======

A line that contains a vertical bar is split where it was not before. A line
without one is still one term, with two exceptions that come from the CSV
reading:

*   A line that starts with a double quote loses it, and the text up to the
    closing quote is read as the enclosed term.
*   A double quote at the start of a line that is never closed swallows the
    lines after it into that one term, up to the next double quote or the end
    of the field.

`fluid_styled_content` reads the field the same way, so it shows the same
terms and descriptions for such lines, except that it renders an empty term
and an empty description where this theme skips them.
