..  include:: /Includes.rst.txt

..  _feature-extended-component-library:

=======================================
Feature: More components in the library
=======================================

Description
===========

The :ref:`component library <feature-component-library>` gains the components a
theme for extension development is most often asked to show, all of them pure
HTML and CSS first, flat and framed like the rest of the library:

*   **Tabs**, **dialog** and **tooltip** - the three components that need the
    theme's script. Each of them stays usable without it: tabs fall back to
    every panel shown under its own heading - also when JavaScript is on but
    the script fails to load - a dialog opener is not shown, and a tooltip
    still appears on hover and focus.
*   **Panel**, for grouped content with a header, a body and a footer, and the
    **close button**.
*   **Button**: a link variant, an icon-only variant, a pressed and a busy
    state, and an attached button group for one control in several parts.
*   **Forms**: the input group - a control joined to text or a button - and the
    choice group for a set of checkboxes or radio buttons.
*   **Alert**: two asides next to the four severities, :css:`--note` and
    :css:`--tip`. The tip follows the selected colour palette.
*   **Typography**: heading levels four to six have a step of their own, and
    three text roles - display, lead and eyebrow - are classes.

A state these components draw in colour - a selected tab, a pressed toggle -
is drawn in the system highlight colour under forced colours, and the
components that depend on a fill for their edge get a border there instead.

What each one expects, and which of them need JavaScript, is on
:ref:`components`. The styleguide renders all of them, the three that need the
script in a section of their own - see :ref:`feature-styleguide`.

Impact
======

Headings of level four to six render differently wherever they occur, content
from the rich text editor included: they used to hold at the size of level
three, and now step down to the body size and below it, with levels five and
six set in capitals. The titles of the hero and the teaser, which follow the
heading level an editor picks, look as before on every level.

Everything else is new and changes nothing that renders today.
:file:`Resources/Public/JavaScript/theme.js` binds the new components in
addition to what it did before, and still has no dependency. The compiled
stylesheet grows from roughly 63 kB to roughly 78 kB, uncompressed and before
transport compression, and one design token is added,
:css:`--theme-letter-spacing-caps`, for text set in capitals.
