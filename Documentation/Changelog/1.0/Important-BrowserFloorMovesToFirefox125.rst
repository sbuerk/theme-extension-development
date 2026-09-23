..  include:: /Includes.rst.txt

..  _important-browser-floor-moves-to-firefox-125:

=================================================
Important: The browser floor moves to Firefox 125
=================================================

Description
===========

The oldest browsers this theme supports are now **Firefox 125, Chrome and Edge
125, and Safari 17.5**. The previous floor was Firefox 120.

It moved for the **Popover API**. The toggletip is built on :html:`popover`
and :html:`popovertarget`, which needs Firefox 125. It would otherwise need a
hand-written script for showing, hiding, light dismiss and the top layer -
behaviour the platform already has and gets right.

The language dropdown of the site header was built on it as well and is not any
more: a popover is in the top layer, and a top layer box is positioned against
the viewport, so its panel could not be placed under its own trigger. It is a
native :html:`details` / :html:`summary` pair now - see
:ref:`feature-language-menu`. That needs no floor of its own, and the floor
stays where the toggletip puts it.

Nothing else about how the floor is chosen changed: it is the oldest version of
each engine that supports every feature the theme actually relies on, never a
feature it merely could use.

Impact
======

On a browser older than the floor the toggletip does not open. Everything else
renders: the theme uses no other feature above the old floor, and
:css:`light-dark()`, :css:`:has()`, :css:`color-mix()` and the logical
properties it has always used are all below it.

If a site has to serve browsers older than this, the toggletip is the one
component to replace or leave out. The language menu and the dropdown around
it work anywhere - a list of links inside a :html:`details`.

Two features remain **out of scope** even though the floor moved, because both
are still above it: CSS anchor positioning, which is what the tooltip would
need to keep its bubble inside the viewport whatever its trigger - and what a
popover would need to open under its trigger, which is why the dropdown left
the top layer rather than waiting for it - and invoker commands
(:html:`command` / :html:`commandfor`), which is what the dialog would need to
open without a script. Each is a change of its own.
