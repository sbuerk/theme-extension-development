..  include:: /Includes.rst.txt

..  _important-form-fields-fit-their-column:

============================================================
Important: Form fields fit their column, inline fields stack
============================================================

Description
===========

The form, :css:`.theme-form`, the field, :css:`.theme-field`, and the
fieldset, :css:`.theme-fieldset`, are as wide as the column they are placed
in. Their controls shrink with it. Before, the widest control set the width:
an input group with its addon, a date input or a file input made the form
wider than a narrow column, and the page scrolled sideways, on
:samp:`/forms` of the showcase by up to 37 pixels at 305 pixels, a screen of
320 with a classic scrollbar.

An inline field, :css:`.theme-field--inline`, keeps its control beside the
label while the control fits there at its own width, the width at which it
shows its whole value. Where it does not fit, the control goes under the
label and takes the whole width, and a long label wraps between its words.
Hint and messages stay on a line of their own.

Impact
======

On a wide screen the fields look as before. In a narrow column a form is no
wider than the column, and an inline field may take two lines where it took
one. A control that is wider than the whole column on its own is drawn at the
width of the column.

A site package that lays out a form in more than one column sets
:css:`grid-template-columns` on :css:`.theme-form` in its own CSS, included
after :file:`theme.css`, as before.
