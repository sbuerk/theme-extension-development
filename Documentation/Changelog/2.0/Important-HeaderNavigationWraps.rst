..  include:: /Includes.rst.txt

..  _important-header-navigation-wraps:

=====================================================
Important: The main navigation wraps before the title
=====================================================

Description
===========

On a wide screen the site header keeps the site title, the main navigation
and the display settings in one row. From 1024 pixels up, when the row gets
tight, the top level of the main navigation now wraps onto a second row inside
its frame, and the site title keeps its line. It used to be the other way
round: the navigation did not shrink, and a sixth top level entry pushed the
site title onto a second line at 1280 pixels. The header now holds seven top
level entries next to the title of the showcase at that width. The title
takes at most half the row; a title longer than that still wraps, as the last
resort.

Between 768 and 1024 pixels the row is too narrow for that trade, and the
menu keeps its row as it did before, as long as it takes at most 60% of the
row; the title wraps beside it. A menu wider than that wraps inside that
width, so the header no longer spills sideways. Below 768 pixels nothing
changes: the menu is behind its toggle.

Impact
======

From 1024 pixels up, a site with more top level entries than fit beside its
title gets a two row menu instead of a two line title. Between 768 and 1024
pixels a site whose menu fits in 60% of the row looks as before: the title
wraps beside a menu of one row. A menu that does not fit there, which used to
run out of the header sideways, wraps onto further rows. A site whose entries
fit beside its title at every width, like the showcase at 1280 pixels, looks
as before.

The switch at 1024 pixels is a second breakpoint of the stylesheet,
``bp.$lg``, used by the site header only. Moving it means recompiling the
SCSS, like the first one.
