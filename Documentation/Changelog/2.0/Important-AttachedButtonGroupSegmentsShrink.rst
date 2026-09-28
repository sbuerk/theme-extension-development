..  include:: /Includes.rst.txt

..  _important-attached-button-group-segments-shrink:

===================================================
Important: The segments of an attached group shrink
===================================================

Description
===========

The attached button group, :css:`.theme-button-group--attached`, still does
not wrap. Its segments now shrink when the row is narrower than they are,
each in proportion to its width. The label stays centred, runs into the
padding of its segment and wraps between its words. An icon segment,
:css:`.theme-button--icon`, does not shrink and stays a square of 44 pixels.

Before, the segments kept the width of their labels, and a group wider than
its column made the whole page scroll sideways: the three segments of the
styleguide specimen did at 305 pixels, a screen of 320 with a classic
scrollbar.

Impact
======

A group that fits its column looks as before. A group that does not is as
wide as its column, and its labels use the room their padding gave. A single
word wider than its segment, once the padding is used up, is drawn across the
borders of the segment. Keep an attached group to a few short labels, and
use a plain :css:`.theme-button-group`, which wraps, for anything longer.
