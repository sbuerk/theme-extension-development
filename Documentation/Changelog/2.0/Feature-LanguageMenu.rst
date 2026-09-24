..  include:: /Includes.rst.txt

..  _feature-language-menu:

==========================
Feature: Language menu
==========================

Description
===========

A site with more than one language shows a language menu in the site header,
inside a dropdown. A site with one language shows nothing at all.

The menu lists every language of the site configuration. The language being
read is marked; a language the current page **has no translation for is shown
as plain text rather than as a link**.

That last point is the whole design of this feature. Leaving the language out
would tell a reader the site has fewer languages than it has. Linking it would
promise a translation that does not exist and land the reader on a fallback
page without a word about it. So the language is shown, and shown to be
unavailable - announced as disabled to assistive technology.

Whether a language is available for a page is decided by TYPO3, not by the
theme: it is available when the page has a translation in that language, or in
a **non-default** fallback language that does have one. Setting
:yaml:`fallbackType: fallback` with :yaml:`fallbacks: 0` does not make a page
count as translated - the default language is filtered out of the overlay chain
- although it does change what the page itself renders.

The dropdown
------------

The menu sits behind a labelled trigger in the header that discloses a panel.
The component is a native :html:`details` / :html:`summary` pair, so the
browser opens it, closes it and announces the expanded state.

In the site header the panel is placed against the header, not against its
own trigger: it drops under the whole header and ends at the end of the content
container, in either reading direction - the inline end it shares with the
display settings panel beside it. A panel placed under the trigger alone would
cover the header's own navigation: in the one row header the row is as tall as
its tallest child and the main navigation may wrap onto a second line inside
it, and in the other three arrangements the navigation has a row of its own
below the controls. A dropdown standing on its own, outside the header, opens
under its own trigger.

The dropdown works without the theme's JavaScript and is rendered whether the
script loaded or not. What the script adds is the dismissal: :kbd:`Escape`, a
click outside, and focus leaving the control - the last of them so that an open
panel never sits over the element that has just taken focus (WCAG 2.2, 2.4.11).
A page whose script failed to load therefore has a dropdown that still opens
and closes from its own trigger, and stays open until that trigger is used
again.

Impact
======

The development instances gain a second language, German, in
:file:`config/sites/demo/config.yaml` and :file:`config/sites/demo-legacy/config.yaml`.
The showcase itself is seeded in English only - the seeding tool cannot express
a translation - so the demo shows the unavailable state on every page, which is
the truth about that tree.

Adding a language to a site needs no database record: the ``sys_language`` table
was removed in TYPO3 v12, and a site language is the site configuration alone.

The menu is core :php:`LanguageMenuProcessor` at
:typoscript:`page.10.dataProcessing.40`, so a site package drops it with
:typoscript:`page.10.dataProcessing.40 >` and keeps the other three navigations.
Its markup is :file:`Partials/Navigation/Language.html`, the dropdown around it
:file:`Partials/Page/Dropdown.html`.

Two new components ship: :css:`.theme-language-menu` and the generic
:css:`.theme-dropdown` - see :ref:`components`. The dropdown is a native
:html:`details` / :html:`summary` pair, so the browser opens it, closes it and
announces the expanded state without a script; :file:`theme.js` adds only the
dismissal - Escape, a click outside, and focus leaving the control. The panel
is placed against what it sits in - the header in the site header, its own
trigger anywhere else - which is why it is not a :html:`popover`: a popover is
in the top layer, and a top layer box is positioned against the viewport rather
than against whatever it sits in.
