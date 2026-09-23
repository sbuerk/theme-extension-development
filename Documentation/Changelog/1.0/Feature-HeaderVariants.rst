..  include:: /Includes.rst.txt

..  _feature-header-variants:

============================
Feature: Header variants
============================

Description
===========

The site header comes in four arrangements, selected by
:typoscript:`theme.header.variant` - a **site setting** of the set on TYPO3
v13, and the **TypoScript constant** of the same name on TYPO3 v12 and on any
site that takes the theme through the static include:

..  list-table::
    :header-rows: 1

    *   -   Value
        -   The header is

    *   -   ``simple``
        -   Title, navigation and controls in one row. **The default, and
            unchanged from before.**

    *   -   ``centred``
        -   The title centred on a row of its own with the controls at the end
            of it, the navigation centred below.

    *   -   ``actions``
        -   The title, a call to action and the controls in the first row, the
            navigation on a second row below.

    *   -   ``two-tier``
        -   A tinted meta row carrying the controls, above a row with the title
            and the navigation.

Each variant is a Fluid partial of its own below
:file:`Resources/Private/Partials/Page/Header/` and a modifier class on
:css:`.theme-site-header`, not a set of conditions inside one template — a site
package overrides the one it uses, or adds a fifth, without touching the other
three.

The call to action
------------------

The ``actions`` variant takes two more settings, constants on TYPO3 v12:
:typoscript:`theme.header.actionPage`, the uid of the page it leads to, and
:typoscript:`theme.header.actionLabel`, its text. **Both are required**: with
one of them the header would carry a button with no destination, or a button
with no name, and it renders nothing instead.

The destination is a page uid and **not a link field**. A link an integrator
can type would accept a scheme, and the value goes straight into an
:html:`href`; an integer can only ever resolve to a page. The deliberate cost
is that the call to action cannot leave the site.

Settings and constants
----------------------

All three are TypoScript constants of the same names and the same defaults as
well, so the arrangement can be configured on a site that takes the theme
through the classic static template rather than the site set - on TYPO3 v12,
which has no site sets and no settings editor, that is the only way, and the
:file:`settings.definitions.yaml` of the set is read by nothing there. A value
of the same key in the site's :file:`settings.yaml` does not help on the
static include: TYPO3 adds it as a constant before the static template, and
the theme's default overrules it. On a
TYPO3 v13 site that uses the set, where both exist, TYPO3 lets the site
setting win. See :ref:`configuration-site-settings`.

Impact
======

**A site that does not touch the setting is unaffected.** The default is
``simple``, its partial produces byte for byte the header that shipped before,
and it carries no modifier class at all.

A site package that overrode :file:`Partials/Page/Header.html` keeps working —
the file still exists and is still what the layout renders — but it now
contains the switch rather than the markup. The markup moved to
:file:`Partials/Page/Header/Simple.html`, with the brand, the controls and the
call to action in :file:`Brand.html`, :file:`Controls.html` and
:file:`Action.html` beside it.

The styleguide gained a section :guilabel:`Page chrome` showing all four.
