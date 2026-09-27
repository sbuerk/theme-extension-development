..  include:: /Includes.rst.txt

..  _important-simple-header-stacks-on-narrow-screens:

===============================================================
Important: The default header stacks its title below 480 pixels
===============================================================

Description
===========

Below 30rem (480 pixels), the default header arrangement ``simple`` puts the
site title on a line of its own, and the menu toggle and the header controls
on the next line, at the end of it. Before, the one row of ``simple`` held the
title, the toggle and both controls side by side and needed about 380 pixels
with the title of the showcase: on a screen 320 pixels wide the page scrolled
sideways by 75 pixels.

The other three arrangements wrap their rows already and are unchanged.

Impact
======

On screens narrower than 480 pixels the default header is taller: 164 pixels
for the showcase, 133 before, when the title was squeezed into four lines
beside the controls. From 480 pixels up nothing changes.

The width is the Sass variable ``bp.$header-simple-stack`` in
:file:`Resources/Private/Scss/abstracts/_breakpoints.scss`. It is
``!default``, so a site package that compiles the stylesheet from its own entry
point can move it with :scss:`@use 'abstracts/breakpoints' with (...)`, like the
other header breakpoints.
