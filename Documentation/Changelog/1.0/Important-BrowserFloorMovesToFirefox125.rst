..  include:: /Includes.rst.txt

..  _important-browser-floor-moves-to-firefox-125:

=================================================
Important: The browser floor moves to Firefox 125
=================================================

Description
===========

The oldest browsers this theme supports are now **Firefox 125, Chrome and Edge
125, and Safari 17.5**. The previous floor was Firefox 120.

It moved for the **Popover API**. The toggletip and the language dropdown of
the site header are built on :html:`popover` and :html:`popovertarget`, which
needs Firefox 125. Both components would otherwise need a hand-written script
for showing, hiding, light dismiss and the top layer - behaviour the platform
already has and gets right.

Nothing else about how the floor is chosen changed: it is the oldest version of
each engine that supports every feature the theme actually relies on, never a
feature it merely could use.

Impact
======

On a browser older than the floor the two components built on :html:`popover`
do not open. Everything else renders: the theme uses no other feature above the
old floor, and :css:`light-dark()`, :css:`:has()`, :css:`color-mix()` and the
logical properties it has always used are all below it.

If a site has to serve browsers older than this, the two affected components -
the toggletip and the dropdown around the language menu - are the ones to
replace or leave out. The language menu itself is a plain list of links and
works anywhere; only the dropdown around it needs the newer browser.

Two features remain **out of scope** even though the floor moved, because both
are still above it: CSS anchor positioning, which is what the tooltip would
need to keep its bubble inside the viewport whatever its trigger, and invoker
commands (:html:`command` / :html:`commandfor`), which is what the dialog would
need to open without a script. Each is a change of its own.
