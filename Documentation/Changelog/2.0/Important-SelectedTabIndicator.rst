..  include:: /Includes.rst.txt

..  _important-selected-tab-indicator:

================================================
Important: The selected tab carries an indicator
================================================

Description
===========

The selected tab of :css:`.theme-tabs` - the component, and the tabs content
element that renders it - now carries a bar of two pixels in the primary
accent along its top edge. It used to be told apart from the other tabs mainly
by the colour of its label; its frame is drawn in the decorative border colour
and hardly shows. The bar is a shape the other tabs do not have, so the
selection no longer depends on colour alone (WCAG 1.4.1).

Every tab carries the top edge of the bar, transparent until it is selected,
so selecting a tab paints the bar and moves nothing.

Impact
======

The height of a tab is unchanged: it is the minimum height of the tap target,
which includes the edge. The colour of the bar is the component
token :css:`--theme-tabs-indicator-color`, its width
:css:`--theme-tabs-indicator-width`; a site package re-points either in its
own CSS. Under forced colours the selected tab keeps the system highlight
colour it had before.
