..  include:: /Includes.rst.txt

..  _important-button-group-and-table-margin:

=================================================
Important: Button groups and tables keep a margin
=================================================

Description
===========

The button group, :css:`.theme-button-group`, and the wrapper of a table,
:css:`.theme-table-wrapper`, now end on a bottom margin, the one every other
block of the component library ends on - a list, a figure, a code block, a
paragraph. Before, the component after them touched them: a list right under a
row of buttons, a figure right under the border of a table.

Impact
======

Whatever follows a row of buttons or a table moves down by
:css:`var(--theme-space-4)`, 20 pixels. That includes the table content
element, which renders its table in the wrapper. A button group or a table at
the end of a box - a panel, a band - leaves that margin inside the box, as a
list or a paragraph already did.

A site package that wants the previous spacing sets the margin back in its own
CSS, included after :file:`theme.css`:

..  code-block:: css

    .theme-button-group,
    .theme-table-wrapper {
        margin-block-end: 0;
    }
